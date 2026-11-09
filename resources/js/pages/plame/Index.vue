<script setup lang="ts">
import PantallaExportacion from '@/components/archivos/PantallaExportacion.vue';
import { index, periodo, todas } from '@/routes/plame';
import type { DatosExportacion } from '@/types';

defineProps<DatosExportacion>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'PLAME', href: index() }],
    },
});
</script>

<template>
    <PantallaExportacion
        v-bind="$props"
        titulo="PLAME"
        descripcion="Archivos de importación del PDT PLAME (formulario 0601): .rem, .jor, .snl y .toc."
        texto-descarga="Descargar zip"
        :url-index="index.url()"
        :url-todas="(anio, mes) => todas.url({ query: { anio, mes } })"
        :url-periodo="(id) => periodo.url(id)"
    />
</template>
