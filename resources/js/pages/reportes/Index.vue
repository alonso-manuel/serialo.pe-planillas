<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowLeftRight,
    BookCopy,
    BookOpen,
    Network,
    FileSpreadsheet,
    FileText,
    Info,
    Landmark,
    Table,
    Wallet,
} from '@lucide/vue';
import type { Component } from 'vue';
import { computed, ref } from 'vue';
import EstadoPeriodo from '@/components/periodos/EstadoPeriodo.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { index as periodosIndex } from '@/routes/periodos';
import { descargar, index } from '@/routes/reportes';
import type { EstadoPeriodo as Estado } from '@/types';

const props = defineProps<{
    empresa: string;
    periodos: {
        id: number;
        etiqueta: string;
        estado: Estado;
        estado_etiqueta: string;
        cerrado: boolean;
    }[];
    tipos: { clave: string; titulo: string }[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Reportes', href: index() }],
    },
});

const periodoId = ref<number | null>(props.periodos[0]?.id ?? null);
const periodo = computed(
    () => props.periodos.find((p) => p.id === periodoId.value) ?? null,
);

const DESCRIPCIONES: Record<string, { texto: string; icono: Component }> = {
    planilla: {
        texto: 'Una fila por trabajador con sus días, cada concepto en su columna, el neto y los totales.',
        icono: Table,
    },
    resumen: {
        texto: 'Lo que hay que pagar a cada entidad (ONP, AFP, EsSalud, SUNAT, trabajadores) y los totales por concepto.',
        icono: Landmark,
    },
    centralizacion: {
        texto: 'Asiento contable del mes con las cuentas del PCGE de cada concepto. Debe y haber cuadran.',
        icono: BookOpen,
    },
    costo: {
        texto: 'Por trabajador: remuneración bruta, aportes del empleador y provisiones del mes (gratificación, CTS, vacaciones).',
        icono: Wallet,
    },
    centros: {
        texto: 'El costo laboral del mes agrupado por el centro de costo que tenía cada trabajador.',
        icono: Network,
    },
    asiento: {
        texto: 'La centralización con los cargos separados por centro de costo y los abonos sin centro. Debe y haber cuadran.',
        icono: BookCopy,
    },
    comparativo: {
        texto: 'Neto, bruto y costo de cada trabajador frente al mes anterior: variación, altas y bajas.',
        icono: ArrowLeftRight,
    },
};

function url(tipo: string, formato: 'xlsx' | 'pdf'): string {
    return periodoId.value === null
        ? '#'
        : descargar.url({ periodo: periodoId.value, tipo, formato });
}
</script>

<template>
    <Head title="Reportes" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading
                title="Reportes"
                :description="`Reportes de la planilla de ${empresa}.`"
            />
            <div v-if="periodos.length" class="flex items-end gap-3">
                <div class="grid gap-1.5">
                    <Label for="periodo">Mes</Label>
                    <select
                        id="periodo"
                        v-model="periodoId"
                        class="h-9 rounded-md border border-input bg-background px-3 text-sm"
                    >
                        <option v-for="p in periodos" :key="p.id" :value="p.id">
                            {{ p.etiqueta }}
                        </option>
                    </select>
                </div>
                <EstadoPeriodo
                    v-if="periodo"
                    :estado="periodo.estado"
                    :etiqueta="periodo.estado_etiqueta"
                    class="mb-1.5"
                />
            </div>
        </div>

        <div
            v-if="periodos.length === 0"
            class="flex flex-col items-center gap-3 rounded-lg border border-dashed px-6 py-14 text-center"
        >
            <p class="font-medium">Todavía no hay planillas calculadas</p>
            <p class="max-w-sm text-sm text-muted-foreground">
                Calcula la planilla de un mes para ver sus reportes.
            </p>
            <Button variant="outline" as-child>
                <Link :href="periodosIndex()">Ir a Periodos</Link>
            </Button>
        </div>

        <template v-else>
            <p
                v-if="periodo && !periodo.cerrado"
                class="flex items-start gap-2 rounded-md bg-amber-50 p-3 text-sm text-amber-900 dark:bg-amber-950/40 dark:text-amber-300"
            >
                <Info class="mt-0.5 size-4 shrink-0" />
                El periodo aún no está cerrado: los reportes llevan la marca
                «BORRADOR» y pueden cambiar si se recalcula la planilla.
            </p>

            <div class="grid gap-4 md:grid-cols-2">
                <section
                    v-for="t in tipos"
                    :key="t.clave"
                    class="flex flex-col gap-3 rounded-lg border p-4"
                >
                    <div class="flex items-start gap-3">
                        <div
                            class="flex size-9 shrink-0 items-center justify-center rounded-md bg-muted"
                        >
                            <component
                                :is="DESCRIPCIONES[t.clave]?.icono ?? Table"
                                class="size-4"
                            />
                        </div>
                        <div>
                            <h2 class="font-medium">{{ t.titulo }}</h2>
                            <p class="text-sm text-muted-foreground">
                                {{ DESCRIPCIONES[t.clave]?.texto }}
                            </p>
                        </div>
                    </div>
                    <div class="mt-auto flex gap-2">
                        <Button variant="outline" size="sm" as-child>
                            <a :href="url(t.clave, 'xlsx')">
                                <FileSpreadsheet />
                                Excel
                            </a>
                        </Button>
                        <Button variant="outline" size="sm" as-child>
                            <a
                                :href="url(t.clave, 'pdf')"
                                target="_blank"
                                rel="noopener"
                            >
                                <FileText />
                                PDF
                            </a>
                        </Button>
                    </div>
                </section>
            </div>
        </template>
    </div>
</template>
