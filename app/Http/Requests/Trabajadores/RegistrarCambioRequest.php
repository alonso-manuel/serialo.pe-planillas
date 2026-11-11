<?php

namespace App\Http\Requests\Trabajadores;

use App\Enums\RegimenLaboral;
use App\Enums\SeguroSalud;
use App\Enums\SistemaPension;
use App\Enums\TipoComisionAfp;
use App\Enums\TipoContrato;
use App\Models\Trabajador;
use App\Services\Trabajadores\HistorialTrabajador;
use Brick\Math\BigDecimal;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/** Nuevo cambio en un grupo de datos del trabajador, vigente desde un mes (AAAA-MM). */
class RegistrarCambioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('update', $this->trabajador());
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('cuspp')) {
            $this->merge(['cuspp' => mb_strtoupper(trim((string) $this->input('cuspp')))]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $grupo = (string) $this->input('grupo');
        $esAfp = $this->input('sistema_pension') === SistemaPension::Afp->value;

        $reglas = [
            'grupo' => ['required', Rule::in(array_keys(HistorialTrabajador::GRUPOS))],
            'vigente_desde' => ['required', 'date_format:Y-m'],
        ];

        return $reglas + match ($grupo) {
            'remuneracion' => [
                'remuneracion_basica' => ['required', 'regex:/^\d{1,10}(\.\d{1,2})?$/', 'not_regex:/^0+(\.0+)?$/'],
            ],
            'cargo' => ['cargo' => ['required', 'string', 'max:100']],
            'asignacion_familiar' => ['asignacion_familiar' => ['required', 'boolean']],
            'pension' => [
                'sistema_pension' => ['required', Rule::enum(SistemaPension::class)],
                'afp_id' => [Rule::requiredIf($esAfp), 'nullable', Rule::exists('afps', 'id')->where('activo', true)],
                'cuspp' => [Rule::requiredIf($esAfp), 'nullable', 'regex:/^[A-Z0-9]{12}$/'],
                'tipo_comision_afp' => [Rule::requiredIf($esAfp), 'nullable', Rule::enum(TipoComisionAfp::class)],
            ],
            'seguro_salud' => ['seguro_salud' => ['required', Rule::enum(SeguroSalud::class)]],
            'tipo_contrato' => ['tipo_contrato' => ['required', Rule::enum(TipoContrato::class)]],
            'centro_costo' => ['centro_costo_id' => ['nullable', Rule::exists('centros_costo', 'id')->where('empresa_id', $this->trabajadorEmpresaId())->where('activo', true)]],
            default => [],
        };
    }

    /**
     * @return array<int, \Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->input('grupo') === 'seguro_salud'
                    && $this->input('seguro_salud') === SeguroSalud::Sis->value
                    && $this->trabajador()->empresa?->regimen_laboral !== RegimenLaboral::Microempresa) {
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
            'vigente_desde.date_format' => 'Indica el mes desde el que rige (AAAA-MM).',
            'remuneracion_basica.regex' => 'La remuneración debe ser un monto con hasta 2 decimales.',
            'remuneracion_basica.not_regex' => 'La remuneración debe ser mayor que cero.',
            'cuspp.regex' => 'El CUSPP tiene 12 letras o dígitos.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['afp_id' => 'AFP', 'cuspp' => 'CUSPP', 'tipo_comision_afp' => 'tipo de comisión', 'grupo' => 'dato'];
    }

    /**
     * Nuevos valores del grupo, normalizados como texto (igual que en el historial).
     *
     * @return array<string, string|null>
     */
    public function nuevos(): array
    {
        return match ($this->string('grupo')->toString()) {
            'remuneracion' => [
                'remuneracion_basica' => (string) BigDecimal::of($this->string('remuneracion_basica')->toString())->toScale(2),
            ],
            'cargo' => ['cargo' => trim($this->string('cargo')->toString())],
            'asignacion_familiar' => ['asignacion_familiar' => $this->boolean('asignacion_familiar') ? '1' : '0'],
            'pension' => $this->input('sistema_pension') === SistemaPension::Afp->value
                ? [
                    'sistema_pension' => SistemaPension::Afp->value,
                    'afp_id' => (string) $this->integer('afp_id'),
                    'tipo_comision_afp' => $this->string('tipo_comision_afp')->toString(),
                    'cuspp' => $this->string('cuspp')->toString(),
                ]
                : [
                    'sistema_pension' => $this->string('sistema_pension')->toString(),
                    'afp_id' => null,
                    'tipo_comision_afp' => null,
                    'cuspp' => null,
                ],
            'seguro_salud' => ['seguro_salud' => $this->string('seguro_salud')->toString()],
            'centro_costo' => ['centro_costo_id' => $this->filled('centro_costo_id') ? (string) $this->integer('centro_costo_id') : null],
            'tipo_contrato' => ['tipo_contrato' => $this->string('tipo_contrato')->toString()],
            default => [],
        };
    }

    public function trabajador(): Trabajador
    {
        /** @var Trabajador */
        return $this->route('trabajador');
    }

    private function trabajadorEmpresaId(): int
    {
        $trabajador = $this->route('trabajador');

        return $trabajador instanceof Trabajador ? $trabajador->empresa_id : 0;
    }
}
