<?php

use App\Http\Controllers\EmpresaActivaController;
use App\Http\Controllers\Empresas\ConsultaRucController;
use App\Http\Controllers\Empresas\EmpresaController;
use App\Http\Controllers\Empresas\EstablecimientoController;
use App\Http\Controllers\Empresas\EstadoEmpresaController;
use App\Http\Controllers\Usuarios\EstadoUsuarioController;
use App\Http\Controllers\Usuarios\UsuarioController;
use Illuminate\Support\Facades\Route;

// Sistema instalable: no hay página pública; la raíz lleva al panel (o al login).
Route::redirect('/', '/dashboard')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');

    Route::put('empresa-activa', [EmpresaActivaController::class, 'update'])->name('empresa-activa.update');

    // Empresas y establecimientos (solo admin; no se eliminan, se desactivan)
    Route::get('empresas/consulta-ruc/{ruc}', [ConsultaRucController::class, 'show'])
        ->middleware('throttle:20,1')->name('empresas.consulta-ruc');
    Route::resource('empresas', EmpresaController::class)->except(['show', 'destroy']);
    Route::patch('empresas/{empresa}/estado', [EstadoEmpresaController::class, 'update'])->name('empresas.estado');
    Route::scopeBindings()->group(function () {
        Route::post('empresas/{empresa}/establecimientos', [EstablecimientoController::class, 'store'])
            ->name('empresas.establecimientos.store');
        Route::put('empresas/{empresa}/establecimientos/{establecimiento}', [EstablecimientoController::class, 'update'])
            ->name('empresas.establecimientos.update');
        Route::delete('empresas/{empresa}/establecimientos/{establecimiento}', [EstablecimientoController::class, 'destroy'])
            ->name('empresas.establecimientos.destroy');
    });

    // Usuarios (solo admin; nunca se eliminan, se desactivan)
    Route::resource('usuarios', UsuarioController::class)->except(['show', 'destroy']);
    Route::patch('usuarios/{usuario}/estado', [EstadoUsuarioController::class, 'update'])->name('usuarios.estado');
});

require __DIR__.'/settings.php';
