<?php

use App\Http\Controllers\Auditoria\AuditoriaController;
use App\Http\Controllers\Beneficios\CtsController;
use App\Http\Controllers\Beneficios\LiquidacionController;
use App\Http\Controllers\Beneficios\VacacionesController;
use App\Http\Controllers\Conceptos\ConceptoController;
use App\Http\Controllers\Conceptos\EstadoConceptoController;
use App\Http\Controllers\EmpresaActivaController;
use App\Http\Controllers\Empresas\ConsultaRucController;
use App\Http\Controllers\Empresas\EmpresaController;
use App\Http\Controllers\Empresas\EstablecimientoController;
use App\Http\Controllers\Empresas\EstadoEmpresaController;
use App\Http\Controllers\Empresas\FirmaDigitalController;
use App\Http\Controllers\Empresas\LogoEmpresaController;
use App\Http\Controllers\Instalador\InstaladorController;
use App\Http\Controllers\Licencia\LicenciaController;
use App\Http\Controllers\PanelController;
use App\Http\Controllers\Parametros\ParametroController;
use App\Http\Controllers\Periodos\AsistenciaController;
use App\Http\Controllers\Periodos\BoletaController;
use App\Http\Controllers\Periodos\CalculoController;
use App\Http\Controllers\Periodos\CierrePeriodoController;
use App\Http\Controllers\Periodos\EnvioBoletaController;
use App\Http\Controllers\Periodos\HonorariosController;
use App\Http\Controllers\Periodos\LineaPlanillaController;
use App\Http\Controllers\Periodos\PeriodoController;
use App\Http\Controllers\Periodos\ResumenBoletasController;
use App\Http\Controllers\Reportes\ReporteController;
use App\Http\Controllers\Respaldos\RespaldoController;
use App\Http\Controllers\Soporte\SoporteController;
use App\Http\Controllers\Sunat\AfpnetController;
use App\Http\Controllers\Sunat\PlameController;
use App\Http\Controllers\Sunat\TRegistroController;
use App\Http\Controllers\TasasAfp\ImportacionTasasAfpController;
use App\Http\Controllers\TasasAfp\TasaAfpController;
use App\Http\Controllers\Trabajadores\CambioTrabajadorController;
use App\Http\Controllers\Trabajadores\CeseTrabajadorController;
use App\Http\Controllers\Trabajadores\ConsultaDniController;
use App\Http\Controllers\Trabajadores\DerechohabienteController;
use App\Http\Controllers\Trabajadores\ImportacionTrabajadoresController;
use App\Http\Controllers\Trabajadores\OtroEmpleadorController;
use App\Http\Controllers\Trabajadores\PrestamoController;
use App\Http\Controllers\Trabajadores\TrabajadorController;
use App\Http\Controllers\Usuarios\EstadoUsuarioController;
use App\Http\Controllers\Usuarios\UsuarioController;
use App\Http\Middleware\AsegurarUsuarioActivo;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Support\Facades\Route;

// Panel central del modo SaaS (admin.serialo.pe). Antes que el resto: su «/» tiene prioridad en su dominio.
require __DIR__.'/panel.php';

// Sistema instalable: no hay página pública; la raíz lleva al panel (o al login).
Route::redirect('/', '/dashboard')->name('home');

// Instalador web: solo existe mientras el sistema no está instalado (middleware ModoInstalador).
Route::prefix('instalar')->name('instalar.')
    ->withoutMiddleware([AsegurarUsuarioActivo::class, HandleInertiaRequests::class])
    ->controller(InstaladorController::class)->group(function () {
        Route::get('/', 'inicio')->name('inicio');
        Route::post('/', 'codigo')->name('codigo');
        Route::get('base-de-datos', 'baseDatos')->name('base-datos');
        Route::post('base-de-datos', 'guardarBaseDatos')->name('base-datos.store');
        Route::get('sistema', 'sistema')->name('sistema');
        Route::post('sistema', 'instalar')->name('sistema.store');
    });

// Acceso de soporte (modo SaaS): enlace de un solo uso generado en el panel central.
Route::get('soporte/entrar/{token}', [SoporteController::class, 'entrar'])->middleware('throttle:10,1')->name('soporte.entrar');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', PanelController::class)->name('dashboard');

    Route::put('empresa-activa', [EmpresaActivaController::class, 'update'])->name('empresa-activa.update');

    // Personal de la empresa activa (admin y asistente; aislado por empresa)
    Route::middleware('empresa.activa')->group(function () {
        Route::resource('periodos', PeriodoController::class)->only(['index', 'store', 'show', 'destroy']);
        Route::post('periodos/{periodo}/calcular', [CalculoController::class, 'periodo'])->name('periodos.calcular');
        Route::get('periodos/{periodo}/honorarios', [HonorariosController::class, 'index'])->name('periodos.honorarios.index');
        Route::get('periodos/{periodo}/asistencia/plantilla', [AsistenciaController::class, 'plantilla'])->name('periodos.asistencia.plantilla');
        Route::post('periodos/{periodo}/asistencia', [AsistenciaController::class, 'importar'])->middleware('throttle:20,1')->name('periodos.asistencia.importar');
        Route::post('periodos/{periodo}/honorarios', [HonorariosController::class, 'store'])->name('periodos.honorarios.store');
        Route::delete('periodos/{periodo}/honorarios/{recibo}', [HonorariosController::class, 'destroy'])->name('periodos.honorarios.destroy');
        Route::post('periodos/{periodo}/cerrar', [CierrePeriodoController::class, 'cerrar'])->name('periodos.cerrar');
        Route::post('periodos/{periodo}/reabrir', [CierrePeriodoController::class, 'reabrir'])->name('periodos.reabrir');
        Route::post('periodos/{periodo}/sincronizar', [PeriodoController::class, 'sincronizar'])->name('periodos.sincronizar');
        Route::put('periodos/{periodo}/lineas/{linea}', [LineaPlanillaController::class, 'update'])
            ->scopeBindings()->name('periodos.lineas.update');
        Route::get('periodos/{periodo}/lineas/{linea}', [LineaPlanillaController::class, 'show'])
            ->scopeBindings()->name('periodos.lineas.show');
        Route::get('periodos/{periodo}/lineas/{linea}/boleta', [BoletaController::class, 'show'])
            ->scopeBindings()->name('periodos.lineas.boleta');
        Route::get('periodos/{periodo}/boletas/pdf', [BoletaController::class, 'lote'])->name('periodos.boletas.pdf');
        Route::get('periodos/{periodo}/boletas/zip', [BoletaController::class, 'zip'])->name('periodos.boletas.zip');
        Route::post('periodos/{periodo}/boletas/correo', [EnvioBoletaController::class, 'store'])->name('periodos.boletas.correo');
        Route::post('periodos/{periodo}/lineas/{linea}/boleta/correo', [EnvioBoletaController::class, 'reenviar'])
            ->scopeBindings()->name('periodos.lineas.boleta.correo');

        // Accesos del menú: boletas y gratificaciones de la empresa activa
        Route::get('boletas', [ResumenBoletasController::class, 'boletas'])->name('boletas.index');
        Route::get('gratificaciones', [ResumenBoletasController::class, 'gratificaciones'])->name('gratificaciones.index');

        // Reportes de la planilla de la empresa activa
        Route::get('reportes', [ReporteController::class, 'index'])->name('reportes.index');
        Route::get('reportes/{periodo}/{tipo}/{formato}', [ReporteController::class, 'descargar'])
            ->whereIn('tipo', ['planilla', 'resumen', 'centralizacion', 'costo'])
            ->whereIn('formato', ['xlsx', 'pdf'])
            ->name('reportes.descargar');

        // Vacaciones de la empresa activa
        Route::get('vacaciones', [VacacionesController::class, 'index'])->name('vacaciones.index');
        Route::post('vacaciones/{trabajador}/goces', [VacacionesController::class, 'store'])->name('vacaciones.store');
        Route::delete('vacaciones/goces/{goce}', [VacacionesController::class, 'destroy'])->name('vacaciones.destroy');

        // CTS de la empresa activa (mayo / noviembre)
        // Liquidación de beneficios sociales al cese (se paga en la planilla del mes del cese)
        Route::get('liquidaciones', [LiquidacionController::class, 'index'])->name('liquidaciones.index');
        // T-Registro de la empresa activa
        Route::get('t-registro', [TRegistroController::class, 'index'])->name('tregistro.index');
        Route::get('t-registro/zip', [TRegistroController::class, 'zip'])->name('tregistro.zip');
        Route::get('t-registro/derechohabientes', [TRegistroController::class, 'derechohabientes'])->name('tregistro.derechohabientes');
        Route::get('t-registro/ocupaciones', [TRegistroController::class, 'ocupaciones'])->middleware('throttle:120,1')->name('tregistro.ocupaciones');
        Route::post('liquidaciones/{trabajador}/calcular', [LiquidacionController::class, 'calcular'])->name('liquidaciones.calcular');
        Route::post('liquidaciones/{liquidacion}/cerrar', [LiquidacionController::class, 'cerrar'])->name('liquidaciones.cerrar');
        Route::post('liquidaciones/{liquidacion}/reabrir', [LiquidacionController::class, 'reabrir'])->name('liquidaciones.reabrir');
        Route::get('liquidaciones/{liquidacion}/pdf', [LiquidacionController::class, 'hoja'])->name('liquidaciones.pdf');
        Route::get('liquidaciones/{liquidacion}/carta-cts', [LiquidacionController::class, 'cartaCts'])->name('liquidaciones.carta-cts');
        Route::get('trabajadores/{trabajador}/certificado', [LiquidacionController::class, 'certificado'])->name('trabajadores.certificado');

        Route::get('cts', [CtsController::class, 'index'])->name('cts.index');
        Route::post('cts/calcular', [CtsController::class, 'calcular'])->name('cts.calcular');
        Route::post('cts/{liquidacion}/cerrar', [CtsController::class, 'cerrar'])->name('cts.cerrar');
        Route::post('cts/{liquidacion}/reabrir', [CtsController::class, 'reabrir'])->name('cts.reabrir');
        Route::get('cts/{liquidacion}/constancias/pdf', [CtsController::class, 'constanciasPdf'])->name('cts.constancias.pdf');
        Route::get('cts/{liquidacion}/constancias/zip', [CtsController::class, 'constanciasZip'])->name('cts.constancias.zip');
        Route::get('cts/{liquidacion}/constancias/{linea}', [CtsController::class, 'constancia'])
            ->scopeBindings()->name('cts.constancia');

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
        Route::post('trabajadores/{trabajador}/derechohabientes', [DerechohabienteController::class, 'store'])
            ->name('trabajadores.derechohabientes.store');
        Route::patch('trabajadores/{trabajador}/derechohabientes/{derechohabiente}/baja', [DerechohabienteController::class, 'baja'])
            ->name('trabajadores.derechohabientes.baja');
        Route::delete('trabajadores/{trabajador}/derechohabientes/{derechohabiente}', [DerechohabienteController::class, 'destroy'])
            ->name('trabajadores.derechohabientes.destroy');
        Route::post('trabajadores/{trabajador}/prestamos', [PrestamoController::class, 'store'])->name('trabajadores.prestamos.store');
        Route::delete('trabajadores/{trabajador}/prestamos/{prestamo}', [PrestamoController::class, 'destroy'])->name('trabajadores.prestamos.destroy');
        Route::post('trabajadores/{trabajador}/otros-empleadores', [OtroEmpleadorController::class, 'store'])
            ->name('trabajadores.otros-empleadores.store');
        Route::delete('trabajadores/{trabajador}/otros-empleadores/{renta}', [OtroEmpleadorController::class, 'destroy'])
            ->name('trabajadores.otros-empleadores.destroy');
        Route::delete('trabajadores/{trabajador}/cambios/{historial}', [CambioTrabajadorController::class, 'destroy'])
            ->name('trabajadores.cambios.destroy');
        Route::resource('trabajadores', TrabajadorController::class)
            ->except(['show'])->parameters(['trabajadores' => 'trabajador']);
    });

    // Archivos oficiales de las empresas visibles del usuario
    Route::get('plame', [PlameController::class, 'index'])->name('plame.index');
    Route::get('plame/zip', [PlameController::class, 'todas'])->name('plame.todas');
    Route::get('plame/periodos/{periodo}/zip', [PlameController::class, 'periodo'])->name('plame.periodo');
    Route::get('afpnet', [AfpnetController::class, 'index'])->name('afpnet.index');
    Route::get('afpnet/zip', [AfpnetController::class, 'todas'])->name('afpnet.todas');
    Route::get('afpnet/periodos/{periodo}/txt', [AfpnetController::class, 'periodo'])->name('afpnet.periodo');

    // Cálculo de todas las empresas visibles del usuario (en cola)
    Route::post('calculos', [CalculoController::class, 'iniciar'])->name('calculos.store');
    Route::get('calculos/{calculo}', [CalculoController::class, 'estado'])->whereUuid('calculo')->name('calculos.show');

    // Empresas y establecimientos (solo admin; no se eliminan, se desactivan)
    Route::get('empresas/consulta-ruc/{ruc}', [ConsultaRucController::class, 'show'])
        ->middleware('throttle:20,1')->name('empresas.consulta-ruc');
    Route::resource('empresas', EmpresaController::class)->except(['show', 'destroy']);
    Route::patch('empresas/{empresa}/estado', [EstadoEmpresaController::class, 'update'])->name('empresas.estado');
    Route::get('empresas/{empresa}/logo', [LogoEmpresaController::class, 'show'])->name('empresas.logo.show');
    Route::post('empresas/{empresa}/logo', [LogoEmpresaController::class, 'store'])->name('empresas.logo.store');
    Route::delete('empresas/{empresa}/logo', [LogoEmpresaController::class, 'destroy'])->name('empresas.logo.destroy');
    Route::post('empresas/{empresa}/firma', [FirmaDigitalController::class, 'store'])->middleware('throttle:10,1')->name('empresas.firma.store');
    Route::delete('empresas/{empresa}/firma', [FirmaDigitalController::class, 'destroy'])->name('empresas.firma.destroy');
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

    // Licencia (solo admin; se puede registrar aunque el sistema esté en solo lectura)
    Route::get('licencia', [LicenciaController::class, 'show'])->name('licencia.show');
    Route::put('licencia', [LicenciaController::class, 'update'])->name('licencia.update');

    // Respaldos (solo admin; crear uno se permite aunque el sistema esté en solo lectura)
    Route::get('respaldos', [RespaldoController::class, 'index'])->name('respaldos.index');
    Route::post('respaldos', [RespaldoController::class, 'store'])->middleware('throttle:5,1')->name('respaldos.store');
    Route::get('respaldos/{archivo}', [RespaldoController::class, 'descargar'])->where('archivo', '[A-Za-z0-9.-]+')->name('respaldos.descargar');

    // Acceso de soporte de Serialo.pe (solo admin del estudio, modo SaaS)
    Route::post('soporte/permiso', [SoporteController::class, 'autorizar'])->name('soporte.autorizar');
    Route::delete('soporte/permiso', [SoporteController::class, 'revocar'])->name('soporte.revocar');

    // Usuarios (solo admin; nunca se eliminan, se desactivan)
    Route::resource('usuarios', UsuarioController::class)->except(['show', 'destroy']);
    Route::patch('usuarios/{usuario}/estado', [EstadoUsuarioController::class, 'update'])->name('usuarios.estado');
});

require __DIR__.'/settings.php';
