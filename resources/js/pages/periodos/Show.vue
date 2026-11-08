<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    Calculator,
    CircleAlert,
    RefreshCw,
    Trash2,
    TriangleAlert,
} from '@lucide/vue';
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
import { calcular, destroy, index, sincronizar } from '@/routes/periodos';
import { index as parametros } from '@/routes/parametros';
import { index as tasasAfp } from '@/routes/tasas-afp';
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
    problemas: string[];
}>();

const page = usePage();
const esAdmin = computed(() => page.props.auth.esAdmin);

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

/* ---------- Calcular ---------- */
const grilla = ref<InstanceType<typeof GrillaNovedades> | null>(null);
const calculando = ref(false);

async function calcularPlanilla(): Promise<void> {
    calculando.value = true;
    // Primero se guardan las filas editadas que aún no se guardaron.
    const ok = (await grilla.value?.guardarPendientes()) ?? true;

    if (!ok) {
        calculando.value = false;

        return;
    }

    router.post(
        calcular.url(props.periodo.id),
        {},
        {
            preserveScroll: true,
            // Se recarga el estado de la página: netos y estado vienen del servidor.
            preserveState: false,
            onFinish: () => (calculando.value = false),
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
                    :disabled="calculando || lineas.length === 0"
                    @click="calcularPlanilla"
                >
                    <Calculator :class="{ 'animate-pulse': calculando }" />
                    {{
                        calculando
                            ? 'Calculando…'
                            : estado === 'calculado'
                              ? 'Recalcular'
                              : 'Calcular planilla'
                    }}
                </Button>
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

        <div
            v-if="problemas.length"
            class="grid gap-2 rounded-lg border border-red-300 bg-red-50 p-4 text-sm text-red-900 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200"
            role="alert"
        >
            <p class="flex items-center gap-2 font-medium">
                <CircleAlert class="size-4" />
                No se calculó la planilla: corrige lo siguiente y vuelve a
                calcular.
            </p>
            <ul class="ml-6 list-disc space-y-1">
                <li v-for="p in problemas" :key="p">{{ p }}</li>
            </ul>
            <p v-if="esAdmin" class="flex flex-wrap gap-3 text-xs">
                <Link :href="tasasAfp()" class="underline">Ir a Tasas AFP</Link>
                <Link :href="parametros()" class="underline"
                    >Ir a Parámetros</Link
                >
            </p>
            <p v-else class="text-xs">
                Las tasas AFP y los parámetros los carga el administrador.
            </p>
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
            ref="grilla"
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
