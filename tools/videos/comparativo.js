// Contador abajo a la izquierda: arriba taparía los botones que se presionan.
// Video comparativo «Un mes de planillas: Excel vs. Serialo» (recreación, datos ficticios). Dos tomas con el mismo
// caso (comparativo-caso.json) y el MISMO ritmo por acción; lo que se compara es cuántas acciones pide cada uno,
// no el tiempo. Un contador en pantalla suma cada dato escrito, clic, copiar/pegar y archivo armado a mano.
//
//   1. php artisan migrate:fresh --seed && php tools/videos/comparativo-preparar.php
//   2. composer dev   (servidor y cola)
//   3. node comparativo.js serialo   → videos/comparativo-serialo.webm
//      php tools/videos/comparativo-resultado.php   → videos/comparativo-resultado.json (montos para el Excel)
//   4. node comparativo.js excel     → videos/comparativo-excel.webm   (recreación en comparativo-excel.html)
const fs = require('fs');
const path = require('path');
const { grabar } = require('./lib');

const CASO = JSON.parse(fs.readFileSync(path.resolve(__dirname, 'comparativo-caso.json'), 'utf8'));
const SALIDA = process.env.SALIDA || path.resolve(__dirname, '../../videos');
const RESULTADO = path.join(SALIDA, 'comparativo-resultado.json');

/** Contador fijo arriba a la izquierda; guarda las cifras en sessionStorage para que sobreviva a cada navegación. */
const CONTADOR = `
(() => {
  const claves = [['datos','Datos escritos'],['clics','Clics'],['copias','Copiar y pegar'],['archivos','Archivos armados a mano']];
  const leer = () => JSON.parse(sessionStorage.getItem('cmp') || '{"datos":0,"clics":0,"copias":0,"archivos":0}');
  const pintar = () => {
    let c = document.getElementById('cmp-contador');
    if (!c) {
      if (!document.body) return;
      const css = document.createElement('style');
      css.textContent = '#cmp-contador{position:fixed;z-index:2147483645;left:10px;bottom:38px;display:grid;grid-template-columns:repeat(2,118px);gap:5px;font:600 11px system-ui,Segoe UI,sans-serif;pointer-events:none}#cmp-contador div{background:rgba(15,23,42,.9);color:#fff;border-radius:9px;padding:5px 8px;text-align:center}#cmp-contador b{display:block;font-size:22px;line-height:1.1;font-variant-numeric:tabular-nums}#cmp-contador .t{grid-column:span 2;font-size:13px;font-weight:700;background:var(--cmp-color,#2558a8)}';
      document.head.appendChild(css);
      c = document.createElement('div'); c.id = 'cmp-contador'; document.body.appendChild(c);
    }
    document.documentElement.style.setProperty('--cmp-color', sessionStorage.getItem('cmp-color') || '#2558a8');
    if (sessionStorage.getItem('cmp-lado') === 'derecha') { c.style.left = 'auto'; c.style.right = '10px'; }
    const v = leer();
    c.innerHTML = '<div class="t">' + (sessionStorage.getItem('cmp-titulo') || '') + '</div>' +
      claves.map(([k, t]) => '<div><b>' + v[k] + '</b>' + t + '</div>').join('');
  };
  window.__cmp = (clave, n = 1) => { const v = leer(); v[clave] += n; sessionStorage.setItem('cmp', JSON.stringify(v)); pintar(); };
  window.__cmpTitulo = (t, color, lado) => { sessionStorage.setItem('cmp-titulo', t); if (color) sessionStorage.setItem('cmp-color', color); if (lado) sessionStorage.setItem('cmp-lado', lado); pintar(); };
  window.__cmpLeer = leer;
  if (document.body) pintar(); else document.addEventListener('DOMContentLoaded', pintar);
})();
`;

/** Envuelve clic/escribir del demo para que cada acción sume en el contador. */
function contar(d) {
    const { clic, escribir } = d;
    const sumar = (clave, n = 1) => d.page.evaluate(([c, n]) => window.__cmp && window.__cmp(c, n), [clave, n]);
    d.sumar = sumar;
    d.clic = async (loc, pausa) => {
        await sumar('clics');
        await clic.call(d, loc, pausa);
    };
    // escribir() hace un clic para enfocar: ese clic no se cuenta aparte (el dato escrito sí).
    d.escribir = async (loc, texto, opciones) => {
        await sumar('datos');
        d.clic = clic;
        try {
            await escribir.call(d, loc, texto, opciones);
        } finally {
            d.clic = async (l, p) => {
                await sumar('clics');
                await clic.call(d, l, p);
            };
        }
    };
    return d;
}

async function preparar(d, titulo, color, lado = 'izquierda') {
    await d.page.addInitScript(CONTADOR);
    await d.page.evaluate(CONTADOR);
    await d.page.evaluate(([t, c, l]) => {
        sessionStorage.setItem('cmp', '{"datos":0,"clics":0,"copias":0,"archivos":0}');
        window.__cmpTitulo(t, c, l);
    }, [titulo, color, lado]);
    return contar(d);
}

const nombreFila = (apellido) => {
    const t = CASO.trabajadores.find((x) => x.apellido_paterno === apellido);
    return `${t.apellido_paterno} ${t.apellido_materno}`;
};

/** El selector de mes de PLAME y AFPnet: cuenta como un dato escrito. */
async function elegirNoviembre(d) {
    const mes = d.page.locator('input[type="month"]').first();
    await d.sumar('datos');
    await d.mover(mes);
    await mes.fill('2026-11');
    await mes.dispatchEvent('change');
    await d.page.waitForLoadState('networkidle');
    await d.espera(1200);
}

/* ---------------------------------------------------------------- Serialo */
// Los subtítulos son, frase por frase, el texto de la voz en off (docs/videos/elevenlabs/comparativo-serialo.txt):
// se leen de ese archivo para que nunca se desfasen. Cada frase queda en pantalla lo que tarda en decirse
// (~15 caracteres por segundo, como la voz de ElevenLabs) más su pausa.
const VOZ = fs
    .readFileSync(path.resolve(__dirname, '../../docs/videos/elevenlabs/comparativo-serialo.txt'), 'utf8')
    .split(/\r?\n/)
    .map((l) => l.trim())
    .filter((l) => l && !l.startsWith('<break'));
const duracionFrase = (t) => Math.round(t.length * 66) + 700;

/** Muestra la frase `i` de la voz; las acciones de `mientras` ocurren mientras se dice. */
async function narrar(d, i, mientras = null) {
    const inicio = Date.now();
    await d.sub(VOZ[i], 0);
    if (mientras) await mientras();
    const resta = duracionFrase(VOZ[i]) - (Date.now() - inicio);
    if (resta > 0) await d.espera(resta);
}

async function serialo(demo) {
    const p = demo.page;
    await demo.ir('/dashboard');
    const d = await preparar(demo, 'Serialo Planillas', '#2558a8');
    await d.escena('Serialo Planillas');
    await narrar(d, 0); // Así se hace un mes de planillas…

    await d.escena('1 · Novedades del mes');
    await narrar(d, 1, async () => {
        // Los trabajadores y sus sueldos ya están… solo escribes las novedades.
        await d.clic(p.getByRole('link', { name: 'Periodos' }).first(), 900);
        await d.clic(p.getByRole('link', { name: /Noviembre 2026/ }).first(), 1200);
        for (const n of CASO.novedades) {
            const campo = p.locator(`input[aria-label^="${n.campo} de ${nombreFila(n.trabajador)}"]`).first();
            await d.escribir(campo, n.valor);
            await p.keyboard.press('Enter');
            await d.espera(400);
        }
    });
    await narrar(d, 2, async () => {
        // Cada fila se guarda sola.
        await d.page.getByText('Escribe y muévete con').first().click();
        await p.waitForLoadState('networkidle');
    });

    await d.escena('2 · Cálculo');
    await narrar(d, 3, async () => {
        await d.clic(p.getByRole('button', { name: /Calcular planilla|Recalcular/ }).first(), 2500);
    });

    await d.escena('3 · Cierre');
    await narrar(d, 4, async () => {
        await d.clic(p.getByRole('button', { name: 'Cerrar periodo' }).first(), 1000);
        await d.clic(p.getByRole('dialog').getByRole('button', { name: 'Cerrar periodo' }), 1500);
    });

    await d.escena('4 · Boletas');
    await narrar(d, 5, async () => {
        await d.sumar('clics');
        await d.verPdf(p.getByRole('link', { name: /Boletas PDF/ }).first(), { titulo: 'Boletas de noviembre 2026.pdf', paginas: 2 });
    });
    await d.cerrarVisor();

    await d.escena('5 · PLAME');
    await narrar(d, 6, async () => {
        await d.clic(p.getByRole('link', { name: 'PLAME' }).first(), 1000);
        await elegirNoviembre(d);
        const fila = p.locator('tr', { hasText: 'Inversiones Ejemplo' });
        await d.sumar('clics');
        await d.verArchivo(await d.descargar(fila.locator('a, button').filter({ hasText: /Descargar zip/ }).first()));
    });
    await d.cerrarVisor();

    await d.escena('6 · AFPnet');
    await narrar(d, 7, async () => {
        await d.clic(p.getByRole('link', { name: 'AFPnet' }).first(), 1000);
        await elegirNoviembre(d);
        const fila = p.locator('tr', { hasText: 'Inversiones Ejemplo' });
        await d.sumar('clics');
        await d.verArchivo(await d.descargar(fila.locator('a, button').filter({ hasText: /Descargar TXT/ }).first()));
    });
    await d.cerrarVisor();

    const cifras = await p.evaluate(() => window.__cmpLeer());
    await d.escena('Listo');
    await narrar(d, 8); // Mes completo: ocho datos escritos, diez clics…
    await narrar(d, 9); // Eso es Serialo Planillas.

    // La voz dice las cifras en palabras: si cambian, hay que actualizar el texto de ElevenLabs.
    if (cifras.datos !== 8 || cifras.clics !== 10) {
        console.warn(`⚠ Las cifras cambiaron (${cifras.datos} datos, ${cifras.clics} clics): actualiza comparativo-serialo.txt`);
    }
    fs.writeFileSync(path.join(SALIDA, 'comparativo-serialo-cifras.json'), JSON.stringify(cifras, null, 4));
}

/* ---------------------------------------------------------------- Excel (recreación) */
async function excel(demo) {
    const p = demo.page;
    const resultado = JSON.parse(fs.readFileSync(RESULTADO, 'utf8'));
    const filaDe = (apellido) => 6 + resultado.trabajadores.findIndex((t) => t.nombre.startsWith(apellido + ' '));
    const COLUMNA = { Faltas: 'G', 'HE 25 %': 'H', 'HE 35 %': 'I' };
    const novedades = CASO.novedades.map((n) => ({ celda: `Planilla!${COLUMNA[n.campo]}${filaDe(n.trabajador)}`, valor: n.valor }));
    // Columnas de Planilla que se copian a la hoja PLAME: DNI, remuneración, asignación, horas extra y pensión.
    const plame = [
        ['B', 'B'],
        ['J', 'C'],
        ['F', 'D'],
        ['K', 'E'],
        ['M', 'F'],
    ];
    await p.goto('file:///' + path.resolve(__dirname, 'comparativo-excel.html').replace(/\\/g, '/'));
    await p.evaluate((r) => window.cargar(r), resultado);
    const d = await preparar(demo, 'Excel (recreación)', '#3f6f52', 'derecha');
    const celda = (id) => p.locator(`[data-celda="${id}"]`).first();
    const hoja = (n) => d.clic(p.locator(`[data-hoja="${n}"]`).first(), 700);
    const menu = async (...pasos) => {
        for (const paso of pasos) await d.clic(p.locator(`[data-menu="${paso}"]`).first(), 450);
    };
    const guardarComo = async (nombre, tipo) => {
        await menu('archivo', 'guardar-como');
        await d.escribir('#dialogo-nombre', nombre, { retraso: 35 });
        if (tipo) await p.selectOption('#dialogo-tipo', tipo);
        await d.clic('#dialogo-guardar', 900);
        await d.sumar('archivos');
    };
    /** Selecciona un rango en Planilla, lo copia, va a la otra hoja, pega y vuelve. */
    const copiarPegar = async (desde, hasta, hojaDestino, destino) => {
        await d.clic(celda(`Planilla!${desde}`), 250);
        await p.keyboard.down('Shift');
        await d.clic(celda(`Planilla!${hasta}`), 250);
        await p.keyboard.up('Shift');
        await p.keyboard.press('Control+C');
        await d.sumar('copias');
        await d.espera(500);
        await hoja(hojaDestino);
        await d.clic(celda(`${hojaDestino}!${destino}`), 300);
        await p.keyboard.press('Control+V');
        await d.espera(800);
    };

    await d.escena('Excel de planillas (recreación)');
    await d.sub('La misma empresa ficticia y las mismas novedades, en una planilla de Excel sin macros.', 3200);

    await d.escena('0 · Libro del mes');
    await d.sub('Se parte del libro de octubre y se guarda como el de noviembre.', 2600);
    await guardarComo('Planilla_Noviembre_2026', 'xlsx');
    await hoja('Datos');
    await d.escribir(celda('Datos!B2'), 'NOVIEMBRE 2026');
    await p.keyboard.press('Enter');

    await d.escena('1 · Novedades del mes');
    await hoja('Planilla');
    for (const n of novedades) {
        await d.escribir(celda(n.celda), n.valor);
        await p.keyboard.press('Enter');
        await d.espera(350);
    }
    await d.sub('Las fórmulas recalculan ingresos, aportes y neto. Hay que revisar que ninguna se haya movido.', 3200);

    await d.escena('2 · Boletas, una por una');
    await hoja('Boleta');
    for (const [i] of resultado.trabajadores.entries()) {
        await d.escribir(celda('Boleta!C4'), String(i + 1));
        await p.keyboard.press('Enter');
        await d.espera(500);
        await menu('archivo', 'exportar-pdf');
        await d.escribir('#dialogo-nombre', `Boleta_11-2026_${resultado.trabajadores[i].dni}`, { retraso: 25 });
        await d.clic('#dialogo-guardar', 600);
        await d.sumar('archivos');
        if (i === 1) await d.sub('Elegir al trabajador, exportar a PDF y ponerle nombre… diez veces.', 2400);
    }

    await d.escena('3 · PLAME');
    await hoja('Planilla');
    await d.sub('Para el PLAME se copian los montos a la hoja de exportación, en el orden que pide SUNAT.', 3000);
    for (const [origen, destino] of plame) {
        await copiarPegar(`${origen}6`, `${origen}15`, 'PLAME', `${destino}3`);
        await hoja('Planilla');
    }
    await hoja('PLAME');
    for (const archivo of ['rem', 'jor', 'snl']) {
        await guardarComo(`0601202611${resultado.empresa.ruc}`, archivo);
    }

    await d.escena('4 · AFPnet');
    await hoja('Planilla');
    await d.clic(p.locator('[data-menu="filtro-afp"]').first(), 900);
    await d.sub('Se filtran los afiliados a AFP y se copian a la hoja de AFPnet.', 2600);
    // Con el filtro, el rango termina en el último afiliado a AFP visible.
    const ultimaAfp = 6 + resultado.trabajadores.map((t) => t.pension !== 'ONP').lastIndexOf(true);
    await copiarPegar('B6', `L${ultimaAfp}`, 'AFPnet', 'A3');
    await guardarComo(`AFPnet_11-2026_${resultado.empresa.ruc}`, 'xls');

    const cifras = await p.evaluate(() => window.__cmpLeer());
    await d.escena('Listo');
    await d.sub(
        `Mes completo: ${cifras.datos} datos escritos, ${cifras.clics} clics, ${cifras.copias} copiar y pegar y ${cifras.archivos} archivos armados a mano.`,
        5000,
    );
    fs.writeFileSync(path.join(SALIDA, 'comparativo-excel-cifras.json'), JSON.stringify(cifras, null, 4));
}

module.exports = { CASO };

if (require.main === module) {
    void (async () => {
        const que = process.argv.slice(2);
        if (que.length === 0 || que.includes('serialo')) {
            await grabar('comparativo-serialo', serialo, { empresa: 'Inversiones Ejemplo' });
        }
        if (que.length === 0 || que.includes('excel')) {
            await grabar('comparativo-excel', excel, { sesion: null });
        }
    })();
}
