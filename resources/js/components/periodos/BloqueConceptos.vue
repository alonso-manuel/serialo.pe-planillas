<script setup lang="ts">
import { formatoMonto } from '@/lib/montos';
import type { GrupoDetalle } from '@/types';

defineProps<{ grupo: GrupoDetalle }>();
</script>

<template>
    <section class="rounded-lg border">
        <h2 class="border-b px-4 py-2 text-sm font-medium">
            {{ grupo.titulo }}
        </h2>
        <table class="w-full text-sm">
            <tbody>
                <tr
                    v-for="c in grupo.conceptos"
                    :key="c.codigo"
                    class="border-b last:border-b-0"
                >
                    <td
                        class="w-16 px-4 py-2 font-mono text-xs text-muted-foreground"
                    >
                        {{ c.codigo }}
                    </td>
                    <td class="py-2">{{ c.nombre }}</td>
                    <td class="px-4 py-2 text-right tabular-nums">
                        {{ formatoMonto(c.monto) }}
                    </td>
                </tr>
                <tr v-if="grupo.conceptos.length === 0">
                    <td colspan="3" class="px-4 py-3 text-muted-foreground">
                        Sin conceptos.
                    </td>
                </tr>
            </tbody>
            <tfoot class="border-t bg-muted/30 font-medium">
                <tr>
                    <td colspan="2" class="px-4 py-2">Total</td>
                    <td class="px-4 py-2 text-right tabular-nums">
                        {{ formatoMonto(grupo.total) }}
                    </td>
                </tr>
            </tfoot>
        </table>
    </section>
</template>
