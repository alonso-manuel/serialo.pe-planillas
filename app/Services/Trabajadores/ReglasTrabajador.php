<?php

namespace App\Services\Trabajadores;

use App\Enums\CategoriaOcupacional;
use App\Enums\ModalidadContrato;
use App\Enums\RegimenLaboral;
use App\Enums\SeguroSalud;
use App\Enums\SistemaPension;
use App\Enums\SituacionEducativa;
use App\Enums\SituacionEspecial;
use App\Enums\TipoComisionAfp;
use App\Enums\TipoContrato;
use App\Enums\TipoDocumento;
use App\Models\Empresa;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Reglas de la ficha del trabajador, compartidas por el formulario y la carga masiva
 * para que ambos validen exactamente igual.
 */
final class ReglasTrabajador
{
    /**
     * @param  array<string, mixed>  $datos
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public static function reglas(Empresa $empresa, array $datos, ?int $ignorarTrabajadorId = null): array
    {
        $esAfp = ($datos['sistema_pension'] ?? null) === SistemaPension::Afp->value;

        return [
            // Personales
            'tipo_documento' => ['required', Rule::enum(TipoDocumento::class)],
            'numero_documento' => [
                'required', 'string',
                Rule::unique('trabajadores', 'numero_documento')
                    ->where('empresa_id', $empresa->id)
                    ->where('tipo_documento', $datos['tipo_documento'] ?? null)
                    ->ignore($ignorarTrabajadorId),
            ],
            'apellido_paterno' => ['required', 'string', 'max:100'],
            'apellido_materno' => ['nullable', 'string', 'max:100'],
            'nombres' => ['required', 'string', 'max:150'],
            'fecha_nacimiento' => ['nullable', 'date', 'before:today'],
            'sexo' => ['nullable', Rule::in(['M', 'F'])],
            'email' => ['nullable', 'email', 'max:255'],
            'telefono' => ['nullable', 'string', 'max:20'],
            'nacionalidad' => ['nullable', 'regex:/^\d{3,4}$/'],   // tabla 4 de SUNAT (extranjeros)
            'pais_emisor' => ['nullable', 'regex:/^\d{3}$/'],      // tabla 26 (pasaporte)
            // Laborales
            'establecimiento_id' => ['nullable', Rule::exists('establecimientos', 'id')->where('empresa_id', $empresa->id)],
            'cargo' => ['nullable', 'string', 'max:100'],
            // T-Registro (tablas 24, 30, 9, 12 y 35 de SUNAT)
            'categoria_ocupacional' => ['nullable', Rule::enum(CategoriaOcupacional::class)],
            'ocupacion' => ['nullable', Rule::exists('ocupaciones_sunat', 'codigo')],
            'situacion_educativa' => ['nullable', Rule::enum(SituacionEducativa::class)],
            'modalidad_contrato' => ['nullable', Rule::enum(ModalidadContrato::class)],
            'situacion_especial' => ['nullable', Rule::enum(SituacionEspecial::class)],
            'discapacidad' => ['boolean'],
            'sindicalizado' => ['boolean'],
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
            'entidad_cts' => ['nullable', Rule::exists('entidades_financieras', 'codigo')->where('activo', true)],
        ];
    }

    /**
     * Validaciones que dependen de varios campos o de la empresa.
     *
     * @param  array<string, mixed>  $datos
     */
    public static function validarExtras(Validator $validator, Empresa $empresa, array $datos): void
    {
        $tipo = TipoDocumento::tryFrom((string) ($datos['tipo_documento'] ?? ''));
        $numero = (string) ($datos['numero_documento'] ?? '');

        if ($tipo !== null && $numero !== '' && ! $tipo->esNumeroValido($numero)) {
            $validator->errors()->add('numero_documento', $tipo->mensajeFormato());
        }

        // La ocupación debe estar habilitada para la categoría ocupacional (tabla 30).
        $categoria = CategoriaOcupacional::tryFrom((string) ($datos['categoria_ocupacional'] ?? ''));
        $ocupacion = (string) ($datos['ocupacion'] ?? '');
        if ($categoria !== null && $ocupacion !== ''
            && DB::table('ocupaciones_sunat')->where('codigo', $ocupacion)->where($categoria->columnaOcupacion(), false)->exists()) {
            $validator->errors()->add('ocupacion', 'Esa ocupación no aplica a la categoría «'.$categoria->etiqueta().'» según la tabla 30 de SUNAT.');
        }

        if (($datos['seguro_salud'] ?? null) === SeguroSalud::Sis->value
            && $empresa->regimen_laboral !== RegimenLaboral::Microempresa) {
            $validator->errors()->add('seguro_salud', 'El SIS solo aplica a trabajadores de microempresas.');
        }
    }

    /**
     * @return array<string, string>
     */
    public static function mensajes(): array
    {
        return [
            'numero_documento.unique' => 'Ya existe un trabajador con ese documento en esta empresa.',
            'remuneracion_basica.regex' => 'La remuneración debe ser un monto con hasta 2 decimales.',
            'remuneracion_basica.not_regex' => 'La remuneración debe ser mayor que cero.',
            'cuspp.regex' => 'El CUSPP tiene 12 letras o dígitos.',
            'fecha_nacimiento.before' => 'La fecha de nacimiento debe ser anterior a hoy.',
            'cuenta_sueldo.regex' => 'Solo dígitos y guiones.',
            'cuenta_cts.regex' => 'Solo dígitos y guiones.',
            'nacionalidad.regex' => 'Código de nacionalidad de la tabla 4 de SUNAT (3 o 4 dígitos).',
            'pais_emisor.regex' => 'Código de país de la tabla 26 de SUNAT (3 dígitos).',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function atributos(): array
    {
        return [
            'afp_id' => 'AFP',
            'cuspp' => 'CUSPP',
            'tipo_comision_afp' => 'tipo de comisión',
            'tipo_contrato' => 'tipo de contrato',
            'sexo' => 'sexo',
            'cuenta_sueldo' => 'cuenta de sueldo',
            'cuenta_cts' => 'cuenta CTS',
            'entidad_cts' => 'entidad de la cuenta CTS',
            'categoria_ocupacional' => 'categoría ocupacional',
            'ocupacion' => 'ocupación',
            'situacion_educativa' => 'situación educativa',
            'modalidad_contrato' => 'modalidad del contrato',
            'situacion_especial' => 'situación especial',
            'pais_emisor' => 'país emisor del documento',
        ];
    }

    /**
     * Datos validados listos para guardar: sin empresa_id y sin datos de AFP si no es AFP.
     *
     * @param  array<string, mixed>  $validados
     * @return array<string, mixed>
     */
    public static function paraGuardar(array $validados, bool $asignacionFamiliar): array
    {
        unset($validados['empresa_id']);
        $validados['asignacion_familiar'] = $asignacionFamiliar;

        // T-Registro: casillas y valores por defecto; la modalidad solo aplica al plazo fijo.
        foreach (['discapacidad', 'sindicalizado'] as $casilla) {
            if (array_key_exists($casilla, $validados)) {
                $validados[$casilla] = (bool) $validados[$casilla];
            }
        }
        if (array_key_exists('situacion_especial', $validados)) {
            $validados['situacion_especial'] ??= SituacionEspecial::Ninguna->value;
        }
        if (($validados['tipo_contrato'] ?? null) !== TipoContrato::PlazoFijo->value) {
            $validados['modalidad_contrato'] = null;
        }

        if (($validados['sistema_pension'] ?? null) !== SistemaPension::Afp->value) {
            $validados['afp_id'] = null;
            $validados['cuspp'] = null;
            $validados['tipo_comision_afp'] = null;
        }

        return $validados;
    }
}
