@php
    use App\Support\Formato;
@endphp
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Boletas de pago {{ $periodo->etiqueta() }}</title>
<style>
    @page { margin: 26px 30px; }
    * { font-family: "DejaVu Sans", sans-serif; }
    body { font-size: 9.5px; color: #0f172a; margin: 0; }
    .hoja { position: relative; }
    .salto { page-break-after: always; }
    table { width: 100%; border-collapse: collapse; }
    td, th { vertical-align: top; }

    .cabecera td { padding: 0; }
    .empresa { font-size: 13px; font-weight: bold; }
    .suave { color: #475569; }
    .titulo { text-align: right; }
    .titulo .doc { font-size: 15px; font-weight: bold; letter-spacing: 1px; color: #1e3a8a; }
    .titulo .mes { font-size: 11px; margin-top: 2px; }
    .linea-azul { height: 3px; background: #1e3a8a; margin: 8px 0 10px; }

    .caja { border: 1px solid #cbd5e1; border-radius: 4px; }
    .datos td { padding: 4px 7px; border-bottom: 1px solid #e2e8f0; }
    .datos tr:last-child td { border-bottom: none; }
    .etq { color: #64748b; font-size: 8px; text-transform: uppercase; letter-spacing: .3px; display: block; }
    .val { font-weight: bold; }

    .dias { margin-top: 8px; }
    .dias td { text-align: center; padding: 5px 4px; border-right: 1px solid #e2e8f0; }
    .dias td:last-child { border-right: none; }

    .conceptos { margin-top: 10px; }
    .conceptos > tbody > tr > td { width: 33.33%; padding: 0; }
    .conceptos > tbody > tr > td + td { padding-left: 8px; }
    .grupo th { background: #eff6ff; color: #1e3a8a; text-align: left; font-size: 9px; padding: 5px 6px;
        border-bottom: 1px solid #bfdbfe; }
    .grupo td { padding: 3px 6px; border-bottom: 1px solid #f1f5f9; }
    .grupo .cod { color: #64748b; font-size: 8px; width: 26px; }
    .grupo .monto { text-align: right; white-space: nowrap; }
    .grupo .total td { border-top: 1px solid #cbd5e1; border-bottom: none; font-weight: bold; background: #f8fafc; }
    .vacio { color: #94a3b8; font-style: italic; }

    .neto { margin-top: 10px; }
    .neto td { padding: 8px 10px; }
    .neto .importe { background: #1e3a8a; color: #fff; text-align: right; width: 34%; border-radius: 4px; }
    .neto .importe .etq { color: #bfdbfe; }
    .neto .importe .cifra { font-size: 15px; font-weight: bold; }
    .letras { font-size: 9px; }

    .firmas { margin-top: 60px; }
    .firmas td { width: 50%; text-align: center; padding: 0 30px; }
    .firma { border-top: 1px solid #0f172a; padding-top: 4px; }

    .pie { position: absolute; bottom: -10px; left: 0; right: 0; font-size: 7.5px; color: #94a3b8; text-align: center; }
    .borrador { position: absolute; top: 330px; left: 40px; width: 640px; text-align: center; font-size: 96px;
        font-weight: bold; color: #dc2626; opacity: .10; transform: rotate(-30deg); letter-spacing: 8px; }
</style>
</head>
<body>
@foreach ($boletas as $b)
    @php
        $t = $b['trabajador'];
        $l = $b['linea'];
        $g = $b['grupos'];
    @endphp
    <div class="hoja{{ $loop->last ? '' : ' salto' }}" style="height: 1035px;">
        @if ($borrador)
            <div class="borrador">BORRADOR</div>
        @endif

        <table class="cabecera">
            <tr>
                <td>
                    @if (!empty($logo))<img src="{{ $logo }}" alt="" style="max-height: 46px; max-width: 160px; margin-bottom: 4px;"><br>@endif
                    <div class="empresa">{{ $empresa->razon_social }}</div>
                    <div class="suave">RUC {{ $empresa->ruc }}</div>
                    @if ($empresa->direccion)
                        <div class="suave">{{ $empresa->direccion }}</div>
                    @endif
                    <div class="suave">{{ $empresa->regimen_laboral->etiqueta() }}</div>
                </td>
                <td class="titulo">
                    <div class="doc">BOLETA DE PAGO</div>
                    <div class="mes">Periodo: <b>{{ $periodo->etiqueta() }}</b></div>
                    <div class="suave">D.S. 001-98-TR</div>
                </td>
            </tr>
        </table>
        <div class="linea-azul"></div>

        <div class="caja">
            <table class="datos">
                <tr>
                    <td colspan="2"><span class="etq">Trabajador</span><span class="val">{{ $t['nombre'] }}</span></td>
                    <td><span class="etq">Documento</span><span class="val">{{ $t['documento'] }}</span></td>
                    <td><span class="etq">Fecha de ingreso</span><span class="val">{{ $t['fecha_ingreso'] }}</span></td>
                </tr>
                <tr>
                    <td><span class="etq">Cargo</span>{{ $t['cargo'] ?? '—' }}</td>
                    <td><span class="etq">Contrato</span>{{ $t['contrato'] }}</td>
                    <td><span class="etq">Remuneración básica</span>S/ {{ Formato::monto($t['remuneracion_basica']) }}</td>
                    <td><span class="etq">Seguro de salud</span>{{ $t['seguro_salud'] }}</td>
                </tr>
                <tr>
                    <td><span class="etq">Sistema de pensiones</span>{{ $t['pension'] }}</td>
                    <td><span class="etq">Comisión AFP</span>{{ $t['comision'] ?? '—' }}</td>
                    <td><span class="etq">CUSPP</span>{{ $t['cuspp'] ?? '—' }}</td>
                    <td><span class="etq">Fecha de cese</span>{{ $t['fecha_cese'] ?? '—' }}</td>
                </tr>
            </table>
        </div>

        <div class="caja dias">
            <table>
                <tr>
                    <td><span class="etq">Días laborados</span><span class="val">{{ $l['dias_laborados'] }}</span></td>
                    <td><span class="etq">Faltas</span><span class="val">{{ $l['dias_no_laborados'] }}</span></td>
                    <td><span class="etq">Días subsidiados</span><span class="val">{{ $l['dias_subsidiados'] }}</span></td>
                    <td><span class="etq">Licencia sin goce</span><span class="val">{{ $l['dias_licencia'] }}</span></td>
                    <td><span class="etq">Vacaciones</span><span class="val">{{ $l['dias_vacaciones'] }}</span></td>
                    <td><span class="etq">Horas extra 25 %</span><span class="val">{{ $l['horas_extra_25'] }}</span></td>
                    <td><span class="etq">Horas extra 35 %</span><span class="val">{{ $l['horas_extra_35'] }}</span></td>
                </tr>
            </table>
        </div>

        <table class="conceptos">
            <tr>
                @foreach ([
                    ['Ingresos', $g['ingresos']['conceptos'], $l['total_ingresos']],
                    ['Descuentos', $b['descuentos'], $l['total_descuentos']],
                    ['Aportes del empleador', $g['aportes_empleador']['conceptos'], $l['total_aportes_empleador']],
                ] as [$titulo, $filas, $total])
                    <td>
                        <table class="grupo caja">
                            <tr><th colspan="3">{{ $titulo }}</th></tr>
                            @forelse ($filas as $c)
                                <tr>
                                    <td class="cod">{{ $c['codigo'] }}</td>
                                    <td>{{ $c['nombre'] }}</td>
                                    <td class="monto">{{ Formato::monto($c['monto']) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="vacio">Sin conceptos</td></tr>
                            @endforelse
                            <tr class="total">
                                <td colspan="2">Total</td>
                                <td class="monto">{{ Formato::monto($total) }}</td>
                            </tr>
                        </table>
                    </td>
                @endforeach
            </tr>
        </table>

        <table class="neto caja">
            <tr>
                <td class="letras">
                    <span class="etq">Son</span>
                    <b>{{ $b['neto_letras'] }}</b>
                </td>
                <td class="importe">
                    <span class="etq">Neto a pagar</span>
                    <span class="cifra">S/ {{ Formato::monto($l['neto_pagar']) }}</span>
                </td>
            </tr>
        </table>

        <table class="firmas">
            <tr>
                <td><div class="firma">Empleador<br><span class="suave">{{ $empresa->razon_social }}</span></div></td>
                <td><div class="firma">Trabajador<br><span class="suave">{{ $t['nombre'] }}</span></div></td>
            </tr>
        </table>

        <div class="pie">
            Boleta generada con Serialo Planillas · {{ $empresa->ruc }} · {{ $periodo->etiqueta() }}
            @if ($borrador) · Documento de revisión: el periodo aún no está cerrado @endif
        </div>
    </div>
@endforeach
</body>
</html>
