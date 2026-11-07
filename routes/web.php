<?php

use App\Http\Controllers\EmpresaActivaController;
use App\Http\Controllers\Usuarios\EstadoUsuarioController;
use App\Http\Controllers\Usuarios\UsuarioController;
use Illuminate\Support\Facades\Route;

// Sistema instalable: no hay página pública; la raíz lleva al panel (o al login).
Route::redirect('/', '/dashboard')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');

    Route::put('empresa-activa', [EmpresaActivaController::class, 'update'])->name('empresa-activa.update');

    // Usuarios (solo admin; nunca se eliminan, se desactivan)
    Route::resource('usuarios', UsuarioController::class)->except(['show', 'destroy']);
    Route::patch('usuarios/{usuario}/estado', [EstadoUsuarioController::class, 'update'])->name('usuarios.estado');
});

require __DIR__.'/settings.php';
