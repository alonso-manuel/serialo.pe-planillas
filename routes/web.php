<?php

use App\Http\Controllers\EmpresaActivaController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');

    Route::put('empresa-activa', [EmpresaActivaController::class, 'update'])->name('empresa-activa.update');
});

require __DIR__.'/settings.php';
