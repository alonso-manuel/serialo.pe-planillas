<?php

namespace App\Http\Requests\Periodos;

use App\Models\Periodo;
use App\Models\PlanillaLinea;
use App\Models\TipoSuspension;
use App\Services\Planilla\NovedadesPlanilla;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/** Una fila de la grilla de novedades: faltas, subsidios, horas extra y montos por concepto. */
class GuardarLineaRequest extends FormRequest
{
    private const MONTO = '/^\d{1,10}(\.\d{1,2})?$/';

    public function authorize(): bool
    {
        return (bool) $this->user()?->can('update', $this->periodo());
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'horas_extra_25' => $this->filled('horas_extra_25') ? str_replace(',', '.', (string) $this->input('horas_extra_25')) : '0',
            'horas_extra_35' => $this->filled('horas_extra_35') ? str_replace(',', '.', (string) $this->input('horas_extra_35')) : '0',
            'dias_no_laborados' => $this->filled('dias_no_laborados') ? $this->input('dias_no_laborados') : 0,
            'dias_subsidiados' => $this->filled('dias_subsidiados') ? $this->input('dias_subsidiados') : 0,
            'tipo_subsidio' => $this->filled('tipo_subsidio') ? (string) $this->input('tipo_subsidio') : null,
            'dias_licencia' => $this->filled('dias_licencia') ? $this->input('dias_licencia') : 0,
            'novedades' => collect((array) $this->input('novedades', []))
                ->map(fn ($m) => $m === null ? null : str_replace(',', '.', trim((string) $m)))
                ->all(),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'dias_no_laborados' => ['required', 'integer', 'between:0,30'],
            'dias_subsidiados' => ['required', 'integer', 'between:0,30'],
            'dias_licencia' => ['required', 'integer', 'between:0,30'],
            'tipo_subsidio' => ['nullable', Rule::exists('tipos_suspension', 'codigo')->where('uso', TipoSuspension::USO_SUBSIDIO)->where('activo', true)],
            'horas_extra_25' => ['required', 'regex:/^\d{1,3}(\.\d{1,2})?$/'],
            'horas_extra_35' => ['required', 'regex:/^\d{1,3}(\.\d{1,2})?$/'],
            'novedades' => ['array'],
            'novedades.*' => ['nullable', 'regex:'.self::MONTO],
        ];
    }

    /**
     * @return array<int, \Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $servicio = app(NovedadesPlanilla::class);

                $permitidos = $servicio->conceptosDisponibles()->pluck('id')->all();
                foreach (array_keys((array) $this->input('novedades', [])) as $conceptoId) {
                    if (! in_array((int) $conceptoId, $permitidos, true)) {
                        $validator->errors()->add("novedades.{$conceptoId}", 'Este concepto no se ingresa como novedad.');
                    }
                }

                if ($validator->errors()->hasAny(['dias_no_laborados', 'dias_subsidiados', 'dias_licencia'])) {
                    return;
                }

                $diasMes = $servicio->diasDelMes($this->linea(), $this->periodo());
                $suma = (int) $this->input('dias_no_laborados') + (int) $this->input('dias_subsidiados') + (int) $this->input('dias_licencia');
                if ($suma > $diasMes) {
                    $validator->errors()->add(
                        'dias_no_laborados',
                        "Faltas, días subsidiados y licencias suman {$suma}: no pueden superar los {$diasMes} días del mes.",
                    );
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
            'horas_extra_25.regex' => 'Horas con hasta 2 decimales (máx. 999).',
            'horas_extra_35.regex' => 'Horas con hasta 2 decimales (máx. 999).',
            'novedades.*.regex' => 'Monto mayor o igual a 0 con hasta 2 decimales.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'dias_no_laborados' => 'faltas',
            'dias_subsidiados' => 'días subsidiados',
            'horas_extra_25' => 'horas extra 25 %',
            'horas_extra_35' => 'horas extra 35 %',
        ];
    }

    /**
     * @return array{dias_no_laborados: int, dias_subsidiados: int, tipo_subsidio: string|null, dias_licencia: int, horas_extra_25: string, horas_extra_35: string, novedades: array<int, string|null>}
     */
    public function datos(): array
    {
        return [
            'dias_no_laborados' => (int) $this->input('dias_no_laborados'),
            'dias_subsidiados' => (int) $this->input('dias_subsidiados'),
            'tipo_subsidio' => $this->filled('tipo_subsidio') ? (string) $this->input('tipo_subsidio') : null,
            'dias_licencia' => (int) $this->input('dias_licencia'),
            'horas_extra_25' => (string) $this->input('horas_extra_25'),
            'horas_extra_35' => (string) $this->input('horas_extra_35'),
            'novedades' => collect((array) $this->input('novedades', []))
                ->mapWithKeys(fn ($m, $id) => [(int) $id => $m === null || $m === '' ? null : (string) $m])
                ->all(),
        ];
    }

    public function periodo(): Periodo
    {
        /** @var Periodo */
        return $this->route('periodo');
    }

    public function linea(): PlanillaLinea
    {
        /** @var PlanillaLinea */
        return $this->route('linea');
    }
}
