<script setup lang="ts">
import { Calculator, CircleDot, Lock } from '@lucide/vue';
import { Badge } from '@/components/ui/badge';
import type { EstadoPeriodo } from '@/types';

defineProps<{
    estado: EstadoPeriodo;
    etiqueta: string;
}>();

const ESTILOS: Record<EstadoPeriodo, string> = {
    abierto:
        'border-sky-300 text-sky-700 dark:border-sky-800 dark:text-sky-400',
    calculado:
        'border-emerald-300 text-emerald-700 dark:border-emerald-800 dark:text-emerald-400',
    cerrado: 'border-muted-foreground/40 text-muted-foreground',
};
</script>

<template>
    <Badge variant="outline" :class="ESTILOS[estado]">
        <CircleDot v-if="estado === 'abierto'" />
        <Calculator v-else-if="estado === 'calculado'" />
        <Lock v-else />
        {{ etiqueta }}
    </Badge>
</template>
