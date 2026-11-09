<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { aNumero } from '@/lib/graficos';

/** Barras horizontales de una sola serie, ordenadas de mayor a menor, con el valor al final. */
const props = withDefaults(
    defineProps<{
        filas: {
            clave: string | number;
            etiqueta: string;
            valor: string;
            href?: string | null;
        }[];
        formato: (valor: string) => string;
        color?: string;
        descripcion: string;
    }>(),
    { color: 'var(--serie-1)' },
);

const maximo = computed(
    () => Math.max(0, ...props.filas.map((f) => aNumero(f.valor) ?? 0)) || 1,
);

function ancho(valor: string): string {
    const v = aNumero(valor) ?? 0;

    return `${Math.max(v > 0 ? 1 : 0, (v / maximo.value) * 100)}%`;
}
</script>

<template>
    <ul class="grid gap-3" role="list" :aria-label="descripcion">
        <li
            v-for="f in filas"
            :key="f.clave"
            class="group grid grid-cols-[minmax(0,1fr)_auto] items-center gap-x-3 gap-y-1"
        >
            <component
                :is="f.href ? Link : 'span'"
                :href="f.href ?? undefined"
                class="truncate text-sm"
                :class="f.href ? 'hover:underline' : ''"
                :title="f.etiqueta"
            >
                {{ f.etiqueta }}
            </component>
            <span class="text-sm font-medium tabular-nums">{{
                formato(f.valor)
            }}</span>
            <div class="col-span-2 h-3 rounded-sm bg-muted/60">
                <div
                    class="h-full rounded-r-[4px] transition-[filter] group-hover:brightness-110"
                    :style="{ width: ancho(f.valor), background: color }"
                />
            </div>
        </li>
    </ul>
</template>
