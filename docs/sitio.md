# Web pública (sitio/)

Página de producto de Serialo Planillas: qué hace, para quién, seguridad, preguntas frecuentes y el formulario
«Pide tu cuenta de prueba». Vive en `sitio/` del mismo repositorio, con su propio `package.json`.

| Pieza                | Qué es                                                                                                     |
| -------------------- | ---------------------------------------------------------------------------------------------------------- |
| `sitio/`             | Astro 7 estático + Tailwind 4. Páginas en `src/pages` (inicio, gracias, privacidad, 404).                  |
| `sitio/src/datos.ts` | Número de WhatsApp y mensaje por defecto de los botones.                                                   |
| Formulario           | Envía a `POST /solicitud` (mismo dominio). Laravel lo guarda en la tabla central `solicitudes`.            |
| Panel central        | Pestaña **Solicitudes**: datos del interesado, botón de WhatsApp, marcar atendida; contador de pendientes. |
| Aviso                | Correo a cada superadmin por cada solicitud (`NuevaSolicitud`, en la cola).                                |

## Desarrollo

```bash
cd sitio
npm install
npm run dev        # http://localhost:4321 (el formulario no envía: no hay Laravel detrás)
npm run build      # genera sitio/dist
```

Reglas de contenido:

- Solo funciones que el sistema ya hace (revisar `docs/roadmap.md`). Nada de «importa sin errores en PLAME» mientras
  esa prueba siga pendiente; construcción civil y minería se dicen como «todavía no».
- Sin precios (decisión comercial). Sin testimonios ni logos de clientes hasta tenerlos reales y con permiso.
- Montos de ejemplo coherentes entre sí y con datos ficticios.
- No copiar textos ni diseños de otros productos (regla 9 de CLAUDE.md).

## Producción

- El Dockerfile compila la web en la etapa `sitio` y la copia a la imagen `web` (`/var/www/sitio`).
- Nginx (`deploy/docker/nginx.conf`) la entrega en los dominios de **dos etiquetas** (`impulso.cfd`, luego
  `serialo.pe`); `www.` redirige al dominio sin `www`. Los estudios y el panel tienen tres etiquetas y siguen igual.
- Laravel solo atiende `POST /solicitud` en `planillas.dominio_sitio` (`PLANILLAS_DOMINIO_SITIO`, por defecto
  `impulso.cfd`), sin sesión ni CSRF; lo protegen `throttle:5,1` y un campo trampa (`sitio_web`).
- Caddy emite el certificado del dominio raíz y de `www` (los permite `/caddy/permitido`).
- DNS: el dominio raíz (`@`) y `www` deben apuntar a la IP del VPS (registros A), igual que `*`.

Al pasar a `serialo.pe`: `PLANILLAS_DOMINIO_SITIO=serialo.pe`, `docker compose build --build-arg SITIO_URL=https://serialo.pe`
y actualizar `sitio/public/robots.txt`.

## Video «Excel vs. Serialo» (sección `#comparativo`)

Recreación con una empresa **ficticia** (`tools/videos/comparativo-caso.json`: Inversiones Ejemplo S.A.C., 10
trabajadores, noviembre 2026). Las dos tomas usan las mismas novedades y el mismo ritmo por acción; se publican
**acciones contadas** (datos escritos, clics, copiar y pegar, archivos armados a mano), nunca tiempos. El lado Excel es
un libro genérico propio (`comparativo-excel.html`), sin macros ni nada copiado de libros de terceros, y muestra los
montos que calculó Serialo (cuadran al céntimo).

```bash
php artisan migrate:fresh --seed && php tools/videos/comparativo-preparar.php
composer dev                                   # servidor y cola
cd tools/videos
node comparativo.js serialo                    # videos/comparativo-serialo.webm + cifras
php comparativo-resultado.php                 # montos para el Excel
node comparativo.js excel                      # videos/comparativo-excel.webm + cifras
node comparativo-montar.js                     # pantalla partida + cierre → comparativo-web.mp4
```

Luego copiar `videos/comparativo-web.mp4` a `sitio/public/videos/excel-vs-serialo.mp4` y actualizar la tabla de
`sitio/src/pages/index.astro` con las cifras de `videos/comparativo-*-cifras.json`.
