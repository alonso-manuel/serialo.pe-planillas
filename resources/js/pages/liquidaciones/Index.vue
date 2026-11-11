<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    Calculator,
    ChevronDown,
    ChevronRight,
    FileText,
    Info,
    Lock,
    LockOpen,
    Mail,
    ScrollText,
    TriangleAlert,
    UserMinus,
} from '@lucide/vue';
import { ref } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
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
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { formatoMonto } from '@/lib/montos';
import {
    calcular,
    cartaCts,
    cerrar,
    index,
    pdf,
    reabrir,
} from '@/routes/liquidaciones';
import { certificado } from '@/routes/trabajadores';

type Fila = {
    trabajador_id: number;
    nombre: string;
    documento: string;
    cargo: string | null;
    ingreso: string;
    cese: string | null;
    motivo: string | null;
    es_despido: boolean;
    plazo_fijo: boolean;
    puede_calcular: boolean;
    liquidacion: {
        id: number;
        estado: 'borrador' | 'cerrada';
        total: string;
        despido_arbitrario: boolean;
        fin_contrato: string | null;
        conceptos: { etiqueta: string; monto: string }[];
        avisos: string[];
        calculado_en: string | null;
        cerrado_por: string | null;
        cerrado_en: string | null;
        puede_cerrar: boolean;
        puede_reabrir: boolean;
    } | null;
};

defineProps<{ empresa: string; filas: Fila[] }>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Liquidaciones', href: index() }],
    },
});

const abiertas = ref(new Set<number>());
function alternar(id: number): void {
    const s = new Set(abiertas.value);
    if (s.has(id)) {
        s.delete(id);
    } else {
        s.add(id);
    }
    abiertas.value = s;
}

const calculando = ref<number | null>(null);
/** Antes de calcular: ¿corresponde indemnización por despido arbitrario? (y, si es plazo fijo, su vencimiento). */
const aCalcular = ref<Fila | null>(null);
const formCalculo = useForm({ despido_arbitrario: false, fin_contrato: '' });

function abrirCalculo(f: Fila): void {
    aCalcular.value = f;
    formCalculo.clearErrors();
    formCalculo.despido_arbitrario =
        f.liquidacion?.despido_arbitrario ?? f.es_despido;
    formCalculo.fin_contrato = f.liquidacion?.fin_contrato ?? '';
}

function confirmarCalculo(): void {
    const f = aCalcular.value;

    if (f) {
        aCalcular.value = null;
        calcularLiquidacion(f, {
            despido_arbitrario: formCalculo.despido_arbitrario,
            fin_contrato:
                f.plazo_fijo && formCalculo.despido_arbitrario
                    ? formCalculo.fin_contrato || null
                    : null,
        });
    }
}

function calcularLiquidacion(
    f: Fila,
    datos: { despido_arbitrario: boolean; fin_contrato: string | null },
): void {
    calculando.value = f.trabajador_id;
    router.post(calcular.url(f.trabajador_id), datos, {
        preserveScroll: true,
        onFinish: () => (calculando.value = null),
        onSuccess: () => {
            const s = new Set(abiertas.value);
            s.add(f.trabajador_id);
            abiertas.value = s;
        },
    });
}

const aCerrar = ref<Fila | null>(null);
const formCerrar = useForm({});
function confirmarCierre(): void {
    if (aCerrar.value?.liquidacion) {
        formCerrar.post(cerrar.url(aCerrar.value.liquidacion.id), {
            preserveScroll: true,
            onSuccess: () => (aCerrar.value = null),
        });
    }
}

const aReabrir = ref<Fila | null>(null);
const formReabrir = useForm({ motivo: '' });
function confirmarReapertura(): void {
    if (aReabrir.value?.liquidacion) {
        formReabrir.post(reabrir.url(aReabrir.value.liquidacion.id), {
            preserveScroll: true,
            onSuccess: () => {
                aReabrir.value = null;
                formReabrir.reset();
            },
        });
    }
}
</script>

<template>
    <Head title="Liquidaciones" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <Heading
            title="Liquidaciones"
            :description="`Beneficios sociales al cese del personal de ${empresa}.`"
        />

        <p
            class="flex items-start gap-2 rounded-md bg-muted/50 p-3 text-sm text-muted-foreground"
        >
            <Info class="mt-0.5 size-4 shrink-0" />
            <span>
                Gratificación y CTS truncas, vacaciones no gozadas (con su
                indemnización si vencieron) y vacaciones truncas. Se pagan en la
                <b>planilla del mes del cese</b>: así salen en la boleta, el
                PLAME y los aportes. Plazo de pago: 48 horas desde el cese.
            </span>
        </p>

        <div
            v-if="filas.length === 0"
            class="flex flex-col items-center gap-3 rounded-lg border border-dashed px-6 py-14 text-center"
        >
            <UserMinus class="size-6 text-muted-foreground" />
            <p class="font-medium">No hay trabajadores con cese registrado</p>
            <p class="max-w-sm text-sm text-muted-foreground">
                Registra el cese en la ficha del trabajador (fecha y motivo) y
                aquí podrás calcular su liquidación.
            </p>
        </div>

        <div v-else class="overflow-x-auto rounded-lg border">
            <table class="w-full min-w-[780px] text-sm">
                <thead class="bg-muted/40 text-xs text-muted-foreground">
                    <tr>
                        <th class="w-8 px-2 py-2" />
                        <th class="px-3 py-2 text-left font-medium">
                            Trabajador
                        </th>
                        <th class="px-3 py-2 text-left font-medium">Ingreso</th>
                        <th class="px-3 py-2 text-left font-medium">Cese</th>
                        <th class="px-3 py-2 text-left font-medium">Estado</th>
                        <th class="px-3 py-2 text-right font-medium">Total</th>
                        <th class="px-3 py-2" />
                    </tr>
                </thead>
                <tbody>
                    <template v-for="f in filas" :key="f.trabajador_id">
                        <tr class="border-t align-middle">
                            <td class="px-2 py-2">
                                <button
                                    v-if="f.liquidacion"
                                    type="button"
                                    class="rounded p-1 hover:bg-muted"
                                    :aria-label="`Detalle de ${f.nombre}`"
                                    :aria-expanded="
                                        abiertas.has(f.trabajador_id)
                                    "
                                    @click="alternar(f.trabajador_id)"
                                >
                                    <ChevronDown
                                        v-if="abiertas.has(f.trabajador_id)"
                                        class="size-4"
                                    />
                                    <ChevronRight v-else class="size-4" />
                                </button>
                            </td>
                            <td class="px-3 py-2">
                                <p class="font-medium">{{ f.nombre }}</p>
                                <p class="text-xs text-muted-foreground">
                                    {{ f.documento
                                    }}{{ f.cargo ? ` · ${f.cargo}` : '' }}
                                </p>
                            </td>
                            <td class="px-3 py-2 tabular-nums">
                                {{ f.ingreso }}
                            </td>
                            <td class="px-3 py-2">
                                <p class="tabular-nums">{{ f.cese }}</p>
                                <p class="text-xs text-muted-foreground">
                                    {{ f.motivo }}
                                </p>
                            </td>
                            <td class="px-3 py-2">
                                <Badge
                                    v-if="f.liquidacion?.estado === 'cerrada'"
                                    variant="outline"
                                    ><Lock class="size-3" /> Cerrada</Badge
                                >
                                <Badge
                                    v-else-if="f.liquidacion"
                                    variant="outline"
                                    class="border-sky-300 text-sky-800 dark:border-sky-800 dark:text-sky-300"
                                    >Calculada</Badge
                                >
                                <span
                                    v-else
                                    class="text-xs text-muted-foreground"
                                    >Sin calcular</span
                                >
                            </td>
                            <td
                                class="px-3 py-2 text-right font-semibold tabular-nums"
                            >
                                {{
                                    f.liquidacion
                                        ? `S/ ${formatoMonto(f.liquidacion.total)}`
                                        : '—'
                                }}
                            </td>
                            <td class="px-3 py-2">
                                <div class="flex justify-end gap-1">
                                    <Button
                                        v-if="
                                            f.puede_calcular &&
                                            f.liquidacion?.estado !== 'cerrada'
                                        "
                                        size="sm"
                                        :variant="
                                            f.liquidacion
                                                ? 'outline'
                                                : 'default'
                                        "
                                        :disabled="
                                            calculando === f.trabajador_id
                                        "
                                        @click="abrirCalculo(f)"
                                    >
                                        <Spinner
                                            v-if="
                                                calculando === f.trabajador_id
                                            "
                                        />
                                        <Calculator v-else />
                                        {{
                                            f.liquidacion
                                                ? 'Recalcular'
                                                : 'Calcular'
                                        }}
                                    </Button>
                                    <Button
                                        v-if="f.liquidacion"
                                        variant="outline"
                                        size="sm"
                                        as-child
                                    >
                                        <a
                                            :href="pdf.url(f.liquidacion.id)"
                                            target="_blank"
                                            rel="noopener"
                                        >
                                            <FileText />
                                            Hoja
                                        </a>
                                    </Button>
                                </div>
                            </td>
                        </tr>
                        <tr
                            v-if="
                                f.liquidacion && abiertas.has(f.trabajador_id)
                            "
                            class="bg-muted/20"
                        >
                            <td />
                            <td colspan="6" class="px-3 pt-1 pb-4">
                                <div class="grid gap-4 lg:grid-cols-2">
                                    <table class="w-full text-sm">
                                        <tbody>
                                            <tr
                                                v-for="c in f.liquidacion
                                                    .conceptos"
                                                :key="c.etiqueta"
                                                class="border-b border-dashed"
                                            >
                                                <td class="py-1.5">
                                                    {{ c.etiqueta }}
                                                </td>
                                                <td
                                                    class="py-1.5 text-right tabular-nums"
                                                >
                                                    {{ formatoMonto(c.monto) }}
                                                </td>
                                            </tr>
                                            <tr class="font-semibold">
                                                <td class="pt-2">
                                                    Total beneficios
                                                </td>
                                                <td
                                                    class="pt-2 text-right tabular-nums"
                                                >
                                                    S/
                                                    {{
                                                        formatoMonto(
                                                            f.liquidacion.total,
                                                        )
                                                    }}
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                    <div class="grid content-start gap-3">
                                        <ul
                                            v-if="f.liquidacion.avisos.length"
                                            class="grid gap-1 rounded-md border border-amber-300 bg-amber-50 p-3 text-xs text-amber-900 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-300"
                                        >
                                            <li
                                                v-for="a in f.liquidacion
                                                    .avisos"
                                                :key="a"
                                                class="flex gap-1.5"
                                            >
                                                <TriangleAlert
                                                    class="mt-0.5 size-3.5 shrink-0"
                                                />
                                                {{ a }}
                                            </li>
                                        </ul>
                                        <p
                                            class="text-xs text-muted-foreground"
                                        >
                                            Calculada el
                                            {{ f.liquidacion.calculado_en }}
                                            <template
                                                v-if="f.liquidacion.cerrado_en"
                                            >
                                                · cerrada por
                                                {{ f.liquidacion.cerrado_por }}
                                                el
                                                {{ f.liquidacion.cerrado_en }}
                                            </template>
                                        </p>
                                        <div class="flex flex-wrap gap-2">
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                as-child
                                            >
                                                <a
                                                    :href="
                                                        certificado.url(
                                                            f.trabajador_id,
                                                        )
                                                    "
                                                    target="_blank"
                                                    rel="noopener"
                                                >
                                                    <ScrollText />
                                                    Certificado de trabajo
                                                </a>
                                            </Button>
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                as-child
                                            >
                                                <a
                                                    :href="
                                                        cartaCts.url(
                                                            f.liquidacion.id,
                                                        )
                                                    "
                                                    target="_blank"
                                                    rel="noopener"
                                                >
                                                    <Mail />
                                                    Carta para liberar CTS
                                                </a>
                                            </Button>
                                            <Button
                                                v-if="
                                                    f.liquidacion.puede_cerrar
                                                "
                                                variant="outline"
                                                size="sm"
                                                @click="aCerrar = f"
                                            >
                                                <Lock />
                                                Cerrar
                                            </Button>
                                            <Button
                                                v-if="
                                                    f.liquidacion.puede_reabrir
                                                "
                                                variant="outline"
                                                size="sm"
                                                @click="aReabrir = f"
                                            >
                                                <LockOpen />
                                                Reabrir
                                            </Button>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>

    <Dialog
        :open="aCerrar !== null"
        @update:open="(v) => !v && (aCerrar = null)"
    >
        <DialogContent>
            <DialogHeader>
                <DialogTitle
                    >¿Cerrar la liquidación de
                    {{ aCerrar?.nombre }}?</DialogTitle
                >
                <DialogDescription>
                    Total S/ {{ formatoMonto(aCerrar?.liquidacion?.total) }}.
                    Una vez cerrada no se recalcula, el cese no se puede cambiar
                    y los documentos salen sin la marca «BORRADOR». Solo el
                    administrador puede reabrirla.
                </DialogDescription>
            </DialogHeader>
            <DialogFooter>
                <DialogClose as-child>
                    <Button variant="outline">Cancelar</Button>
                </DialogClose>
                <Button
                    :disabled="formCerrar.processing"
                    @click="confirmarCierre"
                >
                    <Spinner v-if="formCerrar.processing" />
                    <Lock v-else />
                    Cerrar liquidación
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <Dialog
        :open="aCalcular !== null"
        @update:open="(v) => !v && (aCalcular = null)"
    >
        <DialogContent>
            <DialogHeader>
                <DialogTitle
                    >Liquidación de {{ aCalcular?.nombre }}</DialogTitle
                >
                <DialogDescription>
                    Motivo del cese: {{ aCalcular?.motivo ?? 'sin indicar' }}.
                    La indemnización solo corresponde si el despido fue sin
                    causa justa (o no se pudo probar), o si el trabajador se dio
                    por despedido por hostilidad.
                </DialogDescription>
            </DialogHeader>
            <div class="grid gap-4">
                <label class="flex items-start gap-3 text-sm">
                    <Checkbox
                        :model-value="formCalculo.despido_arbitrario"
                        class="mt-0.5"
                        @update:model-value="
                            (v) => (formCalculo.despido_arbitrario = v === true)
                        "
                    />
                    <span>
                        <span class="font-medium"
                            >Pagar indemnización por despido arbitrario</span
                        >
                        <span class="block text-muted-foreground">
                            {{
                                aCalcular?.plazo_fijo
                                    ? '1.5 sueldos por cada mes que faltaba del contrato, hasta 12 (código 0503).'
                                    : '1.5 sueldos por año de servicios, hasta 12 (código 0501). En pequeña y microempresa, 20 y 10 remuneraciones diarias por año.'
                            }}
                        </span>
                    </span>
                </label>
                <div
                    v-if="
                        aCalcular?.plazo_fijo && formCalculo.despido_arbitrario
                    "
                    class="grid gap-2"
                >
                    <Label for="fin-contrato">Vencimiento del contrato</Label>
                    <Input
                        id="fin-contrato"
                        v-model="formCalculo.fin_contrato"
                        type="date"
                        required
                    />
                </div>
            </div>
            <DialogFooter>
                <DialogClose as-child>
                    <Button variant="outline">Cancelar</Button>
                </DialogClose>
                <Button
                    :disabled="
                        aCalcular?.plazo_fijo &&
                        formCalculo.despido_arbitrario &&
                        !formCalculo.fin_contrato
                    "
                    @click="confirmarCalculo"
                >
                    <Calculator />
                    Calcular
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <Dialog
        :open="aReabrir !== null"
        @update:open="(v) => !v && (aReabrir = null)"
    >
        <DialogContent>
            <DialogHeader>
                <DialogTitle
                    >Reabrir la liquidación de
                    {{ aReabrir?.nombre }}</DialogTitle
                >
                <DialogDescription
                    >El motivo queda en la auditoría.</DialogDescription
                >
            </DialogHeader>
            <form
                id="form-reabrir"
                class="grid gap-2"
                @submit.prevent="confirmarReapertura"
            >
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
                    Reabrir
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
