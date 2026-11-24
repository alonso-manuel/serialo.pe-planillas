// Monta el video comparativo para la web: las dos tomas (comparativo-excel.webm y comparativo-serialo.webm) lado a
// lado sobre un fondo, aceleradas por igual (VELOCIDAD), y la tarjeta final con las cifras de cada lado.
// Uso: node comparativo-montar.js   → videos/comparativo-excel-vs-serialo.mp4 (y una copia ligera para sitio/public)
const { chromium } = require('playwright');
const { execFileSync } = require('child_process');
const fs = require('fs');
const path = require('path');

const SALIDA = process.env.SALIDA || path.resolve(__dirname, '../../videos');
const VELOCIDAD = Number(process.env.VELOCIDAD || 3);
const FINAL_S = 7;

/** Duración en segundos (ffprobe local o el de la imagen de Docker). */
function duracion(archivo) {
    const args = ['-v', 'error', '-show_entries', 'format=duration', '-of', 'csv=p=0', archivo];
    try {
        return Number(execFileSync('ffprobe', args, { cwd: SALIDA }).toString().trim());
    } catch (e) {
        if (e.code !== 'ENOENT') throw e;
        return Number(
            execFileSync('docker', ['run', '--rm', '--entrypoint', 'ffprobe', '-v', `${SALIDA}:/w`, '-w', '/w', 'jrottenberg/ffmpeg:7.1-ubuntu', ...args])
                .toString()
                .trim(),
        );
    }
}

function ffmpeg(args) {
    try {
        execFileSync('ffmpeg', args, { cwd: SALIDA, stdio: 'ignore' });
    } catch (e) {
        if (e.code !== 'ENOENT') throw e;
        execFileSync('docker', ['run', '--rm', '-v', `${SALIDA}:/w`, '-w', '/w', 'jrottenberg/ffmpeg:7.1-ubuntu', ...args], {
            stdio: 'ignore',
        });
    }
}

void (async () => {
    const leer = (n) => JSON.parse(fs.readFileSync(path.join(SALIDA, n), 'utf8'));
    const e = leer('comparativo-excel-cifras.json');
    const s = leer('comparativo-serialo-cifras.json');

    const b = await chromium.launch();
    const p = await b.newPage({ viewport: { width: 1920, height: 1080 } });
    const base = 'file:///' + path.resolve(__dirname, 'comparativo-marco.html').split(path.sep).join('/');
    await p.goto(base + '?vista=marco');
    await p.waitForTimeout(1500);
    await p.screenshot({ path: path.join(SALIDA, '_marco.png') });
    const q = new URLSearchParams({
        vista: 'final', ed: e.datos, ec: e.clics, ep: e.copias, ea: e.archivos,
        sd: s.datos, sc: s.clics, sp: s.copias, sa: s.archivos,
    });
    await p.goto(`${base}?${q}`);
    await p.waitForTimeout(1500);
    await p.screenshot({ path: path.join(SALIDA, '_final.png') });
    await b.close();

    // Las dos tomas a 940×529 (16:9) sobre el fondo; la de Serialo, al terminar, se queda en su último cuadro.
    const filtro = [
        `[1:v]setpts=PTS/${VELOCIDAD},fps=30,scale=940:529[ex]`,
        `[2:v]setpts=PTS/${VELOCIDAD},fps=30,scale=940:529,tpad=stop_mode=clone:stop_duration=600[se]`,
        `[0:v][ex]overlay=10:250:shortest=0[a]`,
        `[a][se]overlay=970:250:eof_action=repeat[b]`,
        `[b]trim=duration=__DUR__,setpts=PTS-STARTPTS,format=yuv420p[comp]`,
        `[3:v]trim=duration=${FINAL_S},setpts=PTS-STARTPTS,format=yuv420p[fin]`,
        `[comp][fin]concat=n=2:v=1:a=0[v]`,
    ];
    // Duración de la parte comparativa: la toma de Excel acelerada.
    const durExcel = duracion('comparativo-excel.webm');
    const dur = (durExcel / VELOCIDAD + 1.5).toFixed(2);

    ffmpeg([
        '-y',
        '-loop', '1', '-framerate', '30', '-i', '_marco.png',
        '-i', 'comparativo-excel.webm',
        '-i', 'comparativo-serialo.webm',
        '-loop', '1', '-framerate', '30', '-i', '_final.png',
        '-filter_complex', filtro.join(';').replace('__DUR__', dur),
        '-map', '[v]', '-r', '30', '-c:v', 'libx264', '-preset', 'slow', '-crf', '20', '-movflags', '+faststart',
        'comparativo-excel-vs-serialo.mp4',
    ]);
    // Versión ligera para la web (720p).
    ffmpeg([
        '-y', '-i', 'comparativo-excel-vs-serialo.mp4', '-vf', 'scale=1280:-2', '-c:v', 'libx264', '-preset', 'slow',
        '-crf', '27', '-movflags', '+faststart', 'comparativo-web.mp4',
    ]);
    // Póster (primer cuadro de la tarjeta final) para la web.
    fs.copyFileSync(path.join(SALIDA, '_final.png'), path.join(SALIDA, 'comparativo-poster.png'));
    fs.rmSync(path.join(SALIDA, '_marco.png'));
    fs.rmSync(path.join(SALIDA, '_final.png'));
    console.log(`✓ comparativo-excel-vs-serialo.mp4 (${dur} s + ${FINAL_S} s de cierre) y comparativo-web.mp4`);
})();
