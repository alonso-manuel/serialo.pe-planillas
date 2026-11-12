<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';

/** Etiqueta «Beta» junto al nombre del sistema mientras dura la beta pública (PLANILLAS_BETA). */
const page = usePage();
</script>

<template>
    <span
        v-if="page.props.beta"
        title="Versión beta: el sistema está en evaluación y puede cambiar"
        class="inline-flex items-center rounded-full border border-amber-300 bg-amber-100 px-1.5 py-px align-middle text-[10px] leading-4 font-semibold tracking-wide text-amber-800 uppercase dark:border-amber-800 dark:bg-amber-950 dark:text-amber-300"
        >Beta</span
    >
</template>
