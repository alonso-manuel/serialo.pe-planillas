<?php

namespace App\Http\Controllers\Periodos;

use App\Http\Controllers\Controller;
use App\Models\Periodo;
use App\Models\TipoSuspension;
use App\Services\Planilla\ImportacionAsistencia;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Asistencia del mes desde Excel: plantilla con los trabajadores del periodo e importación todo o nada. */
class AsistenciaController extends Controller
{
    public function __construct(private readonly ImportacionAsistencia $importacion) {}

    public function plantilla(Periodo $periodo): StreamedResponse
    {
        Gate::authorize('view', $periodo);

        $filas = $this->importacion->filasPlantilla($periodo);
        $subsidios = TipoSuspension::query()->subsidios()->get(['codigo', 'descripcion'])
            ->map(fn ($t) => "{$t->codigo} ({$t->descripcion})")->implode(', ');
        $instrucciones = [
            ['Columna', 'Valores'],
            ['numero_documento', 'Documento del trabajador (no lo cambies: identifica la fila)'],
            ['trabajador', 'Solo de referencia'],
            ['faltas', 'Días de falta injustificada del mes (entero)'],
            ['licencia_sin_goce', 'Días de licencia sin goce de haber (entero)'],
            ['dias_subsidio', 'Días de descanso médico subsidiado o maternidad (entero)'],
            ['tipo_subsidio', "Si hay días de subsidio: {$subsidios}. Vacío = incapacidad temporal"],
            ['horas_extra_25', 'Horas extra con sobretasa de 25 % (primeras 2 horas del día), hasta 2 decimales'],
            ['horas_extra_35', 'Horas extra con sobretasa de 35 % (desde la 3.ª hora), hasta 2 decimales'],
            [],
            ['Notas', 'Los días de vacaciones vienen de los goces registrados. Las novedades en montos (bonos, adelantos) se registran en la grilla. Si una fila tiene error no se guarda nada.'],
        ];

        $nombre = 'asistencia-'.Str::slug($periodo->etiqueta()).'.xlsx';

        return response()->streamDownload(function () use ($filas, $instrucciones) {
            $writer = new Writer;
            $writer->openToFile('php://output');
            $writer->getCurrentSheet()->setName('Asistencia');
            $writer->addRow(Row::fromValues(ImportacionAsistencia::PLANTILLA));
            foreach ($filas as $fila) {
                $writer->addRow(Row::fromValues($fila));
            }
            $writer->addNewSheetAndMakeItCurrent()->setName('Instrucciones');
            foreach ($instrucciones as $fila) {
                $writer->addRow(Row::fromValues($fila));
            }
            $writer->close();
        }, $nombre, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    public function importar(Request $request, Periodo $periodo): RedirectResponse
    {
        Gate::authorize('update', $periodo);

        $request->validate(
            ['archivo' => ['required', 'file', 'extensions:xlsx,csv', 'max:2048']],
            ['archivo.extensions' => 'El archivo debe ser .xlsx o .csv.'],
            ['archivo' => 'archivo'],
        );

        /** @var UploadedFile $archivo */
        $archivo = $request->file('archivo');
        $total = $this->importacion->importar($periodo, (string) $archivo->getRealPath(), strtolower($archivo->getClientOriginalExtension()));

        Inertia::flash('toast', ['type' => 'success', 'message' => "Asistencia importada: {$total} ".($total === 1 ? 'trabajador actualizado' : 'trabajadores actualizados').'. Recalcula la planilla.']);

        return back();
    }
}
