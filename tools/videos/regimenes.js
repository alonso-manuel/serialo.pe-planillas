// Video vertical para TikTok: «¿Construcción civil y minería?» (regimenes.html, hecho con la skill Bang Motion).
// Se exporta CUADRO POR CUADRO (mueve el timeline, espera el dibujo y captura): no pierde cuadros como una grabación.
// Uso: node regimenes.js                                  → videos/regimenes-especiales.mp4
//      VOZ=regimenes-especiales.mp3 node regimenes.js     → además une la voz (mp3 en la carpeta videos/)
// Requiere internet (fuentes de Google y GSAP desde CDN) y ffmpeg local o Docker.
const { chromium } = require('playwright');
const { execFileSync } = require('child_process');
const fs = require('fs');
const path = require('path');

const SALIDA = process.env.SALIDA || path.resolve(__dirname, '../../videos');
const FPS = Number(process.env.FPS || 30);
const NOMBRE = 'regimenes-especiales';

/**
 * Sincronía con la voz (regimenes-especiales.mp3, 47.4 s): pares [segundo del timeline, segundo de la voz].
 * Salen de las pausas de la voz (ffmpeg silencedetect) y de cuándo aparece cada cosa en regimenes.html:
 * «ni de minería», «contadores me escriben», «funcionan muy distinto», cada calendario («en obra», «en mina»,
 * «Serialo hoy»), «¿sistema aparte», «o todo en uno», el engranaje, cada duda de la libreta, «si llevas
 * planillas» y «gracias». Entre dos pares el tiempo se estira o encoge en línea recta.
 * Si se regraba la voz, volver a medir las pausas y ajustar la segunda columna.
 */
const PAUTA_VOZ = [
    [0, 0],
    [5.1, 3.95],
    [8.4, 5.35],
    [12.5, 8.75],
    [13.3, 11.7],
    [14.2, 13.8],
    [15.1, 17.5],
    [17.4, 19.85],
    [19.9, 22.4],
    [21.2, 23.3],
    [23.3, 28.6],
    [24.7, 30.3],
    [26.05, 33.3],
    [27.4, 34.75],
    [28.75, 37.0],
    [30.8, 39.0],
    [34.0, 42.9],
    [35.0, 43.6],
    [38, 47.7],
];
/** Segundo de la voz → segundo del timeline. */
function aTimeline(t) {
    for (let i = 1; i < PAUTA_VOZ.length; i++) {
        const [a0, v0] = PAUTA_VOZ[i - 1],
            [a1, v1] = PAUTA_VOZ[i];
        if (t <= v1) return a0 + ((t - v0) / (v1 - v0)) * (a1 - a0);
    }
    return PAUTA_VOZ[PAUTA_VOZ.length - 1][0];
}
const CON_VOZ = Boolean(process.env.VOZ);

/** ffmpeg local o, si no está, el de la imagen de Docker (carpeta montada en /w). */
function ffmpeg(args, carpeta) {
    try {
        execFileSync('ffmpeg', args, { cwd: carpeta, stdio: 'ignore' });
    } catch {
        execFileSync(
            'docker',
            [
                'run',
                '--rm',
                '-v',
                `${carpeta}:/w`,
                '-w',
                '/w',
                'jrottenberg/ffmpeg:7.1-ubuntu',
                ...args,
            ],
            { stdio: 'ignore' },
        );
    }
}

void (async () => {
    const cuadros = path.join(SALIDA, '_cuadros');
    fs.rmSync(cuadros, { recursive: true, force: true });
    fs.mkdirSync(cuadros, { recursive: true });

    const navegador = await chromium.launch();
    const page = await navegador.newPage({
        viewport: { width: 1080, height: 1920 },
    });
    const url =
        'file:///' +
        path.resolve(__dirname, 'regimenes.html').split(path.sep).join('/') +
        '?clean=1';
    await page.goto(url);
    await page.waitForFunction(
        () => window.OPENER && window.OPENER.ready,
        null,
        { timeout: 60000 },
    );
    const duracion = await page.evaluate(() => window.OPENER.DURATION);
    // Con voz, el video dura lo que la voz y el timeline se recorre según PAUTA_VOZ.
    const largo = CON_VOZ ? PAUTA_VOZ[PAUTA_VOZ.length - 1][1] : duracion;
    const total = Math.ceil(largo * FPS);

    for (let i = 0; i < total; i++) {
        await page.evaluate(
            async (t) => {
                window.OPENER.seek(t);
                await new Promise((r) =>
                    requestAnimationFrame(() => requestAnimationFrame(r)),
                );
            },
            CON_VOZ ? aTimeline(i / FPS) : i / FPS,
        );
        await page.screenshot({
            path: path.join(cuadros, `f${String(i).padStart(5, '0')}.jpg`),
            type: 'jpeg',
            quality: 92,
        });
        if (i % FPS === 0) process.stdout.write(`\r${i}/${total}`);
    }
    await navegador.close();

    const mp4 = CON_VOZ ? `${NOMBRE}-mudo-sincronizado.mp4` : `${NOMBRE}.mp4`;
    ffmpeg(
        [
            '-y',
            '-framerate',
            String(FPS),
            '-i',
            '_cuadros/f%05d.jpg',
            '-c:v',
            'libx264',
            '-preset',
            'slow',
            '-crf',
            '18',
            '-pix_fmt',
            'yuv420p',
            '-movflags',
            '+faststart',
            mp4,
        ],
        SALIDA,
    );
    console.log('\n✓', path.join(SALIDA, mp4));

    // Voz en off: se une tal cual (el guion de ElevenLabs está pensado para ~38 s); si es más corta, el video sigue sin voz.
    if (process.env.VOZ) {
        const conVoz = `${NOMBRE}-con-voz.mp4`;
        ffmpeg(
            [
                '-y',
                '-i',
                mp4,
                '-i',
                process.env.VOZ,
                '-map',
                '0:v',
                '-map',
                '1:a',
                '-c:v',
                'copy',
                '-c:a',
                'aac',
                '-b:a',
                '192k',
                '-movflags',
                '+faststart',
                conVoz,
            ],
            SALIDA,
        );
        console.log('✓', path.join(SALIDA, conVoz));
    }
    fs.rmSync(cuadros, { recursive: true, force: true });
})();
