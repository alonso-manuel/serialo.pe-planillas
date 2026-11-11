// Genera docs/manual-usuario.pdf desde docs/manual-usuario.md (A4, portada, índice enlazado, número de página).
// Uso: cd tools/manual && npm install && npm run pdf
import { readFileSync, writeFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { Marked } from 'marked';
import { chromium } from 'playwright';

const raiz = resolve(dirname(fileURLToPath(import.meta.url)), '../..');
const origen = resolve(raiz, 'docs/manual-usuario.md');
const destino = resolve(raiz, 'docs/manual-usuario.pdf');

// Anclas como las de GitHub, para que funcionen los enlaces internos del índice («#36-firma-digital»).
const ancla = (texto) =>
    texto
        .toLowerCase()
        .replace(/<[^>]+>/g, '')
        .replace(/[^\p{L}\p{N}\s-]/gu, '')
        .trim()
        .replace(/\s/g, '-');

const marked = new Marked({
    gfm: true,
    renderer: {
        heading({ tokens, depth, text }) {
            const html = this.parser.parseInline(tokens);
            const clase = depth === 2 ? ' class="seccion"' : '';
            return `<h${depth} id="${ancla(text)}"${clase}>${html}</h${depth}>\n`;
        },
    },
});

let md = readFileSync(origen, 'utf8');
md = md.replace(/^# .*\n/, ''); // el título va en la portada
md = md.replace(/^---\s*$/gm, ''); // los separadores se reemplazan por saltos de página

const meses = [
    'enero',
    'febrero',
    'marzo',
    'abril',
    'mayo',
    'junio',
    'julio',
    'agosto',
    'septiembre',
    'octubre',
    'noviembre',
    'diciembre',
];
const hoy = new Date();
const fecha = `${meses[hoy.getMonth()]} de ${hoy.getFullYear()}`;

const html = `<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Serialo Planillas — Manual de usuario</title>
<style>
  @page { size: A4; margin: 22mm 18mm 20mm 18mm; }
  :root { --tinta: #1f2937; --suave: #6b7280; --linea: #e5e7eb; --marca: #1e3a8a; --fondo: #f3f4f6; }
  * { box-sizing: border-box; }
  body { font-family: "Segoe UI", Arial, sans-serif; font-size: 10.5pt; line-height: 1.5; color: var(--tinta); margin: 0; }
  .portada { height: 250mm; display: flex; flex-direction: column; justify-content: center; page-break-after: always; }
  .portada .marca { color: var(--marca); font-weight: 700; letter-spacing: .08em; text-transform: uppercase; font-size: 11pt; }
  .portada h1 { font-size: 34pt; margin: 8mm 0 2mm; line-height: 1.1; }
  .portada .sub { font-size: 16pt; color: var(--suave); margin: 0 0 14mm; }
  .portada .barra { width: 40mm; height: 3px; background: var(--marca); margin-bottom: 10mm; }
  .portada .pie { margin-top: auto; color: var(--suave); font-size: 10pt; }
  h2 { font-size: 17pt; color: var(--marca); border-bottom: 2px solid var(--marca); padding-bottom: 2mm; margin: 0 0 5mm; }
  h2.seccion { page-break-before: always; }
  h2#contenido { page-break-before: auto; margin-top: 8mm; } /* el índice va con la introducción */
  h3 { font-size: 12.5pt; margin: 7mm 0 2mm; page-break-after: avoid; }
  p, li { orphans: 3; widows: 3; }
  a { color: var(--marca); text-decoration: none; }
  code { font-family: Consolas, monospace; font-size: 9.5pt; background: var(--fondo); padding: 0 1mm; border-radius: 2px; }
  blockquote { margin: 4mm 0; padding: 3mm 4mm; border-left: 3px solid var(--marca); background: #eff6ff; }
  blockquote p { margin: 0; }
  table { width: 100%; border-collapse: collapse; margin: 3mm 0 5mm; font-size: 9.5pt; page-break-inside: auto; }
  tr { page-break-inside: avoid; }
  th { background: var(--fondo); text-align: left; font-weight: 600; }
  th, td { border: 1px solid var(--linea); padding: 1.6mm 2.2mm; vertical-align: top; }
  ul, ol { padding-left: 6mm; }
  #contenido + ol { columns: 2; column-gap: 10mm; }
</style>
</head>
<body>
  <section class="portada">
    <div class="marca">Serialo.pe</div>
    <h1>Serialo Planillas</h1>
    <p class="sub">Manual de usuario</p>
    <div class="barra"></div>
    <p>Sistema web de planillas para el Perú: planilla mensual, beneficios sociales, boletas y archivos para SUNAT y AFP.</p>
    <p class="pie">Versión de ${fecha} · Serialo.pe – Manu Dev</p>
  </section>
  ${marked.parse(md)}
</body>
</html>`;

const navegador = await chromium.launch();
const pagina = await navegador.newPage();
await pagina.setContent(html, { waitUntil: 'load' });
const pdf = await pagina.pdf({
    format: 'A4',
    printBackground: true,
    preferCSSPageSize: true,
    displayHeaderFooter: true,
    headerTemplate: '<div></div>',
    footerTemplate: `<div style="font-family: Segoe UI, Arial, sans-serif; font-size: 8pt; color: #6b7280; width: 100%; padding: 0 18mm; display: flex; justify-content: space-between;">
        <span>Serialo Planillas · Manual de usuario</span><span><span class="pageNumber"></span> / <span class="totalPages"></span></span></div>`,
    tagged: true,
    outline: true,
});
await navegador.close();
writeFileSync(destino, pdf);
console.log(`PDF generado: ${destino} (${Math.round(pdf.length / 1024)} KB)`);
