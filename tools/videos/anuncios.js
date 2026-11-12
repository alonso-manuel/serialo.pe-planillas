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

/** Toma del guion V1: novedades → calcular todas las empresas → planilla lista. Sin subtítulos (van en el montaje). */
async function tomaV1(d) {
    const p = d.page;
    await d.ir('/periodos');
    await d.clic(p.getByRole('link', { name: /Noviembre 2026/ }).first(), 1200);
    await d.mover(p.locator('input[aria-label^="HE 25 % de Vargas"]'));
    await d.escribir(p.locator('input[aria-label^="HE 25 % de Vargas"]'), '4');
    await p.keyboard.press('Enter');
    await d.espera(1200);
    await d.ir('/periodos');
    await d.clic(
        p.getByRole('button', { name: /Calcular todas las empresas/ }),
        900,
    );
    await p.locator('#mes-masivo').fill('2026-11');
    await d.clic(
        p.getByRole('dialog').getByRole('button', { name: 'Calcular' }),
        800,
    );
    await p
        .waitForSelector('text=Terminado', { timeout: 90000 })
        .catch(() => {});
    await d.espera(1800);
    await d.ir('/periodos');
    await d.clic(p.getByRole('link', { name: /Noviembre 2026/ }).first(), 1500);
    await d.mover(p.getByText('Neto').first());
    await d.espera(2500);
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
    }
    const webm = await montar(
        'anuncio-v1',
        'anuncio-v1.html',
        archivo('anuncio-v1-toma.webm'),
    );
    console.log('✓', aMp4(webm));
})();
