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
    const total = Math.ceil(duracion * FPS);

    for (let i = 0; i < total; i++) {
        await page.evaluate(async (t) => {
            window.OPENER.seek(t);
            await new Promise((r) =>
                requestAnimationFrame(() => requestAnimationFrame(r)),
            );
        }, i / FPS);
        await page.screenshot({
            path: path.join(cuadros, `f${String(i).padStart(5, '0')}.jpg`),
            type: 'jpeg',
            quality: 92,
        });
        if (i % FPS === 0) process.stdout.write(`\r${i}/${total}`);
    }
    await navegador.close();

    const mp4 = `${NOMBRE}.mp4`;
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
