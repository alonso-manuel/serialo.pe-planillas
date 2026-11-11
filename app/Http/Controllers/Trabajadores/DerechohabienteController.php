<?php

namespace App\Http\Controllers\Trabajadores;

use App\Enums\DocumentoVinculo;
use App\Enums\MotivoBajaDerechohabiente;
use App\Enums\TipoDocumento;
use App\Enums\VinculoFamiliar;
use App\Http\Controllers\Controller;
use App\Models\Derechohabiente;
use App\Models\Trabajador;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Inertia\Inertia;

/**
 * Derechohabientes del trabajador (EsSalud / T-Registro). Reglas de la estructura 13 y de las tablas 19, 20 y 27:
 * cada vínculo con su documento que lo sustenta; hijo menor con menos de 18 años al alta; gestante con mes de concepción.
 */
class DerechohabienteController extends Controller
{
    public function store(Request $request, Trabajador $trabajador): RedirectResponse
    {
        Gate::authorize('update', $trabajador);

        $datos = $request->validate([
            'tipo_documento' => ['required', Rule::enum(TipoDocumento::class)],
            'numero_documento' => ['required', 'string', 'max:15', Rule::unique('derechohabientes')->where('trabajador_id', $trabajador->id)->where('tipo_documento', $request->input('tipo_documento'))],
            'pais_emisor' => [Rule::requiredIf($request->input('tipo_documento') === TipoDocumento::Pasaporte->value), 'nullable', 'regex:/^\d{3}$/'],
            'fecha_nacimiento' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'apellido_paterno' => ['required', 'string', 'max:40'],
            'apellido_materno' => ['nullable', 'string', 'max:40'],
            'nombres' => ['required', 'string', 'max:40'],
            'sexo' => ['required', Rule::in(['M', 'F'])],
            'vinculo' => ['required', Rule::enum(VinculoFamiliar::class)],
            'documento_vinculo' => ['nullable', Rule::enum(DocumentoVinculo::class)],
            'numero_documento_vinculo' => ['nullable', 'string', 'max:20'],
            'mes_concepcion' => ['nullable', 'date_format:Y-m'],
            'fecha_alta' => ['required', 'date_format:Y-m-d'],
        ], [
            'numero_documento.unique' => 'Ese derechohabiente ya está registrado para este trabajador.',
        ], [
            'numero_documento' => 'número de documento', 'pais_emisor' => 'país emisor', 'vinculo' => 'vínculo familiar',
            'documento_vinculo' => 'documento que sustenta el vínculo', 'numero_documento_vinculo' => 'número del documento del vínculo',
            'mes_concepcion' => 'mes de concepción', 'fecha_alta' => 'fecha de alta',
        ]);

        validator($datos)->after(fn (Validator $v) => $this->validarVinculo($v, $datos))->validate();

        $vinculo = VinculoFamiliar::from($datos['vinculo']);
        $trabajador->derechohabientes()->create([
            ...$datos,
            'mes_concepcion' => $vinculo === VinculoFamiliar::Gestante ? $datos['mes_concepcion'] : null,
        ]);

        $aviso = $vinculo === VinculoFamiliar::HijoMenor && ! $trabajador->asignacion_familiar
            ? ' Tiene un hijo menor registrado: revisa si le corresponde asignación familiar.'
            : '';
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Derechohabiente registrado.'.$aviso]);

        return back();
    }

    public function baja(Request $request, Trabajador $trabajador, Derechohabiente $derechohabiente): RedirectResponse
    {
        Gate::authorize('update', $trabajador);
        abort_unless($derechohabiente->trabajador_id === $trabajador->id, 404);

        $datos = $request->validate([
            'fecha_baja' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.$derechohabiente->fecha_alta->toDateString()],
            'motivo_baja' => ['required', Rule::enum(MotivoBajaDerechohabiente::class)],
        ], [
            'fecha_baja.after_or_equal' => 'La baja no puede ser anterior al alta.',
        ], ['fecha_baja' => 'fecha de baja', 'motivo_baja' => 'motivo de baja']);

        $derechohabiente->update($datos);
        Inertia::flash('toast', ['type' => 'success', 'message' => "Baja de {$derechohabiente->nombre_completo} registrada."]);

        return back();
    }

    /** Solo para corregir un registro hecho por error (si ya se informó a SUNAT, usa la baja con motivo «Error en el registro»). */
    public function destroy(Trabajador $trabajador, Derechohabiente $derechohabiente): RedirectResponse
    {
        Gate::authorize('update', $trabajador);
        abort_unless($derechohabiente->trabajador_id === $trabajador->id, 404);

        $derechohabiente->delete();
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Derechohabiente eliminado.']);

        return back();
    }

    /** @param  array<string, mixed>  $d */
    private function validarVinculo(Validator $v, array $d): void
    {
        $vinculo = VinculoFamiliar::from((string) $d['vinculo']);
        $documento = DocumentoVinculo::tryFrom((string) ($d['documento_vinculo'] ?? ''));
        $tipo = TipoDocumento::from((string) $d['tipo_documento']);

        if ($tipo !== TipoDocumento::Pasaporte && ! $tipo->esNumeroValido((string) $d['numero_documento'])) {
            $v->errors()->add('numero_documento', $tipo->mensajeFormato());
        }

        // Tabla 27: el hijo menor con DNI no necesita documento; los demás vínculos sí.
        $exigeDocumento = ! ($vinculo === VinculoFamiliar::HijoMenor && $tipo === TipoDocumento::Dni);
        if ($exigeDocumento && $documento === null) {
            $v->errors()->add('documento_vinculo', 'Indica el documento que sustenta el vínculo.');
        } elseif ($documento !== null && ! in_array($documento, $vinculo->documentos(), true)) {
            $v->errors()->add('documento_vinculo', 'Ese documento no sustenta el vínculo «'.$vinculo->etiqueta().'» (tabla 27 de SUNAT).');
        }

        if ($vinculo->exigeNumeroDocumento() && blank($d['numero_documento_vinculo'] ?? null)) {
            $v->errors()->add('numero_documento_vinculo', 'Indica el número del documento que sustenta el vínculo.');
        }

        if ($vinculo === VinculoFamiliar::Gestante && blank($d['mes_concepcion'] ?? null)) {
            $v->errors()->add('mes_concepcion', 'Indica el mes de concepción.');
        }

        $edad = CarbonImmutable::parse((string) $d['fecha_nacimiento'])->diffInYears(CarbonImmutable::parse((string) $d['fecha_alta']));
        if ($vinculo === VinculoFamiliar::HijoMenor && $edad >= 18) {
            $v->errors()->add('fecha_nacimiento', 'A la fecha de alta ya es mayor de edad: solo procede como hijo mayor incapacitado.');
        }
        if ($vinculo === VinculoFamiliar::HijoMayorIncapacitado && $edad < 18) {
            $v->errors()->add('vinculo', 'Es menor de edad: regístralo como «Hijo(a) menor de edad».');
        }
    }
}
