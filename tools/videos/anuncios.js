// Anuncios verticales (9:16) para Meta Ads. Ver docs/videos/meta-ads.md.
// 1) Graba la toma del sistema (1000×800, datos ficticios) con la misma herramienta de los tutoriales.
// 2) La monta en anuncio-v1.html (1080×1920) con textos y cierre, y graba ese montaje.
// 3) Convierte a MP4 (H.264) con ffmpeg (en Docker si no está instalado).
// Uso: BASE_URL=http://127.0.0.1:8000 node anuncios.js   (BD de demo con la cola corriendo, como los videos)
//      SOLO_MONTAJE=1 node anuncios.js                     (rehace el montaje con la toma ya grabada)
process.env.ANCHO = process.env.ANCHO || '1000';
process.env.ALTO = process.env.ALTO || '800';

const { chromium } = require('playwright');
const { execFileSync } = require('child_process');
const fs = require('fs');
const path = require('path');
const { grabar } = require('./lib');

const SALIDA = process.env.SALIDA || path.resolve(__dirname, '../../videos');
const archivo = (n) => path.join(SALIDA, n);

/**
 * Marcas de cámara: en qué segundo de la toma y en qué punto de la pantalla ocurre cada momento clave.
 * El montaje hace zoom y se desplaza hacia ellas («movimiento de cámara»). Se guardan junto a la toma (.json).
 */
function crearMarcas() {
    const t0 = Date.now(); // la toma empieza a grabarse al crear la página, justo antes del recorrido
    const marcas = [];
    return {
        marcas,
        async marcar(loc, { zoom = 1.8, dura = 1.6, dx = 0, dy = 0 } = {}) {
            const b = await loc.boundingBox().catch(() => null);
            if (!b) return;
            marcas.push({
                t: (Date.now() - t0) / 1000,
                x: Math.round(b.x + b.width / 2 + dx),
                y: Math.round(b.y + b.height / 2 + dy),
                zoom,
                dura,
            });
        },
    };
}

let marcasV1 = [];

/** Toma del guion V1: novedades → calcular todas las empresas → planilla lista. Sin subtítulos (van en el montaje). */
async function tomaV1(d) {
    const p = d.page;
    const { marcas, marcar } = crearMarcas();
    marcasV1 = marcas;
    await d.ir('/periodos');
    await d.clic(p.getByRole('link', { name: /Noviembre 2026/ }).first(), 900);
    const celda = p.locator('input[aria-label^="HE 25 % de Vargas"]');
    await d.mover(celda);
    await marcar(celda, { zoom: 2.1, dura: 2.2 });
    await d.escribir(celda, '4');
    await p.keyboard.press('Enter');
    await d.espera(900);
    await d.ir('/periodos');
    const boton = p.getByRole('button', {
        name: /Calcular todas las empresas/,
    });
    await d.mover(boton);
    await marcar(boton, { zoom: 2.2, dura: 1.4 });
    await d.clic(boton, 700);
    await p.locator('#mes-masivo').fill('2026-11');
    const calcular = p.getByRole('dialog').getByRole('button', {
        name: 'Calcular',
    });
    await d.mover(calcular);
    await marcar(p.getByRole('dialog'), { zoom: 1.7, dura: 1.2 });
    await d.clic(calcular, 600);
    await p
        .waitForSelector('text=Terminado', { timeout: 90000 })
        .catch(() => {});
    await marcar(p.getByText('Terminado').first(), {
        zoom: 1.7,
        dura: 2.0,
        dy: 70,
    });
    await d.espera(1600);
    await d.ir('/periodos');
    await d.clic(p.getByRole('link', { name: /Noviembre 2026/ }).first(), 1000);
    const neto = p.getByText('Neto').first();
    await d.mover(neto);
    await marcar(neto, { zoom: 1.9, dura: 2.6, dx: -60, dy: 150 });
    await d.espera(2600);
}

/** Graba el montaje vertical (HTML) mientras reproduce la toma; devuelve la ruta del .webm. */
async function montar(nombre, html, toma) {
    const navegador = await chromium.launch();
    const tmp = path.join(SALIDA, '_tmp');
    const ctx = await navegador.newContext({
        viewport: { width: 1080, height: 1920 },
        recordVideo: { dir: tmp, size: { width: 1080, height: 1920 } },
    });
    const page = await ctx.newPage();
    // Marcas de cámara de la toma (si existen) disponibles para el montaje antes de cargar la página.
    const json = toma.replace(/\.webm$/, '.json');
    const marcas = fs.existsSync(json) ? fs.readFileSync(json, 'utf8') : '[]';
    await page.addInitScript(`window.MARCAS = ${marcas};`);
    const aUrl = (ruta) => 'file:///' + ruta.split(path.sep).join('/');
    const url =
        aUrl(path.resolve(__dirname, html)) +
        '?toma=' +
        encodeURIComponent(aUrl(path.resolve(toma)));
    await page.goto(url);
    await page.waitForFunction(() => window.terminado === true, null, {
        timeout: 120000,
    });
    await page.waitForTimeout(400);
    const video = page.video();
    await ctx.close();
    const destino = archivo(`${nombre}.webm`);
    await video.saveAs(destino);
    await video.delete();
    await navegador.close();
    return destino;
}

/** MP4 para Meta: H.264, 30 fps, yuv420p, sin audio. Usa ffmpeg local o la imagen de Docker. */
function aMp4(webm) {
    const mp4 = webm.replace(/\.webm$/, '.mp4');
    const args = [
        '-y',
        '-i',
        path.basename(webm),
        '-c:v',
        'libx264',
        '-preset',
        'slow',
        '-crf',
        '20',
        '-pix_fmt',
        'yuv420p',
        '-r',
        '30',
        '-movflags',
        '+faststart',
        '-an',
        path.basename(mp4),
    ];
    try {
        execFileSync('ffmpeg', args, {
            cwd: path.dirname(webm),
            stdio: 'ignore',
        });
    } catch {
        execFileSync(
            'docker',
            [
                'run',
                '--rm',
                '-v',
                `${path.dirname(webm)}:/w`,
                '-w',
                '/w',
                'jrottenberg/ffmpeg:7.1-ubuntu',
                ...args,
            ],
            { stdio: 'ignore' },
        );
    }
    return mp4;
}

void (async () => {
    fs.mkdirSync(SALIDA, { recursive: true });
    // SOLO_MONTAJE=1: reutiliza la toma ya grabada (cambios de textos o diseño sin volver a grabar el sistema).
    if (
        !process.env.SOLO_MONTAJE ||
        !fs.existsSync(archivo('anuncio-v1-toma.webm'))
    ) {
        await grabar('anuncio-v1-toma', tomaV1, { empresa: 'Distribuidora' });
        fs.writeFileSync(
            archivo('anuncio-v1-toma.json'),
            JSON.stringify(marcasV1, null, 1),
        );
    }
    const webm = await montar(
        'anuncio-v1',
        'anuncio-v1.html',
        archivo('anuncio-v1-toma.webm'),
    );
    console.log('✓', aMp4(webm));
})();
