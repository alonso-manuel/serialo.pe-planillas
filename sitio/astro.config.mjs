// Web pública de Serialo Planillas (estática). Se compila dentro de la imagen «web» (Dockerfile, etapa «sitio») y
// Nginx la entrega en el dominio raíz; el formulario envía a /solicitud, que atiende Laravel. Ver docs/sitio.md.
import sitemap from '@astrojs/sitemap';
import tailwindcss from '@tailwindcss/vite';
import { defineConfig } from 'astro/config';

export default defineConfig({
    site: process.env.SITIO_URL ?? 'https://impulso.cfd',
    trailingSlash: 'never',
    build: { format: 'file' },
    integrations: [sitemap()],
    vite: { plugins: [tailwindcss()] },
});
