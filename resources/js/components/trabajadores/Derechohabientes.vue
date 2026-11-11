<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { Plus, Trash2, UserMinus } from '@lucide/vue';
import { computed, ref } from 'vue';
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
import { baja, destroy, store } from '@/routes/trabajadores/derechohabientes';
import type {
    CatalogosTrabajador,
    DerechohabienteFila,
    TrabajadorEditable,
} from '@/types';

/** Derechohabientes del trabajador (EsSalud / T-Registro). Sin <form> propio: vive dentro de la ficha. */
const props = defineProps<{
    trabajador: TrabajadorEditable;
    derechohabientes: DerechohabienteFila[];
    catalogos: CatalogosTrabajador;
}>();

const SIN = 'sin';
const hoy = new Date().toISOString().slice(0, 10);

const form = useForm({
    tipo_documento: '01',
    numero_documento: '',
    pais_emisor: '',
    fecha_nacimiento: '',
    apellido_paterno: '',
    apellido_materno: '',
    nombres: '',
    sexo: 'F',
    vinculo: '05',
    documento_vinculo: SIN,
    numero_documento_vinculo: '',
    mes_concepcion: '',
    fecha_alta: hoy,
});

const vinculo = computed(() =>
    props.catalogos.vinculos.find((v) => v.value === form.vinculo),
);

function registrar(): void {
    form.transform((d) => ({
        ...d,
        documento_vinculo:
            d.documento_vinculo === SIN ? null : d.documento_vinculo,
        pais_emisor: d.pais_emisor || null,
        apellido_materno: d.apellido_materno || null,
        numero_documento_vinculo: d.numero_documento_vinculo || null,
        mes_concepcion: d.mes_concepcion || null,
    })).post(store.url(props.trabajador.id), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
}

/* ---------- Baja ---------- */
const aDarDeBaja = ref<DerechohabienteFila | null>(null);
const formBaja = useForm({ fecha_baja: hoy, motivo_baja: '' });

function confirmarBaja(): void {
    const d = aDarDeBaja.value;

    if (d) {
        formBaja.patch(
            baja.url({
                trabajador: props.trabajador.id,
                derechohabiente: d.id,
            }),
            {
                preserveScroll: true,
                onSuccess: () => {
                    aDarDeBaja.value = null;
                    formBaja.reset();
                },
            },
        );
    }
}

function eliminar(d: DerechohabienteFila): void {
    router.delete(
        destroy.url({ trabajador: props.trabajador.id, derechohabiente: d.id }),
        { preserveScroll: true },
    );
}

const hijosMenores = computed(() =>
    props.derechohabientes.some(
        (d) => d.fecha_baja === null && d.vinculo.startsWith('Hijo(a) menor'),
    ),
);
</script>

<template>
    <div class="grid gap-6">
        <p class="text-sm text-muted-foreground">
            Familiares con derecho a EsSalud. Se informan a SUNAT desde
            <strong>SUNAT → T-Registro</strong> (archivos de derechohabientes).
        </p>

        <p
            v-if="hijosMenores && !trabajador.asignacion_familiar"
            class="rounded-md border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-200"
        >
            Tiene hijos menores registrados y no cobra asignación familiar:
            revisa si le corresponde.
        </p>

        <div
            v-if="derechohabientes.length"
            class="overflow-x-auto rounded-lg border"
        >
            <table class="w-full text-sm">
                <thead
                    class="bg-muted/50 text-left text-xs text-muted-foreground"
                >
                    <tr>
                        <th class="px-3 py-2 font-medium">Nombre</th>
                        <th class="px-3 py-2 font-medium">Vínculo</th>
                        <th class="px-3 py-2 font-medium">Nacimiento</th>
                        <th class="px-3 py-2 font-medium">Estado</th>
                        <th class="px-3 py-2">
                            <span class="sr-only">Acciones</span>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="d in derechohabientes"
                        :key="d.id"
                        class="border-t"
                        :class="d.fecha_baja ? 'text-muted-foreground' : ''"
                    >
                        <td class="px-3 py-2">
                            {{ d.nombre }}
                            <span
                                class="block text-xs text-muted-foreground tabular-nums"
                                >{{ d.documento }}</span
                            >
                        </td>
                        <td class="px-3 py-2">{{ d.vinculo }}</td>
                        <td class="px-3 py-2 tabular-nums">
                            {{ d.fecha_nacimiento }}
                        </td>
                        <td class="px-3 py-2 text-xs">
                            <template v-if="d.fecha_baja"
                                >Baja {{ d.fecha_baja }} ·
                                {{ d.motivo_baja }}</template
                            >
                            <template v-else>Alta {{ d.fecha_alta }}</template>
                        </td>
                        <td class="px-3 py-2 text-right whitespace-nowrap">
                            <Button
                                v-if="!d.fecha_baja"
                                type="button"
                                variant="ghost"
                                size="sm"
                                @click="aDarDeBaja = d"
                            >
                                <UserMinus />
                                Baja
                            </Button>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                :aria-label="`Eliminar ${d.nombre} (registro por error)`"
                                title="Eliminar (solo si se registró por error)"
                                @click="eliminar(d)"
                            >
                                <Trash2 />
                            </Button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="grid gap-4 rounded-lg border p-4">
            <h3 class="font-medium">Agregar derechohabiente</h3>
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="dh-vinculo">Vínculo</Label>
                    <Select v-model="form.vinculo">
                        <SelectTrigger id="dh-vinculo" class="w-full"
                            ><SelectValue
                        /></SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="v in catalogos.vinculos"
                                :key="v.value"
                                :value="v.value"
                                >{{ v.label }}</SelectItem
                            >
                        </SelectContent>
                    </Select>
                    <InputError :message="form.errors.vinculo" />
                </div>
                <div class="grid gap-2">
                    <Label for="dh-fecha-alta">Fecha de alta</Label>
                    <Input
                        id="dh-fecha-alta"
                        v-model="form.fecha_alta"
                        type="date"
                    />
                    <InputError :message="form.errors.fecha_alta" />
                </div>
                <div class="grid gap-2">
                    <Label for="dh-tipo-doc">Tipo de documento</Label>
                    <Select v-model="form.tipo_documento">
                        <SelectTrigger id="dh-tipo-doc" class="w-full"
                            ><SelectValue
                        /></SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="o in catalogos.tipos_documento"
                                :key="o.value"
                                :value="o.value"
                                >{{ o.label }}</SelectItem
                            >
                        </SelectContent>
                    </Select>
                </div>
                <div class="grid gap-2">
                    <Label for="dh-numero">Número de documento</Label>
                    <Input
                        id="dh-numero"
                        v-model="form.numero_documento"
                        class="tabular-nums"
                    />
                    <InputError :message="form.errors.numero_documento" />
                </div>
                <div v-if="form.tipo_documento === '07'" class="grid gap-2">
                    <Label for="dh-pais">País emisor (tabla 26)</Label>
                    <Input
                        id="dh-pais"
                        v-model="form.pais_emisor"
                        inputmode="numeric"
                        maxlength="3"
                    />
                    <InputError :message="form.errors.pais_emisor" />
                </div>
                <div class="grid gap-2">
                    <Label for="dh-paterno">Apellido paterno</Label>
                    <Input
                        id="dh-paterno"
                        v-model="form.apellido_paterno"
                        maxlength="40"
                    />
                    <InputError :message="form.errors.apellido_paterno" />
                </div>
                <div class="grid gap-2">
                    <Label for="dh-materno">Apellido materno</Label>
                    <Input
                        id="dh-materno"
                        v-model="form.apellido_materno"
                        maxlength="40"
                    />
                </div>
                <div class="grid gap-2">
                    <Label for="dh-nombres">Nombres</Label>
                    <Input
                        id="dh-nombres"
                        v-model="form.nombres"
                        maxlength="40"
                    />
                    <InputError :message="form.errors.nombres" />
                </div>
                <div class="grid gap-2">
                    <Label for="dh-nacimiento">Fecha de nacimiento</Label>
                    <Input
                        id="dh-nacimiento"
                        v-model="form.fecha_nacimiento"
                        type="date"
                    />
                    <InputError :message="form.errors.fecha_nacimiento" />
                </div>
                <div class="grid gap-2">
                    <Label for="dh-sexo">Sexo</Label>
                    <Select v-model="form.sexo">
                        <SelectTrigger id="dh-sexo" class="w-full"
                            ><SelectValue
                        /></SelectTrigger>
                        <SelectContent>
                            <SelectItem value="F">Femenino</SelectItem>
                            <SelectItem value="M">Masculino</SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <div class="grid gap-2">
                    <Label for="dh-doc-vinculo"
                        >Documento que sustenta el vínculo</Label
                    >
                    <Select v-model="form.documento_vinculo">
                        <SelectTrigger id="dh-doc-vinculo" class="w-full"
                            ><SelectValue
                        /></SelectTrigger>
                        <SelectContent>
                            <SelectItem :value="SIN"
                                >No corresponde (hijo con DNI)</SelectItem
                            >
                            <SelectItem
                                v-for="o in vinculo?.documentos ?? []"
                                :key="o.value"
                                :value="o.value"
                                >{{ o.label }}</SelectItem
                            >
                        </SelectContent>
                    </Select>
                    <InputError :message="form.errors.documento_vinculo" />
                </div>
                <div v-if="vinculo?.exige_numero" class="grid gap-2">
                    <Label for="dh-num-vinculo"
                        >Número del documento del vínculo</Label
                    >
                    <Input
                        id="dh-num-vinculo"
                        v-model="form.numero_documento_vinculo"
                        maxlength="20"
                    />
                    <InputError
                        :message="form.errors.numero_documento_vinculo"
                    />
                </div>
                <div v-if="form.vinculo === '04'" class="grid gap-2">
                    <Label for="dh-concepcion">Mes de concepción</Label>
                    <Input
                        id="dh-concepcion"
                        v-model="form.mes_concepcion"
                        type="month"
                    />
                    <InputError :message="form.errors.mes_concepcion" />
                </div>
            </div>
            <div>
                <Button
                    type="button"
                    variant="outline"
                    :disabled="form.processing"
                    @click="registrar"
                >
                    <Spinner v-if="form.processing" />
                    <Plus v-else />
                    Agregar
                </Button>
            </div>
        </div>

        <Dialog
            :open="aDarDeBaja !== null"
            @update:open="(v) => !v && (aDarDeBaja = null)"
        >
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Baja de {{ aDarDeBaja?.nombre }}</DialogTitle>
                    <DialogDescription
                        >Queda en la auditoría y se informa a SUNAT en el
                        archivo de bajas.</DialogDescription
                    >
                </DialogHeader>
                <div class="grid gap-4">
                    <div class="grid gap-2">
                        <Label for="dh-fecha-baja">Fecha de baja</Label>
                        <Input
                            id="dh-fecha-baja"
                            v-model="formBaja.fecha_baja"
                            type="date"
                        />
                        <InputError :message="formBaja.errors.fecha_baja" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="dh-motivo">Motivo</Label>
                        <Select v-model="formBaja.motivo_baja">
                            <SelectTrigger id="dh-motivo" class="w-full"
                                ><SelectValue placeholder="Elige"
                            /></SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="o in catalogos.motivos_baja_derechohabiente"
                                    :key="o.value"
                                    :value="o.value"
                                    >{{ o.label }}</SelectItem
                                >
                            </SelectContent>
                        </Select>
                        <InputError :message="formBaja.errors.motivo_baja" />
                    </div>
                </div>
                <DialogFooter>
                    <DialogClose as-child>
                        <Button variant="outline">Cancelar</Button>
                    </DialogClose>
                    <Button
                        :disabled="formBaja.processing || !formBaja.motivo_baja"
                        @click="confirmarBaja"
                    >
                        Registrar baja
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
