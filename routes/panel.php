<?php

use App\Http\Controllers\Panel\AccesoPanelController;
use App\Http\Controllers\Panel\ActividadPanelController;
use App\Http\Controllers\Panel\CuentaController;
use App\Http\Controllers\Panel\DobleFactorPanelController;
use App\Http\Middleware\ExigirDobleFactorPanel;
use Illuminate\Support\Facades\Route;

/*
| Panel central (solo modo SaaS, dominio config('planillas.dominio_panel')): el superadmin de Serialo.pe
| administra las cuentas. Rutas en español y distintas de las de un estudio (SepararPanelYEstudios).
*/
Route::domain((string) config('planillas.dominio_panel'))
    ->middleware('web')
    ->name('panel.')
    ->group(function () {
        Route::get('/', fn () => to_route('panel.cuentas.index'))->name('inicio');

        Route::middleware('guest:superadmin')->group(function () {
            Route::get('ingresar', [AccesoPanelController::class, 'formulario'])->name('ingresar');
            Route::post('ingresar', [AccesoPanelController::class, 'ingresar'])->middleware('throttle:10,1')->name('ingresar.store');
            Route::get('verificacion', [AccesoPanelController::class, 'verificacion'])->name('verificacion');
            Route::post('verificacion', [AccesoPanelController::class, 'verificar'])->middleware('throttle:10,1')->name('verificacion.store');
        });

        Route::middleware('auth:superadmin')->group(function () {
            Route::post('salir', [AccesoPanelController::class, 'salir'])->name('salir');

            Route::get('seguridad/doble-factor', [DobleFactorPanelController::class, 'mostrar'])->name('doble-factor');
            Route::post('seguridad/doble-factor', [DobleFactorPanelController::class, 'confirmar'])->name('doble-factor.store');

            Route::middleware(ExigirDobleFactorPanel::class)->group(function () {
                Route::get('cuentas', [CuentaController::class, 'index'])->name('cuentas.index');
                Route::get('cuentas/nueva', [CuentaController::class, 'create'])->name('cuentas.create');
                Route::post('cuentas', [CuentaController::class, 'store'])->name('cuentas.store');
                Route::get('cuentas/{estudio}/editar', [CuentaController::class, 'edit'])->name('cuentas.edit');
                Route::put('cuentas/{estudio}', [CuentaController::class, 'update'])->name('cuentas.update');
                Route::patch('cuentas/{estudio}/estado', [CuentaController::class, 'estado'])->name('cuentas.estado');
                Route::post('cuentas/{estudio}/acceso', [CuentaController::class, 'acceso'])->name('cuentas.acceso');
                Route::delete('cuentas/{estudio}', [CuentaController::class, 'destroy'])->name('cuentas.destroy');

                Route::get('actividad', ActividadPanelController::class)->name('actividad');
            });
        });
    });
