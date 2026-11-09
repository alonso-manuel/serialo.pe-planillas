<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import {
    Calculator,
    CircleAlert,
    FileArchive,
    FileText,
    Lock,
    LockOpen,
    RefreshCw,
    Trash2,
    TriangleAlert,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import EstadoPeriodo from '@/components/periodos/EstadoPeriodo.vue';
import EnvioBoletas from '@/components/periodos/EnvioBoletas.vue';
import GratificacionPeriodo from '@/components/periodos/GratificacionPeriodo.vue';
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
import InputError from '@/components/InputError.vue';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { formatoMonto, sumarMontos } from '@/lib/montos';
import {
    calcular,
    cerrar,
    destroy,
    index,
    reabrir,
    sincronizar,
} from '@/routes/periodos';
import {
    pdf as boletasPdf,
    zip as boletasZip,
} from '@/routes/periodos/boletas';
import { index as parametros } from '@/routes/parametros';
import { index as tasasAfp } from '@/routes/tasas-afp';
import type {
    CierrePeriodo,
    DetalleGratificacion,
    EnviosBoletas,
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
    cierre: CierrePeriodo;
    gratificacion: DetalleGratificacion | null;
    envios: EnviosBoletas;
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

/* ---------- Cerrar / reabrir (solo admin) ---------- */
const dialogoCerrar = ref(false);
const netoTotal = computed(() =>
    sumarMontos(props.lineas.map((l) => l.neto_pagar)),
);
const formCerrar = useForm({});

function cerrarPeriodo(): void {
    formCerrar.post(cerrar.url(props.periodo.id), {
        preserveScroll: true,
        preserveState: false,
    });
}

const dialogoReabrir = ref(false);
const formReabrir = useForm({ motivo: '' });

function abrirReabrir(): void {
    formReabrir.reset();
    formReabrir.clearErrors();
    dialogoReabrir.value = true;
}

function reabrirPeriodo(): void {
    formReabrir.post(reabrir.url(props.periodo.id), {
        preserveScroll: true,
        // Con errores se queda el diálogo; si sale bien se recarga la página entera.
        preserveState: (page) => Object.keys(page.props.errors).length > 0,
    });
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
            <div
                v-if="
                    estado !== 'abierto' ||
                    lineas.some((l) => l.neto_pagar !== null)
                "
                class="flex flex-wrap gap-2 sm:order-last"
            >
                <Button variant="outline" as-child>
                    <a
                        :href="boletasPdf.url(periodo.id)"
                        target="_blank"
                        rel="noopener"
                        title="Todas las boletas en un PDF, una por hoja, para imprimir"
                    >
                        <FileText />
                        Boletas PDF
                    </a>
                </Button>
                <Button variant="outline" as-child>
                    <a
                        :href="boletasZip.url(periodo.id)"
                        title="Un PDF por trabajador, en un archivo zip"
                    >
                        <FileArchive />
                        Boletas (zip)
                    </a>
                </Button>
            </div>
            <div
                v-if="
                    cierre.puede_reabrir ||
                    (cierre.puede_cerrar && estado === 'calculado')
                "
                class="flex flex-wrap gap-2 sm:order-last"
            >
                <Button
                    v-if="cierre.puede_cerrar && estado === 'calculado'"
                    variant="outline"
                    @click="dialogoCerrar = true"
                >
                    <Lock />
                    Cerrar periodo
                </Button>
                <Button
                    v-if="cierre.puede_reabrir"
                    variant="outline"
                    @click="abrirReabrir"
                >
                    <LockOpen />
                    Reabrir periodo
                </Button>
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
            class="flex items-center gap-2 rounded-md bg-muted/50 p-3 text-sm text-muted-foreground"
        >
            <Lock class="size-4 shrink-0" />
            <span>
                Periodo cerrado
                <template v-if="cierre.cerrado_por">
                    por {{ cierre.cerrado_por }}</template
                >
                <template v-if="cierre.cerrado_en">
                    el {{ cierre.cerrado_en }}</template
                >. Las novedades y los montos son solo de lectura<template
                    v-if="!cierre.puede_reabrir"
                    >; para corregirlo, pide al administrador que lo
                    reabra</template
                >.
            </span>
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

        <GratificacionPeriodo
            v-if="gratificacion"
            :detalle="gratificacion"
            :mes="periodo.etiqueta"
        />

        <EnvioBoletas
            v-if="envios.filas.length"
            :envios="envios"
            :periodo-id="periodo.id"
            :mes="periodo.etiqueta"
        />
    </div>

    <Dialog v-model:open="dialogoCerrar">
        <DialogContent>
            <DialogHeader>
                <DialogTitle
                    >¿Cerrar la planilla de {{ periodo.etiqueta }}?</DialogTitle
                >
                <DialogDescription>
                    {{ lineas.length }}
                    {{ lineas.length === 1 ? 'trabajador' : 'trabajadores' }},
                    neto total S/ {{ formatoMonto(netoTotal) }}. Una vez cerrado
                    nadie podrá editar novedades ni recalcular; solo el
                    administrador puede reabrirlo indicando el motivo.
                </DialogDescription>
            </DialogHeader>
            <p
                v-if="cierre.anterior_sin_cerrar"
                class="flex items-start gap-2 rounded-md border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-300"
            >
                <TriangleAlert class="mt-0.5 size-4 shrink-0" />
                {{ cierre.anterior_sin_cerrar }} aún no está cerrado. Puedes
                continuar, pero lo usual es cerrar los meses en orden.
            </p>
            <DialogFooter>
                <DialogClose as-child>
                    <Button variant="outline">Cancelar</Button>
                </DialogClose>
                <Button
                    :disabled="formCerrar.processing"
                    @click="cerrarPeriodo"
                >
                    <Spinner v-if="formCerrar.processing" />
                    <Lock v-else />
                    Cerrar periodo
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <Dialog v-model:open="dialogoReabrir">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Reabrir {{ periodo.etiqueta }}</DialogTitle>
                <DialogDescription>
                    El periodo vuelve a «Calculado» con los mismos montos. Si
                    cambias alguna novedad pasará a «Abierto» y deberás
                    recalcular. El motivo queda en la auditoría.
                </DialogDescription>
            </DialogHeader>
            <p
                v-if="cierre.posteriores_cerrados.length"
                class="flex items-start gap-2 rounded-md border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-300"
            >
                <TriangleAlert class="mt-0.5 size-4 shrink-0" />
                Ya están cerrados meses posteriores:
                {{ cierre.posteriores_cerrados.join(', ') }}. Si cambias este
                mes, revisa si también deben corregirse.
            </p>
            <form
                id="form-reabrir"
                class="grid gap-2"
                @submit.prevent="reabrirPeriodo"
            >
                <Label for="motivo">Motivo</Label>
                <textarea
                    id="motivo"
                    v-model="formReabrir.motivo"
                    rows="3"
                    maxlength="500"
                    required
                    placeholder="Ej.: rectificatoria del PLAME por un bono no registrado"
                    class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                />
                <InputError :message="formReabrir.errors.motivo" />
            </form>
            <DialogFooter>
                <DialogClose as-child>
                    <Button variant="outline">Cancelar</Button>
                </DialogClose>
                <Button
                    type="submit"
                    form="form-reabrir"
                    :disabled="formReabrir.processing"
                >
                    <Spinner v-if="formReabrir.processing" />
                    <LockOpen v-else />
                    Reabrir periodo
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

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
