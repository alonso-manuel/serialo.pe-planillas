// Recorridos de cada video. Uso: node videos.js [nombre ...]   (sin argumentos: todos, en orden)
// Requiere la BD recién sembrada (php artisan migrate:fresh --seed) y `composer dev` corriendo.
const path = require('path');
const { grabar, BASE } = require('./lib');

const empresaActiva = (p) =>
    p
        .locator('[data-sidebar="menu-button"]')
        .filter({ hasText: 'RUC' })
        .first();

const VIDEOS = {
    /* ------------------------------------------------------------------ 00 */
    '00-resumen': [
        { sesion: null },
        async (d) => {
            const p = d.page;
            await p.goto(
                'file:///' +
                    path.resolve(__dirname, 'resumen.html').replace(/\\/g, '/'),
            );
            await p.evaluate(() =>
                document.getElementById('demo-cursor')?.remove(),
            );
            await p.evaluate(() => window.iniciar());
            await p.waitForFunction(() => window.terminado === true, null, {
                timeout: 180000,
            });
        },
    ],

    /* ------------------------------------------------------------------ 01 */
    '01-login': [
        { sesion: null },
        async (d) => {
            const p = d.page;
            await d.ir('/login');
            await d.escena('Acceso al sistema');
            await d.sub(
                'Serialo Planillas se instala en el hosting del estudio contable o de la empresa.',
                3200,
            );
            await d.sub(
                'No hay registro público: el administrador crea cada usuario.',
                3000,
            );
            await d.escribir('input[name=email]', 'demo@serialo.pe');
            await d.escribir('input[name=password]', 'demo1234', {
                retraso: 90,
            });
            await d.sub('Se ingresa con correo y contraseña…', 1800);
            await d.clic('button[type=submit]', 1500);
            await d.sub(
                '…o con una llave de acceso (passkey), y opcionalmente con doble factor (2FA).',
                3400,
            );
            await d.escena('Menú principal');
            await d.sub(
                'A la izquierda, los módulos agrupados por tema. Lo marcado «Pronto» llega en las próximas semanas.',
                3800,
            );
            await d.mover(
                p.getByRole('link', { name: 'Trabajadores' }).first(),
            );
            await d.mover(p.getByRole('link', { name: 'Periodos' }).first());
            await d.mover(p.getByText('PLAME').first());
            await d.sub(
                'Arriba, la empresa activa: todo lo que ves y registras pertenece a esa empresa.',
                3200,
            );
            await d.clic(empresaActiva(p), 1800);
            await d.sub(
                'Un estudio contable cambia de empresa en un clic.',
                2600,
            );
            await p.keyboard.press('Escape');
            await d.espera(500);
            await d.escena('Seguridad de la cuenta');
            await d.clic(
                p
                    .locator('[data-sidebar="menu-button"]')
                    .filter({ hasText: 'Usuario Demo' })
                    .first(),
                900,
            );
            await d.clic(
                p
                    .getByRole('menuitem', { name: /Configuración|Ajustes/ })
                    .first(),
                1200,
            );
            await d.clic(
                p.getByRole('link', { name: /Seguridad/ }).first(),
                1200,
            );
            await d.sub(
                'Las opciones de seguridad piden volver a confirmar la contraseña.',
                2400,
            );
            await d.escribir(
                p.locator('input[type=password]').first(),
                'demo1234',
                { retraso: 80 },
            );
            await d.clic(
                p.getByRole('button', { name: 'Confirmar contraseña' }),
                1600,
            );
            await d.sub(
                'Cada usuario puede cambiar su contraseña, activar 2FA y registrar passkeys.',
                3200,
            );
            await d.desplazar(450, 1800);
            await d.desplazar(450, 1800);
            await d.sub('Así se entra al sistema de forma segura.', 2500);
        },
    ],

    /* ------------------------------------------------------------------ 02 */
    '02-empresas': [
        {},
        async (d) => {
            const p = d.page;
            await d.ir('/empresas');
            await d.escena('Empresas');
            await d.sub(
                'El sistema es multiempresa: aquí el administrador registra las empresas que atiende.',
                3400,
            );
            await d.sub(
                'Cada una con su RUC, régimen laboral (micro, pequeña o general) y seguro de salud.',
                3200,
            );
            await d.clic(
                p.getByRole('link', { name: /Nueva empresa/ }).first(),
                1200,
            );
            await d.sub(
                'Registremos una empresa nueva (datos ficticios).',
                2000,
            );
            await d.escribir('#ruc', '20000000044');
            await d.sub(
                'Con «Buscar en SUNAT» se completan razón social y domicilio. Aquí los escribimos a mano.',
                3400,
            );
            await d.escribir(
                '#razon_social',
                'Constructora Amazonas Demo S.R.L.',
            );
            await d.escribir('#nombre_comercial', 'Amazonas Demo');
            await d.escribir('#representante_legal', 'Juana Prueba Ejemplo');
            await d.escribir('#direccion', 'Av. Los Ejemplos 321, Iquitos');
            await d.elegir('#regimen_laboral', /Pequeña/);
            await d.sub(
                'El régimen define los beneficios; solo la microempresa puede usar el SIS en lugar de EsSalud.',
                3400,
            );
            await d.clic(
                p.getByRole('button', { name: 'Registrar empresa' }),
                1800,
            );
            await d.sub(
                'Al registrarla se crea su establecimiento principal (código 0000), que pide SUNAT.',
                3200,
            );
            await d.desplazar(600, 2200);
            await d.desplazar(-600, 600);
            await d.ir('/empresas');
            await d.sub(
                'Ya aparece en la lista. Se puede editar o desactivar; su historial nunca se borra.',
                3200,
            );
            await d.mover(p.getByTitle(/Editar Constructora/).first());
            await d.sub(
                'Y ya está disponible en el selector de empresa activa.',
                1200,
            );
            await d.clic(empresaActiva(p), 2400);
            await p.keyboard.press('Escape');
            await d.espera(600);
        },
    ],

    /* ------------------------------------------------------------------ 03 */
    '03-usuarios': [
        {},
        async (d) => {
            const p = d.page;
            await d.ir('/usuarios');
            await d.escena('Usuarios');
            await d.sub(
                'Dos roles: el administrador ve todo y configura; el asistente trabaja solo con sus empresas.',
                3600,
            );
            await d.clic(
                p.getByRole('link', { name: /Nuevo usuario/ }).first(),
                1200,
            );
            await d.escribir('#name', 'Pedro Contador Demo');
            await d.escribir('#email', 'pedro@estudio-demo.pe');
            await d.sub(
                'El administrador define la contraseña o el sistema le sugiere una segura.',
                2600,
            );
            await d.clic(
                p.getByRole('button', { name: 'Sugerir' }).first(),
                1500,
            );
            await d.clic(
                p.getByText('Asistente', { exact: true }).first(),
                1000,
            );
            await d.sub(
                'Al asistente se le asignan las empresas con las que trabajará.',
                2600,
            );
            await d.desplazar(400, 800);
            await d.clic(
                p.getByText('Constructora Amazonas Demo S.R.L.').first(),
                700,
            );
            await d.clic(
                p.getByText('Distribuidora Pacífico S.A.').first(),
                900,
            );
            await d.clic(
                p.getByRole('button', { name: 'Crear usuario' }),
                1800,
            );
            await d.ir('/usuarios');
            await d.sub(
                'Listo. Los usuarios no se borran: se desactivan, para conservar la auditoría.',
                3200,
            );
            await d.mover(p.getByTitle(/Desactivar a Pedro/).first());
            await d.espera(1200);
        },
    ],

    /* ------------------------------------------------------------------ 04 */
    '04-parametros': [
        {},
        async (d) => {
            const p = d.page;
            await d.ir('/parametros');
            await d.escena('Parámetros legales');
            await d.sub(
                'RMV, UIT, EsSalud, ONP, SIS, asignación familiar…: no están escritos en el código, son datos.',
                3800,
            );
            await d.sub(
                'Cada valor tiene una vigencia y su base legal. Un mes antiguo siempre se recalcula igual.',
                3400,
            );
            await d.mover(p.getByText('S/ 1,130.00').first());
            await d.espera(800);
            await d.sub(
                'Los valores por confirmar con la norma quedan marcados para revisión.',
                2600,
            );
            await d.mover(p.getByText('Por verificar').first());
            await d.espera(800);
            await d.clic(p.getByText(/Historial/).nth(1), 1400);
            await d.sub(
                'El historial muestra cada valor anterior y desde cuándo rigió.',
                2600,
            );
            await d.sub(
                'Si cambia la ley, se registra una vigencia nueva con el «+».',
                2000,
            );
            await d.clic(
                p.getByTitle(/Nueva vigencia de Remuneración Mínima/).first(),
                1200,
            );
            await d.escribir('#valor', '1200.00');
            await d.clic('#vigente_desde', 200);
            await p.locator('#vigente_desde').fill('2027-01');
            await d.escribir(
                '#base_legal',
                'Ejemplo: decreto supremo de la nueva RMV',
            );
            await d.sub(
                'Solo es un ejemplo: cancelamos para no cambiar los datos.',
                2400,
            );
            await d.clic(
                p.getByRole('dialog').getByRole('button', { name: 'Cancelar' }),
                1200,
            );
            await d.desplazar(500, 1800);
        },
    ],

    /* ------------------------------------------------------------------ 05 */
    '05-tasas-afp': [
        {},
        async (d) => {
            const p = d.page;
            await d.ir('/tasas-afp');
            await d.escena('Tasas AFP');
            await d.sub(
                'Cada mes la SBS publica aporte, prima de seguro, comisiones y la remuneración máxima asegurable.',
                3800,
            );
            await d.sub(
                'El calendario muestra qué meses están completos y cuáles faltan.',
                2800,
            );
            await d.mover(p.getByText('Falta').first());
            await d.sub(
                'Diciembre falta: sin sus tasas no se puede calcular la planilla de trabajadores AFP.',
                3200,
            );
            await d.clic(p.getByText('Falta').first(), 1200);
            await d.sub(
                'Si no cambiaron, se copian del mes anterior y se ajusta lo necesario.',
                2600,
            );
            await d.clic(
                p.getByRole('button', { name: /Copiar de/ }).first(),
                1400,
            );
            await d.clic(
                p.getByRole('button', { name: /Guardar diciembre/ }).first(),
                1800,
            );
            await d.sub('Diciembre ya figura como completo.', 2200);
            await d.sub(
                'También se pueden cargar desde Excel con la plantilla del sistema.',
                2400,
            );
            await d.mover(p.getByRole('button', { name: /Importar/ }).first());
            await d.espera(1200);
        },
    ],

    /* ------------------------------------------------------------------ 06 */
    '06-conceptos': [
        {},
        async (d) => {
            const p = d.page;
            await d.ir('/conceptos');
            await d.escena('Conceptos');
            await d.sub(
                'Los pagos y descuentos usan los códigos de la tabla 22 de SUNAT, que exige el PLAME.',
                3400,
            );
            await d.sub(
                'Para cada ingreso se marca si está afecto a EsSalud, ONP, AFP y renta de quinta.',
                3200,
            );
            await d.mover(p.getByText('sistema').first());
            await d.sub(
                'Los marcados «sistema» los usa el motor de cálculo y no se pueden borrar.',
                2800,
            );
            await d.clic(
                p.getByRole('button', { name: /Nuevo concepto/ }).first(),
                1000,
            );
            await d.escribir('#codigo_plame', '0107');
            await d.sub(
                'El tipo (ingreso, descuento o aporte) se deduce del código.',
                2400,
            );
            await d.escribir(
                '#nombre',
                'Trabajo en día feriado o día de descanso',
            );
            for (const a of ['EsSalud', 'ONP', 'AFP']) {
                await d.clic(
                    p
                        .getByRole('dialog')
                        .locator('label')
                        .filter({ hasText: a })
                        .first(),
                    300,
                );
            }
            await d.clic(
                p
                    .getByRole('dialog')
                    .locator('label')
                    .filter({ hasText: /quinta|5\.ª/ })
                    .first(),
                500,
            );
            await d.clic(
                p.getByRole('button', { name: 'Registrar concepto' }),
                1600,
            );
            await d.sub(
                'Listo: ya se puede usar como novedad en la planilla del mes.',
                2400,
            );
            await d.escribir(p.getByLabel('Buscar concepto'), 'feriado');
            await d.espera(1500);
        },
    ],

    /* ------------------------------------------------------------------ 07 */
    '07-trabajadores': [
        { empresa: 'Distribuidora' },
        async (d) => {
            const p = d.page;
            await d.ir('/trabajadores');
            await d.escena('Trabajadores');
            await d.sub(
                'El personal de la empresa activa, con búsqueda y filtros por estado y sistema de pensión.',
                3400,
            );
            await d.escribir(
                p.getByPlaceholder(/Buscar por documento/),
                'Vargas',
            );
            await d.espera(1200);
            await p.getByPlaceholder(/Buscar por documento/).fill('');
            await d.espera(800);
            await d.clic(
                p.getByRole('link', { name: /Nuevo trabajador/ }).first(),
                1200,
            );
            await d.sub(
                'La ficha se organiza en pestañas. Con DNI, un botón consulta RENIEC y completa nombres.',
                3400,
            );
            await d.escribir('#numero_documento', '70000201');
            await d.escribir('#apellido_paterno', 'Ejemplo');
            await d.escribir('#apellido_materno', 'Demostración');
            await d.escribir('#nombres', 'Valeria');
            await p.locator('#fecha_nacimiento').fill('1996-05-10');
            await d.elegir('#sexo', /Femenino/);
            await d.clic(p.getByRole('tab', { name: 'Laborales' }), 900);
            await d.escribir('#cargo', 'Analista de ventas');
            await d.clic('#fecha_ingreso', 200);
            await p.locator('#fecha_ingreso').fill('2026-11-01');
            await d.escribir('#remuneracion_basica', '2400.00');
            await d.clic(p.getByText('Percibe asignación familiar'), 600);
            await d.clic(p.getByRole('tab', { name: 'Pensión y salud' }), 900);
            await d.clic(p.getByText('Sistema Privado de Pensiones'), 800);
            await d.elegir('#afp_id', /Integra/);
            await d.escribir('#cuspp', '70000201DEMO');
            await d.sub(
                'Con AFP se indica la AFP, el CUSPP y el tipo de comisión (flujo o mixta).',
                3000,
            );
            await d.clic(
                p.getByRole('button', { name: 'Registrar trabajador' }),
                1800,
            );
            await d.sub('Guardado: ya figura en la lista de la empresa.', 2400);
            await d.clic(
                p.getByTitle('Editar a Ejemplo Demostración, Valeria'),
                1500,
            );
            await d.sub(
                'Los cambios de sueldo, cargo o pensión se registran con el mes desde el que rigen.',
                3400,
            );
            await d.clic(p.getByRole('tab', { name: 'Historial' }), 1600);
            await d.sub(
                'Así un periodo antiguo siempre se recalcula con los datos que tenía en ese mes.',
                3200,
            );
            await d.sub(
                'El cese se registra con su fecha y motivo (tabla 17 de SUNAT).',
                2600,
            );
            await d.mover(
                p.getByRole('button', { name: /Registrar cese/ }).first(),
            );
            await d.espera(800);
            await d.escena('Carga masiva desde Excel');
            await d.ir('/trabajadores/importar');
            await d.sub(
                'Para empezar rápido: descargar la plantilla, llenarla y subirla.',
                2800,
            );
            await p
                .locator('input[type=file]')
                .setInputFiles(
                    path.resolve(__dirname, 'demo-trabajadores.csv'),
                );
            await d.espera(1500);
            await p.waitForLoadState('networkidle');
            await d.sub(
                'Antes de grabar se muestra una vista previa con los errores por fila.',
                3000,
            );
            await d.desplazar(300, 2200);
            await d.sub(
                'Las filas con errores se corrigen en el archivo; las válidas se importan.',
                2600,
            );
            await d.clic(
                p
                    .getByRole('button', { name: /Registrar \d+ trabajador/ })
                    .first(),
                2000,
            );
            await d.ir('/trabajadores');
            await d.sub('Los nuevos trabajadores ya están en la lista.', 2400);
        },
    ],

    /* ------------------------------------------------------------------ 08 */
    '08-periodos': [
        { empresa: 'Distribuidora' },
        async (d) => {
            const p = d.page;
            await d.ir('/periodos');
            await d.escena('Periodos y planilla mensual');
            await d.sub(
                'Cada mes es un periodo: abierto, calculado o cerrado. Octubre ya está calculado y cerrado.',
                3600,
            );
            await d.clic(
                p.getByRole('link', { name: /Noviembre 2026/ }).first(),
                1500,
            );
            await d.sub(
                'La grilla de novedades funciona como una hoja de cálculo: faltas, horas extra, bonos, adelantos.',
                3800,
            );
            await d.sub(
                'Se guarda sola al pasar a otra fila. Registremos 4 horas extra al 25 %.',
                2600,
            );
            const he = p
                .locator('input[aria-label^="HE 25 % de Campos"]')
                .first();
            await d.escribir(he, '4');
            await p.keyboard.press('Enter');
            await d.espera(1500);
            await d.sub(
                'Ahora calculamos: el sistema revisa antes que no falten tasas ni parámetros.',
                3000,
            );
            await d.clic(
                p
                    .getByRole('button', {
                        name: /Calcular planilla|Recalcular/,
                    })
                    .first(),
                2500,
            );
            await d.sub(
                'Aparece el neto de cada trabajador y el total del mes.',
                2800,
            );
            await d.clic(
                p.locator('a', { hasText: /^\s*[\d,]+\.\d{2}\s*$/ }).first(),
                1500,
            );
            await d.escena('Detalle por trabajador');
            await d.sub(
                'El detalle: ingresos, aportes del trabajador, otros descuentos y aportes del empleador.',
                3600,
            );
            await d.desplazar(400, 2200);
            await d.sub(
                'Con las flechas se pasa al siguiente trabajador.',
                1500,
            );
            await d.clic(p.getByLabel('Trabajador siguiente'), 1600);
            await d.clic(p.getByLabel('Trabajador siguiente'), 1600);
            await d.desplazar(-400, 600);
            await d.clic(
                p.getByRole('link', { name: /Planilla de Noviembre/ }).first(),
                1500,
            );
            await d.escena('Cerrar el periodo');
            await d.sub(
                'Revisada la planilla, el administrador la cierra: ya nadie puede modificarla.',
                3000,
            );
            await d.clic(
                p.getByRole('button', { name: 'Cerrar periodo' }).first(),
                1500,
            );
            await d.clic(
                p
                    .getByRole('dialog')
                    .getByRole('button', { name: 'Cerrar periodo' }),
                2000,
            );
            await d.sub(
                'Para corregir algo, solo el administrador la reabre, indicando el motivo.',
                3000,
            );
            await d.clic(
                p.getByRole('button', { name: 'Reabrir periodo' }).first(),
                1000,
            );
            await d.escribir(
                '#motivo',
                'Rectificatoria: faltó registrar un bono',
            );
            await d.clic(
                p
                    .getByRole('dialog')
                    .getByRole('button', { name: 'Reabrir periodo' }),
                2000,
            );
            await d.sub(
                'Todo queda en la auditoría. Ahora calculemos todas las empresas a la vez.',
                2800,
            );
            await d.ir('/periodos');
            await d.clic(
                p.getByRole('button', { name: /Calcular todas las empresas/ }),
                1000,
            );
            await p.locator('#mes-masivo').fill('2026-11');
            await d.clic(
                p.getByRole('dialog').getByRole('button', { name: 'Calcular' }),
                1500,
            );
            await d.sub(
                'Se procesa en segundo plano y se ve el avance de cada empresa.',
                2500,
            );
            await p
                .waitForSelector('text=Terminado', { timeout: 90000 })
                .catch(() => {});
            await d.espera(1500);
            await d.sub(
                'Las empresas sin el mes abierto o con datos incompletos se informan sin detener a las demás.',
                3800,
            );
        },
    ],

    /* ------------------------------------------------------------------ 09 */
    '09-auditoria': [
        {},
        async (d) => {
            const p = d.page;
            await d.ir('/auditoria');
            await d.escena('Auditoría');
            await d.sub(
                'Todo cambio queda registrado: quién, cuándo, desde qué IP y qué valores cambiaron.',
                3600,
            );
            await d.sub(
                'Aquí están las acciones de los videos anteriores: empresa, usuario, trabajadores, cierre…',
                3400,
            );
            const reabierto = p.getByText('Reabierto').first();
            await d.mover(reabierto);
            await d.clic(
                p
                    .locator('li > button', { has: p.getByText('Reabierto') })
                    .first(),
                1500,
            );
            await d.sub(
                'Al abrir una fila se ve el detalle: por ejemplo, el motivo de la reapertura.',
                3200,
            );
            await d.sub(
                'Se puede filtrar por empresa, módulo, acción, usuario y fechas.',
                2600,
            );
            await d.desplazar(500, 2000);
            await d.desplazar(-500, 800);
            await d.sub(
                'El registro es inmutable: nadie puede editarlo ni borrarlo.',
                2800,
            );
        },
    ],
    /* ------------------------------------------------------------------ 10 */
    '10-panel': [
        {},
        async (d) => {
            const p = d.page;
            await d.ir('/dashboard?anio=2026&mes=11');
            await d.escena('Panel del estudio');
            await d.sub(
                'Al entrar, el panel resume el mes de todas las empresas del estudio.',
                3200,
            );
            await d.mover(p.getByText('Costo laboral').first());
            await d.sub(
                'Costo laboral del mes, cuánto cambió frente al mes anterior y su tendencia.',
                3200,
            );
            await d.mover(p.getByText('Cierre del mes').first());
            await d.sub(
                'Cuántas empresas ya cerraron su planilla y qué queda por atender.',
                3000,
            );
            await d.mover(p.getByText('Por atender').first());
            await d.espera(1200);
            await d.mover(p.getByText('Evolución de la planilla').first());
            await d.sub(
                'La evolución de los últimos 12 meses: costo, neto y aportes.',
                1000,
            );
            const zona = p.locator('rect[tabindex="0"]').first();
            const caja = await zona.boundingBox();
            if (caja) {
                for (const f of [0.15, 0.35, 0.55, 0.75, 0.95]) {
                    const x = Math.round(caja.x + caja.width * f);
                    const y = Math.round(caja.y + caja.height * 0.5);
                    await p.evaluate(([x, y]) => window.__mover(x, y), [x, y]);
                    await p.mouse.move(x, y, { steps: 12 });
                    await d.espera(700);
                }
            }
            await d.sub(
                'Al pasar el mouse se ve el detalle de cada mes. También hay vista de tabla.',
                2600,
            );
            await d.clic(
                p.getByRole('button', { name: 'Ver tabla' }).first(),
                2200,
            );
            await d.clic(
                p.getByRole('button', { name: 'Ver gráfico' }).first(),
                800,
            );
            await d.mover(p.getByText('¿A dónde va el costo laboral?').first());
            await d.sub(
                'A dónde va cada sol: neto al trabajador, pensiones, descuentos y aportes del empleador.',
                3600,
            );
            await d.mover(p.getByText('Neto por empresa').first());
            await d.sub('Y cuánto se paga en cada empresa.', 2200);
            await d.desplazar(-3000, 600);
            await d.escena('Filtro por empresa');
            await d.sub(
                'Con el filtro, el panel muestra una sola empresa.',
                1200,
            );
            await d.elegir('#empresa', /Distribuidora/);
            await d.espera(1500);
            await d.sub(
                'Los mismos indicadores y gráficos, solo de Distribuidora Pacífico.',
                3000,
            );
            await d.desplazar(700, 2000);
            await d.desplazar(-700, 600);
            await d.elegir('#empresa', 'Todas las empresas');
            await d.espera(1000);
            await d.escena('Módulos');
            await d.mover(p.getByRole('heading', { name: 'Módulos' }).first());
            await d.sub(
                'Al final, el acceso a cada módulo con una breve descripción.',
                3000,
            );
            await d.desplazar(500, 2000);
        },
    ],

    /* ------------------------------------------------------------------ 11 */
    '11-boletas': [
        { empresa: 'Distribuidora' },
        async (d) => {
            const p = d.page;
            await d.ir('/boletas');
            await d.escena('Boletas de pago');
            await d.sub(
                'Las boletas se generan solas al calcular la planilla de cada mes.',
                3000,
            );
            await d.mover(p.getByText('con marca «BORRADOR»').first());
            await d.sub(
                'Mientras el mes no se cierra, salen con la marca «BORRADOR».',
                2800,
            );
            const octubre = p.locator('tr', { hasText: 'Octubre 2026' });
            await d.sub(
                'Veamos las de octubre: todas en un solo PDF, listas para imprimir.',
                1500,
            );
            await d.verPdf(octubre.getByRole('link', { name: 'PDF' }), {
                titulo: 'Boletas de Octubre 2026.pdf',
            });
            await d.sub(
                'Con el logo de la empresa, los días, ingresos, descuentos, aportes y el neto a pagar.',
                3400,
            );
            await d.desplazarVisor(420, 2600);
            await d.desplazarVisor(700, 2200);
            await d.cerrarVisor();
            await d.sub(
                'También se descargan en un zip, un PDF por trabajador.',
                2600,
            );
            await d.mover(octubre.getByRole('link', { name: 'Zip' }));
            await d.espera(800);
            await d.escena('Boletas por correo');
            const septiembre = p.locator('tr', { hasText: 'Septiembre 2026' });
            await d.clic(
                septiembre.getByRole('link', { name: /Enviar/ }),
                1500,
            );
            await d.mover(p.getByText('Boletas por correo').first());
            await d.sub(
                'Cada boleta va por correo en un PDF protegido: la clave es el DNI del trabajador.',
                3400,
            );
            await d.clic(
                p.getByRole('button', { name: 'Enviar boletas por correo' }),
                1200,
            );
            await d.sub(
                'Se envían en segundo plano, respetando el límite de correos del hosting.',
                3000,
            );
            await d.clic(
                p.getByRole('dialog').getByRole('button', { name: 'Enviar' }),
                2500,
            );
            await p
                .waitForSelector('text=/Enviada \\d/', { timeout: 30000 })
                .catch(() => {});
            await d.espera(1500);
            await d.mover(p.getByText(/Enviada \d/).first());
            await d.sub(
                'Queda la fecha de cada envío, y se puede reenviar a quien la pida.',
                3200,
            );
            await d.mover(p.getByRole('button', { name: /Reenviar/ }).first());
            await d.espera(1200);
        },
    ],

    /* ------------------------------------------------------------------ 12 */
    '12-gratificaciones': [
        { empresa: 'Distribuidora' },
        async (d) => {
            const p = d.page;
            await d.ir('/gratificaciones');
            await d.escena('Gratificaciones');
            await d.sub(
                'La gratificación de julio y diciembre no se calcula aparte: entra sola en la planilla de ese mes.',
                3800,
            );
            await d.sub(
                'Aquí el resumen: trabajadores, gratificación y bonificación extraordinaria de cada una.',
                3200,
            );
            await d.clic(
                p
                    .locator('tr', { hasText: 'Julio 2026' })
                    .getByRole('link', { name: /Ver detalle/ }),
                1500,
            );
            await d.mover(p.getByText('Gratificación de Julio 2026').first());
            await d.sub(
                'En la planilla de julio, el detalle por trabajador: meses completos, faltas y remuneración computable.',
                3800,
            );
            await d.mover(p.locator('tr', { hasText: 'Vargas León' }).last());
            await d.sub(
                'Quien ingresó en julio aún no tiene un mes completo: no le corresponde en este semestre.',
                3400,
            );
            await d.mover(p.getByText('Bonificación', { exact: true }).last());
            await d.sub(
                'La bonificación extraordinaria es el 9 % (EsSalud) o 6.75 % (EPS). No paga ONP, AFP ni EsSalud.',
                3800,
            );
            await d.desplazar(-2000, 500);
            await d.clic(
                p.locator('a', { hasText: /^\s*[\d,]+\.\d{2}\s*$/ }).nth(3),
                1500,
            );
            await d.escena('Detalle por trabajador');
            await d.sub(
                'En el detalle del trabajador, la gratificación y la bonificación van junto a su sueldo.',
                3400,
            );
            await d.desplazar(350, 2400);
            await d.sub(
                'Y salen en su boleta de julio. En la microempresa no corresponde, y el sistema lo indica.',
                3200,
            );
        },
    ],

    /* ------------------------------------------------------------------ 13 */
    '13-cts': [
        { empresa: 'Distribuidora' },
        async (d) => {
            const p = d.page;
            await d.ir('/cts');
            await d.escena('CTS');
            await d.sub(
                'La CTS se deposita en mayo y noviembre. Se elige el depósito y se calcula con un clic.',
                3400,
            );
            await d.mover('#deposito');
            await d.mover(p.getByText('periodo computable').first());
            await d.sub(
                'Usa las planillas del semestre y la sexta parte de la gratificación pagada en él.',
                3200,
            );
            await d.clic(
                p.getByRole('button', { name: /Recalcular|Calcular CTS/ }),
                2500,
            );
            await d.mover(p.getByText('Total a depositar').first());
            await d.sub(
                'Total a depositar y, por trabajador, el tiempo computable, el sexto de gratificación y la CTS.',
                3600,
            );
            await d.mover(p.getByText(/Falta la entidad/).first());
            await d.sub(
                'Si a alguien le falta la entidad o la cuenta CTS en su ficha, el sistema avisa antes de depositar.',
                3600,
            );
            await d.desplazar(-2000, 600);
            await d.sub(
                'Las constancias de depósito salen en PDF, una por trabajador.',
                1500,
            );
            await d.verPdf(p.getByRole('link', { name: /Constancias PDF/ }), {
                titulo: 'Constancias CTS Noviembre 2026.pdf',
                paginas: 1,
            });
            await d.desplazarVisor(400, 2600);
            await d.cerrarVisor();
            await d.escena('Cerrar la CTS');
            await d.sub(
                'Revisada, se cierra: ya no se recalcula y las constancias salen sin la marca «BORRADOR».',
                3000,
            );
            await d.clic(
                p.getByRole('button', { name: 'Cerrar CTS' }).first(),
                1200,
            );
            await d.clic(
                p
                    .getByRole('dialog')
                    .getByRole('button', { name: 'Cerrar CTS' }),
                2200,
            );
            await d.sub(
                'Solo el administrador puede reabrirla, indicando el motivo. Y todo queda en la auditoría.',
                3200,
            );
        },
    ],

    /* ------------------------------------------------------------------ 14 */
    '14-vacaciones': [
        { empresa: 'Distribuidora' },
        async (d) => {
            const p = d.page;
            await d.ir('/vacaciones');
            await d.escena('Vacaciones');
            await d.sub(
                'El récord vacacional de cada trabajador: días ganados, gozados y el saldo.',
                3200,
            );
            await d.mover(p.getByText(/2 periodos vencidos/).first());
            await d.sub(
                'En rojo, quien tiene vacaciones vencidas sin gozar: un riesgo de indemnización.',
                3400,
            );
            await d.clic(
                p.getByRole('button', { name: /Detalle de Huertas/ }),
                1500,
            );
            await d.sub(
                'El detalle muestra cada año de servicios, lo gozado y la fecha límite.',
                3200,
            );
            await d.clic(
                p.getByRole('button', { name: /Detalle de Huertas/ }),
                600,
            );
            await d.escena('Registrar vacaciones');
            const fila = p.locator('tr', { hasText: 'Campos Arce' });
            await d.clic(fila.getByRole('button', { name: 'Registrar' }), 1000);
            await d.clic('#fecha_inicio', 200);
            await p.locator('#fecha_inicio').fill('2026-11-16');
            await d.escribir('#dias', '15');
            await d.escribir(
                '#observacion',
                'Primera quincena del periodo vencido',
            );
            await d.sub(
                'Se registra desde qué fecha y cuántos días; se descuentan del periodo más antiguo.',
                3200,
            );
            await d.clic(
                p
                    .getByRole('dialog')
                    .getByRole('button', { name: 'Registrar' }),
                1800,
            );
            await d.clic(
                p.getByRole('button', { name: /Detalle de Campos/ }),
                1500,
            );
            await d.sub(
                'Listo: el saldo baja y queda el registro de lo gozado.',
                3000,
            );
            await d.escena('En la planilla');
            await d.ir('/periodos');
            await d.clic(
                p.getByRole('link', { name: /Noviembre 2026/ }).first(),
                1500,
            );
            await d.sub(
                'En la planilla del mes, los días de vacaciones aparecen solos y se pagan como remuneración vacacional.',
                3600,
            );
            await d.clic(
                p
                    .getByRole('button', {
                        name: /Calcular planilla|Recalcular/,
                    })
                    .first(),
                2500,
            );
            await d.mover(p.locator('tr', { hasText: 'Campos Arce' }).first());
            await d.espera(1800);
        },
    ],

    /* ------------------------------------------------------------------ 15 */
    '15-plame': [
        {},
        async (d) => {
            const p = d.page;
            await d.ir('/plame?anio=2026&mes=10');
            await d.escena('PLAME');
            await d.sub(
                'Los archivos para importar en el PDT PLAME de SUNAT, de cada empresa con el mes cerrado.',
                3600,
            );
            await d.mover(
                p.getByText(/Solo se exportan los periodos cerrados/).first(),
            );
            await d.sub(
                'Solo se exportan meses cerrados: lo que se declara es exactamente lo que se pagó.',
                3200,
            );
            const fila = p.locator('tr', { hasText: 'Distribuidora Pacífico' });
            const zip = await d.descargar(
                fila
                    .locator('a, button')
                    .filter({ hasText: /Descargar zip/ })
                    .first(),
            );
            await d.verArchivo(zip);
            await d.sub(
                'El zip trae los archivos con el nombre que pide SUNAT: RUC, periodo y tipo.',
                3200,
            );
            await d.sub(
                '.rem: ingresos, descuentos y aportes de cada trabajador, por código de la tabla 22.',
                3200,
            );
            await d.elegirArchivo(1, 600);
            await d.sub(
                '.jor: días y horas trabajadas. .toc: otras condiciones. Si hubo faltas o descanso médico, va también el .snl.',
                3400,
            );
            await d.elegirArchivo(2, 2000);
            await d.cerrarVisor();
            await d.sub(
                'Con «Descargar todas» se bajan las de todas las empresas en un solo zip.',
                2800,
            );
            await d.mover(
                p
                    .locator('a, button')
                    .filter({ hasText: /Descargar todas/ })
                    .first(),
            );
            await d.espera(800);
            await d.escena('Historial de exportaciones');
            await d.ir('/plame?anio=2026&mes=10');
            await d.mover(p.getByText('Historial de exportaciones').first());
            await d.clic(
                p
                    .locator('button[aria-expanded]')
                    .filter({ hasText: '10/2026' })
                    .first(),
                1200,
            );
            await d.desplazar(300, 600);
            await d.sub(
                'Cada descarga queda registrada con la huella SHA-256 de cada archivo: se sabe exactamente qué se declaró.',
                4000,
            );
        },
    ],

    /* ------------------------------------------------------------------ 16 */
    '16-afpnet': [
        {},
        async (d) => {
            const p = d.page;
            await d.ir('/afpnet?anio=2026&mes=10');
            await d.escena('AFPnet');
            await d.sub(
                'Para pagar a las AFP: el archivo de planilla que se carga en AFPnet.',
                3000,
            );
            await d.sub(
                'Incluye a cada afiliado con su CUSPP, su remuneración asegurable y su tipo de trabajador.',
                3400,
            );
            const fila = p.locator('tr', { hasText: 'Distribuidora Pacífico' });
            const txt = await d.descargar(
                fila
                    .locator('a, button')
                    .filter({ hasText: /Descargar TXT/ })
                    .first(),
            );
            await d.verArchivo(txt);
            await d.sub(
                'Un afiliado por línea, en el formato de AFPnet. Los de ONP no van en este archivo.',
                3400,
            );
            await d.cerrarVisor();
            await d.sub(
                'Igual que en PLAME: solo meses cerrados, todas las empresas a la vez e historial con huella.',
                3400,
            );
            await d.mover(
                p
                    .locator('a, button')
                    .filter({ hasText: /Descargar todas/ })
                    .first(),
            );
            await d.espera(1000);
        },
    ],

    /* ------------------------------------------------------------------ 17 */
    '17-reportes': [
        { empresa: 'Distribuidora' },
        async (d) => {
            const p = d.page;
            await d.ir('/reportes');
            await d.escena('Reportes');
            await d.sub('Cuatro reportes del mes, en Excel y en PDF.', 2400);
            await d.mover('#periodo');
            await p.locator('#periodo').selectOption({ label: 'Octubre 2026' });
            await p.waitForLoadState('networkidle');
            await d.espera(1000);
            const tarjeta = (t) =>
                p.locator('section').filter({
                    has: p.getByRole('heading', { name: t, exact: true }),
                });
            await d.mover(p.getByRole('heading', { name: 'Planilla del mes' }));
            await d.sub(
                'La planilla del mes: una fila por trabajador, cada concepto en su columna y los totales.',
                1500,
            );
            await d.verPdf(
                tarjeta('Planilla del mes').getByRole('link', { name: 'PDF' }),
                { paginas: 1 },
            );
            await d.espera(2600);
            await d.cerrarVisor();
            await d.mover(p.getByRole('heading', { name: 'Resumen' }));
            await d.sub(
                'El resumen: cuánto se paga a ONP, a cada AFP, a EsSalud, a SUNAT y a los trabajadores.',
                1500,
            );
            await d.verPdf(
                tarjeta('Resumen').getByRole('link', { name: 'PDF' }),
                { paginas: 1 },
            );
            await d.espera(2400);
            await d.cerrarVisor();
            await d.mover(
                p.getByRole('heading', { name: 'Centralización contable' }),
            );
            await d.sub(
                'La centralización contable: el asiento del mes con las cuentas del PCGE. Debe y haber cuadran.',
                1500,
            );
            await d.verPdf(
                tarjeta('Centralización contable').getByRole('link', {
                    name: 'PDF',
                }),
                { paginas: 1 },
            );
            await d.espera(2200);
            await d.desplazarVisor(400, 2000);
            await d.cerrarVisor();
            await d.mover(p.getByRole('heading', { name: 'Costo laboral' }));
            await d.sub(
                'Y el costo laboral por trabajador, con las provisiones de gratificación, CTS y vacaciones.',
                3400,
            );
            await d.mover(
                tarjeta('Costo laboral').getByRole('link', { name: 'Excel' }),
            );
            await d.sub('En Excel, para seguir trabajando los números.', 2200);
        },
    ],
};

void (async () => {
    const pedidos = process.argv.slice(2);
    const nombres = pedidos.length ? pedidos : Object.keys(VIDEOS);
    for (const n of nombres) {
        const [opciones, recorrido] = VIDEOS[n];
        await grabar(n, recorrido, opciones);
    }
})();

module.exports = { VIDEOS, BASE };
