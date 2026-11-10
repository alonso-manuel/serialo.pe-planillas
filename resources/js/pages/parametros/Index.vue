<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    ChevronDown,
    CircleAlert,
    History,
    Plus,
    TriangleAlert,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { destroy, index, store } from '@/routes/parametros';
import type { ResumenParametro, VigenciaHistorial } from '@/types';

const props = defineProps<{
    claves: ResumenParametro[];
    hoy: string;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Parámetros', href: index() }],
    },
});

const meses = [
    'enero',
    'febrero',
    'marzo',
    'abril',
    'mayo',
    'junio',
    'julio',
    'agosto',
    'septiembre',
    'octubre',
    'noviembre',
    'diciembre',
];

/** "2025-01-01" → "enero de 2025" (las vigencias siempre empiezan el día 1). */
function mesDe(fecha: string): string {
    const [anio, mes] = fecha.split('-');

    return `${meses[Number(mes) - 1]} de ${anio}`;
}

function mesSiguiente(): string {
    const [anio, mes] = props.hoy.split('-').map(Number);

    return mes === 12
        ? `${anio + 1}-01`
        : `${anio}-${String(mes + 1).padStart(2, '0')}`;
}

/* ---------- Nueva vigencia ---------- */
const seleccionada = ref<ResumenParametro | null>(null);
const form = useForm({
    clave: '',
    valor: '',
    vigente_desde: '',
    base_legal: '',
});

function nuevaVigencia(parametro: ResumenParametro): void {
    form.reset();
    form.clearErrors();
    form.clave = parametro.clave;
    form.vigente_desde = mesSiguiente();
    seleccionada.value = parametro;
}

function guardar(): void {
    form.post(store.url(), {
        preserveScroll: true,
        onSuccess: () => (seleccionada.value = null),
    });
}

const vigenteSeleccionada = computed(() => seleccionada.value?.vigente ?? null);

/* ---------- Anular ---------- */
const porAnular = ref<{
    parametro: ResumenParametro;
    vigencia: VigenciaHistorial;
} | null>(null);

function confirmarAnulacion(): void {
    if (porAnular.value) {
        router.delete(destroy.url(porAnular.value.vigencia.id), {
            preserveScroll: true,
        });
        porAnular.value = null;
    }
}

const sinVigente = computed(() =>
    props.claves.filter((c) => c.vigente === null),
);
const porVerificar = computed(() =>
    props.claves.filter((c) => c.vigente?.verificar),
);
</script>

<template>
    <Head title="Parámetros" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <Heading
            title="Parámetros legales"
            description="Valores que usa el cálculo de planilla. Un cambio legal se registra como una vigencia nueva; los meses anteriores conservan su valor."
        />

        <div
            v-if="sinVigente.length"
            class="flex gap-3 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800 dark:border-red-900 dark:bg-red-950/40 dark:text-red-300"
            role="alert"
        >
            <CircleAlert class="mt-0.5 size-4 shrink-0" />
            <p>
                Sin valor vigente:
                <strong>{{
                    sinVigente.map((c) => c.etiqueta).join(', ')
                }}</strong
                >. La planilla no se podrá calcular hasta registrarlos.
            </p>
        </div>

        <div
            v-if="porVerificar.length"
            class="flex gap-3 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-300"
            role="status"
        >
            <TriangleAlert class="mt-0.5 size-4 shrink-0" />
            <p>
                Por verificar con la norma:
                <strong>{{
                    porVerificar.map((c) => c.etiqueta).join(', ')
                }}</strong
                >. Si el valor es incorrecto, regístralo como vigencia nueva.
            </p>
        </div>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            <article
                v-for="parametro in claves"
                :key="parametro.clave"
                class="flex flex-col rounded-xl border bg-card p-5"
                :class="{
                    'border-red-300 dark:border-red-900': !parametro.vigente,
                }"
            >
                <header class="flex items-start justify-between gap-2">
                    <div>
                        <h3 class="font-medium">{{ parametro.etiqueta }}</h3>
                        <p class="mt-0.5 text-xs text-muted-foreground">
                            {{ parametro.descripcion }}
                        </p>
                    </div>
                    <Button
                        variant="ghost"
                        size="icon"
                        class="size-8 shrink-0"
                        :title="`Nueva vigencia de ${parametro.etiqueta}`"
                        @click="nuevaVigencia(parametro)"
                    >
                        <Plus />
                    </Button>
                </header>

                <div class="mt-4 flex-1">
                    <template v-if="parametro.vigente">
                        <p class="text-2xl font-semibold tabular-nums">
                            {{ parametro.vigente.valor_mostrado }}
                        </p>
                        <p class="mt-1 text-xs text-muted-foreground">
                            Desde {{ mesDe(parametro.vigente.vigente_desde) }}
                            <template v-if="parametro.vigente.base_legal">
                                · {{ parametro.vigente.base_legal }}
                            </template>
                        </p>
                        <Badge
                            v-if="parametro.vigente.verificar"
                            variant="outline"
                            class="mt-2 border-amber-400 text-amber-700 dark:text-amber-400"
                        >
                            Por verificar
                        </Badge>
                    </template>
                    <p
                        v-else
                        class="text-sm font-medium text-red-700 dark:text-red-400"
                    >
                        Sin valor vigente
                    </p>

                    <p
                        v-if="parametro.proxima"
                        class="mt-3 rounded-md bg-muted/60 px-3 py-2 text-xs"
                    >
                        Desde {{ mesDe(parametro.proxima.vigente_desde) }}:
                        <strong class="tabular-nums">
                            {{ parametro.proxima.valor_mostrado }}
                        </strong>
                    </p>
                </div>

                <Collapsible v-if="parametro.historial.length" class="mt-4">
                    <CollapsibleTrigger
                        class="group flex w-full items-center gap-1.5 text-xs text-muted-foreground hover:text-foreground"
                    >
                        <History class="size-3.5" />
                        Historial ({{ parametro.historial.length }})
                        <ChevronDown
                            class="ml-auto size-3.5 transition-transform group-data-[state=open]:rotate-180"
                        />
                    </CollapsibleTrigger>
                    <CollapsibleContent>
                        <ul class="mt-2 divide-y text-xs">
                            <li
                                v-for="v in parametro.historial"
                                :key="v.id"
                                class="flex items-center gap-2 py-2"
                            >
                                <span
                                    class="w-28 shrink-0 text-muted-foreground"
                                >
                                    {{ mesDe(v.vigente_desde) }}
                                </span>
                                <span class="font-medium tabular-nums">
                                    {{ v.valor_mostrado }}
                                </span>
                                <span
                                    class="min-w-0 flex-1 truncate text-muted-foreground"
                                    :title="v.base_legal ?? ''"
                                >
                                    {{ v.base_legal }}
                                </span>
                                <button
                                    v-if="v.anulable"
                                    type="button"
                                    class="shrink-0 text-destructive hover:underline"
                                    @click="
                                        porAnular = { parametro, vigencia: v }
                                    "
                                >
                                    Anular
                                </button>
                            </li>
                        </ul>
                    </CollapsibleContent>
                </Collapsible>
            </article>
        </div>
    </div>

    <!-- Nueva vigencia -->
    <Dialog
        :open="seleccionada !== null"
        @update:open="(abierto) => !abierto && (seleccionada = null)"
    >
        <DialogContent>
            <DialogHeader>
                <DialogTitle
                    >Nueva vigencia · {{ seleccionada?.etiqueta }}</DialogTitle
                >
                <DialogDescription>
                    <template v-if="vigenteSeleccionada">
                        Hoy rige {{ vigenteSeleccionada.valor_mostrado }} desde
                        {{ mesDe(vigenteSeleccionada.vigente_desde) }}.
                    </template>
                    Los meses anteriores a la nueva fecha conservan su valor.
                </DialogDescription>
            </DialogHeader>

            <form
                id="nueva-vigencia"
                class="grid gap-4"
                @submit.prevent="guardar"
            >
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="valor">
                            {{
                                seleccionada?.tipo === 'tasa'
                                    ? 'Porcentaje'
                                    : seleccionada?.tipo === 'dias'
                                      ? 'Días'
                                      : seleccionada?.tipo === 'uit'
                                        ? 'Cantidad de UIT'
                                        : 'Monto'
                            }}
                        </Label>
                        <div class="relative">
                            <span
                                v-if="seleccionada?.tipo === 'monto'"
                                class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-sm text-muted-foreground"
                            >
                                S/
                            </span>
                            <Input
                                id="valor"
                                v-model="form.valor"
                                inputmode="decimal"
                                autocomplete="off"
                                class="tabular-nums"
                                :class="
                                    seleccionada?.tipo === 'monto'
                                        ? 'pl-9'
                                        : 'pr-8'
                                "
                                :placeholder="
                                    seleccionada?.tipo === 'tasa'
                                        ? '9.00'
                                        : seleccionada?.tipo === 'dias'
                                          ? '30'
                                          : seleccionada?.tipo === 'uit'
                                            ? '7'
                                            : '1130.00'
                                "
                                required
                                v-focus
                            />
                            <span
                                v-if="seleccionada?.tipo === 'tasa'"
                                class="pointer-events-none absolute top-1/2 right-3 -translate-y-1/2 text-sm text-muted-foreground"
                            >
                                %
                            </span>
                            <span
                                v-else-if="seleccionada?.tipo === 'uit'"
                                class="pointer-events-none absolute top-1/2 right-3 -translate-y-1/2 text-sm text-muted-foreground"
                            >
                                UIT
                            </span>
                        </div>
                        <InputError :message="form.errors.valor" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="vigente_desde">Rige desde (mes)</Label>
                        <Input
                            id="vigente_desde"
                            v-model="form.vigente_desde"
                            type="month"
                            required
                        />
                        <InputError :message="form.errors.vigente_desde" />
                    </div>
                </div>

                <div class="grid gap-2">
                    <Label for="base_legal">Base legal</Label>
                    <Input
                        id="base_legal"
                        v-model="form.base_legal"
                        placeholder="Ej.: D.S. N.° 000-2026-TR"
                        required
                    />
                    <InputError
                        :message="form.errors.base_legal || form.errors.clave"
                    />
                </div>
            </form>

            <DialogFooter>
                <DialogClose as-child>
                    <Button variant="outline">Cancelar</Button>
                </DialogClose>
                <Button
                    type="submit"
                    form="nueva-vigencia"
                    :disabled="form.processing"
                >
                    <Spinner v-if="form.processing" />
                    Registrar vigencia
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <!-- Anular -->
    <Dialog
        :open="porAnular !== null"
        @update:open="(abierto) => !abierto && (porAnular = null)"
    >
        <DialogContent>
            <DialogHeader>
                <DialogTitle>
                    ¿Anular {{ porAnular?.vigencia.valor_mostrado }} desde
                    {{
                        porAnular
                            ? mesDe(porAnular.vigencia.vigente_desde)
                            : ''
                    }}?
                </DialogTitle>
                <DialogDescription>
                    Ningún periodo cerrado la usó. Los periodos abiertos o
                    calculados deberán recalcularse con el valor anterior.
                </DialogDescription>
            </DialogHeader>
            <DialogFooter>
                <DialogClose as-child>
                    <Button variant="outline">Cancelar</Button>
                </DialogClose>
                <Button variant="destructive" @click="confirmarAnulacion">
                    Anular vigencia
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
