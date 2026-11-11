@php
    use App\Domain\Planilla\MontoEnLetras;
    use App\Support\Formato;
    $d = $liquidacion->detalle ?? [];
    $plural = fn (int $n, string $uno, string $varios) => $n.' '.($n === 1 ? $uno : $varios);
@endphp
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Liquidación de beneficios sociales · {{ $trabajador->nombre_completo }}</title>
<style>
    @page { margin: 30px 36px; }
    * { font-family: "DejaVu Sans", sans-serif; }
    body { font-size: 9.5px; color: #0f172a; margin: 0; }
    table { width: 100%; border-collapse: collapse; }
    td, th { vertical-align: top; }
    .empresa { font-size: 13px; font-weight: bold; }
    .suave { color: #475569; }
    .titulo { text-align: right; }
    .titulo .doc { font-size: 13px; font-weight: bold; letter-spacing: .4px; color: #1e3a8a; }
    .linea { height: 3px; background: #1e3a8a; margin: 8px 0 12px; }
    .caja { border: 1px solid #cbd5e1; border-radius: 4px; }
    .datos td { padding: 4px 8px; border-bottom: 1px solid #e2e8f0; }
    .datos tr:last-child td { border-bottom: none; }
    .etq { color: #64748b; font-size: 7.5px; text-transform: uppercase; letter-spacing: .3px; display: block; }
    .val { font-weight: bold; }
    h2 { font-size: 10px; margin: 12px 0 5px; color: #1e3a8a; }
    .calc td { padding: 3px 8px; border-bottom: 1px solid #f1f5f9; }
    .calc .monto { text-align: right; white-space: nowrap; width: 110px; }
    .calc .sub td { border-top: 1px solid #cbd5e1; font-weight: bold; background: #f8fafc; }
    .total { margin-top: 12px; }
    .total td { padding: 9px; }
    .total .importe { background: #1e3a8a; color: #fff; text-align: right; width: 36%; border-radius: 4px; }
    .total .importe .etq { color: #bfdbfe; }
    .total .cifra { font-size: 15px; font-weight: bold; }
    .nota { margin-top: 8px; color: #475569; line-height: 1.45; }
    .firmas { margin-top: 56px; }
    .firmas td { width: 50%; text-align: center; padding: 0 30px; }
    .firma { border-top: 1px solid #0f172a; padding-top: 4px; }
    .pie { position: fixed; bottom: -8px; left: 0; right: 0; font-size: 7px; color: #94a3b8; text-align: center; }
    .borrador { position: fixed; top: 330px; left: 30px; width: 660px; text-align: center; font-size: 96px;
        font-weight: bold; color: #dc2626; opacity: .10; transform: rotate(-30deg); letter-spacing: 8px; }
</style>
</head>
<body>
@if ($borrador)
    <div class="borrador">BORRADOR</div>
@endif

<table>
    <tr>
        <td>
            @if (!empty($logo))<img src="{{ $logo }}" alt="" style="max-height: 46px; max-width: 160px; margin-bottom: 4px;"><br>@endif
            <div class="empresa">{{ $empresa->razon_social }}</div>
            <div class="suave">RUC {{ $empresa->ruc }}</div>
            @if ($empresa->direccion)<div class="suave">{{ $empresa->direccion }}</div>@endif
        </td>
        <td class="titulo">
            <div class="doc">LIQUIDACIÓN DE BENEFICIOS SOCIALES</div>
            <div>Cese: <b>{{ $liquidacion->fecha_cese->format('d/m/Y') }}</b></div>
            <div class="suave">{{ $empresa->regimen_laboral->etiqueta() }}</div>
        </td>
    </tr>
</table>
<div class="linea"></div>

<div class="caja">
    <table class="datos">
        <tr>
            <td colspan="2"><span class="etq">Trabajador</span><span class="val">{{ $trabajador->nombre_completo }}</span></td>
            <td><span class="etq">Documento</span><span class="val">{{ $trabajador->tipo_documento->abreviatura() }} {{ $trabajador->numero_documento }}</span></td>
            <td><span class="etq">Cargo</span>{{ $trabajador->cargo ?? '—' }}</td>
        </tr>
        <tr>
            <td><span class="etq">Fecha de ingreso</span>{{ $trabajador->fecha_ingreso->format('d/m/Y') }}</td>
            <td><span class="etq">Fecha de cese</span>{{ $liquidacion->fecha_cese->format('d/m/Y') }}</td>
            <td><span class="etq">Tiempo de servicios</span>{{ $plural($tiempo['anios'], 'año', 'años') }}, {{ $plural($tiempo['meses'], 'mes', 'meses') }} y {{ $plural($tiempo['dias'], 'día', 'días') }}</td>
            <td><span class="etq">Motivo del cese</span>{{ $trabajador->motivo_cese?->etiqueta() ?? '—' }}</td>
        </tr>
    </table>
</div>

@foreach (['gratificacion_pendiente' => 'Gratificación del semestre (no pagada en planilla)', 'gratificacion_trunca' => 'Gratificación trunca'] as $clave => $titulo)
    @if (!empty($d[$clave]))
        @php $g = $d[$clave]; @endphp
        <h2>{{ $titulo }}</h2>
        <table class="calc caja">
            <tr><td>Remuneración computable (incluye promedio de variables: S/ {{ Formato::monto($g['promedio_variables']) }})</td><td class="monto">{{ Formato::monto($g['computable']) }}</td></tr>
            <tr><td>Meses computables: {{ $g['meses'] }} · días de falta descontados: {{ $g['faltas'] }}</td><td class="monto"></td></tr>
            <tr><td>Gratificación</td><td class="monto">{{ Formato::monto($g['gratificacion']) }}</td></tr>
            <tr><td>Bonificación extraordinaria (Ley 30334)</td><td class="monto">{{ Formato::monto($g['bonificacion']) }}</td></tr>
        </table>
    @endif
@endforeach

@if (!empty($d['cts']))
    @php $c = $d['cts']; @endphp
    <h2>Compensación por tiempo de servicios trunca</h2>
    <table class="calc caja">
        <tr><td>Periodo computable: {{ \Carbon\CarbonImmutable::parse($c['desde'])->format('d/m/Y') }} al {{ \Carbon\CarbonImmutable::parse($c['hasta'])->format('d/m/Y') }} · {{ $plural($c['meses'], 'mes', 'meses') }} y {{ $plural($c['dias'], 'día', 'días') }}
            @if ($c['dias_descontados'] > 0) · {{ $c['dias_descontados'] }} días descontados @endif
        </td><td class="monto"></td></tr>
        <tr><td>Remuneración básica</td><td class="monto">{{ Formato::monto($c['basico']) }}</td></tr>
        <tr><td>Asignación familiar</td><td class="monto">{{ Formato::monto($c['asignacion']) }}</td></tr>
        <tr><td>1/6 de la gratificación</td><td class="monto">{{ Formato::monto($c['sexto_gratificacion']) }}</td></tr>
        <tr><td>Promedio de remuneraciones variables</td><td class="monto">{{ Formato::monto($c['promedio_variables']) }}</td></tr>
        <tr class="sub"><td>Remuneración computable</td><td class="monto">{{ Formato::monto($c['computable']) }}</td></tr>
        <tr class="sub"><td>CTS trunca (÷ 12 × meses + ÷ 360 × días)</td><td class="monto">{{ Formato::monto($c['monto']) }}</td></tr>
    </table>
@endif

@php $v = $d['vacaciones'] ?? null; @endphp
@if ($v && (\Brick\Math\BigDecimal::of((string) $liquidacion->vacaciones_no_gozadas)->isPositive() || \Brick\Math\BigDecimal::of((string) $liquidacion->vacaciones_truncas)->isPositive()))
    <h2>Vacaciones</h2>
    <table class="calc caja">
        <tr><td>Remuneración computable (incluye promedio de variables: S/ {{ Formato::monto($v['promedio_variables']) }})</td><td class="monto">{{ Formato::monto($v['computable']) }}</td></tr>
        @if ($v['no_gozadas_dias'] > 0)
            <tr><td>Vacaciones ganadas y no gozadas: {{ $v['no_gozadas_dias'] }} días</td><td class="monto">{{ Formato::monto($liquidacion->vacaciones_no_gozadas) }}</td></tr>
        @endif
        @if ($v['indemnizacion_dias'] > 0)
            <tr><td>Indemnización vacacional (periodos vencidos sin gozar): {{ $v['indemnizacion_dias'] }} días</td><td class="monto">{{ Formato::monto($liquidacion->indemnizacion_vacacional) }}</td></tr>
        @endif
        @if ($v['truncas_meses'] > 0)
            <tr><td>Vacaciones truncas: {{ $plural($v['truncas_meses'], 'mes', 'meses') }} y {{ $plural($v['truncas_dias'], 'día', 'días') }}</td><td class="monto">{{ Formato::monto($liquidacion->vacaciones_truncas) }}</td></tr>
        @endif
    </table>
@endif

@php $ind = $d['indemnizacion'] ?? null; @endphp
@if ($ind && \Brick\Math\BigDecimal::of((string) $ind['monto'])->isPositive())
    <h2>Indemnización por despido arbitrario</h2>
    <table class="calc caja">
        <tr><td>Remuneración mensual ordinaria</td><td class="monto">{{ Formato::monto($ind['remuneracion']) }}</td></tr>
        <tr><td>
            @if ($ind['codigo'] === '0503')
                Meses que faltaban del contrato (vence el {{ $liquidacion->fin_contrato?->format('d/m/Y') }}):
            @else
                Tiempo de servicios:
            @endif
            {{ $plural($ind['anios'], 'año', 'años') }}, {{ $plural($ind['meses'], 'mes', 'meses') }} y {{ $plural($ind['dias'], 'día', 'días') }}
        </td><td class="monto"></td></tr>
        <tr class="sub"><td>{{ $ind['codigo'] === '0503' ? 'Art. 76 del D.S. 003-97-TR' : 'Art. 38 del D.S. 003-97-TR' }}{{ $ind['tope'] ? ' · con el tope legal' : '' }}</td><td class="monto">{{ Formato::monto($ind['monto']) }}</td></tr>
    </table>
@endif

<table class="total caja">
    <tr>
        <td>
            <span class="etq">Son</span>
            <b>{{ MontoEnLetras::soles((string) $liquidacion->total) }}</b>
            <div class="suave" style="margin-top:4px">Total de beneficios sociales, antes de los descuentos de ley.</div>
        </td>
        <td class="importe">
            <span class="etq">Total beneficios</span>
            <span class="cifra">S/ {{ Formato::monto($liquidacion->total) }}</span>
        </td>
    </tr>
</table>

@if ($planilla)
    <h2>Pago en la planilla de {{ $mesCese }}</h2>
    <table class="calc caja">
        @foreach ($planilla['remuneraciones'] as $f)
            <tr><td>{{ $f['codigo'] }} · {{ $f['nombre'] }}</td><td class="monto">{{ Formato::monto($f['monto']) }}</td></tr>
        @endforeach
        @foreach ($planilla['beneficios'] as $f)
            <tr><td>{{ $f['codigo'] }} · {{ $f['nombre'] }}</td><td class="monto">{{ Formato::monto($f['monto']) }}</td></tr>
        @endforeach
        @foreach ($planilla['descuentos'] as $f)
            <tr><td>(−) {{ $f['codigo'] }} · {{ $f['nombre'] }}</td><td class="monto">−{{ Formato::monto($f['monto']) }}</td></tr>
        @endforeach
        <tr class="sub"><td>Neto a pagar (remuneración del mes + beneficios − descuentos)</td><td class="monto">S/ {{ Formato::monto($planilla['neto']) }}</td></tr>
    </table>
@else
    <p class="nota">Los aportes de pensión sobre las vacaciones y los demás descuentos de ley se calculan en la planilla de {{ $mesCese }}, donde se paga esta liquidación junto con la remuneración del mes. Calcula (o recalcula) esa planilla para ver aquí el neto a pagar.</p>
@endif

<p class="nota">La CTS de periodos anteriores ya depositados se retira directamente de la entidad financiera. Plazo de pago: 48 horas desde el cese.</p>

<table class="firmas">
    <tr>
        <td><div class="firma">Empleador<br><span class="suave">{{ $empresa->razon_social }}</span>@include('pdf.partials.firma-digital')</div></td>
        <td><div class="firma">Recibí conforme<br><span class="suave">{{ $trabajador->nombre_completo }}</span></div></td>
    </tr>
</table>

<div class="pie">
    Generado con Serialo Planillas · {{ $empresa->ruc }} · Liquidación de {{ $trabajador->nombre_completo }}
    @if ($borrador) · Documento de revisión: la liquidación aún no está cerrada @endif
</div>
</body>
</html>
