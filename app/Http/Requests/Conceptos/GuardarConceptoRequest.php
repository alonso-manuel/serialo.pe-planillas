<?php

namespace App\Http\Requests\Conceptos;

use App\Enums\TipoConcepto;
use App\Models\Concepto;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Alta y edición de conceptos. El código (y con él el tipo) solo se indica al crear.
 * Solo se crean ingresos y descuentos: los aportes los calcula el motor.
 * En un concepto de `sistema` solo cambia el nombre.
 */
class GuardarConceptoRequest extends FormRequest
{
    public function authorize(): bool
    {
        $concepto = $this->conceptoEditado();

        return $concepto === null
            ? (bool) $this->user()?->can('create', Concepto::class)
            : (bool) $this->user()?->can('update', $concepto);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $reglas = [
            'nombre' => ['required', 'string', 'max:255'],
        ];

        foreach (Concepto::AFECTACIONES as $campo) {
            $reglas[$campo] = ['required', 'boolean'];
        }

        if ($this->conceptoEditado() === null) {
            $reglas['codigo_plame'] = ['required', 'digits:4', Rule::unique('conceptos', 'codigo_plame')];
        }

        return $reglas;
    }

    /**
     * @return array<int, \Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $concepto = $this->conceptoEditado();

                if ($concepto === null) {
                    if ($validator->errors()->has('codigo_plame')) {
                        return;
                    }

                    $tipo = TipoConcepto::desdeCodigo($this->string('codigo_plame')->toString());

                    if ($tipo === null) {
                        $validator->errors()->add('codigo_plame', 'El código no corresponde a la tabla 22 (01xx–05xx, 07xx, 09xx o 10xx).');
                    } elseif (in_array($tipo, [TipoConcepto::AporteTrabajador, TipoConcepto::AporteEmpleador], true)) {
                        $validator->errors()->add('codigo_plame', 'Los aportes (06xx y 08xx) los calcula el sistema; solo se crean ingresos y descuentos.');
                    }

                    return;
                }

                if ($concepto->sistema) {
                    foreach (Concepto::AFECTACIONES as $campo) {
                        if ($this->boolean($campo) !== (bool) $concepto->getAttribute($campo)) {
                            $validator->errors()->add('afectaciones', 'Las afectaciones de un concepto del sistema las fija el motor de cálculo.');

                            return;
                        }
                    }
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['codigo_plame' => 'código'];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['codigo_plame.unique' => 'Ese código ya está registrado.'];
    }

    /**
     * @return array{nombre: string, afecto_essalud: bool, afecto_onp: bool, afecto_afp: bool, afecto_quinta: bool}
     */
    public function datos(): array
    {
        return [
            'nombre' => trim($this->string('nombre')->toString()),
            'afecto_essalud' => $this->boolean('afecto_essalud'),
            'afecto_onp' => $this->boolean('afecto_onp'),
            'afecto_afp' => $this->boolean('afecto_afp'),
            'afecto_quinta' => $this->boolean('afecto_quinta'),
        ];
    }

    private function conceptoEditado(): ?Concepto
    {
        $concepto = $this->route('concepto');

        return $concepto instanceof Concepto ? $concepto : null;
    }
}
