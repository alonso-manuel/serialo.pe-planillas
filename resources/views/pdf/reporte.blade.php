@php
    use App\Support\Formato;
    $muchas = collect($reporte['tablas'])->max(fn ($t) => count($t['columnas'])) > 12;
@endphp
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>{{ $reporte['titulo'] }} {{ $periodo->etiqueta() }}</title>
<style>
    @page { margin: 24px 26px 34px; }
    * { font-family: "DejaVu Sans", sans-serif; }
    body { font-size: {{ $muchas ? '6.5px' : '8.5px' }}; color: #0f172a; margin: 0; }
    table { width: 100%; border-collapse: collapse; }
    .cabecera td { vertical-align: top; }
    .empresa { font-size: 12px; font-weight: bold; }
    .suave { color: #475569; }
    .titulo { text-align: right; font-size: 13px; font-weight: bold; color: #1e3a8a; }
    .linea { height: 2px; background: #1e3a8a; margin: 6px 0 10px; }
    h2 { font-size: 9.5px; margin: 12px 0 5px; color: #1e3a8a; }
    .aviso { background: #fffbeb; color: #92400e; padding: 5px 8px; border-radius: 3px; margin-bottom: 6px; font-size: 8px; }
    .datos th { background: #eff6ff; color: #1e3a8a; text-align: left; padding: 4px 4px; border-bottom: 1px solid #bfdbfe; font-weight: bold; white-space: normal; vertical-align: bottom; }
    .datos th .nombre { display: block; font-weight: normal; font-size: .85em; color: #475569; }
    .datos th.num { text-align: right; }
    .datos td { padding: 3px 4px; border-bottom: 1px solid #f1f5f9; }
    .datos td.num { text-align: right; white-space: nowrap; }
    .datos .total td { border-top: 1px solid #94a3b8; font-weight: bold; background: #f8fafc; }
    .borrador { position: fixed; top: 220px; left: 180px; font-size: 90px; font-weight: bold; color: #dc2626;
        opacity: .08; transform: rotate(-20deg); letter-spacing: 8px; }
    .pie { position: fixed; bottom: -20px; left: 0; right: 0; font-size: 7px; color: #94a3b8; text-align: center; }
</style>
</head>
<body>
@if ($borrador)
    <div class="borrador">BORRADOR</div>
@endif
<div class="pie">Serialo Planillas · {{ $empresa->ruc }} · {{ $reporte['titulo'] }} {{ $periodo->etiqueta() }} · generado el {{ now()->format('d/m/Y H:i') }}</div>

<table class="cabecera">
    <tr>
        <td>
            <div class="empresa">{{ $empresa->razon_social }}</div>
            <div class="suave">RUC {{ $empresa->ruc }} · {{ $empresa->regimen_laboral->etiqueta() }}</div>
        </td>
        <td class="titulo">
            {{ $reporte['titulo'] }}<br>
            <span class="suave" style="font-size:10px;font-weight:normal">{{ $periodo->etiqueta() }} · {{ $periodo->estado->etiqueta() }}</span>
        </td>
    </tr>
</table>
<div class="linea"></div>

@foreach ($reporte['avisos'] as $aviso)
    <div class="aviso">{{ $aviso }}</div>
@endforeach

@foreach ($reporte['tablas'] as $tabla)
    <h2>{{ $tabla['titulo'] }}</h2>
    <table class="datos">
        <thead>
            <tr>
                @foreach ($tabla['columnas'] as $c)
                    <th class="{{ $c['tipo'] === 'texto' ? '' : 'num' }}">
                        @if (preg_match('/^(\d{4}) (.+)$/u', $c['titulo'], $m))
                            {{ $m[1] }}<span class="nombre">{{ \Illuminate\Support\Str::limit($m[2], 28) }}</span>
                        @else
                            {{ $c['titulo'] }}
                        @endif
                    </th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($tabla['filas'] as $fila)
                <tr>
                    @foreach ($tabla['columnas'] as $c)
                        @php $v = $fila[$c['clave']] ?? null; @endphp
                        <td class="{{ $c['tipo'] === 'texto' ? '' : 'num' }}">
                            {{ $v === null || $v === '' ? '' : ($c['tipo'] === 'monto' ? Formato::monto((string) $v) : $v) }}
                        </td>
                    @endforeach
                </tr>
            @endforeach
            @if ($tabla['totales'] !== null)
                <tr class="total">
                    @foreach ($tabla['columnas'] as $c)
                        @php $v = $tabla['totales'][$c['clave']] ?? null; @endphp
                        <td class="{{ $c['tipo'] === 'texto' ? '' : 'num' }}">
                            {{ $v === null || $v === '' ? '' : ($c['tipo'] === 'monto' ? Formato::monto((string) $v) : $v) }}
                        </td>
                    @endforeach
                </tr>
            @endif
        </tbody>
    </table>
@endforeach
</body>
</html>
