<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { LifeBuoy } from '@lucide/vue';
import { computed } from 'vue';

/** Franja superior mientras Serialo.pe está dentro como soporte (todo queda en la auditoría). */
const page = usePage();
const esSoporte = computed(() => page.props.auth.esSoporte);
</script>

<template>
    <div
        v-if="esSoporte"
        role="status"
        class="flex items-center gap-2 border-b border-amber-200 bg-amber-50 px-4 py-2 text-sm text-amber-900 md:px-6 dark:border-amber-900 dark:bg-amber-950/50 dark:text-amber-200"
    >
        <LifeBuoy class="size-4 shrink-0" />
        <span class="font-medium">Sesión de soporte de Serialo.pe</span>
        <span class="min-w-0 flex-1"
            >Todo lo que se haga queda en la auditoría a nombre de «Soporte
            Serialo.pe».</span
        >
    </div>
</template>
