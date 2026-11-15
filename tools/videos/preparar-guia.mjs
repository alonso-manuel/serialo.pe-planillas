// Prepara los videos de la Guía (menú Ayuda → Guía) desde los originales editados.
// - Nombre: «Panel administrativo.mp4» → «panel-administrativo.mp4» (la clave de App\Support\GuiaVideos).
// - Formato web: 1080p, H.264 (CRF 23, texto de pantalla nítido), AAC 128k y «faststart» (empieza a reproducirse
//   sin descargar todo). Un minuto pasa de ~70 MB a ~8 MB.
// Uso: node preparar-guia.mjs [origen] [destino]
//      por defecto: videos/Videosparalaguia → guia/   (luego se sube guia/ a /opt/serialo/guia del servidor)
// Usa ffmpeg local o, si no está, la imagen de Docker. Si el destino ya existe y es más nuevo, lo salta.
import { execFileSync } from 'node:child_process';
import { existsSync, mkdirSync, readdirSync, statSync } from 'node:fs';
import { basename, dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const raiz = resolve(dirname(fileURLToPath(import.meta.url)), '../..');
const origen = resolve(
    process.argv[2] ?? resolve(raiz, 'videos/Videosparalaguia'),
);
const destino = resolve(process.argv[3] ?? resolve(raiz, 'guia'));
mkdirSync(destino, { recursive: true });

const clave = (nombre) =>
    basename(nombre, '.mp4')
        .normalize('NFD')
        .replace(/[̀-ͯ]/g, '')
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-|-$/g, '');

/** ffmpeg local o, si no está instalado, el de la imagen de Docker con origen y destino montados. */
function ffmpeg(entrada, salida) {
    const args = (e, s) => [
        '-y',
        '-loglevel',
        'error',
        '-i',
        e,
        '-vf',
        "scale='min(1920,iw)':-2",
        '-c:v',
        'libx264',
        '-preset',
        'slow',
        '-crf',
        '23',
        '-pix_fmt',
        'yuv420p',
        '-r',
        '30',
        '-c:a',
        'aac',
        '-b:a',
        '128k',
        '-movflags',
        '+faststart',
        s,
    ];
    try {
        execFileSync('ffmpeg', args(entrada, salida), { stdio: 'inherit' });
    } catch (error) {
        if (error.code !== 'ENOENT') {
            throw error;
        }
        execFileSync(
            'docker',
            [
                'run',
                '--rm',
                '-v',
                `${dirname(entrada)}:/origen:ro`,
                '-v',
                `${dirname(salida)}:/destino`,
                'jrottenberg/ffmpeg:7.1-ubuntu',
                ...args(
                    `/origen/${basename(entrada)}`,
                    `/destino/${basename(salida)}`,
                ),
            ],
            { stdio: 'inherit' },
        );
    }
}

const archivos = readdirSync(origen).filter((f) =>
    f.toLowerCase().endsWith('.mp4'),
);
let antes = 0;
let despues = 0;
for (const archivo of archivos) {
    const entrada = resolve(origen, archivo);
    const salida = resolve(destino, `${clave(archivo)}.mp4`);
    if (
        !existsSync(salida) ||
        statSync(salida).mtimeMs < statSync(entrada).mtimeMs
    ) {
        process.stdout.write(`… ${archivo} → ${basename(salida)}\n`);
        ffmpeg(entrada, salida);
    }
    antes += statSync(entrada).size;
    despues += statSync(salida).size;
}
const mb = (b) => `${(b / 1024 / 1024).toFixed(0)} MB`;
console.log(
    `✓ ${archivos.length} videos en ${destino}: ${mb(antes)} → ${mb(despues)}`,
);
