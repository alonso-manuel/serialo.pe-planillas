<?php

namespace App\Http\Controllers\Guia;

use App\Http\Controllers\Controller;
use App\Support\GuiaVideos;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Ayuda → Guía (todos los usuarios): videos tutoriales por módulo y el manual de usuario en PDF.
 * No pertenece a ninguna empresa: es el mismo contenido para todos. Los videos solo se entregan con sesión iniciada.
 */
class GuiaController extends Controller
{
    /** Manual generado por tools/manual/generar-pdf.mjs desde docs/manual-usuario.md. */
    public const MANUAL = 'manual/manual-usuario.pdf';

    public function index(): Response
    {
        $secciones = [];
        foreach (GuiaVideos::SECCIONES as $titulo => $videos) {
            $secciones[] = [
                'titulo' => $titulo,
                'videos' => array_map(fn (array $v) => [
                    ...$v,
                    'disponible' => is_file(GuiaVideos::ruta($v['clave'])),
                    'url' => route('guia.video', $v['clave']),
                ], $videos),
            ];
        }

        return Inertia::render('guia/Index', [
            'secciones' => $secciones,
            'manual' => is_file(resource_path(self::MANUAL)),
        ]);
    }

    public function video(string $clave): BinaryFileResponse|HttpResponse
    {
        abort_if(GuiaVideos::buscar($clave) === null, 404);
        $ruta = GuiaVideos::ruta($clave);
        abort_unless(is_file($ruta), 404);

        $cabeceras = ['Content-Type' => 'video/mp4', 'Cache-Control' => 'private, max-age=86400'];

        // Con Nginx delante: PHP ya validó la sesión; Nginx entrega el archivo (rangos para adelantar el video).
        if (config('planillas.guia.x_accel')) {
            return response('', 200, [...$cabeceras, 'X-Accel-Redirect' => '/_guia/'.GuiaVideos::archivo($clave)]);
        }

        // Sin Nginx: Symfony atiende los rangos (Range) para poder adelantar el video.
        return response()->file($ruta, $cabeceras);
    }

    public function manual(): BinaryFileResponse
    {
        $ruta = resource_path(self::MANUAL);
        abort_unless(is_file($ruta), 404);

        return response()->download($ruta, 'Manual de usuario - Serialo Planillas.pdf');
    }
}
