<?php

namespace App\Http\Requests\Trabajadores;

use App\Enums\RegimenLaboral;
use App\Enums\SeguroSalud;
use App\Enums\SistemaPension;
use App\Enums\TipoComisionAfp;
use App\Enums\TipoContrato;
use App\Enums\TipoDocumento;
use App\Models\Empresa;
use App\Models\Trabajador;
use App\Models\User;
use App\Services\Empresas\EmpresaActiva;
use App\Services\Trabajadores\HistorialTrabajador;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
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
        $empresa = $this->empresa();
        $esAfp = $this->input('sistema_pension') === SistemaPension::Afp->value;

        return [
            // Personales
            'tipo_documento' => ['required', Rule::enum(TipoDocumento::class)],
            'numero_documento' => [
                'required', 'string',
                Rule::unique('trabajadores', 'numero_documento')
                    ->where('empresa_id', $empresa->id)
                    ->where('tipo_documento', $this->input('tipo_documento'))
                    ->ignore($this->trabajadorEditado()?->id),
            ],
            'apellido_paterno' => ['required', 'string', 'max:100'],
            'apellido_materno' => ['nullable', 'string', 'max:100'],
            'nombres' => ['required', 'string', 'max:150'],
            'fecha_nacimiento' => ['nullable', 'date', 'before:today'],
            'sexo' => ['nullable', Rule::in(['M', 'F'])],
            'email' => ['nullable', 'email', 'max:255'],
            'telefono' => ['nullable', 'string', 'max:20'],
            // Laborales
            'establecimiento_id' => ['nullable', Rule::exists('establecimientos', 'id')->where('empresa_id', $empresa->id)],
            'cargo' => ['nullable', 'string', 'max:100'],
            'fecha_ingreso' => ['required', 'date'],
            'tipo_contrato' => ['required', Rule::enum(TipoContrato::class)],
            'remuneracion_basica' => ['required', 'regex:/^\d{1,10}(\.\d{1,2})?$/', 'not_regex:/^0+(\.0+)?$/'],
            'asignacion_familiar' => ['boolean'],
            // Pensión y salud
            'sistema_pension' => ['required', Rule::enum(SistemaPension::class)],
            'afp_id' => [Rule::requiredIf($esAfp), 'nullable', Rule::exists('afps', 'id')->where('activo', true)],
            'cuspp' => [Rule::requiredIf($esAfp), 'nullable', 'regex:/^[A-Z0-9]{12}$/'],
            'tipo_comision_afp' => [Rule::requiredIf($esAfp), 'nullable', Rule::enum(TipoComisionAfp::class)],
            'seguro_salud' => ['required', Rule::enum(SeguroSalud::class)],
            // Cuentas
            'cuenta_sueldo' => ['nullable', 'string', 'max:30', 'regex:/^[0-9\- ]+$/'],
            'cuenta_cts' => ['nullable', 'string', 'max:30', 'regex:/^[0-9\- ]+$/'],
        ];
    }

    /**
     * @return array<int, \Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $tipo = TipoDocumento::tryFrom((string) $this->input('tipo_documento'));
                $numero = (string) $this->input('numero_documento');

                if ($tipo !== null && $numero !== '' && ! $tipo->esNumeroValido($numero)) {
                    $validator->errors()->add('numero_documento', $tipo->mensajeFormato());
                }

                $this->bloquearCamposConHistorial($validator);

                if ($this->input('seguro_salud') === SeguroSalud::Sis->value
                    && $this->empresa()->regimen_laboral !== RegimenLaboral::Microempresa) {
                    $validator->errors()->add('seguro_salud', 'El SIS solo aplica a trabajadores de microempresas.');
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'numero_documento.unique' => 'Ya existe un trabajador con ese documento en esta empresa.',
            'remuneracion_basica.regex' => 'La remuneración debe ser un monto con hasta 2 decimales.',
            'remuneracion_basica.not_regex' => 'La remuneración debe ser mayor que cero.',
            'cuspp.regex' => 'El CUSPP tiene 12 letras o dígitos.',
            'fecha_nacimiento.before' => 'La fecha de nacimiento debe ser anterior a hoy.',
            'cuenta_sueldo.regex' => 'Solo dígitos y guiones.',
            'cuenta_cts.regex' => 'Solo dígitos y guiones.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'afp_id' => 'AFP',
            'cuspp' => 'CUSPP',
            'tipo_comision_afp' => 'tipo de comisión',
            'tipo_contrato' => 'tipo de contrato',
            'sexo' => 'sexo',
            'cuenta_sueldo' => 'cuenta de sueldo',
            'cuenta_cts' => 'cuenta CTS',
        ];
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
        $datos = collect($this->validated())->except('empresa_id')->all();
        $datos['asignacion_familiar'] = $this->boolean('asignacion_familiar');

        if ($datos['sistema_pension'] !== SistemaPension::Afp->value) {
            $datos['afp_id'] = null;
            $datos['cuspp'] = null;
            $datos['tipo_comision_afp'] = null;
        }

        return $datos;
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
