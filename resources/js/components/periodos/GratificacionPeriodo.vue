<script setup lang="ts">
import { Gift, Info, TriangleAlert } from '@lucide/vue';
import { computed } from 'vue';
import { formatoMonto, sumarMontos } from '@/lib/montos';
import type { DetalleGratificacion } from '@/types';

const props = defineProps<{
    detalle: DetalleGratificacion;
    mes: string;
}>();

const filas = computed(() => props.detalle.filas);

const totalGratificacion = computed(() =>
    sumarMontos(filas.value.map((f) => (f.cesado ? '0' : f.gratificacion))),
);
const totalBonificacion = computed(() =>
    sumarMontos(filas.value.map((f) => (f.cesado ? '0' : f.bonificacion))),
);

/** Meses del semestre sin planilla en el sistema (se asumieron completos). */
const mesesSinPlanilla = computed(() => {
    const meses = new Set<string>();
    filas.value.forEach((f) => {
        if (!f.cesado) {
            f.sin_planilla.forEach((m) => meses.add(m));
        }
    });

    return [...meses];
});
</script>

<template>
    <section class="grid gap-3 rounded-lg border p-4">
        <div class="flex flex-wrap items-start justify-between gap-2">
            <div>
                <h2 class="flex items-center gap-2 font-medium">
                    <Gift class="size-4" />
                    Gratificación de {{ mes }}
                </h2>
                <p class="text-sm text-muted-foreground">
                    Se agrega sola al calcular (conceptos 0406 y 0312). No está
                    afecta a ONP, AFP ni EsSalud.
                </p>
            </div>
            <div v-if="!detalle.error" class="text-right text-sm tabular-nums">
                <p>
                    Gratificación
                    <span class="font-medium"
                        >S/ {{ formatoMonto(totalGratificacion) }}</span
                    >
                </p>
                <p class="text-muted-foreground">
                    Bonificación S/ {{ formatoMonto(totalBonificacion) }}
                </p>
            </div>
        </div>

        <p
            v-if="detalle.error"
            class="flex items-start gap-2 rounded-md bg-red-50 p-3 text-sm text-red-900 dark:bg-red-950/40 dark:text-red-200"
        >
            <TriangleAlert class="mt-0.5 size-4 shrink-0" />
            {{ detalle.error }}
        </p>

        <template v-else>
            <p
                v-if="mesesSinPlanilla.length"
                class="flex items-start gap-2 rounded-md bg-amber-50 p-3 text-xs text-amber-900 dark:bg-amber-950/40 dark:text-amber-300"
            >
                <Info class="mt-0.5 size-3.5 shrink-0" />
                Sin planilla en el sistema: {{ mesesSinPlanilla.join(', ') }}.
                Esos meses se cuentan completos, sin faltas ni horas extra.
                Revisa los casos que no correspondan.
            </p>

            <div class="overflow-x-auto rounded-md border">
                <table class="w-full min-w-[720px] text-sm">
                    <thead class="bg-muted/40 text-xs text-muted-foreground">
                        <tr>
                            <th class="px-3 py-2 text-left font-medium">
                                Trabajador
                            </th>
                            <th class="px-3 py-2 text-right font-medium">
                                Meses
                            </th>
                            <th class="px-3 py-2 text-right font-medium">
                                Faltas
                            </th>
                            <th
                                class="px-3 py-2 text-right font-medium"
                                title="Promedio de horas extra si se pagaron en 3 meses o más del semestre"
                            >
                                Prom. variables
                            </th>
                            <th class="px-3 py-2 text-right font-medium">
                                Rem. computable
                            </th>
                            <th class="px-3 py-2 text-right font-medium">
                                Gratificación
                            </th>
                            <th class="px-3 py-2 text-right font-medium">
                                Bonificación
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="f in filas"
                            :key="f.linea_id"
                            class="border-t"
                        >
                            <td class="px-3 py-2">{{ f.nombre }}</td>
                            <td
                                v-if="f.cesado"
                                colspan="6"
                                class="px-3 py-2 text-right text-xs text-muted-foreground"
                            >
                                Cesó antes del día de pago: se paga en su
                                liquidación.
                            </td>
                            <template v-else>
                                <td class="px-3 py-2 text-right tabular-nums">
                                    {{ f.meses }}
                                </td>
                                <td class="px-3 py-2 text-right tabular-nums">
                                    {{ f.faltas }}
                                </td>
                                <td
                                    class="px-3 py-2 text-right tabular-nums"
                                    :title="
                                        'Horas extra en ' +
                                        f.meses_variables +
                                        ' meses'
                                    "
                                >
                                    {{ formatoMonto(f.promedio_variables) }}
                                </td>
                                <td class="px-3 py-2 text-right tabular-nums">
                                    {{ formatoMonto(f.computable) }}
                                </td>
                                <td
                                    class="px-3 py-2 text-right font-medium tabular-nums"
                                >
                                    {{ formatoMonto(f.gratificacion) }}
                                </td>
                                <td class="px-3 py-2 text-right tabular-nums">
                                    {{ formatoMonto(f.bonificacion) }}
                                </td>
                            </template>
                        </tr>
                    </tbody>
                </table>
            </div>
        </template>
    </section>
</template>
