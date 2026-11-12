// Utilidades para grabar los videos de demo con Playwright (sin audio: subtítulos + cursor visible).
const { chromium } = require('playwright');
const { pdfAImagenes, leerZip, abrirVisor } = require('./visor');
const fs = require('fs');
const path = require('path');

const BASE = process.env.BASE_URL || 'http://127.0.0.1:8000';
const SALIDA = process.env.SALIDA || path.resolve(__dirname, '../../videos');
// Tamaño de la grabación: 1280×720 en los tutoriales; los anuncios verticales usan otra toma (ANCHO/ALTO).
const TAM = {
    width: Number(process.env.ANCHO) || 1280,
    height: Number(process.env.ALTO) || 720,
};

// Capa que se inyecta en cada página: subtítulo inferior, título de escena y cursor.
const CAPA = `
(() => {
  if (window.__demo) return;
  window.__demo = true;
  const css = document.createElement('style');
  css.textContent = \`
    #demo-cursor{position:fixed;z-index:2147483647;width:22px;height:22px;margin:-11px 0 0 -11px;border-radius:50%;
      background:rgba(37,99,235,.35);border:2px solid #2563eb;pointer-events:none;transition:left .55s cubic-bezier(.4,0,.2,1),top .55s cubic-bezier(.4,0,.2,1),transform .15s}
    #demo-cursor.clic{transform:scale(.6);background:rgba(37,99,235,.7)}
    #demo-sub{position:fixed;z-index:2147483646;left:50%;bottom:22px;transform:translateX(-50%);max-width:880px;
      background:rgba(15,23,42,.88);color:#fff;font:500 19px/1.4 system-ui,Segoe UI,sans-serif;padding:10px 20px;border-radius:12px;
      text-align:center;box-shadow:0 8px 30px rgba(0,0,0,.25);opacity:0;transition:opacity .3s;pointer-events:none}
    #demo-sub.on{opacity:1}
    #demo-esc{position:fixed;z-index:2147483646;right:18px;top:14px;background:#2563eb;color:#fff;font:600 13px system-ui,sans-serif;
      padding:5px 12px;border-radius:999px;opacity:0;transition:opacity .3s;pointer-events:none}
    #demo-esc.on{opacity:.95}\`;
  const montar = () => {
    document.head.appendChild(css);
    for (const id of ['demo-cursor','demo-sub','demo-esc']) {
      const d = document.createElement('div'); d.id = id; document.body.appendChild(d);
    }
    const c = document.getElementById('demo-cursor');
    const p = JSON.parse(sessionStorage.getItem('demo-pos') || '[640,360]');
    c.style.left = p[0] + 'px'; c.style.top = p[1] + 'px';
    const s = sessionStorage.getItem('demo-sub'); if (s) window.__sub(s, true);
    const e = sessionStorage.getItem('demo-esc'); if (e) window.__esc(e);
  };
  window.__sub = (t, sinAnim) => {
    const s = document.getElementById('demo-sub'); if (!s) return;
    sessionStorage.setItem('demo-sub', t || '');
    if (!t) { s.classList.remove('on'); return; }
    s.textContent = t; s.classList.add('on');
  };
  window.__esc = (t) => {
    const e = document.getElementById('demo-esc'); if (!e) return;
    sessionStorage.setItem('demo-esc', t || '');
    e.textContent = t || ''; e.classList.toggle('on', !!t);
  };
  window.__mover = (x, y) => {
    const c = document.getElementById('demo-cursor'); if (!c) return;
    c.style.left = x + 'px'; c.style.top = y + 'px';
    sessionStorage.setItem('demo-pos', JSON.stringify([x, y]));
  };
  window.__clic = () => {
    const c = document.getElementById('demo-cursor'); if (!c) return;
    c.classList.add('clic'); setTimeout(() => c.classList.remove('clic'), 180);
  };
  if (document.body) montar(); else document.addEventListener('DOMContentLoaded', montar);
})();
`;

const espera = (ms) => new Promise((r) => setTimeout(r, ms));

async function asegurarCapa(page) {
    await page.evaluate(CAPA).catch(() => {});
}

function crearDemo(page, nombre = 'video', navegador = null) {
    let n = 0;
    const d = {
        page,
        espera,
        /** Subtítulo (texto de la narración resumido). */
        async sub(texto, ms = 2600) {
            await asegurarCapa(page);
            await page.evaluate((t) => window.__sub && window.__sub(t), texto);
            if (process.env.CAPS) {
                await espera(400);
                fs.mkdirSync(path.join(SALIDA, 'caps'), { recursive: true });
                await page.screenshot({
                    path: path.join(
                        SALIDA,
                        'caps',
                        `${nombre}-${String(++n).padStart(2, '0')}.png`,
                    ),
                });
            }
            if (ms) await espera(ms);
        },
        async quitarSub() {
            await page.evaluate(() => window.__sub && window.__sub(''));
        },
        async escena(texto) {
            await asegurarCapa(page);
            await page.evaluate((t) => window.__esc && window.__esc(t), texto);
        },
        async ir(ruta) {
            await page.goto(BASE + ruta);
            await page.waitForLoadState('networkidle');
            await asegurarCapa(page);
        },
        async mover(loc) {
            const l = typeof loc === 'string' ? page.locator(loc).first() : loc;
            await l.scrollIntoViewIfNeeded();
            // Que no quede detrás del subtítulo (franja inferior).
            const previo = await l.boundingBox();
            if (previo && previo.y + previo.height > TAM.height - 130) {
                await l.evaluate((el) =>
                    el.scrollIntoView({ block: 'center' }),
                );
                await espera(300);
            }
            const b = await l.boundingBox();
            if (!b) return;
            const x = Math.round(b.x + Math.min(b.width / 2, 60));
            const y = Math.round(b.y + b.height / 2);
            await asegurarCapa(page);
            await page.evaluate(([x, y]) => window.__mover(x, y), [x, y]);
            await page.mouse.move(x, y, { steps: 8 });
            await espera(600);
        },
        async clic(loc, pausa = 700) {
            const l = typeof loc === 'string' ? page.locator(loc).first() : loc;
            await d.mover(l);
            await page.evaluate(() => window.__clic && window.__clic());
            await l.click();
            await espera(pausa);
            await page.waitForLoadState('networkidle').catch(() => {});
            await asegurarCapa(page);
        },
        async escribir(loc, texto, { limpiar = true, retraso = 55 } = {}) {
            const l = typeof loc === 'string' ? page.locator(loc).first() : loc;
            await d.clic(l, 200);
            if (limpiar) await l.fill('');
            await l.pressSequentially(texto, { delay: retraso });
            await espera(250);
        },
        /** Select de reka-ui: abre el trigger y elige la opción por texto. */
        async elegir(trigger, opcion) {
            await d.clic(trigger, 400);
            await d.clic(
                page.getByRole('option', { name: opcion }).first(),
                500,
            );
        },
        async desplazar(px, ms = 900) {
            await page.mouse.wheel(0, px);
            await espera(ms);
        },
        /** Señala un enlace a un PDF y lo muestra en el visor (sin abrir pestaña nueva, que no se grabaría). */
        async verPdf(loc, { titulo, paginas = 2 } = {}) {
            const l = typeof loc === 'string' ? page.locator(loc).first() : loc;
            await d.mover(l);
            await page.evaluate(() => window.__clic && window.__clic());
            const href = await l.evaluate(
                (a) => a.closest('a')?.href ?? a.href,
            );
            const respuesta = await page.context().request.get(href);
            const pdf = await respuesta.body();
            const nombre =
                titulo ??
                /filename="?([^";]+)/.exec(
                    respuesta.headers()['content-disposition'] ?? '',
                )?.[1] ??
                'documento.pdf';
            const { imagenes, total } = await pdfAImagenes(navegador, pdf, {
                paginas,
            });
            await abrirVisor(
                page,
                nombre,
                total === 1 ? '1 página' : `${total} páginas`,
                { imagenes },
            );
            await espera(900);
        },
        /** Hace clic en un enlace de descarga y devuelve { nombre, datos }. */
        async descargar(loc, pausa = 900) {
            const l = typeof loc === 'string' ? page.locator(loc).first() : loc;
            await d.mover(l);
            await page.evaluate(() => window.__clic && window.__clic());
            const [descarga] = await Promise.all([
                page.waitForEvent('download'),
                l.click(),
            ]);
            const datos = fs.readFileSync(await descarga.path());
            await espera(pausa);
            return { nombre: descarga.suggestedFilename(), datos };
        },
        /** Muestra en el visor un archivo descargado (.zip con textos o un texto suelto). */
        async verArchivo({ nombre, datos }, { codificacion = 'latin1' } = {}) {
            const archivos = nombre.toLowerCase().endsWith('.zip')
                ? leerZip(datos).map((a) => ({
                      nombre: a.nombre,
                      texto: a.datos.toString(codificacion),
                  }))
                : [{ nombre, texto: datos.toString(codificacion) }];
            const detalle =
                archivos.length > 1 ? `${archivos.length} archivos` : '';
            await abrirVisor(page, nombre, detalle, { archivos });
            await espera(900);
        },
        async elegirArchivo(i, ms = 1800) {
            await page.evaluate(
                (i) => window.__visorArchivo && window.__visorArchivo(i),
                i,
            );
            await espera(ms);
        },
        async desplazarVisor(px, ms = 1200) {
            await page.evaluate((px) => {
                const c = document.querySelector('#demo-visor .cuerpo');
                if (c) c.scrollBy({ top: px, behavior: 'smooth' });
            }, px);
            await espera(ms);
        },
        async cerrarVisor(ms = 500) {
            await page.evaluate(() => {
                const v = document.getElementById('demo-visor');
                if (!v) return;
                v.classList.remove('on');
                setTimeout(() => v.remove(), 350);
            });
            await espera(ms);
        },
    };
    return d;
}

async function iniciarSesion(
    page,
    email = 'demo@serialo.pe',
    clave = 'demo1234',
) {
    await page.goto(BASE + '/login');
    await page.fill('input[name=email]', email);
    await page.fill('input[name=password]', clave);
    await page.click('button[type=submit]');
    await page.waitForURL((u) => !u.pathname.startsWith('/login'));
}

/**
 * Graba un video: `nombre` (sin extensión) y la función con el recorrido.
 * `sesion`: email del usuario con sesión iniciada (null = sin sesión, para el video de login).
 */
async function grabar(
    nombre,
    recorrido,
    { sesion = 'demo@serialo.pe', empresa = null } = {},
) {
    fs.mkdirSync(SALIDA, { recursive: true });
    const navegador = await chromium.launch();
    let estado;
    if (sesion) {
        const ctx = await navegador.newContext({ viewport: TAM });
        const p = await ctx.newPage();
        await iniciarSesion(p, sesion);
        if (empresa) {
            await p.goto(BASE + '/trabajadores');
            await cambiarEmpresa(p, empresa);
        }
        estado = await ctx.storageState();
        await ctx.close();
    }
    const tmp = path.join(SALIDA, '_tmp');
    const contexto = await navegador.newContext({
        viewport: TAM,
        storageState: estado,
        recordVideo: { dir: tmp, size: TAM },
        locale: 'es-PE',
        colorScheme: 'light',
    });
    await contexto.addInitScript(CAPA);
    const page = await contexto.newPage();
    const demo = crearDemo(page, nombre, navegador);
    const inicio = Date.now();
    try {
        await recorrido(demo);
        await espera(1200);
    } catch (e) {
        await page
            .screenshot({ path: path.join(SALIDA, `${nombre}-ERROR.png`) })
            .catch(() => {});
        console.error(`✗ ${nombre}:`, e.message);
        process.exitCode = 1;
    }
    const video = page.video();
    await contexto.close();
    const destino = path.join(SALIDA, `${nombre}.webm`);
    await video.saveAs(destino);
    await video.delete();
    await navegador.close();
    console.log(
        `✓ ${nombre}.webm (${Math.round((Date.now() - inicio) / 1000)} s)`,
    );
}

/** Cambia la empresa activa con el selector del menú lateral (sin grabar). */
async function cambiarEmpresa(page, nombre) {
    await page
        .locator('[data-sidebar="menu-button"]')
        .filter({ hasText: 'RUC' })
        .first()
        .click();
    await page
        .getByRole('menuitem', { name: new RegExp(nombre) })
        .first()
        .click();
    await page.waitForLoadState('networkidle');
}

module.exports = { grabar, BASE, espera, cambiarEmpresa };
