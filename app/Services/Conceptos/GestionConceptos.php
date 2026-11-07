<?php

namespace App\Services\Conceptos;

use App\Enums\TipoConcepto;
use App\Models\Concepto;

/**
 * Conceptos de la tabla 22 SUNAT.
 * - `sistema`: los usa el motor con fórmula fija; solo se renombran (no se desactivan ni eliminan).
 * - Propios: ingresos o descuentos creados por el usuario. Se eliminan solo si nunca se usaron.
 * - Un cambio de afectación vale para cálculos nuevos y recálculos de periodos abiertos;
 *   los periodos cerrados no se recalculan (regla 5).
 */
class GestionConceptos
{
    /**
     * Conceptos agrupados por tipo, en el orden del enum.
     *
     * @return array<int, array{tipo: string, etiqueta: string, conceptos: array<int, array<string, mixed>>}>
     */
    public function agrupados(): array
    {
        $conceptos = Concepto::query()
            ->withExists(['novedades', 'resultados'])
            ->orderBy('codigo_plame')
            ->get()
            ->groupBy(fn (Concepto $c) => $c->tipo->value);

        return array_map(fn (TipoConcepto $tipo) => [
            'tipo' => $tipo->value,
            'etiqueta' => $tipo->etiqueta(),
            'conceptos' => $conceptos->get($tipo->value, collect())
                ->map(fn (Concepto $c) => [
                    'id' => $c->id,
                    'codigo_plame' => $c->codigo_plame,
                    'nombre' => $c->nombre,
                    'tipo' => $c->tipo->value,
                    'afecto_essalud' => $c->afecto_essalud,
                    'afecto_onp' => $c->afecto_onp,
                    'afecto_afp' => $c->afecto_afp,
                    'afecto_quinta' => $c->afecto_quinta,
                    'sistema' => $c->sistema,
                    'activo' => $c->activo,
                    'usado' => (bool) $c->getAttribute('novedades_exists') || (bool) $c->getAttribute('resultados_exists'),
                ])
                ->values()
                ->all(),
        ], TipoConcepto::cases());
    }

    /**
     * @param  array{nombre: string, afecto_essalud: bool, afecto_onp: bool, afecto_afp: bool, afecto_quinta: bool}  $datos
     */
    public function crear(string $codigo, array $datos): Concepto
    {
        $tipo = TipoConcepto::desdeCodigo($codigo)
            ?? throw new \DomainException('Código fuera de la tabla 22.');

        return Concepto::create([
            'codigo_plame' => $codigo,
            'tipo' => $tipo,
            ...$this->conAfectaciones($tipo, $datos),
            'sistema' => false,
            'activo' => true,
        ]);
    }

    /**
     * @param  array{nombre: string, afecto_essalud: bool, afecto_onp: bool, afecto_afp: bool, afecto_quinta: bool}  $datos
     */
    public function actualizar(Concepto $concepto, array $datos): Concepto
    {
        $concepto->update($concepto->sistema
            ? ['nombre' => $datos['nombre']]
            : $this->conAfectaciones($concepto->tipo, $datos));

        return $concepto;
    }

    /** Activa o desactiva. Devuelve el nuevo estado. */
    public function alternarEstado(Concepto $concepto): bool
    {
        if ($concepto->sistema) {
            throw new \DomainException('Los conceptos del sistema no se pueden desactivar.');
        }

        $concepto->activo = ! $concepto->activo;
        $concepto->save();

        return $concepto->activo;
    }

    public function eliminar(Concepto $concepto): void
    {
        if ($concepto->sistema) {
            throw new \DomainException('Los conceptos del sistema no se pueden eliminar.');
        }

        if ($concepto->estaEnUso()) {
            throw new \DomainException('Este concepto ya se usó en planillas; desactívalo en lugar de eliminarlo.');
        }

        $concepto->delete();
    }

    /**
     * Las afectaciones solo tienen sentido en ingresos; los descuentos se guardan sin ellas.
     *
     * @param  array{nombre: string, afecto_essalud: bool, afecto_onp: bool, afecto_afp: bool, afecto_quinta: bool}  $datos
     * @return array{nombre: string, afecto_essalud: bool, afecto_onp: bool, afecto_afp: bool, afecto_quinta: bool}
     */
    private function conAfectaciones(TipoConcepto $tipo, array $datos): array
    {
        if ($tipo->usaAfectaciones()) {
            return $datos;
        }

        return [
            'nombre' => $datos['nombre'],
            'afecto_essalud' => false,
            'afecto_onp' => false,
            'afecto_afp' => false,
            'afecto_quinta' => false,
        ];
    }
}
