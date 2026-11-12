<?php

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Models\Central\Dominio;
use App\Support\ModoSistema;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\IpUtils;

/**
 * Modo SaaS: Caddy pregunta aquí antes de emitir el certificado HTTPS de un subdominio (on-demand TLS).
 * 200 = el panel o un estudio que existe; 404 = no (así nadie fuerza certificados para subdominios inventados).
 * Solo atiende a la red interna (Caddy está en la misma red de Docker) para no revelar qué estudios existen.
 */
class PermisoCertificadoController extends Controller
{
    public function __invoke(Request $request): Response
    {
        abort_unless(ModoSistema::saas(), 404);
        abort_unless(IpUtils::isPrivateIp((string) $request->ip()), 403);

        $dominio = mb_strtolower(trim($request->string('domain')->toString()));
        abort_if($dominio === '', 404);

        if ($dominio === mb_strtolower((string) config('planillas.dominio_panel'))) {
            return response('', 200);
        }

        $base = $this->dominioBase();
        abort_if($base === null || ! str_ends_with($dominio, '.'.$base), 404);

        $subdominio = substr($dominio, 0, -strlen('.'.$base));
        abort_if($subdominio === '' || str_contains($subdominio, '.'), 404);
        abort_unless(Dominio::query()->where('domain', $subdominio)->exists(), 404);

        return response('', 200);
    }

    /** «impulso.cfd» a partir de PLANILLAS_URL_ESTUDIO (https://{estudio}.impulso.cfd). */
    private function dominioBase(): ?string
    {
        $host = parse_url(str_replace('{estudio}', 'x', (string) config('planillas.url_estudio')), PHP_URL_HOST);

        return is_string($host) && str_starts_with($host, 'x.') ? mb_strtolower(substr($host, 2)) : null;
    }
}
