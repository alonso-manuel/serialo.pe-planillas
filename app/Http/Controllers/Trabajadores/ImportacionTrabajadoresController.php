<?php

namespace App\Http\Controllers\Trabajadores;

use App\Http\Controllers\Controller;
use App\Models\Afp;
use App\Models\Empresa;
use App\Models\Establecimiento;
use App\Models\Trabajador;
use App\Models\User;
use App\Services\Empresas\EmpresaActiva;
use App\Services\Trabajadores\ImportadorTrabajadores;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Carga masiva de trabajadores en la empresa activa: plantilla → subir → vista previa → confirmar. */
class ImportacionTrabajadoresController extends Controller
{
    public function __construct(
        private readonly ImportadorTrabajadores $importador,
        private readonly EmpresaActiva $empresaActiva,
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('create', Trabajador::class);
        $empresa = $this->empresa($request);
        $pendiente = $this->importador->pendiente();

        return Inertia::render('trabajadores/Importar', [
            'empresa' => $empresa->razon_social,
            'importacion' => $pendiente !== null && $pendiente['empresa_id'] === $empresa->id ? $pendiente : null,
            'maximo' => ImportadorTrabajadores::MAXIMO_FILAS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Trabajador::class);

        $request->validate(
            ['archivo' => ['required', 'file', 'extensions:xlsx,csv,txt', 'max:4096']],
            ['archivo.extensions' => 'Sube la plantilla en formato .xlsx o .csv.'],
        );

        $archivo = $request->file('archivo');
        $this->importador->previsualizar(
            $this->empresa($request),
            (string) $archivo->getRealPath(),
            mb_strtolower($archivo->getClientOriginalExtension()) === 'xlsx' ? 'xlsx' : 'csv',
            $archivo->getClientOriginalName(),
        );

        return to_route('trabajadores.importar');
    }

    public function confirmar(Request $request): RedirectResponse
    {
        Gate::authorize('create', Trabajador::class);

        try {
            $resultado = $this->importador->confirmar($this->empresa($request));
        } catch (\DomainException $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);

            return to_route('trabajadores.importar');
        }

        Inertia::flash('toast', [
            'type' => $resultado['creados'] > 0 ? 'success' : 'warning',
            'message' => $resultado['creados'] > 0
                ? "Se registraron {$resultado['creados']} trabajadores."
                    .($resultado['omitidos'] > 0 ? " {$resultado['omitidos']} filas quedaron sin registrar: corrígelas y vuelve a subirlas." : '')
                : 'No había filas válidas para registrar.',
        ]);

        return to_route('trabajadores.index');
    }

    public function cancelar(): RedirectResponse
    {
        $this->importador->cancelar();

        return to_route('trabajadores.importar');
    }

    /** Plantilla .xlsx con la hoja de trabajadores (2 ejemplos ficticios) y la de instrucciones. */
    public function plantilla(Request $request): StreamedResponse
    {
        Gate::authorize('create', Trabajador::class);
        $empresa = $this->empresa($request);

        $establecimientos = $empresa->establecimientos()->orderBy('codigo')->get()
            ->map(fn (Establecimiento $e) => "{$e->codigo} ({$e->nombre})")->implode(', ');
        $afps = Afp::query()->where('activo', true)->orderBy('codigo')->pluck('codigo')->implode(', ');

        $instrucciones = [
            ['Columna', 'Obligatoria', 'Valores aceptados'],
            ['tipo_documento', 'Sí', 'DNI, CE (carné de extranjería) o PAS (pasaporte)'],
            ['numero_documento', 'Sí', 'DNI: 8 dígitos. CE y pasaporte: 4 a 12 letras o dígitos. Debe ser único en la empresa'],
            ['apellido_paterno', 'Sí', 'Texto'],
            ['apellido_materno', 'No', 'Texto'],
            ['nombres', 'Sí', 'Texto'],
            ['fecha_nacimiento', 'No', 'dd/mm/aaaa o celda de fecha de Excel'],
            ['sexo', 'No', 'F o M'],
            ['fecha_ingreso', 'Sí', 'dd/mm/aaaa o celda de fecha de Excel'],
            ['cargo', 'No', 'Texto'],
            ['establecimiento', 'No', "Código del local: {$establecimientos}. Vacío = principal"],
            ['tipo_contrato', 'No', 'Indeterminado (por defecto), Plazo fijo o Tiempo parcial'],
            ['remuneracion_basica', 'Sí', 'Monto en soles con hasta 2 decimales (1500 o 1500.00)'],
            ['asignacion_familiar', 'No', 'SÍ o NO (vacío = NO)'],
            ['sistema_pension', 'Sí', 'ONP, AFP o NINGUNO'],
            ['afp', 'Si es AFP', $afps],
            ['cuspp', 'Si es AFP', '12 letras o dígitos'],
            ['comision_afp', 'Si es AFP', 'FLUJO o MIXTA'],
            ['seguro_salud', 'No', 'ESSALUD o SIS (solo microempresa). Vacío = el de la empresa'],
            ['cuenta_sueldo', 'No', 'Dígitos y guiones'],
            ['cuenta_cts', 'No', 'Dígitos y guiones'],
            ['email', 'No', 'Correo electrónico'],
            ['telefono', 'No', 'Texto'],
            [],
            ['Notas', '', 'Solo registra trabajadores nuevos: si el documento ya existe en la empresa, la fila se rechaza. Máximo '.ImportadorTrabajadores::MAXIMO_FILAS.' filas por archivo. Borra las filas de ejemplo antes de subir.'],
        ];

        $ejemplos = [
            ['DNI', '00000001', 'EJEMPLO', 'FICTICIO', 'BORRAR ESTA FILA', '15/03/1995', 'F', '01/02/2026', 'Asistente', '0000', 'Indeterminado', '1500.00', 'SÍ', 'ONP', '', '', '', 'ESSALUD', '', '', '', ''],
            ['DNI', '00000002', 'EJEMPLO', 'FICTICIO', 'BORRAR ESTA FILA', '20/08/1990', 'M', '01/02/2026', 'Vendedor', '', 'Plazo fijo', '1800.00', 'NO', 'AFP', 'HABITAT', 'ABC123DEF456', 'FLUJO', '', '', '', '', ''],
        ];

        return response()->streamDownload(function () use ($ejemplos, $instrucciones) {
            $writer = new Writer;
            $writer->openToFile('php://output');
            $writer->getCurrentSheet()->setName('Trabajadores');
            $writer->addRow(Row::fromValues(ImportadorTrabajadores::COLUMNAS));
            foreach ($ejemplos as $fila) {
                $writer->addRow(Row::fromValues($fila));
            }

            $writer->addNewSheetAndMakeItCurrent()->setName('Instrucciones');
            foreach ($instrucciones as $fila) {
                $writer->addRow(Row::fromValues($fila));
            }

            $writer->close();
        }, 'plantilla-trabajadores.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private function empresa(Request $request): Empresa
    {
        /** @var User $usuario */
        $usuario = $request->user();

        return $this->empresaActiva->obtener($usuario) ?? abort(409);
    }
}
