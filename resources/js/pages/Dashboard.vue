<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    ArrowDownRight,
    ArrowRight,
    ArrowUpRight,
    Building2,
    CircleAlert,
    CircleCheck,
    FileUp,
    Landmark,
    Mail,
    Minus,
    TriangleAlert,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import BarraComposicion from '@/components/graficos/BarraComposicion.vue';
import BarrasHorizontales from '@/components/graficos/BarrasHorizontales.vue';
import GraficoLineas from '@/components/graficos/GraficoLineas.vue';
import Sparkline from '@/components/graficos/Sparkline.vue';
import EstadoPeriodo from '@/components/periodos/EstadoPeriodo.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { aNumero, variacion } from '@/lib/graficos';
import { gruposNav } from '@/lib/modulos';
import { formatoMonto, sumarMontos } from '@/lib/montos';
import { dashboard } from '@/routes';
import { index as afpnet } from '@/routes/afpnet';
import { index as periodos, show as verPeriodo } from '@/routes/periodos';
import { index as plame } from '@/routes/plame';
import type { EstadoPeriodo as Estado } from '@/types';

type MesSerie = {
    anio: number;
    mes: number;
    etiqueta: string;
    costo: string | null;
    neto: string | null;
    aportes: string | null;
    trabajadores: number;
};

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
    serie: MesSerie[];
    composicion: {
        neto: string;
        pensiones: string;
        descuentos: string;
        aportes: string;
        total: string;
    };
    avance: {
        con_periodo: number;
        calculadas: number;
        cerradas: number;
        plame: number;
        afpnet: number;
        boletas: number;
    };
    movimiento: { altas: number; bajas: number; vigentes: number };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Panel', href: dashboard() }],
    },
});

const page = usePage();

const mesElegido = ref(`${props.anio}-${String(props.mes).padStart(2, '0')}`);

function cambiarMes(): void {
    const [anio, mes] = mesElegido.value.split('-').map(Number);

    if (anio && mes) {
        router.get(dashboard.url(), { anio, mes }, { preserveScroll: true });
    }
}

const soles = (v: string) => `S/ ${formatoMonto(v)}`;

// --- Indicadores -----------------------------------------------------------
const actual = computed(() => props.serie[props.serie.length - 1]);
const anterior = computed(() => props.serie[props.serie.length - 2] ?? null);
const nombreAnterior = computed(() => {
    const a = anterior.value;

    return a ? a.etiqueta.split(' ')[0].toLowerCase() : '';
});
const deltaCosto = computed(() =>
    variacion(actual.value?.costo ?? null, anterior.value?.costo ?? null),
);
const deltaTrabajadores = computed(() => {
    const a = actual.value?.trabajadores ?? 0;
    const b = anterior.value?.trabajadores ?? 0;

    return b === 0 || a === 0 ? null : a - b;
});
const hayHistoria = computed(() => props.serie.some((m) => m.costo !== null));

// --- Gráficos --------------------------------------------------------------
const seriesLineas = computed(() => [
    {
        clave: 'costo',
        nombre: 'Costo laboral',
        color: 'var(--serie-1)',
        valores: props.serie.map((m) => m.costo),
    },
    {
        clave: 'neto',
        nombre: 'Neto pagado',
        color: 'var(--serie-2)',
        valores: props.serie.map((m) => m.neto),
    },
    {
        clave: 'aportes',
        nombre: 'Aportes del empleador',
        color: 'var(--serie-3)',
        valores: props.serie.map((m) => m.aportes),
    },
]);

const segmentos = computed(() => [
    {
        clave: 'neto',
        nombre: 'Neto a pagar a los trabajadores',
        valor: props.composicion.neto,
        color: 'var(--serie-1)',
    },
    {
        clave: 'pensiones',
        nombre: 'Retenciones de pensión (ONP / AFP)',
        valor: props.composicion.pensiones,
        color: 'var(--serie-2)',
    },
    {
        clave: 'aportes',
        nombre: 'Aportes del empleador (EsSalud / SIS)',
        valor: props.composicion.aportes,
        color: 'var(--serie-3)',
    },
    {
        clave: 'descuentos',
        nombre: 'Renta de quinta y otros descuentos',
        valor: props.composicion.descuentos,
        color: 'var(--serie-4)',
    },
]);
const segmentoActivo = ref<string | null>(null);

const TOPE_EMPRESAS = 6;
const netoPorEmpresa = computed(() => {
    const con = props.empresas
        .filter((e) => e.periodo?.neto)
        .map((e) => ({
            clave: e.id,
            etiqueta: e.razon_social,
            valor: e.periodo!.neto as string,
            href: verPeriodo.url(e.periodo!.id),
        }))
        .sort((a, b) => (aNumero(b.valor) ?? 0) - (aNumero(a.valor) ?? 0));

    if (con.length <= TOPE_EMPRESAS + 1) {
        return con;
    }

    const resto = con.slice(TOPE_EMPRESAS);

    return [
        ...con.slice(0, TOPE_EMPRESAS),
        {
            clave: 'otras',
            etiqueta: `Otras ${resto.length} empresas`,
            valor: sumarMontos(resto.map((r) => r.valor)),
            href: null,
        },
    ];
});

const etapas = computed(() => {
    const total = props.resumen.empresas;

    return [
        { nombre: 'Periodo abierto', hechas: props.avance.con_periodo },
        { nombre: 'Planilla calculada', hechas: props.avance.calculadas },
        { nombre: 'Periodo cerrado', hechas: props.avance.cerradas },
        { nombre: 'PLAME exportado', hechas: props.avance.plame },
        { nombre: 'AFPnet exportado', hechas: props.avance.afpnet },
        { nombre: 'Boletas enviadas', hechas: props.avance.boletas },
    ].map((e) => ({
        ...e,
        total,
        pct: total ? Math.round((e.hechas / total) * 100) : 0,
    }));
});

// --- Módulos ---------------------------------------------------------------
const descripciones: Record<string, string> = {
    Trabajadores: 'Fichas, altas, ceses y carga desde Excel',
    Periodos: 'Novedades, cálculo y cierre del mes',
    Boletas: 'PDF por trabajador y envío por correo',
    Gratificaciones: 'Julio y diciembre con bonificación',
    CTS: 'Liquidación y constancias de mayo y noviembre',
    Vacaciones: 'Periodos, saldos y goces',
    PLAME: 'Archivos .rem, .jor, .snl y .toc',
    AFPnet: 'Archivo de aportes a las AFP',
    Reportes: 'Planilla, resumen, centralización y costo',
    Empresas: 'Datos, régimen, logo y establecimientos',
    Usuarios: 'Accesos, roles y empresas asignadas',
    Parámetros: 'RMV, UIT y tasas con vigencia',
    'Tasas AFP': 'Comisiones y primas de cada mes',
    Conceptos: 'Tabla 22 de SUNAT y cuentas contables',
    Auditoría: 'Quién cambió qué y cuándo',
};

const gruposModulos = computed(() =>
    gruposNav.filter(
        (g) =>
            g.title !== 'Inicio' && (!g.soloAdmin || page.props.auth.esAdmin),
    ),
);
</script>

<template>
    <Head title="Panel" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <!-- Encabezado y filtro -->
        <div class="flex flex-wrap items-end justify-between gap-4">
            <Heading
                title="Panel del estudio"
                :description="`Avance de ${etiqueta} en tus ${resumen.empresas} ${resumen.empresas === 1 ? 'empresa' : 'empresas'}.`"
            />
            <div class="flex flex-wrap items-end gap-2">
                <div class="grid gap-1.5">
                    <Label for="mes">Mes</Label>
                    <Input
                        id="mes"
                        v-model="mesElegido"
                        type="month"
                        class="w-48"
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

        <!-- Indicadores -->
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <section
                class="min-w-0 rounded-xl border bg-card p-5 md:col-span-2"
            >
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="min-w-0">
                        <h2 class="text-sm text-muted-foreground">
                            Costo laboral de {{ etiqueta.toLowerCase() }}
                        </h2>
                        <p
                            class="mt-1 text-4xl font-semibold tracking-tight break-words md:text-5xl"
                        >
                            <span
                                class="text-2xl font-normal text-muted-foreground md:text-3xl"
                                >S/</span
                            >
                            {{ formatoMonto(actual?.costo ?? '0.00') }}
                        </p>
                        <p
                            class="mt-2 flex items-center gap-1 text-sm text-muted-foreground"
                        >
                            <template v-if="deltaCosto">
                                <ArrowUpRight
                                    v-if="deltaCosto.startsWith('+')"
                                    class="size-4"
                                />
                                <ArrowDownRight v-else class="size-4" />
                                <span
                                    class="font-medium text-foreground tabular-nums"
                                    >{{ deltaCosto }} %</span
                                >
                                vs {{ nombreAnterior }}
                            </template>
                            <template v-else>
                                <Minus class="size-4" /> Sin mes anterior para
                                comparar
                            </template>
                        </p>
                    </div>
                    <Sparkline
                        :valores="serie.map((m) => aNumero(m.costo))"
                        :ancho="160"
                        :alto="48"
                        descripcion="Costo laboral de los últimos 12 meses"
                    />
                </div>
                <dl class="mt-4 grid grid-cols-2 gap-4 border-t pt-4 text-sm">
                    <div>
                        <dt class="text-muted-foreground">Neto a pagar</dt>
                        <dd class="mt-0.5 font-medium tabular-nums">
                            {{ soles(resumen.neto) }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-muted-foreground">
                            Aportes del empleador
                        </dt>
                        <dd class="mt-0.5 font-medium tabular-nums">
                            {{ soles(composicion.aportes) }}
                        </dd>
                    </div>
                </dl>
            </section>

            <section
                class="flex min-w-0 flex-col justify-between rounded-xl border bg-card p-5"
            >
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 class="text-sm text-muted-foreground">
                            Trabajadores en planilla
                        </h2>
                        <p class="mt-1 text-3xl font-semibold">
                            {{ resumen.trabajadores }}
                        </p>
                    </div>
                    <Sparkline
                        :valores="serie.map((m) => m.trabajadores || null)"
                        color="var(--serie-2)"
                        :ancho="96"
                        descripcion="Trabajadores calculados en los últimos 12 meses"
                    />
                </div>
                <p class="mt-3 text-sm text-muted-foreground">
                    <span
                        v-if="deltaTrabajadores !== null"
                        class="font-medium text-foreground tabular-nums"
                    >
                        {{
                            deltaTrabajadores === 0
                                ? 'Sin cambio'
                                : `${deltaTrabajadores > 0 ? '+' : ''}${deltaTrabajadores}`
                        }}
                    </span>
                    <template v-if="deltaTrabajadores !== null">
                        vs {{ nombreAnterior }} ·
                    </template>
                    {{ movimiento.altas }}
                    {{ movimiento.altas === 1 ? 'alta' : 'altas' }} ·
                    {{ movimiento.bajas }}
                    {{ movimiento.bajas === 1 ? 'cese' : 'ceses' }} en el mes
                </p>
            </section>

            <section
                class="flex min-w-0 flex-col justify-between rounded-xl border bg-card p-5"
            >
                <div>
                    <h2 class="text-sm text-muted-foreground">
                        Cierre del mes
                    </h2>
                    <p class="mt-1 text-3xl font-semibold">
                        {{ resumen.cerradas }}
                        <span
                            class="text-base font-normal text-muted-foreground"
                            >de {{ resumen.empresas }} empresas</span
                        >
                    </p>
                </div>
                <div class="mt-3 grid gap-1.5">
                    <div
                        class="h-2 overflow-hidden rounded-full bg-muted"
                        role="meter"
                        :aria-valuenow="resumen.cerradas"
                        aria-valuemin="0"
                        :aria-valuemax="resumen.empresas"
                        aria-label="Empresas con el periodo cerrado"
                    >
                        <div
                            class="h-full rounded-full bg-[var(--serie-1)]"
                            :style="{
                                width: `${resumen.empresas ? (resumen.cerradas / resumen.empresas) * 100 : 0}%`,
                            }"
                        />
                    </div>
                    <p class="text-sm text-muted-foreground">
                        {{ resumen.pendientes }}
                        {{
                            resumen.pendientes === 1
                                ? 'pendiente'
                                : 'pendientes'
                        }}
                    </p>
                </div>
            </section>
        </div>

        <!-- Alertas -->
        <section v-if="alertas.length" class="grid gap-2">
            <h2 class="font-medium">Por atender</h2>
            <ul class="grid gap-2 lg:grid-cols-2">
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

        <!-- Evolución y avance -->
        <div class="grid gap-4 lg:grid-cols-3">
            <section
                class="min-w-0 rounded-xl border bg-card p-5 lg:col-span-2"
            >
                <div class="mb-4">
                    <h2 class="font-medium">Evolución de la planilla</h2>
                    <p class="text-sm text-muted-foreground">
                        Últimos 12 meses, en soles, sumando todas tus empresas.
                    </p>
                </div>
                <GraficoLineas
                    v-if="hayHistoria"
                    :etiquetas="serie.map((m) => m.etiqueta)"
                    :series="seriesLineas"
                    :formato="soles"
                    prefijo-eje="S/ "
                    descripcion="Costo laboral, neto pagado y aportes del empleador por mes"
                />
                <p
                    v-else
                    class="py-16 text-center text-sm text-muted-foreground"
                >
                    Aún no hay planillas calculadas en los últimos 12 meses.
                </p>
            </section>

            <section class="min-w-0 rounded-xl border bg-card p-5">
                <div class="mb-4">
                    <h2 class="font-medium">
                        Avance de {{ etiqueta.toLowerCase() }}
                    </h2>
                    <p class="text-sm text-muted-foreground">
                        Empresas que completaron cada paso.
                    </p>
                </div>
                <ol class="grid gap-4">
                    <li
                        v-for="(e, i) in etapas"
                        :key="e.nombre"
                        class="grid gap-1.5"
                    >
                        <div
                            class="flex items-center justify-between gap-2 text-sm"
                        >
                            <span class="flex items-center gap-2">
                                <span
                                    class="flex size-5 items-center justify-center rounded-full text-[11px] font-medium"
                                    :class="
                                        e.total && e.hechas >= e.total
                                            ? 'bg-[var(--serie-1)] text-white'
                                            : 'bg-muted text-muted-foreground'
                                    "
                                >
                                    <CircleCheck
                                        v-if="e.total && e.hechas >= e.total"
                                        class="size-3.5"
                                    />
                                    <template v-else>{{ i + 1 }}</template>
                                </span>
                                {{ e.nombre }}
                            </span>
                            <span class="text-muted-foreground tabular-nums"
                                >{{ e.hechas }} de {{ e.total }}</span
                            >
                        </div>
                        <div
                            class="ml-7 h-1.5 overflow-hidden rounded-full bg-muted"
                            role="meter"
                            :aria-valuenow="e.hechas"
                            aria-valuemin="0"
                            :aria-valuemax="e.total"
                            :aria-label="e.nombre"
                        >
                            <div
                                class="h-full rounded-full bg-[var(--serie-1)]"
                                :style="{ width: `${e.pct}%` }"
                            />
                        </div>
                    </li>
                </ol>
            </section>
        </div>

        <!-- Composición y empresas -->
        <div class="grid gap-4 lg:grid-cols-2">
            <section class="min-w-0 rounded-xl border bg-card p-5">
                <div class="mb-4">
                    <h2 class="font-medium">¿A dónde va el costo laboral?</h2>
                    <p class="text-sm text-muted-foreground">
                        Reparto de {{ etiqueta.toLowerCase() }}, en soles.
                    </p>
                </div>
                <BarraComposicion
                    v-if="composicion.total !== '0.00'"
                    v-model:activo="segmentoActivo"
                    :segmentos="segmentos"
                    :total="composicion.total"
                    :formato="formatoMonto"
                    descripcion="Reparto del costo laboral del mes"
                />
                <p
                    v-else
                    class="py-10 text-center text-sm text-muted-foreground"
                >
                    Calcula la planilla del mes para ver el reparto.
                </p>
            </section>

            <section class="min-w-0 rounded-xl border bg-card p-5">
                <div class="mb-4">
                    <h2 class="font-medium">Neto por empresa</h2>
                    <p class="text-sm text-muted-foreground">
                        Planillas calculadas de {{ etiqueta.toLowerCase() }}, en
                        soles.
                    </p>
                </div>
                <BarrasHorizontales
                    v-if="netoPorEmpresa.length"
                    :filas="netoPorEmpresa"
                    :formato="formatoMonto"
                    descripcion="Neto a pagar por empresa"
                />
                <p
                    v-else
                    class="py-10 text-center text-sm text-muted-foreground"
                >
                    Ninguna empresa tiene la planilla calculada este mes.
                </p>
            </section>
        </div>

        <!-- Empresas -->
        <section class="grid gap-2">
            <h2 class="font-medium">Empresas</h2>
            <div class="overflow-x-auto rounded-xl border bg-card">
                <table class="w-full min-w-[820px] text-sm">
                    <thead class="bg-muted/40 text-xs text-muted-foreground">
                        <tr>
                            <th class="px-4 py-2 text-left font-medium">
                                Empresa
                            </th>
                            <th class="px-4 py-2 text-left font-medium">
                                Planilla
                            </th>
                            <th class="px-4 py-2 text-right font-medium">
                                Neto
                            </th>
                            <th class="px-4 py-2 text-left font-medium">
                                PLAME
                            </th>
                            <th class="px-4 py-2 text-left font-medium">
                                AFPnet
                            </th>
                            <th class="px-4 py-2 text-left font-medium">
                                Boletas
                            </th>
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
                                        >{{
                                            e.periodo.trabajadores
                                        }}
                                        trab.</span
                                    >
                                    <span
                                        v-if="e.periodo.desactualizado"
                                        class="text-xs text-amber-700 dark:text-amber-400"
                                        >cambios sin recalcular</span
                                    >
                                </div>
                                <span
                                    v-else
                                    class="text-xs text-muted-foreground"
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
                                        e.boletas.enviadas >=
                                        e.boletas.con_correo
                                            ? 'text-green-700 dark:text-green-400'
                                            : 'text-muted-foreground'
                                    "
                                >
                                    <Mail class="size-3.5" />
                                    {{ e.boletas.enviadas }} de
                                    {{ e.boletas.con_correo }}
                                </span>
                                <span v-else class="text-muted-foreground"
                                    >—</span
                                >
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
        </section>

        <!-- Módulos -->
        <section class="grid gap-3">
            <h2 class="font-medium">Módulos</h2>
            <div class="gap-4 sm:columns-2 xl:columns-3">
                <div
                    v-for="g in gruposModulos"
                    :key="g.title"
                    class="mb-4 break-inside-avoid rounded-xl border bg-card p-4"
                >
                    <h3
                        class="mb-2 text-xs font-medium tracking-wide text-muted-foreground uppercase"
                    >
                        {{ g.title }}
                    </h3>
                    <ul class="grid gap-1">
                        <li v-for="item in g.items" :key="item.title">
                            <Link
                                v-if="item.href"
                                :href="item.href"
                                class="group flex items-center gap-3 rounded-lg p-2 transition-colors hover:bg-muted/60"
                            >
                                <span
                                    class="flex size-9 shrink-0 items-center justify-center rounded-lg border bg-background"
                                >
                                    <component :is="item.icon" class="size-4" />
                                </span>
                                <span class="min-w-0 flex-1">
                                    <span class="block text-sm font-medium">{{
                                        item.title
                                    }}</span>
                                    <span
                                        class="block truncate text-xs text-muted-foreground"
                                        >{{
                                            descripciones[item.title] ?? ''
                                        }}</span
                                    >
                                </span>
                                <ArrowRight
                                    class="size-4 shrink-0 text-muted-foreground opacity-0 transition-opacity group-hover:opacity-100"
                                />
                            </Link>
                        </li>
                    </ul>
                </div>
            </div>
        </section>
    </div>
</template>
