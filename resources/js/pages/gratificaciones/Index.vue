<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ChevronRight, Gift, Info } from '@lucide/vue';
import EstadoPeriodo from '@/components/periodos/EstadoPeriodo.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { formatoMonto } from '@/lib/montos';
import { index } from '@/routes/gratificaciones';
import { index as irAPeriodos, show as verPeriodo } from '@/routes/periodos';
import type { EstadoPeriodo as Estado } from '@/types';

defineProps<{
    empresa: string;
    aplica: boolean;
    regimen: string;
    proxima: string;
    periodos: {
        id: number;
        etiqueta: string;
        estado: Estado;
        estado_etiqueta: string;
        calculada: boolean;
        trabajadores: number;
        gratificacion: string;
        bonificacion: string;
    }[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Gratificaciones', href: index() }],
    },
});
</script>

<template>
    <Head title="Gratificaciones" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <Heading
            title="Gratificaciones"
            :description="`Gratificaciones de julio y diciembre de ${empresa}.`"
        />

        <p
            class="flex items-start gap-2 rounded-md bg-muted/50 p-3 text-sm text-muted-foreground"
        >
            <Info class="mt-0.5 size-4 shrink-0" />
            <span v-if="aplica">
                La gratificación y la bonificación extraordinaria se agregan
                solas al calcular la planilla de julio y diciembre (hasta el día
                15). Próxima: <b>{{ proxima }}</b
                >. El detalle por trabajador está en la planilla de ese mes.
            </span>
            <span v-else>
                {{ regimen }}: no corresponde pagar gratificaciones.
            </span>
        </p>

        <div
            v-if="aplica && periodos.length === 0"
            class="flex flex-col items-center gap-3 rounded-lg border border-dashed px-6 py-14 text-center"
        >
            <Gift class="size-6 text-muted-foreground" />
            <p class="font-medium">Aún no hay planillas de julio o diciembre</p>
            <Button variant="outline" as-child>
                <Link :href="irAPeriodos()">Ir a Periodos</Link>
            </Button>
        </div>

        <div v-else-if="aplica" class="overflow-x-auto rounded-lg border">
            <table class="w-full min-w-[640px] text-sm">
                <thead class="bg-muted/40 text-xs text-muted-foreground">
                    <tr>
                        <th class="px-4 py-2 text-left font-medium">Mes</th>
                        <th class="px-4 py-2 text-left font-medium">
                            Planilla
                        </th>
                        <th class="px-4 py-2 text-right font-medium">
                            Trabajadores
                        </th>
                        <th class="px-4 py-2 text-right font-medium">
                            Gratificación
                        </th>
                        <th class="px-4 py-2 text-right font-medium">
                            Bonificación
                        </th>
                        <th class="px-4 py-2" />
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="p in periodos"
                        :key="p.id"
                        class="border-t align-middle"
                    >
                        <td class="px-4 py-3 font-medium">{{ p.etiqueta }}</td>
                        <td class="px-4 py-3">
                            <EstadoPeriodo
                                :estado="p.estado"
                                :etiqueta="p.estado_etiqueta"
                            />
                        </td>
                        <template v-if="p.calculada">
                            <td class="px-4 py-3 text-right tabular-nums">
                                {{ p.trabajadores }}
                            </td>
                            <td
                                class="px-4 py-3 text-right font-medium tabular-nums"
                            >
                                S/ {{ formatoMonto(p.gratificacion) }}
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums">
                                S/ {{ formatoMonto(p.bonificacion) }}
                            </td>
                        </template>
                        <td
                            v-else
                            colspan="3"
                            class="px-4 py-3 text-right text-xs text-muted-foreground"
                        >
                            Sin calcular
                        </td>
                        <td class="px-4 py-3 text-right">
                            <Button variant="ghost" size="sm" as-child>
                                <Link :href="verPeriodo(p.id)">
                                    Ver detalle
                                    <ChevronRight />
                                </Link>
                            </Button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
