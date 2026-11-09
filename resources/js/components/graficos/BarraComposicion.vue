<script setup lang="ts">
import { computed } from 'vue';
import { aNumero, porcentaje } from '@/lib/graficos';

/**
 * Un total partido en segmentos (barra apilada horizontal con 2px de separación)
 * y su leyenda con monto y porcentaje, que también sirve de tabla.
 */
const props = defineProps<{
    segmentos: {
        clave: string;
        nombre: string;
        valor: string;
        color: string;
    }[];
    total: string;
    formato: (valor: string) => string;
    descripcion: string;
}>();

const activo = defineModel<string | null>('activo', { default: null });

const visibles = computed(() =>
    props.segmentos.filter((s) => (aNumero(s.valor) ?? 0) > 0),
);
</script>

<template>
    <div class="grid gap-4">
        <div
            class="flex h-6 w-full gap-[2px] overflow-hidden rounded-[4px]"
            role="img"
            :aria-label="descripcion"
        >
            <div
                v-for="s in visibles"
                :key="s.clave"
                class="h-full transition-opacity"
                :class="activo && activo !== s.clave ? 'opacity-35' : ''"
                :style="{
                    flexGrow: aNumero(s.valor) ?? 0,
                    flexBasis: 0,
                    background: s.color,
                }"
                :title="`${s.nombre}: S/ ${formato(s.valor)} (${porcentaje(s.valor, total)} %)`"
                @pointerenter="activo = s.clave"
                @pointerleave="activo = null"
            />
            <div v-if="visibles.length === 0" class="h-full w-full bg-muted" />
        </div>

        <table class="w-full text-sm">
            <tbody>
                <tr
                    v-for="s in segmentos"
                    :key="s.clave"
                    class="border-b last:border-b-0"
                    :class="activo === s.clave ? 'bg-muted/50' : ''"
                    @pointerenter="activo = s.clave"
                    @pointerleave="activo = null"
                >
                    <td class="py-2 pr-2">
                        <span class="flex items-center gap-2">
                            <span
                                class="size-2.5 shrink-0 rounded-[3px]"
                                :style="{ background: s.color }"
                            />
                            {{ s.nombre }}
                        </span>
                    </td>
                    <td
                        class="py-2 text-right whitespace-nowrap text-muted-foreground tabular-nums"
                    >
                        {{ porcentaje(s.valor, total) }} %
                    </td>
                    <td class="py-2 pl-4 text-right font-medium tabular-nums">
                        {{ formato(s.valor) }}
                    </td>
                </tr>
                <tr class="border-t">
                    <td class="py-2 font-medium">Costo laboral total</td>
                    <td />
                    <td class="py-2 pl-4 text-right font-semibold tabular-nums">
                        {{ formato(total) }}
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
