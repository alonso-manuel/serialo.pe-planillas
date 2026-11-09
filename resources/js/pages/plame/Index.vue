<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ChevronRight,
    Download,
    FileArchive,
    FileCheck2,
    Info,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import EstadoPeriodo from '@/components/periodos/EstadoPeriodo.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { show as verPeriodo } from '@/routes/periodos';
import { index, periodo as zipPeriodo, todas } from '@/routes/plame';
import type { EstadoPeriodo as Estado } from '@/types';

type Archivo = { nombre: string; sha256: string; bytes: number };
type Exportacion = {
    id: number;
    fecha: string;
    usuario: string | null;
    archivos: Archivo[];
};

const props = defineProps<{
    anio: number;
    mes: number;
    etiqueta: string;
    empresas: {
        id: number;
        razon_social: string;
        ruc: string;
        periodo: {
            id: number;
            estado: Estado;
            estado_etiqueta: string;
            trabajadores: number;
        } | null;
        listo: boolean;
        ultima: Exportacion | null;
    }[];
    historial: (Exportacion & { empresa: string | null; mes: string })[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'PLAME', href: index() }],
    },
});

const mesElegido = ref(`${props.anio}-${String(props.mes).padStart(2, '0')}`);

function cambiarMes(): void {
    const [anio, mes] = mesElegido.value.split('-').map(Number);

    if (anio && mes) {
        router.get(index.url(), { anio, mes }, { preserveScroll: true });
    }
}

const listas = computed(() => props.empresas.filter((e) => e.listo).length);

const abiertos = ref(new Set<number>());

function alternar(id: number): void {
    if (abiertos.value.has(id)) {
        abiertos.value.delete(id);
    } else {
        abiertos.value.add(id);
    }
}
</script>

<template>
    <Head title="PLAME" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading
                title="PLAME"
                description="Archivos de importación del PDT PLAME (formulario 0601): .rem, .jor, .snl y .toc."
            />
            <div class="flex flex-wrap items-end gap-2">
                <div class="grid gap-1.5">
                    <Label for="mes">Mes</Label>
                    <Input
                        id="mes"
                        v-model="mesElegido"
                        type="month"
                        class="w-44"
                        @change="cambiarMes"
                    />
                </div>
                <Button :disabled="listas === 0" as-child>
                    <a
                        v-if="listas > 0"
                        :href="todas.url({ query: { anio, mes } })"
                    >
                        <FileArchive />
                        Descargar todas ({{ listas }})
                    </a>
                    <span v-else>
                        <FileArchive />
                        Descargar todas
                    </span>
                </Button>
            </div>
        </div>

        <p
            class="flex items-start gap-2 rounded-md bg-muted/50 p-3 text-sm text-muted-foreground"
        >
            <Info class="mt-0.5 size-4 shrink-0" />
            Solo se exportan los periodos cerrados. Cada descarga queda
            registrada con la huella (SHA-256) de cada archivo para saber
            exactamente qué se declaró.
        </p>

        <div class="overflow-x-auto rounded-lg border">
            <table class="w-full min-w-[720px] text-sm">
                <thead class="bg-muted/40 text-xs text-muted-foreground">
                    <tr>
                        <th class="px-4 py-2 text-left font-medium">Empresa</th>
                        <th class="px-4 py-2 text-left font-medium">
                            {{ etiqueta }}
                        </th>
                        <th class="px-4 py-2 text-left font-medium">
                            Última exportación
                        </th>
                        <th class="w-44 px-4 py-2" />
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="e in empresas"
                        :key="e.id"
                        class="border-t align-middle"
                    >
                        <td class="px-4 py-3">
                            <p class="font-medium">{{ e.razon_social }}</p>
                            <p class="text-xs text-muted-foreground">
                                RUC {{ e.ruc }}
                            </p>
                        </td>
                        <td class="px-4 py-3">
                            <div
                                v-if="e.periodo"
                                class="flex items-center gap-2"
                            >
                                <EstadoPeriodo
                                    :estado="e.periodo.estado"
                                    :etiqueta="e.periodo.estado_etiqueta"
                                />
                                <span class="text-xs text-muted-foreground">
                                    {{ e.periodo.trabajadores }}
                                    {{
                                        e.periodo.trabajadores === 1
                                            ? 'trabajador'
                                            : 'trabajadores'
                                    }}
                                </span>
                            </div>
                            <span v-else class="text-xs text-muted-foreground"
                                >Sin periodo abierto</span
                            >
                        </td>
                        <td class="px-4 py-3 text-xs">
                            <template v-if="e.ultima">
                                <p>{{ e.ultima.fecha }}</p>
                                <p class="text-muted-foreground">
                                    {{ e.ultima.usuario ?? '—' }}
                                </p>
                            </template>
                            <span v-else class="text-muted-foreground"
                                >Nunca</span
                            >
                        </td>
                        <td class="px-4 py-3 text-right">
                            <Button
                                v-if="e.listo && e.periodo"
                                variant="outline"
                                size="sm"
                                as-child
                            >
                                <a :href="zipPeriodo.url(e.periodo.id)">
                                    <Download />
                                    Descargar zip
                                </a>
                            </Button>
                            <Link
                                v-else-if="e.periodo"
                                :href="verPeriodo(e.periodo.id)"
                                class="inline-flex items-center gap-1 text-xs text-muted-foreground hover:text-foreground"
                            >
                                {{
                                    e.periodo.trabajadores === 0
                                        ? 'Calcular planilla'
                                        : 'Cerrar el periodo'
                                }}
                                <ChevronRight class="size-3.5" />
                            </Link>
                        </td>
                    </tr>
                    <tr v-if="empresas.length === 0">
                        <td
                            colspan="4"
                            class="px-4 py-10 text-center text-muted-foreground"
                        >
                            No tienes empresas activas asignadas.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <section v-if="historial.length" class="grid gap-2">
            <h2 class="font-medium">Historial de exportaciones</h2>
            <ul class="divide-y rounded-lg border text-sm">
                <li v-for="x in historial" :key="x.id">
                    <button
                        type="button"
                        class="flex w-full items-center gap-3 px-4 py-2.5 text-left hover:bg-muted/40"
                        :aria-expanded="abiertos.has(x.id)"
                        @click="alternar(x.id)"
                    >
                        <ChevronRight
                            class="size-4 shrink-0 text-muted-foreground transition-transform"
                            :class="{ 'rotate-90': abiertos.has(x.id) }"
                        />
                        <FileCheck2 class="size-4 shrink-0 text-green-600" />
                        <span class="flex-1">
                            <span class="font-medium">{{ x.empresa }}</span>
                            · {{ x.mes }}
                        </span>
                        <span class="text-xs text-muted-foreground"
                            >{{ x.fecha }} · {{ x.usuario ?? '—' }}</span
                        >
                    </button>
                    <div
                        v-if="abiertos.has(x.id)"
                        class="overflow-x-auto px-4 pb-3 pl-14"
                    >
                        <table class="w-full text-xs">
                            <tbody>
                                <tr v-for="a in x.archivos" :key="a.nombre">
                                    <td class="py-1 pr-4 font-mono">
                                        {{ a.nombre }}
                                    </td>
                                    <td class="py-1 pr-4 text-muted-foreground">
                                        {{ a.bytes }} bytes
                                    </td>
                                    <td
                                        class="py-1 font-mono break-all text-muted-foreground"
                                        :title="'SHA-256 ' + a.sha256"
                                    >
                                        {{ a.sha256 }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </li>
            </ul>
        </section>
    </div>
</template>
