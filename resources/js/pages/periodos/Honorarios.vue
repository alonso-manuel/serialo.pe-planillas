<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Lock, Plus, Trash2 } from '@lucide/vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { formatoMonto } from '@/lib/montos';
import { index as periodos, show } from '@/routes/periodos';
import { destroy, store } from '@/routes/periodos/honorarios';

type Prestador = {
    tipo_documento: string;
    numero_documento: string;
    apellido_paterno: string;
    apellido_materno: string | null;
    nombres: string;
    domiciliado: boolean;
    suspension_anio: number | null;
};

const props = defineProps<{
    periodo: {
        id: number;
        etiqueta: string;
        cerrado: boolean;
        desde: string;
        hasta: string;
        puede_editar: boolean;
    };
    recibos: {
        id: number;
        prestador: string;
        documento: string;
        comprobante: string;
        fecha_emision: string;
        fecha_pago: string;
        monto: string;
        retencion: string;
        neto: string;
    }[];
    totales: { monto: string; retencion: string };
    prestadores: Prestador[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Periodos', href: periodos() }],
    },
});

const form = useForm({
    tipo_documento: '06',
    numero_documento: '',
    apellido_paterno: '',
    apellido_materno: '',
    nombres: '',
    domiciliado: true,
    suspension: false,
    tipo_comprobante: 'R',
    serie: 'E001',
    numero: '',
    fecha_emision: props.periodo.hasta,
    fecha_pago: props.periodo.hasta,
    monto: '',
});

/** Al escribir un RUC ya registrado, completa sus datos. */
function completarPrestador(): void {
    const p = props.prestadores.find(
        (x) =>
            x.numero_documento === form.numero_documento.trim() &&
            x.tipo_documento === form.tipo_documento,
    );

    if (p) {
        form.apellido_paterno = p.apellido_paterno;
        form.apellido_materno = p.apellido_materno ?? '';
        form.nombres = p.nombres;
        form.domiciliado = p.domiciliado;
        form.suspension =
            p.suspension_anio === Number(form.fecha_pago.slice(0, 4));
    }
}

function registrar(): void {
    form.post(store.url(props.periodo.id), {
        preserveScroll: true,
        onSuccess: () => form.reset('numero', 'monto'),
    });
}

function eliminar(id: number): void {
    router.delete(destroy.url({ periodo: props.periodo.id, recibo: id }), {
        preserveScroll: true,
    });
}

const editable = props.periodo.puede_editar && !props.periodo.cerrado;
</script>

<template>
    <Head :title="`Honorarios ${periodo.etiqueta}`" />

    <div class="flex max-w-5xl flex-1 flex-col gap-6 p-4 md:p-6">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <Heading
                :title="`Recibos por honorarios · ${periodo.etiqueta}`"
                description="Prestadores de servicios (cuarta categoría) pagados en el mes. Se retiene el 8 % cuando el recibo supera S/ 1,500, salvo constancia de suspensión. Van al PLAME en los archivos .4ta y .ps4."
            />
            <Button variant="outline" as-child>
                <Link :href="show(periodo.id)"><ArrowLeft /> Planilla</Link>
            </Button>
        </div>

        <p
            v-if="periodo.cerrado"
            class="flex items-center gap-2 rounded-md border bg-muted/40 p-3 text-sm text-muted-foreground"
        >
            <Lock class="size-4" /> El periodo está cerrado: los recibos no se
            pueden modificar.
        </p>

        <div class="overflow-x-auto rounded-lg border">
            <table class="w-full text-sm">
                <thead
                    class="bg-muted/50 text-left text-xs text-muted-foreground"
                >
                    <tr>
                        <th class="px-3 py-2 font-medium">Prestador</th>
                        <th class="px-3 py-2 font-medium">Comprobante</th>
                        <th class="px-3 py-2 font-medium">Pago</th>
                        <th class="px-3 py-2 text-right font-medium">Monto</th>
                        <th class="px-3 py-2 text-right font-medium">
                            Retención
                        </th>
                        <th class="px-3 py-2 text-right font-medium">Neto</th>
                        <th class="px-3 py-2">
                            <span class="sr-only">Acciones</span>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="recibos.length === 0">
                        <td
                            colspan="7"
                            class="px-3 py-6 text-center text-muted-foreground"
                        >
                            No hay recibos por honorarios en este periodo.
                        </td>
                    </tr>
                    <tr v-for="r in recibos" :key="r.id" class="border-t">
                        <td class="px-3 py-2">
                            {{ r.prestador }}
                            <span
                                class="block text-xs text-muted-foreground tabular-nums"
                                >RUC {{ r.documento }}</span
                            >
                        </td>
                        <td class="px-3 py-2 tabular-nums">
                            {{ r.comprobante }}
                            <span class="block text-xs text-muted-foreground"
                                >Emitido {{ r.fecha_emision }}</span
                            >
                        </td>
                        <td class="px-3 py-2 tabular-nums">
                            {{ r.fecha_pago }}
                        </td>
                        <td class="px-3 py-2 text-right tabular-nums">
                            {{ formatoMonto(r.monto) }}
                        </td>
                        <td class="px-3 py-2 text-right tabular-nums">
                            {{ formatoMonto(r.retencion) }}
                        </td>
                        <td class="px-3 py-2 text-right tabular-nums">
                            {{ formatoMonto(r.neto) }}
                        </td>
                        <td class="px-3 py-2 text-right">
                            <Button
                                v-if="editable"
                                variant="ghost"
                                size="icon"
                                :aria-label="`Eliminar el recibo ${r.comprobante}`"
                                @click="eliminar(r.id)"
                            >
                                <Trash2 />
                            </Button>
                        </td>
                    </tr>
                </tbody>
                <tfoot v-if="recibos.length" class="border-t font-medium">
                    <tr>
                        <td colspan="3" class="px-3 py-2">Total</td>
                        <td class="px-3 py-2 text-right tabular-nums">
                            {{ formatoMonto(totales.monto) }}
                        </td>
                        <td class="px-3 py-2 text-right tabular-nums">
                            {{ formatoMonto(totales.retencion) }}
                        </td>
                        <td colspan="2"></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <form
            v-if="editable"
            class="grid gap-4 rounded-lg border p-4"
            @submit.prevent="registrar"
        >
            <h2 class="font-medium">Registrar recibo</h2>
            <div class="grid gap-4 sm:grid-cols-3">
                <div class="grid gap-2">
                    <Label for="ruc">RUC del prestador</Label>
                    <Input
                        id="ruc"
                        v-model="form.numero_documento"
                        inputmode="numeric"
                        maxlength="11"
                        class="tabular-nums"
                        list="prestadores"
                        @change="completarPrestador"
                    />
                    <datalist id="prestadores">
                        <option
                            v-for="p in prestadores"
                            :key="p.numero_documento"
                            :value="p.numero_documento"
                        >
                            {{ p.apellido_paterno }} {{ p.nombres }}
                        </option>
                    </datalist>
                    <InputError :message="form.errors.numero_documento" />
                </div>
                <div class="grid gap-2">
                    <Label for="paterno">Apellido paterno</Label>
                    <Input
                        id="paterno"
                        v-model="form.apellido_paterno"
                        maxlength="40"
                    />
                    <InputError :message="form.errors.apellido_paterno" />
                </div>
                <div class="grid gap-2">
                    <Label for="materno">Apellido materno</Label>
                    <Input
                        id="materno"
                        v-model="form.apellido_materno"
                        maxlength="40"
                    />
                </div>
                <div class="grid gap-2">
                    <Label for="nombres">Nombres</Label>
                    <Input id="nombres" v-model="form.nombres" maxlength="40" />
                    <InputError :message="form.errors.nombres" />
                </div>
                <div class="grid gap-2">
                    <Label for="tipo">Comprobante</Label>
                    <Select v-model="form.tipo_comprobante">
                        <SelectTrigger id="tipo" class="w-full"
                            ><SelectValue
                        /></SelectTrigger>
                        <SelectContent>
                            <SelectItem value="R"
                                >Recibo por honorarios</SelectItem
                            >
                            <SelectItem value="N">Nota de crédito</SelectItem>
                            <SelectItem value="D">Dieta</SelectItem>
                            <SelectItem value="O">Otro comprobante</SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <div class="grid gap-2">
                        <Label for="serie">Serie</Label>
                        <Input id="serie" v-model="form.serie" maxlength="4" />
                        <InputError :message="form.errors.serie" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="numero">Número</Label>
                        <Input
                            id="numero"
                            v-model="form.numero"
                            inputmode="numeric"
                            maxlength="8"
                        />
                        <InputError :message="form.errors.numero" />
                    </div>
                </div>
                <div class="grid gap-2">
                    <Label for="emision">Fecha de emisión</Label>
                    <Input
                        id="emision"
                        v-model="form.fecha_emision"
                        type="date"
                    />
                    <InputError :message="form.errors.fecha_emision" />
                </div>
                <div class="grid gap-2">
                    <Label for="pago">Fecha de pago</Label>
                    <Input
                        id="pago"
                        v-model="form.fecha_pago"
                        type="date"
                        :min="periodo.desde"
                        :max="periodo.hasta"
                    />
                    <InputError :message="form.errors.fecha_pago" />
                </div>
                <div class="grid gap-2">
                    <Label for="monto">Monto del servicio (S/)</Label>
                    <Input
                        id="monto"
                        v-model="form.monto"
                        inputmode="decimal"
                        class="tabular-nums"
                        placeholder="0.00"
                    />
                    <InputError :message="form.errors.monto" />
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-6 text-sm">
                <label class="flex items-center gap-2">
                    <Checkbox
                        :model-value="form.suspension"
                        @update:model-value="
                            (v) => (form.suspension = v === true)
                        "
                    />
                    Tiene constancia de suspensión de retenciones este año
                </label>
                <label class="flex items-center gap-2">
                    <Checkbox
                        :model-value="form.domiciliado"
                        @update:model-value="
                            (v) => (form.domiciliado = v === true)
                        "
                    />
                    Domiciliado en el Perú
                </label>
            </div>
            <div>
                <Button type="submit" :disabled="form.processing">
                    <Spinner v-if="form.processing" />
                    <Plus v-else />
                    Registrar recibo
                </Button>
            </div>
        </form>
    </div>
</template>
