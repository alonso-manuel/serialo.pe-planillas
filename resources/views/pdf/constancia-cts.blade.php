@php
    use App\Domain\Planilla\MontoEnLetras;
    use App\Support\Formato;
@endphp
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Constancias de depósito CTS {{ $liquidacion->etiqueta() }}</title>
<style>
    @page { margin: 30px 36px; }
    * { font-family: "DejaVu Sans", sans-serif; }
    body { font-size: 10px; color: #0f172a; margin: 0; }
    .hoja { position: relative; height: 1030px; }
    .salto { page-break-after: always; }
    table { width: 100%; border-collapse: collapse; }
    td, th { vertical-align: top; }
    .empresa { font-size: 13px; font-weight: bold; }
    .suave { color: #475569; }
    .titulo { text-align: right; }
    .titulo .doc { font-size: 14px; font-weight: bold; letter-spacing: .5px; color: #14532d; }
    .linea { height: 3px; background: #14532d; margin: 8px 0 14px; }
    .caja { border: 1px solid #cbd5e1; border-radius: 4px; }
    .datos td { padding: 5px 8px; border-bottom: 1px solid #e2e8f0; }
    .datos tr:last-child td { border-bottom: none; }
    .etq { color: #64748b; font-size: 8px; text-transform: uppercase; letter-spacing: .3px; display: block; }
    .val { font-weight: bold; }
    h2 { font-size: 10.5px; margin: 14px 0 6px; color: #14532d; }
    .calc td { padding: 4px 8px; border-bottom: 1px solid #f1f5f9; }
    .calc .monto { text-align: right; white-space: nowrap; }
    .calc .total td { border-top: 1px solid #cbd5e1; font-weight: bold; background: #f8fafc; }
    .deposito { margin-top: 14px; }
    .deposito td { padding: 10px; }
    .deposito .importe { background: #14532d; color: #fff; text-align: right; width: 36%; border-radius: 4px; }
    .deposito .importe .etq { color: #bbf7d0; }
    .deposito .cifra { font-size: 16px; font-weight: bold; }
    .texto { margin-top: 14px; line-height: 1.5; }
    .firmas { margin-top: 70px; }
    .firmas td { width: 50%; text-align: center; padding: 0 30px; }
    .firma { border-top: 1px solid #0f172a; padding-top: 4px; }
    .pie { position: absolute; bottom: 0; left: 0; right: 0; font-size: 7.5px; color: #94a3b8; text-align: center; }
    .borrador { position: absolute; top: 330px; left: 30px; width: 660px; text-align: center; font-size: 96px;
        font-weight: bold; color: #dc2626; opacity: .10; transform: rotate(-30deg); letter-spacing: 8px; }
</style>
</head>
<body>
@foreach ($lineas as $l)
    @php $t = $l->trabajador; @endphp
    <div class="hoja{{ $loop->last ? '' : ' salto' }}">
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
                    <div class="doc">CONSTANCIA DE DEPÓSITO DE CTS</div>
                    <div>Depósito de <b>{{ $liquidacion->etiqueta() }}</b></div>
                    <div class="suave">Art. 29 del D.S. 001-97-TR</div>
                </td>
            </tr>
        </table>
        <div class="linea"></div>

        <div class="caja">
            <table class="datos">
                <tr>
                    <td colspan="2"><span class="etq">Trabajador</span><span class="val">{{ $t->nombre_completo }}</span></td>
                    <td><span class="etq">Documento</span><span class="val">{{ $t->tipo_documento->abreviatura() }} {{ $t->numero_documento }}</span></td>
                    <td><span class="etq">Fecha de ingreso</span>{{ $t->fecha_ingreso->format('d/m/Y') }}</td>
                </tr>
                <tr>
                    <td colspan="2"><span class="etq">Periodo computable</span>{{ $l->computo_desde->format('d/m/Y') }} al {{ $l->computo_hasta->format('d/m/Y') }}</td>
                    <td><span class="etq">Tiempo computable</span>{{ $l->meses }} {{ $l->meses === 1 ? 'mes' : 'meses' }} y {{ $l->dias }} {{ $l->dias === 1 ? 'día' : 'días' }}</td>
                    <td><span class="etq">Días descontados</span>{{ $l->dias_descontados }}</td>
                </tr>
                <tr>
                    <td colspan="2"><span class="etq">Entidad depositaria</span>{{ $l->entidad_cts ? ($entidades[$l->entidad_cts] ?? $l->entidad_cts) : '—' }}</td>
                    <td colspan="2"><span class="etq">Cuenta CTS (soles)</span>{{ $l->cuenta_cts ?? '—' }}</td>
                </tr>
            </table>
        </div>

        <h2>Remuneración computable</h2>
        <table class="calc caja">
            <tr><td>Remuneración básica</td><td class="monto">{{ Formato::monto($l->remuneracion_basica) }}</td></tr>
            <tr><td>Asignación familiar</td><td class="monto">{{ Formato::monto($l->asignacion_familiar) }}</td></tr>
            <tr><td>1/6 de la gratificación</td><td class="monto">{{ Formato::monto($l->sexto_gratificacion) }}</td></tr>
            <tr><td>Promedio de remuneraciones variables</td><td class="monto">{{ Formato::monto($l->promedio_variables) }}</td></tr>
            <tr class="total"><td>Remuneración computable</td><td class="monto">S/ {{ Formato::monto($l->remuneracion_computable) }}</td></tr>
        </table>

        <table class="deposito caja">
            <tr>
                <td>
                    <span class="etq">Son</span>
                    <b>{{ MontoEnLetras::soles((string) $l->monto) }}</b>
                    <div class="suave" style="margin-top:4px">Cálculo: remuneración computable ÷ 12 × meses + ÷ 360 × días.</div>
                </td>
                <td class="importe">
                    <span class="etq">Monto depositado</span>
                    <span class="cifra">S/ {{ Formato::monto($l->monto) }}</span>
                </td>
            </tr>
        </table>

        <p class="texto">
            {{ $empresa->razon_social }} deja constancia de haber depositado la compensación por tiempo de servicios
            correspondiente al periodo indicado en la cuenta CTS del trabajador. Fecha de depósito: ____ / ____ / ________.
        </p>

        <table class="firmas">
            <tr>
                <td><div class="firma">Empleador<br><span class="suave">{{ $empresa->razon_social }}</span></div></td>
                <td><div class="firma">Recibí conforme<br><span class="suave">{{ $t->nombre_completo }}</span></div></td>
            </tr>
        </table>

        <div class="pie">
            Constancia generada con Serialo Planillas · {{ $empresa->ruc }} · CTS {{ $liquidacion->etiqueta() }}
            @if ($borrador) · Documento de revisión: la liquidación aún no está cerrada @endif
        </div>
    </div>
@endforeach
</body>
</html>
