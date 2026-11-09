<script setup lang="ts">
import PantallaExportacion from '@/components/archivos/PantallaExportacion.vue';
import { index, periodo, todas } from '@/routes/afpnet';
import type { DatosExportacion } from '@/types';

defineProps<DatosExportacion>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'AFPnet', href: index() }],
    },
});
</script>

<template>
    <PantallaExportacion
        v-bind="$props"
        titulo="AFPnet"
        descripcion="Planilla de aportes para AFPnet: un archivo de texto por empresa con todos sus trabajadores en AFP."
        texto-descarga="Descargar TXT"
        :url-index="index.url()"
        :url-todas="(anio, mes) => todas.url({ query: { anio, mes } })"
        :url-periodo="(id) => periodo.url(id)"
    />
</template>
