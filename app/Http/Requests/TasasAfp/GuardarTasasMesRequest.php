<?php

namespace App\Http\Requests\TasasAfp;

use App\Models\TasaAfp;
use App\Services\TasasAfp\TasasAfpMensuales;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/** Grilla de un mes: una fila por AFP activa, tasas en porcentaje y RMA en soles. */
class GuardarTasasMesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('create', TasaAfp::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $campos = [];
        foreach (array_keys(TasasAfpMensuales::TASAS) as $campo) {
            $campos["filas.*.{$campo}"] = ['required'];
        }

        return [
            'filas' => ['required', 'array'],
            'filas.*.afp_id' => [
                'required', 'integer', 'distinct',
                Rule::exists('afps', 'id')->where('activo', true),
            ],
            'filas.*.remuneracion_maxima_asegurable' => ['required'],
            ...$campos,
        ];
    }

    /**
     * @return array<int, \Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $servicio = app(TasasAfpMensuales::class);
                [$anio, $mes] = $this->anioMes();

                if ($servicio->mesBloqueado($anio, $mes)) {
                    $validator->errors()->add('filas', $servicio->mensajeBloqueo($anio, $mes));

                    return;
                }

                /** @var array<int, array<string, mixed>> $filas */
                $filas = (array) $this->input('filas', []);

                if (count($filas) !== $servicio->afps()->count()) {
                    $validator->errors()->add('filas', 'Completa las tasas de todas las AFP del mes.');
                }

                foreach ($filas as $i => $fila) {
                    foreach (array_keys(TasasAfpMensuales::TASAS) as $campo) {
                        $valor = $fila[$campo] ?? null;
                        if ($valor !== null && ($error = $servicio->errorTasa($campo, (string) $valor)) !== null) {
                            $validator->errors()->add("filas.{$i}.{$campo}", $error);
                        }
                    }

                    $rma = $fila['remuneracion_maxima_asegurable'] ?? null;
                    if ($rma !== null && ($error = $servicio->errorRma((string) $rma)) !== null) {
                        $validator->errors()->add("filas.{$i}.remuneracion_maxima_asegurable", $error);
                    }
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
            'filas.*.*.required' => 'Obligatorio.',
        ];
    }

    /** @return array{int, int} */
    public function anioMes(): array
    {
        return [(int) $this->route('anio'), (int) $this->route('mes')];
    }

    /**
     * @return list<array{afp_id: int, anio: int, mes: int, aporte_obligatorio: string, prima_seguro: string, comision_flujo: string, comision_mixta: string, remuneracion_maxima_asegurable: string}>
     */
    public function filas(): array
    {
        [$anio, $mes] = $this->anioMes();

        /** @var list<array<string, mixed>> $filas */
        $filas = $this->validated('filas');

        return array_map(fn (array $f) => [
            'afp_id' => (int) $f['afp_id'],
            'anio' => $anio,
            'mes' => $mes,
            'aporte_obligatorio' => (string) $f['aporte_obligatorio'],
            'prima_seguro' => (string) $f['prima_seguro'],
            'comision_flujo' => (string) $f['comision_flujo'],
            'comision_mixta' => (string) $f['comision_mixta'],
            'remuneracion_maxima_asegurable' => (string) $f['remuneracion_maxima_asegurable'],
        ], $filas);
    }
}
