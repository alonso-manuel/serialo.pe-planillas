<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    Building2,
    CircleAlert,
    CircleCheck,
    Clock,
    FileUp,
    Landmark,
    Mail,
    TriangleAlert,
    Users,
    Wallet,
} from '@lucide/vue';
import { ref } from 'vue';
import EstadoPeriodo from '@/components/periodos/EstadoPeriodo.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatoMonto } from '@/lib/montos';
import { dashboard } from '@/routes';
import { index as afpnet } from '@/routes/afpnet';
import { index as periodos, show as verPeriodo } from '@/routes/periodos';
import { index as plame } from '@/routes/plame';
import type { EstadoPeriodo as Estado } from '@/types';

const props = defineProps<{
    anio: number;
    mes: number;
    etiqueta: string;
    resumen: {
        empresas: number;
        cerradas: number;
        pendientes: number;
        neto: string;
        trabajadores: number;
    };
    empresas: {
        id: number;
        razon_social: string;
        ruc: string;
        periodo: {
            id: number;
            estado: Estado;
            estado_etiqueta: string;
            trabajadores: number;
            neto: string | null;
            desactualizado: boolean;
        } | null;
        plame: string | null;
        afpnet: string | null;
        boletas: { enviadas: number; con_correo: number } | null;
    }[];
    alertas: {
        tipo: string;
        nivel: 'error' | 'aviso';
        mensaje: string;
        enlace: string | null;
    }[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Panel', href: dashboard() }],
    },
});

const mesElegido = ref(`${props.anio}-${String(props.mes).padStart(2, '0')}`);

function cambiarMes(): void {
    const [anio, mes] = mesElegido.value.split('-').map(Number);

    if (anio && mes) {
        router.get(dashboard.url(), { anio, mes }, { preserveScroll: true });
    }
}
</script>

<template>
    <Head title="Panel" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading
                title="Panel del estudio"
                :description="`Avance de ${etiqueta} en tus empresas.`"
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
                <Button variant="outline" as-child>
                    <Link :href="periodos()">
                        <Building2 />
                        Calcular empresas
                    </Link>
                </Button>
                <Button variant="outline" as-child>
                    <Link :href="plame({ query: { anio, mes } })">
                        <FileUp />
                        PLAME
                    </Link>
                </Button>
                <Button variant="outline" as-child>
                    <Link :href="afpnet({ query: { anio, mes } })">
                        <Landmark />
                        AFPnet
                    </Link>
                </Button>
            </div>
        </div>

        <!-- Resumen -->
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-lg border p-4">
                <p
                    class="flex items-center gap-2 text-sm text-muted-foreground"
                >
                    <CircleCheck class="size-4 text-green-600" />
                    Cerradas
                </p>
                <p class="mt-1 text-2xl font-semibold tabular-nums">
                    {{ resumen.cerradas }}
                    <span class="text-base font-normal text-muted-foreground"
                        >de {{ resumen.empresas }}</span
                    >
                </p>
            </div>
            <div class="rounded-lg border p-4">
                <p
                    class="flex items-center gap-2 text-sm text-muted-foreground"
                >
                    <Clock class="size-4 text-amber-600" />
                    Pendientes
                </p>
                <p class="mt-1 text-2xl font-semibold tabular-nums">
                    {{ resumen.pendientes }}
                </p>
            </div>
            <div class="rounded-lg border p-4">
                <p
                    class="flex items-center gap-2 text-sm text-muted-foreground"
                >
                    <Wallet class="size-4" />
                    Neto del mes
                </p>
                <p class="mt-1 text-2xl font-semibold tabular-nums">
                    S/ {{ formatoMonto(resumen.neto) }}
                </p>
            </div>
            <div class="rounded-lg border p-4">
                <p
                    class="flex items-center gap-2 text-sm text-muted-foreground"
                >
                    <Users class="size-4" />
                    Trabajadores en planilla
                </p>
                <p class="mt-1 text-2xl font-semibold tabular-nums">
                    {{ resumen.trabajadores }}
                </p>
            </div>
        </div>

        <!-- Alertas -->
        <section v-if="alertas.length" class="grid gap-2">
            <h2 class="font-medium">Por atender</h2>
            <ul class="grid gap-2">
                <li
                    v-for="(a, i) in alertas"
                    :key="i"
                    class="flex items-start gap-2 rounded-md border p-3 text-sm"
                    :class="
                        a.nivel === 'error'
                            ? 'border-red-300 bg-red-50 text-red-900 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200'
                            : 'border-amber-300 bg-amber-50 text-amber-900 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-200'
                    "
                >
                    <CircleAlert
                        v-if="a.nivel === 'error'"
                        class="mt-0.5 size-4 shrink-0"
                    />
                    <TriangleAlert v-else class="mt-0.5 size-4 shrink-0" />
                    <span class="flex-1">{{ a.mensaje }}</span>
                    <Link
                        v-if="a.enlace"
                        :href="a.enlace"
                        class="shrink-0 font-medium underline underline-offset-2"
                        >Resolver</Link
                    >
                </li>
            </ul>
        </section>

        <!-- Empresas -->
        <div class="overflow-x-auto rounded-lg border">
            <table class="w-full min-w-[820px] text-sm">
                <thead class="bg-muted/40 text-xs text-muted-foreground">
                    <tr>
                        <th class="px-4 py-2 text-left font-medium">Empresa</th>
                        <th class="px-4 py-2 text-left font-medium">
                            Planilla
                        </th>
                        <th class="px-4 py-2 text-right font-medium">Neto</th>
                        <th class="px-4 py-2 text-left font-medium">PLAME</th>
                        <th class="px-4 py-2 text-left font-medium">AFPnet</th>
                        <th class="px-4 py-2 text-left font-medium">Boletas</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="e in empresas"
                        :key="e.id"
                        class="border-t align-middle"
                    >
                        <td class="px-4 py-3">
                            <Link
                                v-if="e.periodo"
                                :href="verPeriodo(e.periodo.id)"
                                class="font-medium hover:underline"
                                >{{ e.razon_social }}</Link
                            >
                            <span v-else class="font-medium">{{
                                e.razon_social
                            }}</span>
                            <p class="text-xs text-muted-foreground">
                                RUC {{ e.ruc }}
                            </p>
                        </td>
                        <td class="px-4 py-3">
                            <div
                                v-if="e.periodo"
                                class="flex flex-wrap items-center gap-2"
                            >
                                <EstadoPeriodo
                                    :estado="e.periodo.estado"
                                    :etiqueta="e.periodo.estado_etiqueta"
                                />
                                <span class="text-xs text-muted-foreground"
                                    >{{ e.periodo.trabajadores }} trab.</span
                                >
                                <span
                                    v-if="e.periodo.desactualizado"
                                    class="text-xs text-amber-700 dark:text-amber-400"
                                    >cambios sin recalcular</span
                                >
                            </div>
                            <span v-else class="text-xs text-muted-foreground"
                                >Sin abrir</span
                            >
                        </td>
                        <td class="px-4 py-3 text-right tabular-nums">
                            {{
                                e.periodo?.neto
                                    ? formatoMonto(e.periodo.neto)
                                    : '—'
                            }}
                        </td>
                        <td class="px-4 py-3 text-xs">
                            <span
                                v-if="e.plame"
                                class="flex items-center gap-1 text-green-700 dark:text-green-400"
                                ><CircleCheck class="size-3.5" />
                                {{ e.plame }}</span
                            >
                            <span v-else class="text-muted-foreground"
                                >Pendiente</span
                            >
                        </td>
                        <td class="px-4 py-3 text-xs">
                            <span
                                v-if="e.afpnet"
                                class="flex items-center gap-1 text-green-700 dark:text-green-400"
                                ><CircleCheck class="size-3.5" />
                                {{ e.afpnet }}</span
                            >
                            <span v-else class="text-muted-foreground"
                                >Pendiente</span
                            >
                        </td>
                        <td class="px-4 py-3 text-xs">
                            <span
                                v-if="e.boletas && e.boletas.con_correo"
                                class="flex items-center gap-1"
                                :class="
                                    e.boletas.enviadas >= e.boletas.con_correo
                                        ? 'text-green-700 dark:text-green-400'
                                        : 'text-muted-foreground'
                                "
                            >
                                <Mail class="size-3.5" />
                                {{ e.boletas.enviadas }} de
                                {{ e.boletas.con_correo }}
                            </span>
                            <span v-else class="text-muted-foreground">—</span>
                        </td>
                    </tr>
                    <tr v-if="empresas.length === 0">
                        <td
                            colspan="6"
                            class="px-4 py-10 text-center text-muted-foreground"
                        >
                            No tienes empresas activas asignadas.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
