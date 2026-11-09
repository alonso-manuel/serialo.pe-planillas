<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    Calculator,
    FileArchive,
    FileText,
    Info,
    Lock,
    LockOpen,
    TriangleAlert,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
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
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { formatoMonto } from '@/lib/montos';
import { calcular, cerrar, constancia, index, reabrir } from '@/routes/cts';
import {
    pdf as constanciasPdf,
    zip as constanciasZip,
} from '@/routes/cts/constancias';

const props = defineProps<{
    empresa: string;
    anio: number;
    mes: number;
    etiqueta: string;
    periodo_computable: string;
    depositos: { anio: number; mes: number; etiqueta: string }[];
    liquidacion: {
        id: number;
        estado: 'borrador' | 'cerrada';
        calculado_en: string | null;
        cerrado_por: string | null;
        cerrado_en: string | null;
        puede_calcular: boolean;
        puede_cerrar: boolean;
        puede_reabrir: boolean;
        total: string;
    } | null;
    lineas: {
        id: number;
        nombre: string;
        documento: string;
        desde: string;
        meses: number;
        dias: number;
        dias_descontados: number;
        computable: string;
        sexto_gratificacion: string;
        monto: string;
        entidad: string | null;
        cuenta: string | null;
        avisos: string[];
    }[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'CTS', href: index() }],
    },
});

const deposito = ref(`${props.anio}-${props.mes}`);

function cambiarDeposito(): void {
    const [anio, mes] = deposito.value.split('-').map(Number);
    router.get(index.url(), { anio, mes }, { preserveScroll: true });
}

const cerrada = computed(() => props.liquidacion?.estado === 'cerrada');
const puedeCalcular = computed(
    () => props.liquidacion === null || props.liquidacion.puede_calcular,
);
const conAvisos = computed(() => props.lineas.filter((l) => l.avisos.length));

const calculando = ref(false);

function calcularCts(): void {
    calculando.value = true;
    router.post(
        calcular.url(),
        { anio: props.anio, mes: props.mes },
        { preserveScroll: true, onFinish: () => (calculando.value = false) },
    );
}

const dialogoCerrar = ref(false);
const formCerrar = useForm({});

function cerrarCts(): void {
    if (props.liquidacion) {
        formCerrar.post(cerrar.url(props.liquidacion.id), {
            preserveScroll: true,
            onSuccess: () => (dialogoCerrar.value = false),
        });
    }
}

const dialogoReabrir = ref(false);
const formReabrir = useForm({ motivo: '' });

function reabrirCts(): void {
    if (props.liquidacion) {
        formReabrir.post(reabrir.url(props.liquidacion.id), {
            preserveScroll: true,
            onSuccess: () => {
                dialogoReabrir.value = false;
                formReabrir.reset();
            },
        });
    }
}
</script>

<template>
    <Head title="CTS" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading
                title="CTS"
                :description="`Compensación por tiempo de servicios de ${empresa}.`"
            />
            <div class="flex flex-wrap items-end gap-2">
                <div class="grid gap-1.5">
                    <Label for="deposito">Depósito</Label>
                    <select
                        id="deposito"
                        v-model="deposito"
                        class="h-9 rounded-md border border-input bg-background px-3 text-sm"
                        @change="cambiarDeposito"
                    >
                        <option
                            v-for="d in depositos"
                            :key="`${d.anio}-${d.mes}`"
                            :value="`${d.anio}-${d.mes}`"
                        >
                            {{ d.etiqueta }}
                        </option>
                    </select>
                </div>
                <Button
                    v-if="!cerrada && puedeCalcular"
                    :disabled="calculando"
                    @click="calcularCts"
                >
                    <Calculator :class="{ 'animate-pulse': calculando }" />
                    {{
                        calculando
                            ? 'Calculando…'
                            : liquidacion
                              ? 'Recalcular'
                              : 'Calcular CTS'
                    }}
                </Button>
            </div>
        </div>

        <p
            class="flex items-start gap-2 rounded-md bg-muted/50 p-3 text-sm text-muted-foreground"
        >
            <Info class="mt-0.5 size-4 shrink-0" />
            <span>
                Depósito de <b>{{ etiqueta }}</b> (hasta el día 15): periodo
                computable {{ periodo_computable }}. Se calcula con las
                planillas del periodo y la gratificación pagada en él; quien
                cesó antes del cierre la recibe en su liquidación.
            </span>
        </p>

        <div
            v-if="!liquidacion"
            class="flex flex-col items-center gap-3 rounded-lg border border-dashed px-6 py-14 text-center"
        >
            <p class="font-medium">
                Aún no se calcula la CTS de {{ etiqueta }}
            </p>
            <p class="max-w-md text-sm text-muted-foreground">
                Usa «Calcular CTS» para obtener el monto a depositar a cada
                trabajador.
            </p>
        </div>

        <template v-else>
            <div
                class="flex flex-wrap items-center justify-between gap-3 rounded-lg border p-4"
            >
                <div>
                    <p class="text-sm text-muted-foreground">
                        Total a depositar
                    </p>
                    <p class="text-2xl font-semibold tabular-nums">
                        S/ {{ formatoMonto(liquidacion.total) }}
                    </p>
                    <p class="text-xs text-muted-foreground">
                        {{ lineas.length }}
                        {{
                            lineas.length === 1 ? 'trabajador' : 'trabajadores'
                        }}
                        · calculado el {{ liquidacion.calculado_en }}
                        <template v-if="cerrada">
                            · cerrada por {{ liquidacion.cerrado_por }} el
                            {{ liquidacion.cerrado_en }}</template
                        >
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Button variant="outline" as-child>
                        <a
                            :href="constanciasPdf.url(liquidacion.id)"
                            target="_blank"
                            rel="noopener"
                        >
                            <FileText />
                            Constancias PDF
                        </a>
                    </Button>
                    <Button variant="outline" as-child>
                        <a :href="constanciasZip.url(liquidacion.id)">
                            <FileArchive />
                            Constancias (zip)
                        </a>
                    </Button>
                    <Button
                        v-if="liquidacion.puede_cerrar"
                        variant="outline"
                        @click="dialogoCerrar = true"
                    >
                        <Lock />
                        Cerrar CTS
                    </Button>
                    <Button
                        v-if="liquidacion.puede_reabrir"
                        variant="outline"
                        @click="dialogoReabrir = true"
                    >
                        <LockOpen />
                        Reabrir
                    </Button>
                </div>
            </div>

            <p
                v-if="conAvisos.length"
                class="flex items-start gap-2 rounded-md border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-300"
            >
                <TriangleAlert class="mt-0.5 size-4 shrink-0" />
                {{ conAvisos.length }}
                {{
                    conAvisos.length === 1
                        ? 'trabajador tiene'
                        : 'trabajadores tienen'
                }}
                avisos: revísalos en la tabla antes de depositar.
            </p>

            <div class="overflow-x-auto rounded-lg border">
                <table class="w-full min-w-[900px] text-sm">
                    <thead class="bg-muted/40 text-xs text-muted-foreground">
                        <tr>
                            <th class="px-3 py-2 text-left font-medium">
                                Trabajador
                            </th>
                            <th class="px-3 py-2 text-left font-medium">
                                Desde
                            </th>
                            <th class="px-3 py-2 text-right font-medium">
                                Tiempo
                            </th>
                            <th class="px-3 py-2 text-right font-medium">
                                1/6 grati.
                            </th>
                            <th class="px-3 py-2 text-right font-medium">
                                Rem. computable
                            </th>
                            <th class="px-3 py-2 text-right font-medium">
                                CTS
                            </th>
                            <th class="px-3 py-2 text-left font-medium">
                                Cuenta CTS
                            </th>
                            <th class="w-12 px-3 py-2" />
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="l in lineas"
                            :key="l.id"
                            class="border-t align-top"
                        >
                            <td class="px-3 py-2">
                                <p class="font-medium">{{ l.nombre }}</p>
                                <p class="text-xs text-muted-foreground">
                                    {{ l.documento }}
                                </p>
                                <p
                                    v-for="a in l.avisos"
                                    :key="a"
                                    class="mt-1 flex items-start gap-1 text-xs text-amber-700 dark:text-amber-400"
                                >
                                    <TriangleAlert
                                        class="mt-0.5 size-3 shrink-0"
                                    />
                                    {{ a }}
                                </p>
                            </td>
                            <td class="px-3 py-2 tabular-nums">
                                {{ l.desde }}
                            </td>
                            <td
                                class="px-3 py-2 text-right whitespace-nowrap tabular-nums"
                            >
                                {{ l.meses }} m {{ l.dias }} d
                                <span
                                    v-if="l.dias_descontados"
                                    class="block text-xs text-muted-foreground"
                                    >−{{ l.dias_descontados }} d</span
                                >
                            </td>
                            <td class="px-3 py-2 text-right tabular-nums">
                                {{ formatoMonto(l.sexto_gratificacion) }}
                            </td>
                            <td class="px-3 py-2 text-right tabular-nums">
                                {{ formatoMonto(l.computable) }}
                            </td>
                            <td
                                class="px-3 py-2 text-right font-medium tabular-nums"
                            >
                                {{ formatoMonto(l.monto) }}
                            </td>
                            <td class="px-3 py-2 text-xs">
                                <p>{{ l.entidad ?? '—' }}</p>
                                <p class="font-mono text-muted-foreground">
                                    {{ l.cuenta ?? '' }}
                                </p>
                            </td>
                            <td class="px-3 py-2 text-right">
                                <a
                                    :href="
                                        constancia.url([liquidacion.id, l.id])
                                    "
                                    target="_blank"
                                    rel="noopener"
                                    class="inline-flex text-muted-foreground hover:text-foreground"
                                    :title="`Constancia de ${l.nombre}`"
                                >
                                    <FileText class="size-4" />
                                </a>
                            </td>
                        </tr>
                        <tr v-if="lineas.length === 0">
                            <td
                                colspan="8"
                                class="px-4 py-10 text-center text-muted-foreground"
                            >
                                Ningún trabajador tiene CTS en este depósito (la
                                microempresa no tiene CTS).
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </template>
    </div>

    <Dialog v-model:open="dialogoCerrar">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>¿Cerrar la CTS de {{ etiqueta }}?</DialogTitle>
                <DialogDescription>
                    Total S/ {{ formatoMonto(liquidacion?.total) }}. Una vez
                    cerrada no se puede recalcular y las constancias salen sin
                    la marca «BORRADOR». Solo el administrador puede reabrirla.
                </DialogDescription>
            </DialogHeader>
            <DialogFooter>
                <DialogClose as-child>
                    <Button variant="outline">Cancelar</Button>
                </DialogClose>
                <Button :disabled="formCerrar.processing" @click="cerrarCts">
                    <Spinner v-if="formCerrar.processing" />
                    <Lock v-else />
                    Cerrar CTS
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <Dialog v-model:open="dialogoReabrir">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Reabrir la CTS de {{ etiqueta }}</DialogTitle>
                <DialogDescription>
                    Se podrá recalcular. El motivo queda en la auditoría.
                </DialogDescription>
            </DialogHeader>
            <form class="grid gap-2" @submit.prevent="reabrirCts">
                <Label for="motivo">Motivo</Label>
                <textarea
                    id="motivo"
                    v-model="formReabrir.motivo"
                    rows="3"
                    maxlength="500"
                    required
                    class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm"
                />
                <InputError :message="formReabrir.errors.motivo" />
                <DialogFooter class="mt-2">
                    <DialogClose as-child>
                        <Button variant="outline" type="button"
                            >Cancelar</Button
                        >
                    </DialogClose>
                    <Button type="submit" :disabled="formReabrir.processing">
                        <Spinner v-if="formReabrir.processing" />
                        <LockOpen v-else />
                        Reabrir
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
