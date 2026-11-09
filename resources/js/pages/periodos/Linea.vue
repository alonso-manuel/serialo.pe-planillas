<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Calculator,
    FileText,
    ChevronLeft,
    ChevronRight,
    TriangleAlert,
} from '@lucide/vue';
import { onBeforeUnmount, onMounted, ref } from 'vue';
import BloqueConceptos from '@/components/periodos/BloqueConceptos.vue';
import EstadoPeriodo from '@/components/periodos/EstadoPeriodo.vue';
import { Button } from '@/components/ui/button';
import { formatoMonto } from '@/lib/montos';
import { calcular, index, show } from '@/routes/periodos';
import { boleta, show as verLinea } from '@/routes/periodos/lineas';
import { edit as editarTrabajador } from '@/routes/trabajadores';
import type { DetalleLinea } from '@/types';

const props = defineProps<DetalleLinea>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Periodos', href: index() }],
    },
});

const BLOQUES_IZQUIERDA = ['ingresos'] as const;
const BLOQUES_DERECHA = [
    'aportes_trabajador',
    'descuentos',
    'aportes_empleador',
] as const;

/* ---------- Calcular desde el detalle ---------- */
const calculando = ref(false);

function calcularPlanilla(): void {
    calculando.value = true;
    router.post(
        calcular.url(props.periodo.id),
        {},
        { onFinish: () => (calculando.value = false) },
    );
}

/* ---------- Navegación con ← → (fuera de campos de texto) ---------- */
function alPresionar(e: KeyboardEvent): void {
    const destino =
        e.key === 'ArrowLeft'
            ? props.anterior
            : e.key === 'ArrowRight'
              ? props.siguiente
              : null;

    if (
        destino === null ||
        e.altKey ||
        e.ctrlKey ||
        e.metaKey ||
        (e.target as HTMLElement | null)?.closest('input, textarea, select')
    ) {
        return;
    }

    router.visit(verLinea.url([props.periodo.id, destino]), {
        preserveScroll: true,
    });
}

onMounted(() => window.addEventListener('keydown', alPresionar));
onBeforeUnmount(() => window.removeEventListener('keydown', alPresionar));
</script>

<template>
    <Head :title="`${trabajador.nombre} · ${periodo.etiqueta}`" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <!-- Encabezado y navegación -->
        <div class="flex flex-wrap items-center justify-between gap-3">
            <Link
                :href="show(periodo.id)"
                class="inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground"
            >
                <ArrowLeft class="size-4" />
                Planilla de {{ periodo.etiqueta }}
            </Link>

            <nav class="flex items-center gap-2" aria-label="Trabajadores">
                <Button
                    variant="outline"
                    size="icon"
                    :disabled="anterior === null"
                    :as="anterior === null ? 'button' : Link"
                    :href="
                        anterior === null
                            ? undefined
                            : verLinea.url([periodo.id, anterior])
                    "
                    preserve-scroll
                    aria-label="Trabajador anterior"
                >
                    <ChevronLeft />
                </Button>
                <span class="text-sm text-muted-foreground tabular-nums">
                    {{ posicion }} de {{ total }}
                </span>
                <Button
                    variant="outline"
                    size="icon"
                    :disabled="siguiente === null"
                    :as="siguiente === null ? 'button' : Link"
                    :href="
                        siguiente === null
                            ? undefined
                            : verLinea.url([periodo.id, siguiente])
                    "
                    preserve-scroll
                    aria-label="Trabajador siguiente"
                >
                    <ChevronRight />
                </Button>
            </nav>
        </div>

        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="grid gap-1">
                <h1 class="text-xl font-semibold tracking-tight">
                    {{ trabajador.nombre }}
                </h1>
                <p class="text-sm text-muted-foreground">
                    {{ trabajador.documento }}
                    <template v-if="trabajador.cargo">
                        · {{ trabajador.cargo }}</template
                    >
                    ·
                    <Link
                        :href="editarTrabajador(trabajador.id)"
                        class="underline-offset-2 hover:underline"
                        >Ver ficha</Link
                    >
                </p>
            </div>
            <div class="flex items-center gap-3">
                <EstadoPeriodo
                    :estado="periodo.estado"
                    :etiqueta="periodo.estado_etiqueta"
                />
                <Button
                    v-if="
                        periodo.puede_editar &&
                        (!linea.calculada || linea.desactualizada)
                    "
                    :disabled="calculando"
                    @click="calcularPlanilla"
                >
                    <Calculator :class="{ 'animate-pulse': calculando }" />
                    {{ calculando ? 'Calculando…' : 'Calcular planilla' }}
                </Button>
                <Button v-if="linea.calculada" variant="outline" as-child>
                    <a
                        :href="boleta.url([periodo.id, linea.id])"
                        target="_blank"
                        rel="noopener"
                    >
                        <FileText />
                        Boleta PDF
                    </a>
                </Button>
            </div>
        </div>

        <!-- Datos del mes -->
        <dl
            class="grid grid-cols-2 gap-x-6 gap-y-3 rounded-lg border p-4 text-sm sm:grid-cols-3 lg:grid-cols-6"
        >
            <div>
                <dt class="text-xs text-muted-foreground">
                    Remuneración básica
                </dt>
                <dd class="font-medium tabular-nums">
                    S/ {{ formatoMonto(trabajador.remuneracion_basica) }}
                </dd>
            </div>
            <div>
                <dt class="text-xs text-muted-foreground">
                    Asignación familiar
                </dt>
                <dd class="font-medium">
                    {{ trabajador.asignacion_familiar ? 'Sí' : 'No' }}
                </dd>
            </div>
            <div>
                <dt class="text-xs text-muted-foreground">Pensión</dt>
                <dd class="font-medium">
                    {{ trabajador.pension }}
                    <span
                        v-if="trabajador.comision"
                        class="block text-xs font-normal text-muted-foreground"
                        >{{ trabajador.comision }}</span
                    >
                </dd>
            </div>
            <div>
                <dt class="text-xs text-muted-foreground">Seguro de salud</dt>
                <dd class="font-medium">{{ trabajador.seguro_salud }}</dd>
            </div>
            <div>
                <dt class="text-xs text-muted-foreground">Contrato</dt>
                <dd class="font-medium">
                    {{ trabajador.contrato }}
                    <span
                        v-if="periodo.regimen"
                        class="block text-xs font-normal text-muted-foreground"
                        >{{ periodo.regimen }}</span
                    >
                </dd>
            </div>
            <div>
                <dt class="text-xs text-muted-foreground">Ingreso / cese</dt>
                <dd class="font-medium tabular-nums">
                    {{ trabajador.fecha_ingreso }}
                    <span
                        v-if="trabajador.fecha_cese"
                        class="block text-xs font-normal text-muted-foreground"
                        >Cese {{ trabajador.fecha_cese }}</span
                    >
                </dd>
            </div>
            <div>
                <dt class="text-xs text-muted-foreground">Días laborados</dt>
                <dd class="font-medium tabular-nums">
                    {{ linea.dias_laborados }}
                </dd>
            </div>
            <div>
                <dt class="text-xs text-muted-foreground">Faltas</dt>
                <dd class="font-medium tabular-nums">
                    {{ linea.dias_no_laborados }}
                </dd>
            </div>
            <div>
                <dt class="text-xs text-muted-foreground">Días subsidiados</dt>
                <dd class="font-medium tabular-nums">
                    {{ linea.dias_subsidiados }}
                </dd>
            </div>
            <div>
                <dt class="text-xs text-muted-foreground">Horas extra 25 %</dt>
                <dd class="font-medium tabular-nums">
                    {{ linea.horas_extra_25 }}
                </dd>
            </div>
            <div>
                <dt class="text-xs text-muted-foreground">Horas extra 35 %</dt>
                <dd class="font-medium tabular-nums">
                    {{ linea.horas_extra_35 }}
                </dd>
            </div>
            <div v-if="trabajador.cuspp">
                <dt class="text-xs text-muted-foreground">CUSPP</dt>
                <dd class="font-mono font-medium">{{ trabajador.cuspp }}</dd>
            </div>
        </dl>

        <!-- Avisos -->
        <div
            v-if="!linea.calculada"
            class="flex items-start gap-2 rounded-lg border border-dashed p-6 text-sm text-muted-foreground"
        >
            <Calculator class="mt-0.5 size-4 shrink-0" />
            Este trabajador aún no tiene resultado. Calcula la planilla del
            periodo para ver el detalle de conceptos.
        </div>

        <p
            v-else-if="linea.desactualizada"
            class="flex items-start gap-2 rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-200"
            role="status"
        >
            <TriangleAlert class="mt-0.5 size-4 shrink-0" />
            Se cambiaron novedades después del último cálculo ({{
                linea.calculado_en
            }}). Los montos pueden no estar al día: vuelve a calcular la
            planilla.
        </p>

        <!-- Detalle de conceptos -->
        <template v-if="linea.calculada">
            <div class="grid gap-6 lg:grid-cols-2">
                <div class="grid content-start gap-6">
                    <BloqueConceptos
                        v-for="clave in BLOQUES_IZQUIERDA"
                        :key="clave"
                        :grupo="grupos[clave]"
                    />

                    <section
                        v-if="afectas"
                        class="grid gap-2 rounded-lg border p-4 text-sm"
                    >
                        <h2 class="font-medium">Remuneración afecta</h2>
                        <dl class="grid grid-cols-3 gap-3">
                            <div>
                                <dt class="text-xs text-muted-foreground">
                                    EsSalud
                                </dt>
                                <dd class="tabular-nums">
                                    {{ formatoMonto(afectas.essalud) }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-xs text-muted-foreground">
                                    Pensión
                                </dt>
                                <dd class="tabular-nums">
                                    {{ formatoMonto(afectas.pension) }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-xs text-muted-foreground">
                                    Renta de quinta
                                </dt>
                                <dd class="tabular-nums">
                                    {{ formatoMonto(afectas.quinta) }}
                                </dd>
                            </div>
                        </dl>
                        <p class="text-xs text-muted-foreground">
                            Suma de los ingresos afectos a cada aporte. La base
                            de cálculo puede diferir: EsSalud toma como mínimo
                            la RMV y la prima de seguro AFP tiene tope en la
                            remuneración máxima asegurable.
                        </p>
                    </section>
                </div>

                <div class="grid content-start gap-6">
                    <BloqueConceptos
                        v-for="clave in BLOQUES_DERECHA"
                        :key="clave"
                        :grupo="grupos[clave]"
                    />
                </div>
            </div>

            <!-- Resumen -->
            <div
                class="grid gap-4 rounded-lg border bg-muted/30 p-4 sm:grid-cols-3"
            >
                <div>
                    <p class="text-xs text-muted-foreground">Total ingresos</p>
                    <p class="text-lg font-medium tabular-nums">
                        S/ {{ formatoMonto(linea.total_ingresos) }}
                    </p>
                </div>
                <div>
                    <p class="text-xs text-muted-foreground">
                        Total descuentos (aportes y otros)
                    </p>
                    <p class="text-lg font-medium tabular-nums">
                        S/ {{ formatoMonto(linea.total_descuentos) }}
                    </p>
                </div>
                <div>
                    <p class="text-xs text-muted-foreground">Neto a pagar</p>
                    <p
                        class="text-2xl font-semibold tabular-nums"
                        :class="{
                            'text-muted-foreground line-through':
                                linea.desactualizada,
                        }"
                    >
                        S/ {{ formatoMonto(linea.neto_pagar) }}
                    </p>
                </div>
                <p class="text-xs text-muted-foreground sm:col-span-3">
                    Aportes del empleador (no se descuentan al trabajador): S/
                    {{ formatoMonto(linea.total_aportes_empleador) }}. Calculado
                    el {{ linea.calculado_en }}.
                </p>
            </div>
        </template>
    </div>
</template>
