@php
    $meses = ['', 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'setiembre', 'octubre', 'noviembre', 'diciembre'];
    $fecha = fn ($f) => $f->day.' de '.$meses[$f->month].' de '.$f->year;
@endphp
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Carta de liberación de CTS · {{ $trabajador->nombre_completo }}</title>
<style>
    @page { margin: 60px 70px; }
    * { font-family: "DejaVu Sans", sans-serif; }
    body { font-size: 11px; color: #0f172a; margin: 0; line-height: 1.65; }
    .empresa { font-size: 13px; font-weight: bold; }
    .suave { color: #475569; font-size: 10px; }
    p { text-align: justify; margin: 0 0 12px; }
    table.datos { border-collapse: collapse; margin: 6px 0 16px; }
    table.datos td { padding: 3px 14px 3px 0; }
    .firma { margin: 90px 0 0; width: 260px; text-align: center; border-top: 1px solid #0f172a; padding-top: 6px; }
    .pie { position: fixed; bottom: -30px; left: 0; right: 0; font-size: 7.5px; color: #94a3b8; text-align: center; }
    .borrador { position: fixed; top: 300px; left: 0; width: 600px; text-align: center; font-size: 90px;
        font-weight: bold; color: #dc2626; opacity: .10; transform: rotate(-30deg); letter-spacing: 8px; }
</style>
</head>
<body>
@if ($borrador)
    <div class="borrador">BORRADOR</div>
@endif

@if (!empty($logo))<img src="{{ $logo }}" alt="" style="max-height: 50px; max-width: 170px; margin-bottom: 6px;"><br>@endif
<div class="empresa">{{ $empresa->razon_social }}</div>
<div class="suave">RUC {{ $empresa->ruc }}@if ($empresa->direccion) · {{ $empresa->direccion }}@endif</div>

<p style="text-align: right; margin-top: 30px;">{{ $fecha(now()) }}</p>

<p>
    Señores<br>
    <b>{{ $entidad ?? '________________________________' }}</b><br>
    Presente.-
</p>

<p><b>Asunto:</b> Comunicación de cese para la liberación de la CTS</p>

<p>
    Por medio de la presente comunicamos el cese del siguiente trabajador, a fin de que se ponga a su disposición
    el íntegro del depósito de su compensación por tiempo de servicios y sus intereses, conforme al artículo 45
    del Texto Único Ordenado de la Ley de CTS (D.S. 001-97-TR):
</p>

<table class="datos">
    <tr><td class="suave">Trabajador</td><td><b>{{ $trabajador->nombre_completo }}</b></td></tr>
    <tr><td class="suave">Documento</td><td>{{ $trabajador->tipo_documento->abreviatura() }} {{ $trabajador->numero_documento }}</td></tr>
    <tr><td class="suave">Cuenta CTS</td><td>{{ $trabajador->cuenta_cts ?? '________________________' }}</td></tr>
    <tr><td class="suave">Fecha de ingreso</td><td>{{ $trabajador->fecha_ingreso->format('d/m/Y') }}</td></tr>
    <tr><td class="suave">Fecha de cese</td><td>{{ $liquidacion->fecha_cese->format('d/m/Y') }}</td></tr>
    <tr><td class="suave">Motivo</td><td>{{ $trabajador->motivo_cese?->etiqueta() ?? '—' }}</td></tr>
</table>

<p>Sin otro particular, quedamos de ustedes.</p>

<p>Atentamente,</p>

<div class="firma">
    {{ $empresa->representante_legal ?? 'Representante legal' }}<br>
    <span class="suave">{{ $empresa->razon_social }}</span>
    @include('pdf.partials.firma-digital')
</div>

<div class="pie">Generado con Serialo Planillas · {{ $empresa->ruc }}</div>
</body>
</html>
