<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    CircleCheck,
    CircleX,
    Download,
    FileSpreadsheet,
    TriangleAlert,
    Upload,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { importar, index } from '@/routes/trabajadores';
import {
    cancelar,
    confirmar,
    plantilla,
    store,
} from '@/routes/trabajadores/importar';
import type { ImportacionTrabajadores } from '@/types';

const props = defineProps<{
    empresa: string;
    importacion: ImportacionTrabajadores | null;
    maximo: number;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Trabajadores', href: index() },
            { title: 'Importar', href: importar() },
        ],
    },
});

/* ---------- Subir ---------- */
const archivo = ref<HTMLInputElement | null>(null);
const subida = useForm<{ archivo: File | null }>({ archivo: null });

function subir(evento: Event): void {
    const elegido = (evento.target as HTMLInputElement).files?.[0];

    if (!elegido) {
        return;
    }

    subida.archivo = elegido;
    subida.post(store.url(), {
        preserveScroll: true,
        onFinish: () => {
            subida.reset();
            if (archivo.value) {
                archivo.value.value = '';
            }
        },
    });
}

/* ---------- Vista previa ---------- */
const filtro = ref<'todas' | 'errores' | 'avisos'>('todas');
const filas = computed(() => {
    const todas = props.importacion?.filas ?? [];

    return filtro.value === 'errores'
        ? todas.filter((f) => f.errores.length > 0)
        : filtro.value === 'avisos'
          ? todas.filter((f) => f.errores.length === 0 && f.avisos.length > 0)
          : todas;
});
const conAvisos = computed(
    () =>
        props.importacion?.filas.filter(
            (f) => f.errores.length === 0 && f.avisos.length > 0,
        ).length ?? 0,
);

const confirmando = ref(false);

function confirmarImportacion(): void {
    router.post(
        confirmar.url(),
        {},
        {
            onStart: () => (confirmando.value = true),
            onFinish: () => (confirmando.value = false),
        },
    );
}

function cancelarImportacion(): void {
    router.delete(cancelar.url(), { preserveScroll: true });
}

const soles = new Intl.NumberFormat('es-PE', {
    style: 'currency',
    currency: 'PEN',
});
</script>

<template>
    <Head title="Importar trabajadores" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <Heading
            title="Importar trabajadores"
            :description="`Registro masivo de trabajadores nuevos en ${empresa} desde Excel.`"
        />

        <!-- Pasos -->
        <ol v-if="!importacion" class="grid max-w-3xl gap-4 sm:grid-cols-3">
            <li class="rounded-lg border p-4">
                <p class="text-xs font-medium text-muted-foreground">Paso 1</p>
                <p class="mt-1 font-medium">Descarga la plantilla</p>
                <p class="mt-1 text-sm text-muted-foreground">
                    Incluye una hoja de instrucciones con los valores aceptados.
                </p>
                <Button variant="outline" size="sm" class="mt-3" as-child>
                    <a :href="plantilla.url()"><Download />Plantilla Excel</a>
                </Button>
            </li>
            <li class="rounded-lg border p-4">
                <p class="text-xs font-medium text-muted-foreground">Paso 2</p>
                <p class="mt-1 font-medium">Llénala</p>
                <p class="mt-1 text-sm text-muted-foreground">
                    Una fila por trabajador, hasta {{ maximo }}. Borra las filas
                    de ejemplo.
                </p>
            </li>
            <li class="rounded-lg border p-4">
                <p class="text-xs font-medium text-muted-foreground">Paso 3</p>
                <p class="mt-1 font-medium">Súbela y revisa</p>
                <p class="mt-1 text-sm text-muted-foreground">
                    Verás cada fila antes de registrar nada.
                </p>
                <Button
                    size="sm"
                    class="mt-3"
                    :disabled="subida.processing"
                    @click="archivo?.click()"
                >
                    <Spinner v-if="subida.processing" />
                    <Upload v-else />
                    Subir archivo
                </Button>
            </li>
        </ol>
        <input
            ref="archivo"
            type="file"
            accept=".xlsx,.csv"
            class="hidden"
            @change="subir"
        />
        <InputError :message="subida.errors.archivo" />

        <!-- Vista previa -->
        <section v-if="importacion" class="grid gap-4">
            <div
                class="flex flex-wrap items-start justify-between gap-3 rounded-xl border bg-muted/30 p-4"
            >
                <div class="flex gap-3">
                    <FileSpreadsheet
                        class="mt-0.5 size-5 text-muted-foreground"
                    />
                    <div>
                        <p class="font-medium">{{ importacion.archivo }}</p>
                        <p class="text-sm text-muted-foreground">
                            <span class="text-emerald-700 dark:text-emerald-400"
                                >{{ importacion.validas }} listas para
                                registrar</span
                            >
                            <template v-if="importacion.con_errores">
                                ·
                                <span class="text-red-700 dark:text-red-400"
                                    >{{ importacion.con_errores }} con errores
                                    (no se registrarán)</span
                                >
                            </template>
                            <template v-if="conAvisos">
                                · {{ conAvisos }} con avisos</template
                            >
                        </p>
                    </div>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Button variant="outline" @click="cancelarImportacion"
                        >Cancelar</Button
                    >
                    <Button
                        variant="outline"
                        :disabled="subida.processing"
                        @click="archivo?.click()"
                    >
                        <Upload />
                        Subir otro archivo
                    </Button>
                    <Button
                        :disabled="importacion.validas === 0 || confirmando"
                        @click="confirmarImportacion"
                    >
                        <Spinner v-if="confirmando" />
                        Registrar {{ importacion.validas }} trabajadores
                    </Button>
                </div>
            </div>

            <div class="flex gap-2 text-sm">
                <Button
                    v-for="(nombre, clave) in {
                        todas: 'Todas',
                        errores: 'Con errores',
                        avisos: 'Con avisos',
                    }"
                    :key="clave"
                    size="sm"
                    :variant="filtro === clave ? 'secondary' : 'ghost'"
                    @click="filtro = clave"
                >
                    {{ nombre }}
                </Button>
            </div>

            <div class="overflow-x-auto rounded-lg border">
                <table class="w-full text-sm">
                    <thead class="bg-muted/50 text-left text-muted-foreground">
                        <tr>
                            <th class="w-16 px-3 py-2 font-medium">Línea</th>
                            <th class="px-3 py-2 font-medium">Trabajador</th>
                            <th class="px-3 py-2 font-medium">Cargo</th>
                            <th class="px-3 py-2 text-right font-medium">
                                Básico
                            </th>
                            <th class="px-3 py-2 font-medium">Pensión</th>
                            <th class="px-3 py-2 font-medium">Revisión</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="f in filas"
                            :key="f.linea"
                            class="border-t align-top"
                            :class="{
                                'bg-red-50 dark:bg-red-950/30':
                                    f.errores.length,
                            }"
                        >
                            <td
                                class="px-3 py-2 text-muted-foreground tabular-nums"
                            >
                                {{ f.linea }}
                            </td>
                            <td class="px-3 py-2">
                                <div class="font-medium">
                                    {{ f.nombre || '—' }}
                                </div>
                                <div class="text-xs text-muted-foreground">
                                    {{ f.documento }}
                                </div>
                            </td>
                            <td class="px-3 py-2">{{ f.cargo ?? '—' }}</td>
                            <td class="px-3 py-2 text-right tabular-nums">
                                {{
                                    /^\d+(\.\d+)?$/.test(f.remuneracion_basica)
                                        ? soles.format(
                                              Number(f.remuneracion_basica),
                                          )
                                        : f.remuneracion_basica
                                }}
                            </td>
                            <td class="px-3 py-2">{{ f.pension }}</td>
                            <td class="px-3 py-2 text-xs">
                                <p
                                    v-for="e in f.errores"
                                    :key="e"
                                    class="flex gap-1.5 text-red-700 dark:text-red-400"
                                >
                                    <CircleX
                                        class="mt-px size-3.5 shrink-0"
                                    />{{ e }}
                                </p>
                                <p
                                    v-for="a in f.avisos"
                                    :key="a"
                                    class="flex gap-1.5 text-amber-700 dark:text-amber-400"
                                >
                                    <TriangleAlert
                                        class="mt-px size-3.5 shrink-0"
                                    />{{ a }}
                                </p>
                                <p
                                    v-if="!f.errores.length && !f.avisos.length"
                                    class="flex gap-1.5 text-emerald-700 dark:text-emerald-400"
                                >
                                    <CircleCheck
                                        class="mt-px size-3.5 shrink-0"
                                    />Correcta
                                </p>
                            </td>
                        </tr>
                        <tr v-if="filas.length === 0">
                            <td
                                colspan="6"
                                class="px-3 py-8 text-center text-muted-foreground"
                            >
                                No hay filas en este filtro.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <Button v-if="!importacion" variant="ghost" class="w-fit" as-child>
            <Link :href="index()">Volver al listado</Link>
        </Button>
    </div>
</template>
