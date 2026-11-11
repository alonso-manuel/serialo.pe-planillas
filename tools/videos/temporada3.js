// Temporada 3 (18–30): los módulos que llegaron después de los reportes. Se graban en orden, sobre la base de la
// temporada 2 (migrate:fresh --seed + HistorialDemoSeeder): cada video deja datos que usa el siguiente.
// Todos los datos son ficticios (DNI 7000xxxx, RUC inventados, certificado autofirmado).
const path = require('path');

const ARCHIVO = (n) => path.resolve(__dirname, n);
const DISTRIBUIDORA = { empresa: 'Distribuidora' };

/** Abre la ficha de un trabajador desde el listado (como lo haría el usuario). */
async function abrirFicha(d, apellido) {
    const p = d.page;
    await d.ir('/trabajadores');
    await d.clic(
        p.getByRole('link', { name: new RegExp(apellido) }).first(),
        1200,
    );
}

async function pestana(d, nombre, pausa = 900) {
    await d.clic(d.page.getByRole('tab', { name: nombre }), pausa);
}

/** Planilla de noviembre de la empresa activa. */
async function planillaNoviembre(d) {
    const p = d.page;
    await d.ir('/periodos');
    await d.clic(p.getByRole('link', { name: /Noviembre 2026/ }).first(), 1500);
}

module.exports = {
    /* ------------------------------------------------------------------ 18 */
    '18-ficha-sunat': [
        DISTRIBUIDORA,
        async (d) => {
            const p = d.page;
            await abrirFicha(d, 'Ríos Medina');
            await d.escena('Ficha del trabajador');
            await d.sub(
                'La ficha del trabajador reúne todo lo que el sistema necesita para la planilla, las boletas y SUNAT.',
                3400,
            );
            await d.mover(p.getByRole('tablist'));
            await d.sub(
                'Está organizada en pestañas: datos personales, laborales, pensión y salud, cuentas, SUNAT y más.',
                3400,
            );

            await d.escena('Cuentas');
            await pestana(d, 'Cuentas');
            await d.sub(
                'En «Cuentas» van la cuenta de sueldo con su banco y la cuenta CTS con su entidad.',
                3000,
            );
            await d.escribir('#cuenta_sueldo', '191-70000012-0-55');
            await d.elegir('#entidad_sueldo', /Crédito/);
            await d.sub(
                'Con la cuenta de sueldo, el archivo de pagos al banco ya sabe dónde depositar su neto.',
                3200,
            );

            await d.escena('Datos para SUNAT');
            await pestana(d, 'SUNAT');
            await d.sub(
                'En «SUNAT» están los datos que exige el T-Registro para dar de alta al trabajador.',
                3200,
            );
            await d.elegir('#categoria_ocupacional', /Ejecutivo/);
            await d.sub(
                'La categoría ocupacional define el tipo de trabajador en SUNAT: ejecutivo, empleado u obrero.',
                3200,
            );
            await d.escribir('#buscar_ocupacion', 'gerente general', {
                retraso: 70,
            });
            await d.espera(1500);
            await d.desplazar(200, 600);
            await d.sub(
                'La ocupación se busca por nombre o por código en la tabla 30 de SUNAT: solo aparecen las válidas para su categoría.',
                3600,
            );
            await d.clic(
                p
                    .locator('#buscar_ocupacion ~ ul button', {
                        hasText: 'COMERCIO MAYORISTA',
                    })
                    .first(),
                900,
            );
            await p.keyboard.press('Escape');
            await d.elegir('#situacion_educativa', /Universitaria completa/);
            await d.sub(
                'Se completan la situación educativa y, si corresponde, la situación especial, discapacidad o afiliación sindical.',
                3600,
            );
            await d.clic(
                p.getByRole('button', { name: 'Guardar cambios' }),
                1600,
            );
            await d.sub(
                'Guardamos. Con estos datos, el T-Registro genera sus archivos sin pedir nada más.',
                3000,
            );
        },
    ],

    /* ------------------------------------------------------------------ 19 */
    '19-derechohabientes': [
        DISTRIBUIDORA,
        async (d) => {
            const p = d.page;
            await abrirFicha(d, 'Ríos Medina');
            await d.escena('Derechohabientes');
            await pestana(d, 'Derechohabientes', 1200);
            await d.sub(
                'Los derechohabientes son los familiares con derecho a EsSalud: cónyuge, concubino, gestante e hijos.',
                3600,
            );
            await d.sub(
                'Registremos a un hijo menor de edad. El vínculo define qué documentos y datos se piden.',
                3200,
            );
            await d.elegir('#dh-vinculo', /menor de edad/);
            await d.escribir('#dh-numero', '70000091');
            await d.escribir('#dh-paterno', 'Ríos');
            await d.escribir('#dh-materno', 'Pinto');
            await d.escribir('#dh-nombres', 'Mateo');
            await p.locator('#dh-nacimiento').fill('2018-05-14');
            await d.mover('#dh-nacimiento');
            await d.elegir('#dh-sexo', 'Masculino');
            await d.sub(
                'Con DNI no hace falta un documento que sustente el vínculo; con otro documento, el sistema lo pide.',
                3400,
            );
            await d.clic(
                p.getByRole('button', { name: 'Agregar', exact: true }).last(),
                1800,
            );
            await d.sub(
                'Queda registrado con su fecha de alta. Si deja de tener derecho, se le da de baja con el motivo de SUNAT.',
                3600,
            );
            await d.mover(p.getByRole('button', { name: 'Baja' }).first());
            await d.sub(
                'Las altas y bajas de derechohabientes se descargan en el módulo T-Registro, listas para SUNAT.',
                3400,
            );
        },
    ],

    /* ------------------------------------------------------------------ 20 */
    '20-prestamos': [
        DISTRIBUIDORA,
        async (d) => {
            const p = d.page;
            await abrirFicha(d, 'Huertas Loayza');
            await d.escena('Préstamos al trabajador');
            await pestana(d, 'Préstamos', 1200);
            await d.sub(
                'Cuando la empresa presta dinero a un trabajador, se registra aquí y se descuenta en cuotas.',
                3200,
            );
            await d.escribir('#pr-monto', '1500');
            await d.escribir('#pr-cuotas', '3');
            await p.locator('#pr-inicio').fill('2026-11');
            await d.mover('#pr-inicio');
            await d.sub(
                'Monto, número de cuotas y el mes de la primera: este mismo noviembre.',
                3000,
            );
            await d.escribir('#pr-glosa', 'Préstamo por emergencia familiar');
            await d.clic(
                p.getByRole('button', { name: 'Registrar préstamo' }),
                1800,
            );
            await d.sub(
                'El sistema arma el cronograma: tres cuotas de 500 soles, de noviembre a enero.',
                3400,
            );
            await d.desplazar(300, 1200);
            await d.sub(
                'Cada cuota se descuenta sola en la planilla de su mes con el código 0706, y cuenta como pagada al cerrar esa planilla.',
                3800,
            );
            await planillaNoviembre(d);
            await d.escena('Planilla de noviembre');
            await d.clic(
                p.getByRole('button', { name: 'Calcular planilla' }),
                2500,
            );
            await d.clic(
                p.getByRole('link', { name: /Huertas Loayza/ }).first(),
                1800,
            );
            await d.mover(p.getByText('0706').first());
            await d.sub(
                'En el detalle de noviembre ya aparece la primera cuota del préstamo como descuento.',
                3400,
            );
            await d.sub(
                'Si el trabajador cesa con saldo pendiente, la liquidación lo avisa para descontarlo.',
                3200,
            );
        },
    ],

    /* ------------------------------------------------------------------ 21 */
    '21-centros-costo': [
        DISTRIBUIDORA,
        async (d) => {
            const p = d.page;
            await d.ir('/empresas');
            await d.escena('Centros de costo');
            await d.clic(p.getByTitle(/Editar Distribuidora/).first(), 1400);
            await d.mover(p.getByRole('heading', { name: 'Centros de costo' }));
            await d.sub(
                'Los centros de costo son las áreas, obras o sedes de la empresa. Sirven para repartir el costo de la planilla.',
                3600,
            );
            for (const [codigo, nombre] of [
                ['ADM', 'Administración'],
                ['VEN', 'Ventas'],
                ['ALM', 'Almacén'],
            ]) {
                await d.escribir('#cc-codigo', codigo);
                await d.escribir('#cc-nombre', nombre);
                await d.clic(
                    p
                        .locator('form')
                        .filter({ has: p.locator('#cc-codigo') })
                        .getByRole('button', { name: 'Agregar' }),
                    1200,
                );
            }
            await d.sub(
                'Creamos tres: Administración, Ventas y Almacén.',
                2600,
            );

            await d.escena('Asignar a un trabajador');
            await abrirFicha(d, 'Vargas León');
            await pestana(d, 'Historial', 1200);
            await d.sub(
                'Cada trabajador tiene su centro de costo con vigencia: se asigna desde el historial, indicando desde qué mes rige.',
                3800,
            );
            await d.clic(
                p.getByRole('button', { name: 'Registrar cambio' }).first(),
                1000,
            );
            await d.elegir('#grupo', 'Centro de costo');
            await p.locator('#vigente_desde').fill('2026-11');
            await d.mover('#vigente_desde');
            await d.elegir(p.getByLabel('Centro de costo'), /Ventas/);
            await d.clic(
                p
                    .getByRole('dialog')
                    .getByRole('button', { name: 'Registrar cambio' }),
                1800,
            );
            await d.sub(
                'Desde noviembre, Sofía Vargas pertenece a Ventas. Los meses anteriores conservan su centro de entonces.',
                3800,
            );
            await d.sub(
                'Al calcular la planilla, cada línea guarda el centro de su trabajador: así salen el costo y el asiento por centro.',
                3800,
            );
        },
    ],

    /* ------------------------------------------------------------------ 22 */
    '22-asistencia': [
        DISTRIBUIDORA,
        async (d) => {
            const p = d.page;
            await planillaNoviembre(d);
            await d.escena('Importar asistencia');
            await d.sub(
                'Cuando la asistencia viene de un reloj o de otra hoja, no hace falta escribirla trabajador por trabajador.',
                3600,
            );
            await d.clic(
                p.getByRole('button', { name: 'Importar asistencia' }),
                1200,
            );
            await d.mover(p.getByRole('link', { name: /Plantilla Excel/ }));
            await d.sub(
                'Se descarga la plantilla: ya trae a los trabajadores del periodo con sus valores actuales.',
                3200,
            );
            await d.sub(
                'Se completan faltas, licencias sin goce, días y tipo de subsidio y horas extra, y se sube el archivo.',
                3600,
            );
            await p
                .locator('#archivo-asistencia')
                .setInputFiles(ARCHIVO('demo-asistencia.csv'));
            await d.mover('#archivo-asistencia');
            await d.espera(800);
            await d.clic(
                p.getByRole('dialog').getByRole('button', { name: 'Importar' }),
                2200,
            );
            await d.sub(
                'Si una fila tuviera un error, no se guarda nada y se listan todos los errores juntos.',
                3200,
            );
            await d.mover(p.locator('table').first());
            await d.sub(
                'La grilla ya muestra las faltas, la licencia, los cinco días de subsidio y las horas extra importadas.',
                3800,
            );
        },
    ],

    /* ------------------------------------------------------------------ 23 */
    '23-quinta': [
        DISTRIBUIDORA,
        async (d) => {
            const p = d.page;
            await planillaNoviembre(d);
            await d.escena('Renta de quinta categoría');
            await d.sub(
                'La retención de quinta categoría se calcula sola en cada planilla, con el procedimiento del reglamento de la Ley del Impuesto a la Renta.',
                3800,
            );
            await d.clic(
                p.getByRole('button', { name: /Calcular planilla|Recalcular/ }),
                2600,
            );
            await d.clic(
                p.getByRole('link', { name: /Ríos Medina/ }).first(),
                1800,
            );
            await d.sub(
                'Carlos Ríos gana 8,500 soles al mes: su renta anual supera las 7 UIT, así que le corresponde retención.',
                3600,
            );
            await d.mover(p.getByText('Renta de quinta categoría').first());
            await d.sub(
                'El detalle muestra el cálculo completo: renta anual proyectada, deducción de 7 UIT y renta neta…',
                3600,
            );
            await d.desplazar(250, 1200);
            await d.sub(
                '…el impuesto anual por tramos, lo ya retenido en los meses anteriores y el divisor del mes.',
                3600,
            );
            await d.mover(p.getByText('0605').first());
            await d.sub(
                'El resultado entra como descuento 0605 y se recalcula el neto. También sale en la boleta y en el PLAME.',
                3800,
            );

            await d.escena('Otros empleadores');
            await abrirFicha(d, 'Ríos Medina');
            await pestana(d, 'Quinta', 1200);
            await d.sub(
                'Si el trabajador tuvo otro empleador en el año, se registra lo que dice su certificado de rentas y retenciones.',
                3600,
            );
            await d.mover('#oe-renta');
            await d.sub(
                'Esa renta se suma a la proyección y lo ya retenido se descuenta, para no retener de más ni de menos.',
                3600,
            );
            await d.escena('Certificado anual');
            await d.mover(
                p.getByText('Certificado de rentas y retenciones').first(),
            );
            await d.sub(
                'Y cada año, o al cese, se entrega el certificado de rentas y retenciones: de un trabajador o de todos en un zip.',
                2000,
            );
            await d.verPdf(
                p.getByRole('link', { name: /Certificado de este trabajador/ }),
                { paginas: 1 },
            );
            await d.espera(3200);
            await d.cerrarVisor();
        },
    ],

    /* ------------------------------------------------------------------ 24 */
    '24-honorarios': [
        DISTRIBUIDORA,
        async (d) => {
            const p = d.page;
            await planillaNoviembre(d);
            await d.escena('Recibos por honorarios');
            await d.clic(
                p.getByRole('link', { name: /Honorarios \(4ta\)/ }),
                1500,
            );
            await d.sub(
                'Los prestadores de servicios con recibo por honorarios —cuarta categoría— también se declaran en el PLAME.',
                3600,
            );
            await d.sub(
                'Registramos el recibo electrónico de un consultor externo: su RUC, sus nombres y el comprobante.',
                3200,
            );
            await d.escribir('#ruc', '10700000996');
            await d.escribir('#paterno', 'Paredes');
            await d.escribir('#materno', 'Luna');
            await d.escribir('#nombres', 'Andrés');
            await d.escribir('#numero', '125');
            await d.escribir('#monto', '2000');
            await d.sub(
                'El recibo es de 2,000 soles: supera los 1,500, así que se retiene el 8 %.',
                3000,
            );
            await d.clic(
                p.getByRole('button', { name: 'Registrar recibo' }),
                1800,
            );
            await d.mover(p.locator('table').first());
            await d.sub(
                'Retención de 160 soles y neto a pagar de 1,840.',
                3000,
            );

            await d.escribir('#paterno', 'Gómez');
            await d.escribir('#materno', 'Salas');
            await d.escribir('#nombres', 'Lucía');
            await d.escribir('#ruc', '10700000988');
            await d.escribir('#numero', '48');
            await d.escribir('#monto', '1200');
            await d.clic(
                p.getByRole('button', { name: 'Registrar recibo' }),
                1800,
            );
            await d.sub(
                'Un recibo de 1,200 soles no supera el monto mínimo: no lleva retención. Tampoco si el prestador tiene constancia de suspensión.',
                3800,
            );
            await d.sub(
                'Al cerrar el periodo, el PLAME incluye los archivos .ps4 (prestadores) y .4ta (recibos).',
                3400,
            );
        },
    ],

    /* ------------------------------------------------------------------ 25 */
    '25-liquidacion': [
        DISTRIBUIDORA,
        async (d) => {
            const p = d.page;
            await abrirFicha(d, 'Campos Arce');
            await d.escena('Cese del trabajador');
            await d.sub(
                'Diego Campos tiene contrato a plazo fijo. La empresa decide terminar el vínculo a fin de mes, antes del vencimiento.',
                3800,
            );
            await d.clic(
                p.getByRole('button', { name: 'Registrar cese' }).first(),
                1000,
            );
            await p.locator('#fecha_cese').fill('2026-11-30');
            await d.mover('#fecha_cese');
            await d.elegir('#motivo_cese', /Despido/);
            await d.sub(
                'Se registra la fecha de cese y el motivo, de la tabla 17 de SUNAT.',
                2800,
            );
            await d.clic(
                p
                    .getByRole('dialog')
                    .getByRole('button', { name: 'Registrar cese' }),
                1800,
            );

            await d.escena('Liquidación de beneficios');
            await d.ir('/liquidaciones');
            await d.sub(
                'En Liquidaciones aparece el trabajador cesado. La liquidación incluye gratificación y CTS truncas y las vacaciones pendientes.',
                3800,
            );
            await d.clic(
                p.getByRole('button', { name: 'Calcular' }).first(),
                1000,
            );
            await d.sub(
                'Si el despido fue sin causa justa, corresponde indemnización: en plazo fijo, 1.5 sueldos por cada mes que faltaba, hasta 12.',
                3800,
            );
            await d.mover(p.getByRole('dialog').getByRole('checkbox'));
            await d.sub(
                'Como el motivo es despido, la casilla de indemnización ya viene marcada.',
                2800,
            );
            await p.locator('#fin-contrato').fill('2027-03-31');
            await d.mover('#fin-contrato');
            await d.sub(
                'El contrato vencía el 31 de marzo de 2027: faltaban cuatro meses.',
                2800,
            );
            await d.clic(
                p.getByRole('dialog').getByRole('button', { name: 'Calcular' }),
                2200,
            );
            await p.waitForLoadState('networkidle');
            await d.espera(800);
            const detalle = p.getByRole('button', {
                name: /Detalle de Campos/,
            });
            await d.clic(detalle, 1200);
            if (
                !(await p
                    .getByRole('link', { name: /Certificado de trabajo/ })
                    .isVisible())
            ) {
                await d.clic(detalle, 1200); // el primer clic pudo llegar durante la recarga
            }
            await d.sub(
                'El detalle por concepto, con su código de la tabla 22: truncas, vacaciones e indemnización 0503.',
                3800,
            );
            await d.desplazar(250, 1200);
            await d.sub(
                'Se paga en la planilla del mes del cese: así entra en la boleta, en el PLAME y en los aportes.',
                3400,
            );
            await d.escena('Documentos');
            await d.verPdf(p.getByRole('link', { name: 'Hoja' }).first(), {
                paginas: 1,
            });
            await d.sub(
                'La hoja de liquidación, con el cálculo de cada beneficio.',
                3200,
            );
            await d.desplazarVisor(400, 1600);
            await d.cerrarVisor();
            await d.verPdf(
                p.getByRole('link', { name: /Certificado de trabajo/ }),
                { paginas: 1 },
            );
            await d.sub('El certificado de trabajo…', 2400);
            await d.cerrarVisor();
            await d.verPdf(
                p.getByRole('link', { name: /Carta para liberar CTS/ }),
                { paginas: 1 },
            );
            await d.sub('…y la carta para que el banco libere su CTS.', 2600);
            await d.cerrarVisor();
            await d.clic(
                p.getByRole('button', { name: 'Cerrar' }).first(),
                1000,
            );
            await d.sub(
                'Revisada, se cierra: ya no se recalcula, el cese no cambia y los documentos salen sin la marca «borrador».',
                3800,
            );
            await d.clic(
                p
                    .getByRole('dialog')
                    .getByRole('button', { name: /Cerrar liquidación/ }),
                1800,
            );
        },
    ],

    /* ------------------------------------------------------------------ 26 */
    '26-tregistro': [
        DISTRIBUIDORA,
        async (d) => {
            const p = d.page;
            await d.ir('/t-registro');
            await d.escena('T-Registro');
            await d.sub(
                'Cada alta y baja de trabajadores se informa a SUNAT en el T-Registro, dentro del día hábil siguiente.',
                3600,
            );
            await d.mover(p.getByRole('button', { name: 'Consultar' }));
            await d.sub(
                'Se elige un rango de fechas: el sistema lista las altas (ingresos) y las bajas (ceses) de ese rango.',
                3400,
            );
            await d.clic(p.getByRole('button', { name: 'Consultar' }), 1500);
            await d.mover(p.getByText(/^Bajas/).first());
            await d.sub(
                'Aquí está la baja de Diego Campos, con su fecha y motivo. Si a alguien le faltara un dato, se avisa y se enlaza a su ficha.',
                3800,
            );
            await d.sub(
                'Con todo completo, se descarga un zip con los archivos en el formato del PVS de SUNAT.',
                2400,
            );
            const zip = await d.descargar(
                p.getByRole('link', { name: /Descargar archivos/ }),
            );
            await d.verArchivo(zip);
            await d.espera(4200);
            await d.cerrarVisor();
            await d.mover(p.getByText('Derechohabientes').first());
            await d.sub(
                'Y aparte, los derechohabientes: el alta del hijo que registramos en la ficha, lista para la carga masiva en SOL.',
                3800,
            );
        },
    ],

    /* ------------------------------------------------------------------ 27 */
    '27-pagos-reportes': [
        DISTRIBUIDORA,
        async (d) => {
            const p = d.page;
            await planillaNoviembre(d);
            await d.escena('Calcular y cerrar');
            await d.sub(
                'Con todo registrado —asistencia, préstamo, quinta, honorarios y la liquidación—, calculamos noviembre.',
                3600,
            );
            await d.clic(
                p.getByRole('button', { name: /Calcular planilla|Recalcular/ }),
                2800,
            );
            await d.clic(
                p.getByRole('button', { name: 'Cerrar periodo' }).first(),
                900,
            );
            await d.clic(
                p
                    .getByRole('dialog')
                    .getByRole('button', { name: 'Cerrar periodo' }),
                2200,
            );
            await d.sub('Revisada, la cerramos.', 2000);

            await d.escena('Pagos al banco');
            await d.mover(p.getByRole('link', { name: /Pagos \(banco\)/ }));
            await d.sub(
                '«Pagos (banco)» genera el archivo para la transferencia: cada trabajador con su banco, su cuenta y su neto.',
                2400,
            );
            const url = await p
                .getByRole('link', { name: /Pagos \(banco\)/ })
                .getAttribute('href');
            const csv = await p
                .context()
                .request.get(
                    new URL(url.replace('xlsx', 'csv'), p.url()).toString(),
                );
            await d.verArchivo(
                { nombre: 'pagos-noviembre-2026.csv', datos: await csv.body() },
                { codificacion: 'utf8' },
            );
            await d.espera(2600);
            await d.sub(
                'Aquí solo Carlos Ríos tiene cuenta de sueldo. En el Excel, los demás van en otra hoja para pago en efectivo, con el total por banco.',
                3600,
            );
            await d.cerrarVisor();

            await d.escena('Reportes nuevos');
            await d.ir('/reportes');
            await p
                .locator('#periodo')
                .selectOption({ label: 'Noviembre 2026' });
            await p.waitForLoadState('networkidle');
            await d.mover('#periodo');
            await d.sub('A los reportes del mes se sumaron tres nuevos.', 2400);
            const tarjeta = (t) =>
                p
                    .locator('section')
                    .filter({
                        has: p.getByRole('heading', { name: t, exact: true }),
                    });
            await d.mover(
                p.getByRole('heading', {
                    name: 'Comparativo con el mes anterior',
                }),
            );
            await d.sub(
                'El comparativo con el mes anterior: neto, bruto y costo de cada trabajador, la variación, y quién entró o salió.',
                1800,
            );
            await d.verPdf(
                tarjeta('Comparativo con el mes anterior').getByRole('link', {
                    name: 'PDF',
                }),
                { paginas: 1 },
            );
            await d.espera(3400);
            await d.cerrarVisor();
            await d.mover(
                p.getByRole('heading', { name: 'Costo por centro de costo' }),
            );
            await d.sub('El costo laboral agrupado por centro de costo…', 1600);
            await d.verPdf(
                tarjeta('Costo por centro de costo').getByRole('link', {
                    name: 'PDF',
                }),
                { paginas: 1 },
            );
            await d.espera(3000);
            await d.cerrarVisor();
            await d.mover(
                p.getByRole('heading', {
                    name: 'Asiento contable por centro de costo',
                }),
            );
            await d.sub(
                '…y el asiento contable con los cargos separados por centro. Debe y haber cuadran.',
                1600,
            );
            await d.verPdf(
                tarjeta('Asiento contable por centro de costo').getByRole(
                    'link',
                    { name: 'PDF' },
                ),
                { paginas: 1 },
            );
            await d.espera(3200);
            await d.cerrarVisor();
        },
    ],

    /* ------------------------------------------------------------------ 28 */
    '28-firma-digital': [
        DISTRIBUIDORA,
        async (d) => {
            const p = d.page;
            await d.ir('/empresas');
            await d.escena('Firma digital');
            await d.clic(p.getByTitle(/Editar Distribuidora/).first(), 1400);
            await d.mover(p.getByRole('heading', { name: 'Firma digital' }));
            await d.sub(
                'Cada empresa puede registrar el certificado digital de su representante legal, emitido por RENIEC u otra entidad acreditada.',
                3800,
            );
            await p
                .locator('#firma-certificado')
                .setInputFiles(ARCHIVO('demo-firma.p12'));
            await d.mover('#firma-certificado');
            await d.escribir('#firma-clave', 'demo1234', { retraso: 90 });
            await d.sub(
                'Se elige el archivo .pfx o .p12 y se escribe su clave.',
                2800,
            );
            await d.clic(
                p.getByRole('button', { name: 'Registrar' }).first(),
                2200,
            );
            await d.mover(p.getByRole('heading', { name: 'Firma digital' }));
            await d.sub(
                'El sistema lo valida al momento y muestra el titular, el emisor y su vigencia. Se guarda cifrado.',
                3800,
            );
            await d.escena('Boletas firmadas');
            await planillaNoviembre(d);
            await d.sub(
                'Desde ahora, las boletas de periodos cerrados salen firmadas digitalmente.',
                2000,
            );
            await d.verPdf(p.getByRole('link', { name: 'Boletas PDF' }), {
                paginas: 1,
            });
            await d.espera(1800);
            await d.desplazarVisor(500, 1600);
            await d.sub(
                'En el recuadro del empleador: «Firmado digitalmente por…». El lector de PDF muestra quién firmó y avisa si el archivo se modificó.',
                4200,
            );
            await d.cerrarVisor();
            await d.sub(
                'También se firman la liquidación, el certificado de trabajo y las constancias de CTS. Los borradores, nunca.',
                3600,
            );
        },
    ],

    /* ------------------------------------------------------------------ 29 */
    '29-respaldos-licencia': [
        DISTRIBUIDORA,
        async (d) => {
            const p = d.page;
            await d.ir('/respaldos');
            await d.escena('Respaldos');
            await d.sub(
                'Cada madrugada el sistema guarda una copia completa de la base de datos, y conserva las de los últimos 14 días.',
                3800,
            );
            await d.clic(
                p.getByRole('button', { name: 'Crear respaldo ahora' }),
                2600,
            );
            await d.sub(
                'También se crea uno al momento, por ejemplo antes de una carga masiva.',
                3000,
            );
            await d.mover(p.getByRole('link', { name: 'Descargar' }).first());
            await d.sub(
                'Se recomienda descargar uno cada semana y guardarlo fuera del servidor. Cada descarga queda en la auditoría.',
                3800,
            );
            await d.ir('/licencia');
            await d.escena('Licencia');
            await d.mover(p.getByText('Uso del plan').first());
            await d.sub(
                'En Licencia se ve el plan contratado y su uso: empresas, usuarios y trabajadores activos.',
                3400,
            );
            await d.sub(
                'Sin una licencia vigente, el sistema queda en solo lectura: se consulta y descarga todo, y nunca se pierden datos.',
                3800,
            );
        },
    ],

    /* ------------------------------------------------------------------ 30 */
    '30-agrario': [
        DISTRIBUIDORA,
        async (d) => {
            const p = d.page;
            await d.ir('/empresas');
            await d.escena('Régimen agrario');
            await d.sub(
                'El sistema también calcula el régimen laboral agrario de la Ley 31110. Registremos una empresa agroexportadora.',
                3800,
            );
            await d.clic(p.getByRole('link', { name: /Nueva empresa/ }), 1400);
            await d.escribir('#ruc', '20000000061');
            await d.escribir('#razon_social', 'Agrícola Valle Verde S.A.C.');
            await d.elegir('#regimen_laboral', /agrario/);
            await d.mover(p.getByText(/100 o más trabajadores/));
            await d.sub(
                'La tasa de EsSalud agrario depende del tamaño: 100 o más trabajadores o ventas de 1,700 UIT, el año anterior.',
                3800,
            );
            await d.sub(
                'Esta empresa es pequeña: aporta el 6 % hasta 2027.',
                2600,
            );
            await d.clic(
                p.getByRole('button', { name: 'Registrar empresa' }),
                2000,
            );

            await d.escena('Trabajador agrario');
            await d.ir('/trabajadores');
            await d.clic(
                p
                    .locator('[data-sidebar="menu-button"]')
                    .filter({ hasText: 'RUC' })
                    .first(),
                900,
            );
            await d.clic(
                p
                    .getByRole('menuitem', { name: /Agrícola Valle Verde/ })
                    .first(),
                1500,
            );
            await d.clic(
                p.getByRole('link', { name: /Nuevo trabajador/ }).first(),
                1400,
            );
            await d.escribir('#numero_documento', '70000101');
            await d.escribir('#apellido_paterno', 'Cóndor');
            await d.escribir('#apellido_materno', 'Yupanqui');
            await d.escribir('#nombres', 'Elena');
            await pestana(d, 'Laborales');
            await d.escribir('#cargo', 'Operaria de campo');
            await p.locator('#fecha_ingreso').fill('2026-03-02');
            await d.mover('#fecha_ingreso');
            await d.escribir('#remuneracion_basica', '1130');
            await d.mover(p.getByText(/Gratificaciones y CTS prorrateadas/));
            await d.sub(
                'Por defecto, la gratificación y la CTS se pagan cada mes dentro de la remuneración diaria.',
                3400,
            );
            await d.sub(
                'Si el trabajador pidió por escrito cobrarlas en julio, diciembre, mayo y noviembre, se desmarca esta casilla.',
                3800,
            );
            await d.clic(
                p.getByRole('button', { name: 'Registrar trabajador' }),
                2000,
            );

            await d.escena('Planilla agraria');
            await d.ir('/periodos');
            await d.sub('Abrimos su primer periodo: noviembre de 2026.', 2400);
            await d.clic(
                p.getByRole('button', { name: /Abrir primer periodo/ }).first(),
                900,
            );
            await p.locator('#mes').fill('2026-11');
            await d.clic(
                p
                    .getByRole('dialog')
                    .getByRole('button', { name: 'Abrir periodo' }),
                2000,
            );
            await d.clic(
                p.getByRole('button', { name: 'Calcular planilla' }),
                2600,
            );
            await d.clic(
                p.getByRole('link', { name: /Cóndor Yupanqui/ }).first(),
                1800,
            );
            await d.sub(
                'Sobre el básico de 1,130 soles, la boleta agrega la gratificación prorrateada —16.66 %— y su bonificación del 9 %…',
                4000,
            );
            await d.mover(p.getByText('0904').first());
            await d.sub(
                '…la CTS prorrateada —9.72 %— y la BETA: 30 % de la RMV, que no es remunerativa.',
                3800,
            );
            await d.mover(p.getByText('0804').first());
            await d.sub(
                'EsSalud agrario al 6 %, sin la base mínima de la RMV. Y quien tiene prorrateo no recibe gratificación ni CTS aparte.',
                4200,
            );
        },
    ],
};
