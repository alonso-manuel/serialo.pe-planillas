<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ChevronRight, FileArchive, FileText, Mail } from '@lucide/vue';
import EstadoPeriodo from '@/components/periodos/EstadoPeriodo.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { index } from '@/routes/boletas';
import { index as irAPeriodos, show as verPeriodo } from '@/routes/periodos';
import { pdf, zip } from '@/routes/periodos/boletas';
import type { EstadoPeriodo as Estado } from '@/types';

defineProps<{
    empresa: string;
    periodos: {
        id: number;
        etiqueta: string;
        estado: Estado;
        estado_etiqueta: string;
        cerrado: boolean;
        boletas: number;
        enviadas: number;
        con_correo: number;
    }[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Boletas', href: index() }],
    },
});
</script>

<template>
    <Head title="Boletas" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <Heading
            title="Boletas de pago"
            :description="`Boletas por mes de ${empresa}: descarga en PDF y envío por correo.`"
        />

        <div
            v-if="periodos.length === 0"
            class="flex flex-col items-center gap-3 rounded-lg border border-dashed px-6 py-14 text-center"
        >
            <p class="font-medium">Todavía no hay planillas calculadas</p>
            <p class="max-w-sm text-sm text-muted-foreground">
                Las boletas se generan al calcular la planilla del mes.
            </p>
            <Button variant="outline" as-child>
                <Link :href="irAPeriodos()">Ir a Periodos</Link>
            </Button>
        </div>

        <div v-else class="overflow-x-auto rounded-lg border">
            <table class="w-full min-w-[720px] text-sm">
                <thead class="bg-muted/40 text-xs text-muted-foreground">
                    <tr>
                        <th class="px-4 py-2 text-left font-medium">Mes</th>
                        <th class="px-4 py-2 text-left font-medium">Estado</th>
                        <th class="px-4 py-2 text-right font-medium">
                            Boletas
                        </th>
                        <th class="px-4 py-2 text-left font-medium">
                            Por correo
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
                            <span
                                v-if="!p.cerrado"
                                class="ml-2 text-xs text-muted-foreground"
                                >con marca «BORRADOR»</span
                            >
                        </td>
                        <td class="px-4 py-3 text-right tabular-nums">
                            {{ p.boletas }}
                        </td>
                        <td class="px-4 py-3 text-xs">
                            <span
                                v-if="p.con_correo"
                                class="flex items-center gap-1"
                                :class="
                                    p.enviadas >= p.con_correo
                                        ? 'text-green-700 dark:text-green-400'
                                        : 'text-muted-foreground'
                                "
                            >
                                <Mail class="size-3.5" />
                                {{ p.enviadas }} de {{ p.con_correo }}
                                enviadas
                            </span>
                            <span v-else class="text-muted-foreground"
                                >Sin correos registrados</span
                            >
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex justify-end gap-2">
                                <Button variant="outline" size="sm" as-child>
                                    <a
                                        :href="pdf.url(p.id)"
                                        target="_blank"
                                        rel="noopener"
                                    >
                                        <FileText />
                                        PDF
                                    </a>
                                </Button>
                                <Button variant="outline" size="sm" as-child>
                                    <a :href="zip.url(p.id)">
                                        <FileArchive />
                                        Zip
                                    </a>
                                </Button>
                                <Button variant="ghost" size="sm" as-child>
                                    <Link
                                        :href="verPeriodo(p.id)"
                                        :title="
                                            p.cerrado
                                                ? 'Enviar por correo'
                                                : 'Ver la planilla'
                                        "
                                    >
                                        {{ p.cerrado ? 'Enviar' : 'Ver' }}
                                        <ChevronRight />
                                    </Link>
                                </Button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
