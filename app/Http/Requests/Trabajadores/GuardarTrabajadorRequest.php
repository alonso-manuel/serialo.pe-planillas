<?php

namespace App\Http\Requests\Trabajadores;

use App\Enums\SistemaPension;
use App\Models\Empresa;
use App\Models\Trabajador;
use App\Models\User;
use App\Services\Empresas\EmpresaActiva;
use App\Services\Trabajadores\HistorialTrabajador;
use App\Services\Trabajadores\ReglasTrabajador;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Ficha del trabajador. La empresa NUNCA viene del formulario: es la del trabajador
 * (al editar) o la empresa activa (al crear).
 */
class GuardarTrabajadorRequest extends FormRequest
{
    public function authorize(): bool
    {
        $trabajador = $this->trabajadorEditado();

        return $trabajador === null
            ? (bool) $this->user()?->can('create', Trabajador::class)
            : (bool) $this->user()?->can('update', $trabajador);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'numero_documento' => mb_strtoupper(trim((string) $this->input('numero_documento'))),
            'cuspp' => $this->filled('cuspp') ? mb_strtoupper(trim((string) $this->input('cuspp'))) : null,
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return ReglasTrabajador::reglas($this->empresa(), $this->all(), $this->trabajadorEditado()?->id);
    }

    /**
     * @return array<int, \Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                ReglasTrabajador::validarExtras($validator, $this->empresa(), $this->all());
                $this->bloquearCamposConHistorial($validator);
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ReglasTrabajador::mensajes();
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ReglasTrabajador::atributos();
    }

    /** Empresa del trabajador editado, o la empresa activa si es nuevo. */
    public function empresa(): Empresa
    {
        $trabajador = $this->trabajadorEditado();

        if ($trabajador !== null) {
            /** @var Empresa */
            return $trabajador->empresa;
        }

        /** @var User $usuario */
        $usuario = $this->user();

        return app(EmpresaActiva::class)->obtener($usuario)
            ?? throw new \DomainException('No hay empresa activa.');
    }

    /**
     * Datos listos para guardar (sin empresa_id; los de AFP se limpian si no es AFP).
     *
     * @return array<string, mixed>
     */
    public function datos(): array
    {
        return ReglasTrabajador::paraGuardar($this->validated(), $this->boolean('asignacion_familiar'));
    }

    /**
     * Si el trabajador ya figura en planillas, sueldo, cargo, pensión... solo cambian
     * con «Registrar cambio» (historial con vigencia), no editando la ficha.
     */
    private function bloquearCamposConHistorial(Validator $validator): void
    {
        $trabajador = $this->trabajadorEditado();

        if ($trabajador === null || ! $trabajador->lineas()->exists()) {
            return;
        }

        $historial = app(HistorialTrabajador::class);
        $esAfp = $this->input('sistema_pension') === SistemaPension::Afp->value;

        foreach (HistorialTrabajador::campos() as $campo) {
            $enviado = $this->input($campo);

            if (! $esAfp && in_array($campo, ['afp_id', 'tipo_comision_afp', 'cuspp'], true)) {
                $enviado = null;
            } elseif ($campo === 'asignacion_familiar') {
                $enviado = $this->boolean($campo);
            } elseif ($campo === 'remuneracion_basica' && is_string($enviado) && preg_match('/^\d+(\.\d+)?$/', $enviado) === 1) {
                $enviado = (string) BigDecimal::of($enviado)->toScale(2, RoundingMode::HalfUp);
            }

            if ($historial->normalizar($enviado) !== $historial->valorActual($trabajador, $campo)) {
                $validator->errors()->add($campo, 'Ya figura en planillas: usa «Registrar cambio» para que rija desde un mes.');
            }
        }
    }

    private function trabajadorEditado(): ?Trabajador
    {
        $trabajador = $this->route('trabajador');

        return $trabajador instanceof Trabajador ? $trabajador : null;
    }
}
