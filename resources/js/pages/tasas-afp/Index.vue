<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    ChevronLeft,
    ChevronRight,
    CircleAlert,
    Copy,
    Download,
    Lock,
    Upload,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { cancelar, confirmar } from '@/routes/tasas-afp/importar';
import { importar, index, plantilla, update } from '@/routes/tasas-afp';
import type { FilaTasaAfp, ImportacionTasasAfp, MesTasasAfp } from '@/types';

const props = defineProps<{
    anio: number;
    mes: number;
    resumen: MesTasasAfp[];
    grilla: FilaTasaAfp[];
    anterior: FilaTasaAfp[];
    bloqueado: boolean;
    afps: { id: number; codigo: string; nombre: string }[];
    importacion: ImportacionTasasAfp | null;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Tasas AFP', href: index() }],
    },
});

const MESES = [
    'Enero',
    'Febrero',
    'Marzo',
    'Abril',
    'Mayo',
    'Junio',
    'Julio',
    'Agosto',
    'Septiembre',
    'Octubre',
    'Noviembre',
    'Diciembre',
];

const COLUMNAS = [
    { campo: 'aporte_obligatorio', titulo: 'Aporte', sufijo: '%' },
    { campo: 'prima_seguro', titulo: 'Prima de seguro', sufijo: '%' },
    { campo: 'comision_flujo', titulo: 'Comisión flujo', sufijo: '%' },
    { campo: 'comision_mixta', titulo: 'Comisión mixta', sufijo: '%' },
    { campo: 'remuneracion_maxima_asegurable', titulo: 'RMA', prefijo: 'S/' },
] as const;

type Campo = (typeof COLUMNAS)[number]['campo'];

function irA(anio: number, mes: number): void {
    router.get(
        index.url({ query: { anio, mes } }),
        {},
        { preserveScroll: true },
    );
}

/* ---------- Grilla del mes ---------- */
function filasDe(grilla: FilaTasaAfp[]) {
    return grilla.map((f) => ({
        afp_id: f.afp_id,
        aporte_obligatorio: f.aporte_obligatorio ?? '',
        prima_seguro: f.prima_seguro ?? '',
        comision_flujo: f.comision_flujo ?? '',
        comision_mixta: f.comision_mixta ?? '',
        remuneracion_maxima_asegurable: f.remuneracion_maxima_asegurable ?? '',
    }));
}

const form = useForm({ filas: filasDe(props.grilla) });

watch(
    () => props.grilla,
    (grilla) => {
        form.defaults({ filas: filasDe(grilla) });
        form.reset();
        form.clearErrors();
    },
);

const hayAnterior = computed(() =>
    props.anterior.some((f) => f.aporte_obligatorio !== null),
);
const mesAnterior = computed(() =>
    props.mes === 1
        ? `Diciembre ${props.anio - 1}`
        : `${MESES[props.mes - 2]} ${props.anio}`,
);

function copiarAnterior(): void {
    form.filas = filasDe(props.anterior);
}

function error(i: number, campo: Campo): string | undefined {
    return (form.errors as Record<string, string>)[`filas.${i}.${campo}`];
}

function guardar(): void {
    form.put(update.url({ anio: props.anio, mes: props.mes }), {
        preserveScroll: true,
    });
}

/* ---------- Importación ---------- */
const archivo = ref<HTMLInputElement | null>(null);
const subida = useForm<{ archivo: File | null }>({ archivo: null });

function subir(evento: Event): void {
    const elegido = (evento.target as HTMLInputElement).files?.[0];

    if (!elegido) {
        return;
    }

    subida.archivo = elegido;
    subida.post(importar.url(), {
        preserveScroll: true,
        onFinish: () => {
            subida.reset();
            if (archivo.value) {
                archivo.value.value = '';
            }
        },
    });
}

const confirmando = ref(false);

function confirmarImportacion(): void {
    router.post(
        confirmar.url(),
        {},
        {
            preserveScroll: true,
            onStart: () => (confirmando.value = true),
            onFinish: () => (confirmando.value = false),
        },
    );
}

function cancelarImportacion(): void {
    router.delete(cancelar.url(), { preserveScroll: true });
}

function fecha(anio: number | null, mes: number | null): string {
    return anio && mes ? `${String(mes).padStart(2, '0')}/${anio}` : '—';
}
</script>

<template>
    <Head title="Tasas AFP" />

    <div class="flex flex-1 flex-col gap-8 p-4 md:p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading
                title="Tasas AFP"
                description="Aporte, prima de seguro, comisiones y remuneración máxima asegurable que publica la SBS cada mes."
            />
            <div class="flex gap-2">
                <Button variant="outline" as-child>
                    <a :href="plantilla.url()">
                        <Download />
                        Plantilla Excel
                    </a>
                </Button>
                <Button
                    variant="outline"
                    :disabled="subida.processing"
                    @click="archivo?.click()"
                >
                    <Spinner v-if="subida.processing" />
                    <Upload v-else />
                    Importar
                </Button>
                <input
                    ref="archivo"
                    type="file"
                    accept=".xlsx,.csv"
                    class="hidden"
                    @change="subir"
                />
            </div>
        </div>
        <InputError :message="subida.errors.archivo" class="-mt-6" />

        <!-- Vista previa de importación -->
        <section
            v-if="importacion"
            class="grid gap-4 rounded-xl border border-primary/30 bg-primary/5 p-5"
        >
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="font-medium">
                        Vista previa · {{ importacion.archivo }}
                    </h2>
                    <p class="text-sm text-muted-foreground">
                        {{ importacion.validas }} filas listas para importar
                        <template v-if="importacion.con_errores">
                            · {{ importacion.con_errores }} con errores (no se
                            importarán)
                        </template>
                    </p>
                </div>
                <div class="flex gap-2">
                    <Button variant="outline" @click="cancelarImportacion">
                        Cancelar
                    </Button>
                    <Button
                        :disabled="importacion.validas === 0 || confirmando"
                        @click="confirmarImportacion"
                    >
                        <Spinner v-if="confirmando" />
                        Importar {{ importacion.validas }} filas
                    </Button>
                </div>
            </div>

            <div class="max-h-96 overflow-auto rounded-lg border bg-background">
                <table class="w-full text-sm">
                    <thead
                        class="sticky top-0 bg-muted text-left text-xs text-muted-foreground"
                    >
                        <tr>
                            <th class="px-3 py-2 font-medium">Línea</th>
                            <th class="px-3 py-2 font-medium">Mes</th>
                            <th class="px-3 py-2 font-medium">AFP</th>
                            <th class="px-3 py-2 text-right font-medium">
                                Aporte %
                            </th>
                            <th class="px-3 py-2 text-right font-medium">
                                Prima %
                            </th>
                            <th class="px-3 py-2 text-right font-medium">
                                Flujo %
                            </th>
                            <th class="px-3 py-2 text-right font-medium">
                                Mixta %
                            </th>
                            <th class="px-3 py-2 text-right font-medium">
                                RMA S/
                            </th>
                            <th class="px-3 py-2 font-medium">Observaciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="fila in importacion.filas"
                            :key="fila.linea"
                            class="border-t align-top"
                            :class="{
                                'bg-red-50 dark:bg-red-950/30':
                                    fila.errores.length,
                            }"
                        >
                            <td
                                class="px-3 py-2 text-muted-foreground tabular-nums"
                            >
                                {{ fila.linea }}
                            </td>
                            <td class="px-3 py-2 tabular-nums">
                                {{ fecha(fila.anio, fila.mes) }}
                            </td>
                            <td class="px-3 py-2">{{ fila.afp }}</td>
                            <td class="px-3 py-2 text-right tabular-nums">
                                {{ fila.aporte_obligatorio }}
                            </td>
                            <td class="px-3 py-2 text-right tabular-nums">
                                {{ fila.prima_seguro }}
                            </td>
                            <td class="px-3 py-2 text-right tabular-nums">
                                {{ fila.comision_flujo }}
                            </td>
                            <td class="px-3 py-2 text-right tabular-nums">
                                {{ fila.comision_mixta }}
                            </td>
                            <td class="px-3 py-2 text-right tabular-nums">
                                {{ fila.remuneracion_maxima_asegurable }}
                            </td>
                            <td class="px-3 py-2 text-xs">
                                <p
                                    v-for="e in fila.errores"
                                    :key="e"
                                    class="text-red-700 dark:text-red-400"
                                >
                                    {{ e }}
                                </p>
                                <p
                                    v-for="a in fila.avisos"
                                    :key="a"
                                    class="text-amber-700 dark:text-amber-400"
                                >
                                    {{ a }}
                                </p>
                                <span
                                    v-if="
                                        !fila.errores.length &&
                                        !fila.avisos.length
                                    "
                                    class="text-muted-foreground"
                                >
                                    Correcta
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Resumen del año -->
        <section class="grid gap-3">
            <div class="flex items-center gap-2">
                <Button
                    variant="ghost"
                    size="icon"
                    class="size-8"
                    title="Año anterior"
                    @click="irA(anio - 1, mes)"
                >
                    <ChevronLeft />
                </Button>
                <h2 class="w-14 text-center text-lg font-semibold tabular-nums">
                    {{ anio }}
                </h2>
                <Button
                    variant="ghost"
                    size="icon"
                    class="size-8"
                    title="Año siguiente"
                    @click="irA(anio + 1, mes)"
                >
                    <ChevronRight />
                </Button>
            </div>

            <div class="grid grid-cols-3 gap-2 sm:grid-cols-6 lg:grid-cols-12">
                <Link
                    v-for="m in resumen"
                    :key="m.mes"
                    :href="index.url({ query: { anio, mes: m.mes } })"
                    preserve-scroll
                    class="rounded-lg border px-2 py-2 text-center text-xs transition-colors hover:bg-muted"
                    :class="{
                        'border-primary ring-1 ring-primary': m.mes === mes,
                        'border-red-300 bg-red-50 text-red-800 dark:border-red-900 dark:bg-red-950/30 dark:text-red-300':
                            m.estado !== 'completo' && m.mes !== mes,
                    }"
                >
                    <span class="block font-medium">
                        {{ MESES[m.mes - 1].slice(0, 3) }}
                    </span>
                    <span
                        class="mt-0.5 flex items-center justify-center gap-1 text-muted-foreground"
                    >
                        <Lock v-if="m.bloqueado" class="size-3" />
                        {{
                            m.estado === 'completo'
                                ? 'Completo'
                                : m.estado === 'vacio'
                                  ? 'Falta'
                                  : `${m.cargadas}/${afps.length}`
                        }}
                    </span>
                </Link>
            </div>
        </section>

        <!-- Grilla del mes -->
        <section class="grid gap-4">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h2 class="text-lg font-semibold">
                        {{ MESES[mes - 1] }} {{ anio }}
                    </h2>
                    <p class="text-sm text-muted-foreground">
                        Tasas en porcentaje (escribe 1.37 para 1.37 %).
                    </p>
                </div>
                <Button
                    v-if="!bloqueado && hayAnterior"
                    variant="outline"
                    size="sm"
                    @click="copiarAnterior"
                >
                    <Copy />
                    Copiar de {{ mesAnterior }}
                </Button>
            </div>

            <div
                v-if="bloqueado"
                class="flex gap-3 rounded-lg border bg-muted/50 p-4 text-sm"
            >
                <Lock class="mt-0.5 size-4 shrink-0" />
                <p>
                    Hay periodos cerrados de este mes. Sus tasas quedan fijas
                    para que la planilla pueda volver a generarse igual.
                </p>
            </div>

            <form class="grid gap-4" @submit.prevent="guardar">
                <div class="overflow-x-auto rounded-lg border">
                    <table class="w-full text-sm">
                        <thead
                            class="bg-muted/50 text-left text-muted-foreground"
                        >
                            <tr>
                                <th class="px-4 py-3 font-medium">AFP</th>
                                <th
                                    v-for="c in COLUMNAS"
                                    :key="c.campo"
                                    class="px-2 py-3 font-medium"
                                >
                                    {{ c.titulo }}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="(fila, i) in form.filas"
                                :key="fila.afp_id"
                                class="border-t align-top"
                            >
                                <td class="px-4 py-3 font-medium">
                                    {{ grilla[i]?.nombre }}
                                </td>
                                <td
                                    v-for="c in COLUMNAS"
                                    :key="c.campo"
                                    class="min-w-28 px-2 py-2"
                                >
                                    <div class="relative">
                                        <span
                                            v-if="'prefijo' in c"
                                            class="pointer-events-none absolute top-1/2 left-2.5 -translate-y-1/2 text-xs text-muted-foreground"
                                        >
                                            {{ c.prefijo }}
                                        </span>
                                        <Input
                                            v-model="fila[c.campo]"
                                            inputmode="decimal"
                                            class="h-9 text-right tabular-nums"
                                            :class="
                                                'prefijo' in c ? 'pl-8' : 'pr-7'
                                            "
                                            :disabled="bloqueado"
                                            :aria-label="`${c.titulo} ${grilla[i]?.nombre}`"
                                            :aria-invalid="!!error(i, c.campo)"
                                        />
                                        <span
                                            v-if="'sufijo' in c"
                                            class="pointer-events-none absolute top-1/2 right-2.5 -translate-y-1/2 text-xs text-muted-foreground"
                                        >
                                            {{ c.sufijo }}
                                        </span>
                                    </div>
                                    <InputError
                                        :message="error(i, c.campo)"
                                        class="mt-1 text-xs"
                                    />
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <p
                    v-if="form.errors.filas"
                    class="flex items-center gap-2 text-sm text-red-600 dark:text-red-500"
                >
                    <CircleAlert class="size-4" />
                    {{ form.errors.filas }}
                </p>

                <div v-if="!bloqueado" class="flex gap-3">
                    <Button
                        type="submit"
                        :disabled="form.processing || !form.isDirty"
                    >
                        <Spinner v-if="form.processing" />
                        Guardar {{ MESES[mes - 1].toLowerCase() }}
                    </Button>
                    <Button
                        v-if="form.isDirty"
                        type="button"
                        variant="ghost"
                        @click="form.reset()"
                    >
                        Descartar cambios
                    </Button>
                </div>
            </form>
        </section>
    </div>
</template>
