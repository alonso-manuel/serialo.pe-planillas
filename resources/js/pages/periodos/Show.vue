<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { RefreshCw, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import EstadoPeriodo from '@/components/periodos/EstadoPeriodo.vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { destroy, index, sincronizar } from '@/routes/periodos';
import { edit as editarTrabajador } from '@/routes/trabajadores';
import type { LineaPeriodo, PeriodoDetalle } from '@/types';

const props = defineProps<{
    periodo: PeriodoDetalle;
    lineas: LineaPeriodo[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Periodos', href: index() }],
    },
});

const actualizando = ref(false);

function actualizar(): void {
    router.post(
        sincronizar.url(props.periodo.id),
        {},
        {
            preserveScroll: true,
            onStart: () => (actualizando.value = true),
            onFinish: () => (actualizando.value = false),
        },
    );
}

const confirmarEliminar = ref(false);

function eliminar(): void {
    router.delete(destroy.url(props.periodo.id));
}

const totalDias = computed(() =>
    props.lineas.reduce((s, l) => s + l.dias_laborados, 0),
);
const soles = new Intl.NumberFormat('es-PE', {
    style: 'currency',
    currency: 'PEN',
});
</script>

<template>
    <Head :title="`Planilla ${periodo.etiqueta}`" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="flex flex-wrap items-center gap-3">
                <Heading
                    :title="`Planilla de ${periodo.etiqueta}`"
                    :description="periodo.empresa ?? undefined"
                />
                <EstadoPeriodo
                    :estado="periodo.estado"
                    :etiqueta="periodo.estado_etiqueta"
                />
            </div>
            <div v-if="periodo.puede_editar" class="flex flex-wrap gap-2">
                <Button
                    variant="outline"
                    :disabled="actualizando"
                    @click="actualizar"
                >
                    <RefreshCw :class="{ 'animate-spin': actualizando }" />
                    Actualizar trabajadores
                </Button>
                <Button
                    variant="ghost"
                    class="text-destructive hover:text-destructive"
                    @click="confirmarEliminar = true"
                >
                    <Trash2 />
                    Eliminar periodo
                </Button>
            </div>
        </div>

        <p
            v-if="periodo.estado === 'abierto'"
            class="max-w-3xl rounded-md bg-muted/50 p-3 text-sm text-muted-foreground"
        >
            Revisa los días de cada trabajador. Si registraste ingresos o ceses
            después de abrir el mes, usa «Actualizar trabajadores».
        </p>

        <div class="overflow-x-auto rounded-lg border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left text-muted-foreground">
                    <tr>
                        <th class="px-4 py-3 font-medium">Trabajador</th>
                        <th class="px-4 py-3 font-medium">Cargo</th>
                        <th class="px-4 py-3 text-right font-medium">Días</th>
                        <th class="px-4 py-3 font-medium">Observación</th>
                        <th class="px-4 py-3 text-right font-medium">Neto</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="l in lineas" :key="l.id" class="border-t">
                        <td class="px-4 py-3">
                            <Link
                                :href="editarTrabajador(l.trabajador_id)"
                                class="font-medium hover:underline"
                            >
                                {{ l.nombre }}
                            </Link>
                            <div class="text-xs text-muted-foreground">
                                {{ l.documento }}
                            </div>
                        </td>
                        <td class="px-4 py-3">{{ l.cargo ?? '—' }}</td>
                        <td class="px-4 py-3 text-right tabular-nums">
                            {{ l.dias_laborados }}
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex flex-wrap gap-1">
                                <Badge v-if="l.ingresa_en_mes" variant="outline"
                                    >Ingresa {{ l.ingresa_en_mes }}</Badge
                                >
                                <Badge v-if="l.cesa_en_mes" variant="outline"
                                    >Cesa {{ l.cesa_en_mes }}</Badge
                                >
                                <Badge v-if="l.novedades" variant="secondary">
                                    {{ l.novedades }}
                                    {{
                                        l.novedades === 1
                                            ? 'novedad'
                                            : 'novedades'
                                    }}
                                </Badge>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-right tabular-nums">
                            {{
                                l.neto_pagar !== null
                                    ? soles.format(Number(l.neto_pagar))
                                    : '—'
                            }}
                        </td>
                    </tr>
                    <tr v-if="lineas.length === 0">
                        <td
                            colspan="5"
                            class="px-4 py-10 text-center text-muted-foreground"
                        >
                            Ningún trabajador laboró en este mes. Registra
                            trabajadores y usa «Actualizar trabajadores».
                        </td>
                    </tr>
                </tbody>
                <tfoot
                    v-if="lineas.length"
                    class="border-t bg-muted/30 text-sm"
                >
                    <tr>
                        <td class="px-4 py-2 font-medium" colspan="2">
                            {{ lineas.length }}
                            {{
                                lineas.length === 1
                                    ? 'trabajador'
                                    : 'trabajadores'
                            }}
                        </td>
                        <td class="px-4 py-2 text-right tabular-nums">
                            {{ totalDias }}
                        </td>
                        <td colspan="2" />
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <Dialog v-model:open="confirmarEliminar">
        <DialogContent>
            <DialogHeader>
                <DialogTitle
                    >¿Eliminar el periodo {{ periodo.etiqueta }}?</DialogTitle
                >
                <DialogDescription>
                    Solo es posible si es el último periodo y aún no se calculó
                    (por ejemplo, si abriste el mes equivocado). Se borran sus
                    líneas y novedades.
                </DialogDescription>
            </DialogHeader>
            <DialogFooter>
                <DialogClose as-child>
                    <Button variant="outline">Cancelar</Button>
                </DialogClose>
                <Button variant="destructive" @click="eliminar"
                    >Eliminar periodo</Button
                >
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
