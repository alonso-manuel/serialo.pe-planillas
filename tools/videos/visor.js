// Visor superpuesto para los videos: muestra dentro de la página grabada un PDF (renderizado con pdf.js)
// o el contenido de un archivo descargado (.txt o .zip con archivos de texto).
// Chromium sin ventana no dibuja su visor de PDF en el video, por eso se renderiza aparte.
const path = require('path');
const zlib = require('zlib');

const PDFJS = path.join(__dirname, 'node_modules', 'pdfjs-dist');

/** Convierte un PDF (Buffer) en imágenes PNG (data URL), una por página, en un contexto que no se graba. */
async function pdfAImagenes(
    navegador,
    pdf,
    { paginas = 2, ancho = 1100 } = {},
) {
    const ctx = await navegador.newContext();
    const p = await ctx.newPage();
    await p.route('http://visor.local/**', (r) => {
        const ruta = new URL(r.request().url()).pathname;
        if (ruta === '/') {
            return r.fulfill({
                contentType: 'text/html',
                body: '<!doctype html><meta charset="utf-8"><body></body>',
            });
        }
        const archivo = ruta.startsWith('/standard_fonts/')
            ? path.join(PDFJS, ruta)
            : path.join(PDFJS, 'build', path.basename(ruta));
        return r.fulfill({
            path: archivo,
            contentType: ruta.endsWith('.mjs') ? 'text/javascript' : undefined,
        });
    });
    await p.goto('http://visor.local/');
    const resultado = await p.evaluate(
        async ({ b64, paginas, ancho }) => {
            const pdfjs = await import('/pdf.min.mjs');
            pdfjs.GlobalWorkerOptions.workerSrc = '/pdf.worker.min.mjs';
            const datos = Uint8Array.from(atob(b64), (c) => c.charCodeAt(0));
            const doc = await pdfjs.getDocument({
                data: datos,
                standardFontDataUrl: '/standard_fonts/',
            }).promise;
            const imagenes = [];
            for (let i = 1; i <= Math.min(paginas, doc.numPages); i++) {
                const pagina = await doc.getPage(i);
                const vista = pagina.getViewport({
                    scale: ancho / pagina.getViewport({ scale: 1 }).width,
                });
                const lienzo = document.createElement('canvas');
                lienzo.width = Math.round(vista.width);
                lienzo.height = Math.round(vista.height);
                await pagina.render({
                    canvasContext: lienzo.getContext('2d'),
                    viewport: vista,
                }).promise;
                imagenes.push(lienzo.toDataURL('image/png'));
            }
            return { imagenes, total: doc.numPages };
        },
        { b64: pdf.toString('base64'), paginas, ancho },
    );
    await ctx.close();
    return resultado;
}

/** Lee un .zip en memoria: [{ nombre, datos: Buffer }]. Solo lo necesario (stored y deflate). */
function leerZip(buf) {
    let fin = buf.length - 22;
    while (fin >= 0 && buf.readUInt32LE(fin) !== 0x06054b50) fin--;
    if (fin < 0) throw new Error('No es un zip');
    const total = buf.readUInt16LE(fin + 10);
    let pos = buf.readUInt32LE(fin + 16);
    const archivos = [];
    for (let i = 0; i < total; i++) {
        const metodo = buf.readUInt16LE(pos + 10);
        const comprimido = buf.readUInt32LE(pos + 20);
        const largoNombre = buf.readUInt16LE(pos + 28);
        const largoExtra = buf.readUInt16LE(pos + 30);
        const largoComentario = buf.readUInt16LE(pos + 32);
        const local = buf.readUInt32LE(pos + 42);
        const nombre = buf.toString('utf8', pos + 46, pos + 46 + largoNombre);
        const inicio =
            local +
            30 +
            buf.readUInt16LE(local + 26) +
            buf.readUInt16LE(local + 28);
        const crudo = buf.subarray(inicio, inicio + comprimido);
        archivos.push({
            nombre,
            datos: metodo === 8 ? zlib.inflateRawSync(crudo) : crudo,
        });
        pos += 46 + largoNombre + largoExtra + largoComentario;
    }
    return archivos;
}

const CSS = `
#demo-visor{position:fixed;inset:0;z-index:2147483640;background:rgba(15,23,42,.55);display:flex;align-items:center;justify-content:center;
  opacity:0;transition:opacity .35s;font-family:system-ui,Segoe UI,sans-serif}
#demo-visor.on{opacity:1}
#demo-visor .marco{width:860px;max-width:92vw;height:600px;background:#525659;border-radius:12px;overflow:hidden;display:flex;flex-direction:column;
  box-shadow:0 20px 60px rgba(0,0,0,.4);transform:translateY(12px);transition:transform .35s}
#demo-visor.on .marco{transform:none}
#demo-visor .barra{background:#323639;color:#fff;font-size:14px;padding:10px 16px;display:flex;gap:10px;align-items:center}
#demo-visor .barra b{font-weight:600}
#demo-visor .barra span{color:#bdc1c6;margin-left:auto;font-size:13px}
#demo-visor .cuerpo{flex:1;overflow:auto;padding:18px;display:flex;flex-direction:column;align-items:center;gap:14px;scroll-behavior:smooth}
#demo-visor .cuerpo img{width:100%;max-width:800px;background:#fff;box-shadow:0 2px 10px rgba(0,0,0,.35)}
#demo-visor.texto .marco{background:#fff}
#demo-visor.texto .cuerpo{flex-direction:row;align-items:stretch;padding:0;gap:0}
#demo-visor ul{list-style:none;margin:0;padding:8px;width:220px;flex-shrink:0;border-right:1px solid #e5e7eb;background:#f8fafc;overflow:auto}
#demo-visor li{font:500 13px ui-monospace,Consolas,monospace;padding:7px 10px;border-radius:6px;color:#334155;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
#demo-visor li.sel{background:#2558a8;color:#fff}
#demo-visor pre{margin:0;flex:1;overflow:auto;padding:14px 16px;font:13px/1.6 ui-monospace,Consolas,monospace;color:#0f172a;white-space:pre}
`;

/** Abre el visor en la página grabada. `contenido`: { imagenes } (PDF) o { archivos: [{nombre, texto}] }. */
async function abrirVisor(page, titulo, detalle, contenido) {
    await page.evaluate(
        ({ css, titulo, detalle, contenido }) => {
            document.getElementById('demo-visor')?.remove();
            if (!document.getElementById('demo-visor-css')) {
                const s = document.createElement('style');
                s.id = 'demo-visor-css';
                s.textContent = css;
                document.head.appendChild(s);
            }
            const v = document.createElement('div');
            v.id = 'demo-visor';
            const marco = document.createElement('div');
            marco.className = 'marco';
            const barra = document.createElement('div');
            barra.className = 'barra';
            barra.innerHTML = '<b></b><span></span>';
            barra.querySelector('b').textContent = titulo;
            barra.querySelector('span').textContent = detalle || '';
            const cuerpo = document.createElement('div');
            cuerpo.className = 'cuerpo';
            if (contenido.imagenes) {
                for (const src of contenido.imagenes) {
                    const img = document.createElement('img');
                    img.src = src;
                    cuerpo.appendChild(img);
                }
            } else {
                v.classList.add('texto');
                const lista = document.createElement('ul');
                const pre = document.createElement('pre');
                contenido.archivos.forEach((a, i) => {
                    const li = document.createElement('li');
                    li.textContent = a.nombre;
                    li.dataset.i = i;
                    lista.appendChild(li);
                });
                window.__visorArchivo = (i) => {
                    lista
                        .querySelectorAll('li')
                        .forEach((li) =>
                            li.classList.toggle(
                                'sel',
                                Number(li.dataset.i) === i,
                            ),
                        );
                    pre.textContent = contenido.archivos[i].texto;
                };
                if (contenido.archivos.length > 1) cuerpo.appendChild(lista);
                cuerpo.appendChild(pre);
                window.__visorArchivo(0);
            }
            marco.append(barra, cuerpo);
            v.appendChild(marco);
            document.body.appendChild(v);
            requestAnimationFrame(() => v.classList.add('on'));
        },
        { css: CSS, titulo, detalle, contenido },
    );
}

module.exports = { pdfAImagenes, leerZip, abrirVisor };
