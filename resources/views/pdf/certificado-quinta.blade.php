@php
    use App\Support\Formato;
    $meses = ['', 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'setiembre', 'octubre', 'noviembre', 'diciembre'];
    $hoy = now();
    $diferencia = \Brick\Math\BigDecimal::of($c['diferencia']);
@endphp
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Certificado de rentas y retenciones {{ $c['anio'] }} · {{ $trabajador->nombre_completo }}</title>
<style>
    @page { margin: 50px 60px; }
    * { font-family: "DejaVu Sans", sans-serif; }
    body { font-size: 10.5px; color: #0f172a; margin: 0; line-height: 1.6; }
    .cabecera { text-align: center; margin-bottom: 18px; }
    .empresa { font-size: 13px; font-weight: bold; }
    .suave { color: #475569; font-size: 9.5px; }
    h1 { text-align: center; font-size: 14px; letter-spacing: 1px; margin: 16px 0 4px; }
    h2 { text-align: center; font-size: 11px; font-weight: normal; margin: 0 0 18px; color: #475569; }
    table { width: 100%; border-collapse: collapse; }
    .datos td { padding: 3px 6px; }
    .datos td.etq { color: #475569; width: 32%; }
    .calc { margin-top: 14px; border: 1px solid #cbd5e1; }
    .calc td { padding: 6px 10px; border-bottom: 1px solid #e2e8f0; }
    .calc td.monto { text-align: right; width: 30%; font-variant-numeric: tabular-nums; }
    .calc tr.total td { font-weight: bold; background: #f1f5f9; }
    .nota { margin-top: 12px; font-size: 9px; color: #475569; }
    .firma { margin: 70px auto 0; width: 260px; text-align: center; border-top: 1px solid #0f172a; padding-top: 6px; }
    .pie { position: fixed; bottom: -25px; left: 0; right: 0; font-size: 7.5px; color: #94a3b8; text-align: center; }
</style>
</head>
<body>
<div class="cabecera">
    @if (!empty($logo))<img src="{{ $logo }}" alt="" style="max-height: 50px; max-width: 170px; margin-bottom: 6px;"><br>@endif
    <div class="empresa">{{ $empresa->razon_social }}</div>
    <div class="suave">RUC {{ $empresa->ruc }}@if ($empresa->direccion) · {{ $empresa->direccion }}@endif</div>
</div>

<h1>CERTIFICADO DE RENTAS Y RETENCIONES</h1>
<h2>Rentas de quinta categoría · Ejercicio {{ $c['anio'] }} (art. 45 del Reglamento de la Ley del Impuesto a la Renta)</h2>

<table class="datos">
    <tr><td class="etq">Trabajador</td><td><b>{{ $trabajador->nombre_completo }}</b></td></tr>
    <tr><td class="etq">Documento</td><td>{{ $trabajador->tipo_documento->abreviatura() }} {{ $trabajador->numero_documento }}</td></tr>
    <tr><td class="etq">Periodo laborado en el ejercicio</td><td>{{ $c['desde'] }} al {{ $c['hasta'] }}</td></tr>
</table>

<table class="calc">
    <tr><td>Remuneraciones (básico, asignación familiar, vacaciones)</td><td class="monto">S/ {{ Formato::monto($c['remuneraciones']) }}</td></tr>
    <tr><td>Gratificaciones y bonificaciones extraordinarias</td><td class="monto">S/ {{ Formato::monto($c['gratificaciones']) }}</td></tr>
    <tr><td>Otros ingresos afectos (horas extra, bonos y otros)</td><td class="monto">S/ {{ Formato::monto($c['otros']) }}</td></tr>
    <tr class="total"><td>Renta bruta del ejercicio</td><td class="monto">S/ {{ Formato::monto($c['renta_bruta']) }}</td></tr>
    <tr><td>Deducción anual</td><td class="monto">− S/ {{ Formato::monto($c['deduccion']) }}</td></tr>
    <tr class="total"><td>Renta neta</td><td class="monto">S/ {{ Formato::monto($c['renta_neta']) }}</td></tr>
    <tr><td>Impuesto a la renta del ejercicio</td><td class="monto">S/ {{ Formato::monto($c['impuesto']) }}</td></tr>
    <tr class="total"><td>Retenciones efectuadas por el empleador</td><td class="monto">S/ {{ Formato::monto($c['retenido']) }}</td></tr>
    <tr>
        <td>{{ $diferencia->isNegative() ? 'Retenido en exceso' : 'Saldo del impuesto' }}</td>
        <td class="monto">S/ {{ Formato::monto((string) $diferencia->abs()) }}</td>
    </tr>
</table>

<p class="nota">
    Rentas pagadas por {{ $empresa->razon_social }} en sus planillas cerradas del ejercicio. No incluye rentas de otros
    empleadores ni las deducciones adicionales de hasta 3 UIT, que el trabajador aplica en su declaración anual.
    @if ($c['pendientes'] > 0)
        <br><b>Hay {{ $c['pendientes'] }} {{ $c['pendientes'] === 1 ? 'planilla' : 'planillas' }} del ejercicio sin cerrar que no se incluyen.</b>
    @endif
</p>

<p style="text-align: right; margin-top: 18px;">{{ $hoy->day }} de {{ $meses[$hoy->month] }} de {{ $hoy->year }}</p>

<div class="firma">
    {{ $empresa->representante_legal ?? 'Representante legal' }}<br>
    <span class="suave">{{ $empresa->razon_social }}</span>
    @include('pdf.partials.firma-digital')
</div>

<div class="pie">Generado con Serialo Planillas · {{ $empresa->ruc }}</div>
</body>
</html>
