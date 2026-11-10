<?php

namespace App\Http\Controllers\Auditoria;

use App\Http\Controllers\Controller;
use App\Models\Auditoria;
use App\Models\Empresa;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/** Consulta del historial de auditoría (solo lectura). */
class AuditoriaController extends Controller
{
    /** Etiquetas de `auditable_type` (claves del morph map). */
    private const TIPOS = [
        'empresa' => 'Empresa',
        'establecimiento' => 'Establecimiento',
        'usuario' => 'Usuario',
        'trabajador' => 'Trabajador',
        'trabajador_historial' => 'Cambio de trabajador',
        'novedad' => 'Novedad',
        'planilla_linea' => 'Línea de planilla',
        'parametro' => 'Parámetro',
        'tasa_afp' => 'Tasa AFP',
        'concepto' => 'Concepto',
        'periodo' => 'Periodo',
        'liquidacion_cts' => 'CTS',
        'vacacion' => 'Vacaciones',
        'liquidacion' => 'Liquidación',
        'licencia' => 'Licencia',
        'respaldo' => 'Respaldo',
        'soporte' => 'Soporte',
    ];

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Auditoria::class);

        $filtros = [
            'empresa' => $request->integer('empresa') ?: null,
            'usuario' => $request->integer('usuario') ?: null,
            'tipo' => array_key_exists((string) $request->query('tipo'), self::TIPOS) ? (string) $request->query('tipo') : null,
            'accion' => array_key_exists((string) $request->query('accion'), Auditoria::ACCIONES) ? (string) $request->query('accion') : null,
            'desde' => $this->fecha($request->query('desde')),
            'hasta' => $this->fecha($request->query('hasta')),
        ];

        $registros = Auditoria::query()
            ->with(['usuario:id,name', 'empresa:id,razon_social'])
            ->when($filtros['empresa'], fn (Builder $q, int $id) => $q->where('empresa_id', $id))
            ->when($filtros['usuario'], fn (Builder $q, int $id) => $q->where('user_id', $id))
            ->when($filtros['tipo'], fn (Builder $q, string $tipo) => $q->where('auditable_type', $tipo))
            ->when($filtros['accion'], fn (Builder $q, string $accion) => $q->where('accion', $accion))
            ->when($filtros['desde'], fn (Builder $q, string $f) => $q->whereDate('created_at', '>=', $f))
            ->when($filtros['hasta'], fn (Builder $q, string $f) => $q->whereDate('created_at', '<=', $f))
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString()
            ->through(fn (Auditoria $a) => [
                'id' => $a->id,
                'fecha' => $a->created_at->format('d/m/Y H:i'),
                'usuario' => $a->usuario->name ?? 'Sistema',
                'empresa' => $a->empresa?->razon_social,
                'tipo' => $a->auditable_type,
                'tipo_etiqueta' => self::TIPOS[$a->auditable_type] ?? $a->auditable_type,
                'descripcion' => $a->descripcion,
                'accion' => $a->accion,
                'accion_etiqueta' => Auditoria::ACCIONES[$a->accion] ?? Str::ucfirst($a->accion),
                'ip' => $a->ip,
                'cambios' => $this->cambiosLegibles($a->cambios ?? []),
            ]);

        return Inertia::render('auditoria/Index', [
            'registros' => $registros,
            'filtros' => $filtros,
            'opciones' => [
                'empresas' => Empresa::withTrashed()->orderBy('razon_social')->get(['id', 'razon_social'])
                    ->map(fn (Empresa $e) => ['value' => (string) $e->id, 'label' => $e->razon_social])->values(),
                'usuarios' => User::query()->orderBy('name')->get(['id', 'name'])
                    ->map(fn (User $u) => ['value' => (string) $u->id, 'label' => $u->name])->values(),
                'tipos' => collect(self::TIPOS)->map(fn ($label, $value) => compact('value', 'label'))->values(),
                'acciones' => collect(Auditoria::ACCIONES)->map(fn ($label, $value) => compact('value', 'label'))->values(),
            ],
        ]);
    }

    /**
     * @param  array<string, array{0: mixed, 1: mixed}>  $cambios
     * @return list<array{campo: string, antes: string, despues: string}>
     */
    private function cambiosLegibles(array $cambios): array
    {
        $filas = [];

        foreach ($cambios as $campo => [$antes, $despues]) {
            $filas[] = [
                'campo' => $this->etiqueta((string) $campo),
                'antes' => $this->valor($antes),
                'despues' => $this->valor($despues),
            ];
        }

        return $filas;
    }

    private function etiqueta(string $campo): string
    {
        $clave = "validation.attributes.{$campo}";
        $etiqueta = __($clave);

        return Str::ucfirst($etiqueta === $clave ? str_replace('_', ' ', $campo) : (string) $etiqueta);
    }

    private function valor(mixed $valor): string
    {
        // Columnas JSON (p. ej. el detalle de una liquidación) llegan como texto: se muestran como lista legible.
        if (is_string($valor) && in_array($valor[0] ?? '', ['{', '['], true)) {
            $decodificado = json_decode($valor, true);
            if (is_array($decodificado)) {
                $valor = $decodificado;
            }
        }

        return match (true) {
            $valor === null => '—',
            $valor === true => 'Sí',
            $valor === false => 'No',
            is_array($valor) && array_is_list($valor) && array_filter($valor, is_array(...)) === [] => $valor === [] ? 'Ninguna' : implode(', ', array_map(fn ($v) => $this->valor($v), $valor)),
            is_array($valor) => implode("\n", $this->lineas($valor)) ?: '—',
            default => (string) $valor,
        };
    }

    /**
     * {"vacaciones": {"computable": "1243.00"}} → ["Vacaciones › Remuneración computable: 1243.00"]. Omite lo vacío.
     *
     * @param  array<mixed>  $datos
     * @return list<string>
     */
    private function lineas(array $datos, string $prefijo = ''): array
    {
        $lineas = [];

        foreach ($datos as $clave => $v) {
            if ($v === null || $v === []) {
                continue;
            }

            $nombre = $prefijo.(is_int($clave) ? '#'.($clave + 1) : $this->etiqueta($clave));

            if (is_array($v) && ! (array_is_list($v) && array_filter($v, is_array(...)) === [])) {
                array_push($lineas, ...$this->lineas($v, $nombre.' › '));
            } else {
                $lineas[] = "{$nombre}: {$this->valor($v)}";
            }
        }

        return $lineas;
    }

    private function fecha(mixed $valor): ?string
    {
        return is_string($valor) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor) === 1 ? $valor : null;
    }
}
