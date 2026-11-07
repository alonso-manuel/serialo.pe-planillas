<?php

namespace App\Services\Empresas;

use App\Models\Empresa;
use App\Models\Establecimiento;
use Illuminate\Support\Facades\DB;

/**
 * Alta, edición y activación de empresas y sus establecimientos.
 * - Toda empresa nace con su establecimiento principal (código SUNAT 0000).
 * - Las empresas no se eliminan: se desactivan (salen del selector de empresa activa).
 */
class GestionEmpresas
{
    /**
     * @param  array<string, mixed>  $datos  validados por GuardarEmpresaRequest
     */
    public function crear(array $datos): Empresa
    {
        return DB::transaction(function () use ($datos) {
            $empresa = Empresa::create([...$datos, 'activo' => true]);

            $empresa->establecimientos()->create([
                'codigo' => Establecimiento::CODIGO_PRINCIPAL,
                'nombre' => 'Principal',
                'direccion' => $empresa->direccion,
            ]);

            return $empresa;
        });
    }

    /**
     * @param  array<string, mixed>  $datos  validados (sin RUC)
     */
    public function actualizar(Empresa $empresa, array $datos): Empresa
    {
        unset($datos['ruc']);
        $empresa->update($datos);

        return $empresa;
    }

    /** Activa o desactiva. Devuelve el nuevo estado. */
    public function alternarEstado(Empresa $empresa): bool
    {
        $empresa->activo = ! $empresa->activo;
        $empresa->save();

        return $empresa->activo;
    }

    /**
     * @param  array{codigo: string, nombre: string, direccion?: string|null}  $datos
     */
    public function agregarEstablecimiento(Empresa $empresa, array $datos): Establecimiento
    {
        return $empresa->establecimientos()->create($datos);
    }

    /**
     * @param  array{codigo: string, nombre: string, direccion?: string|null}  $datos
     */
    public function actualizarEstablecimiento(Establecimiento $establecimiento, array $datos): Establecimiento
    {
        $establecimiento->update($datos);

        return $establecimiento;
    }

    public function eliminarEstablecimiento(Establecimiento $establecimiento): void
    {
        if ($establecimiento->esPrincipal()) {
            throw new \DomainException('El establecimiento principal no se puede eliminar.');
        }

        // Los trabajadores asignados quedan sin establecimiento (FK nullOnDelete).
        $establecimiento->delete();
    }
}
