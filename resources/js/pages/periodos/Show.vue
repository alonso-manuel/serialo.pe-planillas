<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { RefreshCw, Trash2, TriangleAlert } from '@lucide/vue';
import { computed, ref } from 'vue';
import EstadoPeriodo from '@/components/periodos/EstadoPeriodo.vue';
import GrillaNovedades from '@/components/periodos/GrillaNovedades.vue';
import Heading from '@/components/Heading.vue';
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
import type {
    ConceptoNovedad,
    EstadoPeriodo as Estado,
    LineaPeriodo,
    PeriodoDetalle,
} from '@/types';

const props = defineProps<{
    periodo: PeriodoDetalle;
    lineas: LineaPeriodo[];
    columnas: number[];
    conceptos: ConceptoNovedad[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Periodos', href: index() }],
    },
});

/* El estado puede cambiar al guardar una fila (calculado → abierto). */
const estado = ref<Estado>(props.periodo.estado);
const ETIQUETAS: Record<Estado, string> = {
    abierto: 'Abierto',
    calculado: 'Calculado',
    cerrado: 'Cerrado',
};
const volvioAbierto = computed(
    () => props.periodo.estado === 'calculado' && estado.value === 'abierto',
);

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
</script>

<template>
    <Head :title="`Planilla ${periodo.etiqueta}`" />

    <div class="flex min-w-0 flex-1 flex-col gap-6 p-4 md:p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="flex flex-wrap items-center gap-3">
                <Heading
                    :title="`Planilla de ${periodo.etiqueta}`"
                    :description="periodo.empresa ?? undefined"
                />
                <EstadoPeriodo :estado="estado" :etiqueta="ETIQUETAS[estado]" />
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
            v-if="volvioAbierto"
            class="flex items-center gap-2 rounded-md border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-300"
            role="status"
        >
            <TriangleAlert class="size-4 shrink-0" />
            Hay cambios sin calcular: el periodo volvió a «Abierto». Recalcula
            la planilla para actualizar los netos.
        </p>
        <p
            v-else-if="estado === 'cerrado'"
            class="rounded-md bg-muted/50 p-3 text-sm text-muted-foreground"
        >
            Periodo cerrado: las novedades son solo de lectura.
        </p>

        <GrillaNovedades
            :periodo-id="periodo.id"
            :lineas="lineas"
            :columnas="columnas"
            :conceptos="conceptos"
            :editable="periodo.puede_editar"
            @estado="(e) => (estado = e)"
        />
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
