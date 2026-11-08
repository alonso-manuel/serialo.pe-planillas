<?php

use App\Http\Controllers\Auditoria\AuditoriaController;
use App\Http\Controllers\Conceptos\ConceptoController;
use App\Http\Controllers\Conceptos\EstadoConceptoController;
use App\Http\Controllers\EmpresaActivaController;
use App\Http\Controllers\Empresas\ConsultaRucController;
use App\Http\Controllers\Empresas\EmpresaController;
use App\Http\Controllers\Empresas\EstablecimientoController;
use App\Http\Controllers\Empresas\EstadoEmpresaController;
use App\Http\Controllers\Parametros\ParametroController;
use App\Http\Controllers\Periodos\CalculoController;
use App\Http\Controllers\Periodos\CierrePeriodoController;
use App\Http\Controllers\Periodos\LineaPlanillaController;
use App\Http\Controllers\Periodos\PeriodoController;
use App\Http\Controllers\TasasAfp\ImportacionTasasAfpController;
use App\Http\Controllers\TasasAfp\TasaAfpController;
use App\Http\Controllers\Trabajadores\CambioTrabajadorController;
use App\Http\Controllers\Trabajadores\CeseTrabajadorController;
use App\Http\Controllers\Trabajadores\ConsultaDniController;
use App\Http\Controllers\Trabajadores\ImportacionTrabajadoresController;
use App\Http\Controllers\Trabajadores\TrabajadorController;
use App\Http\Controllers\Usuarios\EstadoUsuarioController;
use App\Http\Controllers\Usuarios\UsuarioController;
use Illuminate\Support\Facades\Route;

// Sistema instalable: no hay página pública; la raíz lleva al panel (o al login).
Route::redirect('/', '/dashboard')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');

    Route::put('empresa-activa', [EmpresaActivaController::class, 'update'])->name('empresa-activa.update');

    // Personal de la empresa activa (admin y asistente; aislado por empresa)
    Route::middleware('empresa.activa')->group(function () {
        Route::resource('periodos', PeriodoController::class)->only(['index', 'store', 'show', 'destroy']);
        Route::post('periodos/{periodo}/calcular', [CalculoController::class, 'periodo'])->name('periodos.calcular');
        Route::post('periodos/{periodo}/cerrar', [CierrePeriodoController::class, 'cerrar'])->name('periodos.cerrar');
        Route::post('periodos/{periodo}/reabrir', [CierrePeriodoController::class, 'reabrir'])->name('periodos.reabrir');
        Route::post('periodos/{periodo}/sincronizar', [PeriodoController::class, 'sincronizar'])->name('periodos.sincronizar');
        Route::put('periodos/{periodo}/lineas/{linea}', [LineaPlanillaController::class, 'update'])
            ->scopeBindings()->name('periodos.lineas.update');
        Route::get('periodos/{periodo}/lineas/{linea}', [LineaPlanillaController::class, 'show'])
            ->scopeBindings()->name('periodos.lineas.show');

        Route::get('trabajadores/importar', [ImportacionTrabajadoresController::class, 'index'])->name('trabajadores.importar');
        Route::get('trabajadores/importar/plantilla', [ImportacionTrabajadoresController::class, 'plantilla'])->name('trabajadores.importar.plantilla');
        Route::post('trabajadores/importar', [ImportacionTrabajadoresController::class, 'store'])->name('trabajadores.importar.store');
        Route::post('trabajadores/importar/confirmar', [ImportacionTrabajadoresController::class, 'confirmar'])->name('trabajadores.importar.confirmar');
        Route::delete('trabajadores/importar', [ImportacionTrabajadoresController::class, 'cancelar'])->name('trabajadores.importar.cancelar');
        Route::get('trabajadores/consulta-dni/{dni}', [ConsultaDniController::class, 'show'])
            ->middleware('throttle:10,1')->name('trabajadores.consulta-dni');
        Route::post('trabajadores/{trabajador}/cese', [CeseTrabajadorController::class, 'store'])
            ->name('trabajadores.cese.store');
        Route::delete('trabajadores/{trabajador}/cese', [CeseTrabajadorController::class, 'destroy'])
            ->name('trabajadores.cese.destroy');
        Route::post('trabajadores/{trabajador}/cambios', [CambioTrabajadorController::class, 'store'])
            ->name('trabajadores.cambios.store');
        Route::delete('trabajadores/{trabajador}/cambios/{historial}', [CambioTrabajadorController::class, 'destroy'])
            ->name('trabajadores.cambios.destroy');
        Route::resource('trabajadores', TrabajadorController::class)
            ->except(['show'])->parameters(['trabajadores' => 'trabajador']);
    });

    // Cálculo de todas las empresas visibles del usuario (en cola)
    Route::post('calculos', [CalculoController::class, 'iniciar'])->name('calculos.store');
    Route::get('calculos/{calculo}', [CalculoController::class, 'estado'])->whereUuid('calculo')->name('calculos.show');

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

    // Parámetros legales (solo admin; vigencias nuevas, nunca se editan)
    Route::resource('parametros', ParametroController::class)->only(['index', 'store', 'destroy']);

    // Tasas AFP mensuales (solo admin; un mes con periodo cerrado ya no cambia)
    Route::get('tasas-afp', [TasaAfpController::class, 'index'])->name('tasas-afp.index');
    Route::get('tasas-afp/plantilla', [TasaAfpController::class, 'plantilla'])->name('tasas-afp.plantilla');
    Route::post('tasas-afp/importar', [ImportacionTasasAfpController::class, 'store'])->name('tasas-afp.importar');
    Route::post('tasas-afp/importar/confirmar', [ImportacionTasasAfpController::class, 'confirmar'])->name('tasas-afp.importar.confirmar');
    Route::delete('tasas-afp/importar', [ImportacionTasasAfpController::class, 'cancelar'])->name('tasas-afp.importar.cancelar');
    Route::put('tasas-afp/{anio}/{mes}', [TasaAfpController::class, 'update'])
        ->whereNumber('anio')->where('mes', '[1-9]|1[0-2]')->name('tasas-afp.update');

    // Conceptos tabla 22 (solo admin; los de sistema no se borran ni desactivan)
    Route::resource('conceptos', ConceptoController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::patch('conceptos/{concepto}/estado', [EstadoConceptoController::class, 'update'])->name('conceptos.estado');

    // Auditoría (solo admin, solo lectura)
    Route::get('auditoria', [AuditoriaController::class, 'index'])->name('auditoria.index');

    // Usuarios (solo admin; nunca se eliminan, se desactivan)
    Route::resource('usuarios', UsuarioController::class)->except(['show', 'destroy']);
    Route::patch('usuarios/{usuario}/estado', [EstadoUsuarioController::class, 'update'])->name('usuarios.estado');
});

require __DIR__.'/settings.php';
