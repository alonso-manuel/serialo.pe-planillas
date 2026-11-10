@php
    $meses = ['', 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'setiembre', 'octubre', 'noviembre', 'diciembre'];
    $fecha = fn ($f) => $f->day.' de '.$meses[$f->month].' de '.$f->year;
    $hoy = now();
@endphp
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Certificado de trabajo · {{ $trabajador->nombre_completo }}</title>
<style>
    @page { margin: 60px 70px; }
    * { font-family: "DejaVu Sans", sans-serif; }
    body { font-size: 11px; color: #0f172a; margin: 0; line-height: 1.7; }
    .cabecera { text-align: center; margin-bottom: 30px; }
    .empresa { font-size: 13px; font-weight: bold; }
    .suave { color: #475569; font-size: 10px; }
    h1 { text-align: center; font-size: 17px; letter-spacing: 2px; margin: 30px 0 30px; }
    p { text-align: justify; margin: 0 0 14px; }
    .firma { margin: 110px auto 0; width: 260px; text-align: center; border-top: 1px solid #0f172a; padding-top: 6px; }
    .pie { position: fixed; bottom: -30px; left: 0; right: 0; font-size: 7.5px; color: #94a3b8; text-align: center; }
</style>
</head>
<body>
<div class="cabecera">
    @if (!empty($logo))<img src="{{ $logo }}" alt="" style="max-height: 54px; max-width: 180px; margin-bottom: 6px;"><br>@endif
    <div class="empresa">{{ $empresa->razon_social }}</div>
    <div class="suave">RUC {{ $empresa->ruc }}@if ($empresa->direccion) · {{ $empresa->direccion }}@endif</div>
</div>

<h1>CERTIFICADO DE TRABAJO</h1>

<p>
    {{ $empresa->razon_social }}, con RUC {{ $empresa->ruc }}, certifica que
    <b>{{ $trabajador->nombre_completo }}</b>, identificado(a) con {{ $trabajador->tipo_documento->abreviatura() }}
    N.° {{ $trabajador->numero_documento }}, ha laborado en nuestra empresa desde el
    <b>{{ $fecha($trabajador->fecha_ingreso) }}</b>
    @if ($trabajador->fecha_cese) hasta el <b>{{ $fecha($trabajador->fecha_cese) }}</b>, @else a la fecha, @endif
    desempeñando el cargo de <b>{{ $trabajador->cargo ?? '—' }}</b>.
</p>

<p>Se expide el presente certificado a solicitud del interesado(a), para los fines que estime convenientes.</p>

<p style="text-align: right; margin-top: 30px;">{{ $fecha($hoy) }}</p>

<div class="firma">
    {{ $empresa->representante_legal ?? 'Representante legal' }}<br>
    <span class="suave">{{ $empresa->razon_social }}</span>
</div>

<div class="pie">Generado con Serialo Planillas · {{ $empresa->ruc }}</div>
</body>
</html>
