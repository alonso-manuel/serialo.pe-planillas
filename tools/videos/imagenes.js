// Imagen fija para la prueba A/B de Meta Ads (frente al video V1). Ver docs/videos/meta-ads.md.
// 1) Captura la planilla calculada de la demo (datos ficticios) en alta resolución.
// 2) La monta en imagen-v1.html y exporta PNG en 4:5 (feed) y 1:1; también la imagen de planes (imagen-planes.html).
// Uso: BASE_URL=http://127.0.0.1:8000 node imagenes.js   (base de demo)
//      SOLO_MONTAJE=1 node imagenes.js                    (reusa la captura)
const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');
const { cambiarEmpresa } = require('./lib');

const BASE = process.env.BASE_URL || 'http://127.0.0.1:8000';
const SALIDA = process.env.SALIDA || path.resolve(__dirname, '../../videos');
const archivo = (n) => path.join(SALIDA, n);
const aUrl = (ruta) => 'file:///' + ruta.split(path.sep).join('/');

async function capturar(navegador) {
    const p = await navegador.newPage({
        viewport: { width: 1280, height: 800 },
        deviceScaleFactor: 2,
    });
    await p.goto(BASE + '/login');
    await p.fill('input[name=email]', 'demo@serialo.pe');
    await p.fill('input[name=password]', 'demo1234');
    await p.click('button[type=submit]');
    await p.waitForURL((u) => !u.pathname.startsWith('/login'));
    await p.goto(BASE + '/periodos');
    await cambiarEmpresa(p, 'Distribuidora');
    await p
        .getByRole('link', { name: /Octubre 2026/ })
        .first()
        .click();
    await p.waitForURL((u) => /periodos.[0-9]+/.test(u.pathname));
    await p.waitForLoadState('networkidle');
    await p.waitForTimeout(800);
    // Solo la tabla de la planilla: trabajadores, días y neto (sin menú lateral ni avisos).
    await p.screenshot({
        path: archivo('imagen-v1-captura.png'),
        clip: { x: 286, y: 318, width: 994, height: 400 },
    });
    await p.close();
}

void (async () => {
    fs.mkdirSync(SALIDA, { recursive: true });
    const navegador = await chromium.launch();
    if (
        !process.env.SOLO_MONTAJE ||
        !fs.existsSync(archivo('imagen-v1-captura.png'))
    ) {
        await capturar(navegador);
    }
    const captura = encodeURIComponent(aUrl(archivo('imagen-v1-captura.png')));
    // [archivo de salida, diseño, alto]: anuncio con captura del sistema y planes mensuales (docs/producto.md).
    for (const [nombre, html, alto] of [
        ['anuncio-imagen-v1-4x5', 'imagen-v1.html', 1350],
        ['anuncio-imagen-v1-1x1', 'imagen-v1.html', 1080],
        ['planes-4x5', 'imagen-planes.html', 1350],
        ['planes-1x1', 'imagen-planes.html', 1080],
    ]) {
        const p = await navegador.newPage({
            viewport: { width: 1080, height: alto },
        });
        await p.goto(
            aUrl(path.resolve(__dirname, html)) +
                `?captura=${captura}&alto=${alto}`,
        );
        await p.waitForFunction(() => window.listo === true);
        await p.screenshot({ path: archivo(`${nombre}.png`) });
        await p.close();
        console.log('✓', archivo(`${nombre}.png`));
    }
    await navegador.close();
})();
