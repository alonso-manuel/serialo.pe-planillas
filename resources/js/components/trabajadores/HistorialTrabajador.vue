<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { ArrowRight, History, Plus, Undo2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
import { destroy, store } from '@/routes/trabajadores/cambios';
import type {
    CambioHistorial,
    CatalogosTrabajador,
    Opcion,
    TrabajadorEditable,
} from '@/types';

const props = defineProps<{
    trabajador: TrabajadorEditable;
    historial: CambioHistorial[];
    grupos: Opcion[];
    catalogos: CatalogosTrabajador;
}>();

const NINGUNO = 'ninguno';

/** Mes siguiente al actual, como AAAA-MM (propuesta por defecto). */
function mesSiguiente(): string {
    const hoy = new Date();
    const siguiente = new Date(hoy.getFullYear(), hoy.getMonth() + 1, 1);

    return `${siguiente.getFullYear()}-${String(siguiente.getMonth() + 1).padStart(2, '0')}`;
}

const abierto = ref(false);
const form = useForm({
    grupo: 'remuneracion',
    vigente_desde: mesSiguiente(),
    remuneracion_basica: '',
    cargo: '',
    asignacion_familiar: false,
    sistema_pension: 'onp' as string,
    afp_id: NINGUNO,
    tipo_comision_afp: 'flujo',
    cuspp: '',
    seguro_salud: 'essalud' as string,
    tipo_contrato: 'indeterminado',
    centro_costo_id: NINGUNO,
});

function abrir(grupo = 'remuneracion'): void {
    const t = props.trabajador;
    form.clearErrors();
    Object.assign(form, {
        grupo,
        vigente_desde: mesSiguiente(),
        remuneracion_basica: t.remuneracion_basica,
        cargo: t.cargo ?? '',
        asignacion_familiar: t.asignacion_familiar,
        sistema_pension: t.sistema_pension,
        afp_id: t.afp_id ? String(t.afp_id) : NINGUNO,
        tipo_comision_afp: t.tipo_comision_afp ?? 'flujo',
        cuspp: t.cuspp ?? '',
        seguro_salud: t.seguro_salud,
        tipo_contrato: t.tipo_contrato,
        centro_costo_id: t.centro_costo_id
            ? String(t.centro_costo_id)
            : NINGUNO,
    });
    abierto.value = true;
}

const etiquetaGrupo = computed(
    () => props.grupos.find((g) => g.value === form.grupo)?.label ?? '',
);

function guardar(): void {
    form.transform((d) => ({
        ...d,
        afp_id: d.afp_id === NINGUNO ? null : d.afp_id,
        centro_costo_id:
            d.centro_costo_id === NINGUNO ? null : d.centro_costo_id,
    })).post(store.url(props.trabajador.id), {
        preserveScroll: true,
        onSuccess: () => (abierto.value = false),
    });
}

/* ---------- Anular ---------- */
const porAnular = ref<CambioHistorial | null>(null);

function anular(): void {
    if (porAnular.value) {
        router.delete(
            destroy.url({
                trabajador: props.trabajador.id,
                historial: porAnular.value.id,
            }),
            { preserveScroll: true },
        );
        porAnular.value = null;
    }
}
</script>

<template>
    <section class="grid gap-4">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <p class="max-w-md text-sm text-muted-foreground">
                Cambios de sueldo, cargo, contrato, asignación familiar, pensión
                y seguro con el mes desde el que rigen. Cada planilla usa los
                valores vigentes en su mes.
            </p>
            <Button type="button" @click="abrir()">
                <Plus />
                Registrar cambio
            </Button>
        </div>

        <div
            v-if="historial.length === 0"
            class="flex flex-col items-center gap-2 rounded-lg border border-dashed px-6 py-10 text-center text-sm text-muted-foreground"
        >
            <History class="size-6" />
            Aún no hay cambios registrados.
        </div>

        <ol v-else class="relative grid gap-4 border-l pl-6">
            <li v-for="c in historial" :key="c.id" class="relative">
                <span
                    class="absolute top-1.5 -left-[1.95rem] size-3 rounded-full border-2 border-background bg-primary"
                />
                <div
                    class="flex flex-wrap items-baseline justify-between gap-2"
                >
                    <p class="text-sm">
                        <span class="font-medium">{{ c.grupo_etiqueta }}</span>
                        <span class="text-muted-foreground">
                            · desde {{ c.desde }}</span
                        >
                    </p>
                    <div
                        class="flex items-center gap-2 text-xs text-muted-foreground"
                    >
                        {{ c.usuario
                        }}<template v-if="c.registrado"
                            >, {{ c.registrado }}</template
                        >
                        <Button
                            v-if="c.anulable"
                            type="button"
                            variant="ghost"
                            size="sm"
                            class="h-7 text-destructive hover:text-destructive"
                            @click="porAnular = c"
                        >
                            <Undo2 class="size-3.5" />
                            Anular
                        </Button>
                    </div>
                </div>
                <ul class="mt-1 grid gap-0.5 text-sm">
                    <li
                        v-for="cambio in c.cambios"
                        :key="cambio.campo"
                        class="flex flex-wrap items-center gap-1.5"
                    >
                        <span
                            v-if="c.cambios.length > 1"
                            class="text-xs text-muted-foreground"
                        >
                            {{ cambio.campo }}:
                        </span>
                        <span class="text-muted-foreground line-through">{{
                            cambio.antes
                        }}</span>
                        <ArrowRight class="size-3.5 text-muted-foreground" />
                        <span class="font-medium">{{ cambio.despues }}</span>
                    </li>
                </ul>
            </li>
        </ol>
    </section>

    <!-- Registrar cambio -->
    <Dialog v-model:open="abierto">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Registrar cambio</DialogTitle>
                <DialogDescription>
                    Rige desde el día 1 del mes elegido. Los meses anteriores
                    conservan el valor que tenían.
                </DialogDescription>
            </DialogHeader>

            <form id="form-cambio" class="grid gap-4" @submit.prevent="guardar">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="grupo">Dato que cambia</Label>
                        <Select v-model="form.grupo">
                            <SelectTrigger id="grupo" class="w-full"
                                ><SelectValue
                            /></SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="g in grupos"
                                    :key="g.value"
                                    :value="g.value"
                                >
                                    {{ g.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="form.errors.grupo" />
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

                <fieldset class="grid gap-4 rounded-lg border p-4">
                    <legend class="px-1 text-sm font-medium">
                        {{ etiquetaGrupo }} nuevo
                    </legend>

                    <template v-if="form.grupo === 'remuneracion'">
                        <div class="relative sm:max-w-xs">
                            <span
                                class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-sm text-muted-foreground"
                                >S/</span
                            >
                            <Input
                                v-model="form.remuneracion_basica"
                                inputmode="decimal"
                                class="pl-9 tabular-nums"
                                aria-label="Nueva remuneración básica"
                            />
                        </div>
                        <InputError
                            :message="form.errors.remuneracion_basica"
                        />
                    </template>

                    <template v-else-if="form.grupo === 'cargo'">
                        <Input v-model="form.cargo" aria-label="Nuevo cargo" />
                        <InputError :message="form.errors.cargo" />
                    </template>

                    <template v-else-if="form.grupo === 'asignacion_familiar'">
                        <label class="flex items-center gap-2 text-sm">
                            <Checkbox v-model="form.asignacion_familiar" />
                            Percibe asignación familiar
                        </label>
                        <InputError
                            :message="form.errors.asignacion_familiar"
                        />
                    </template>

                    <template v-else-if="form.grupo === 'pension'">
                        <div class="flex flex-wrap gap-4 text-sm">
                            <label
                                v-for="o in [
                                    { v: 'onp', l: 'ONP' },
                                    { v: 'afp', l: 'AFP' },
                                    { v: 'ninguno', l: 'Ninguno' },
                                ]"
                                :key="o.v"
                                class="flex items-center gap-2"
                            >
                                <input
                                    v-model="form.sistema_pension"
                                    type="radio"
                                    :value="o.v"
                                    class="accent-primary"
                                />
                                {{ o.l }}
                            </label>
                        </div>
                        <div
                            v-if="form.sistema_pension === 'afp'"
                            class="grid gap-3 sm:grid-cols-3"
                        >
                            <div class="grid gap-1">
                                <Select v-model="form.afp_id">
                                    <SelectTrigger
                                        class="w-full"
                                        aria-label="AFP"
                                        ><SelectValue placeholder="AFP"
                                    /></SelectTrigger>
                                    <SelectContent>
                                        <SelectItem
                                            v-for="o in catalogos.afps"
                                            :key="o.value"
                                            :value="o.value"
                                            >{{ o.label }}</SelectItem
                                        >
                                    </SelectContent>
                                </Select>
                                <InputError :message="form.errors.afp_id" />
                            </div>
                            <div class="grid gap-1">
                                <Input
                                    v-model="form.cuspp"
                                    maxlength="12"
                                    placeholder="CUSPP"
                                    class="font-mono uppercase"
                                    aria-label="CUSPP"
                                />
                                <InputError :message="form.errors.cuspp" />
                            </div>
                            <div class="grid gap-1">
                                <Select v-model="form.tipo_comision_afp">
                                    <SelectTrigger
                                        class="w-full"
                                        aria-label="Comisión"
                                        ><SelectValue
                                    /></SelectTrigger>
                                    <SelectContent>
                                        <SelectItem
                                            v-for="o in catalogos.tipos_comision"
                                            :key="o.value"
                                            :value="o.value"
                                            >{{ o.label }}</SelectItem
                                        >
                                    </SelectContent>
                                </Select>
                                <InputError
                                    :message="form.errors.tipo_comision_afp"
                                />
                            </div>
                        </div>
                        <InputError :message="form.errors.sistema_pension" />
                    </template>

                    <template v-else-if="form.grupo === 'seguro_salud'">
                        <Select v-model="form.seguro_salud">
                            <SelectTrigger
                                class="w-full sm:max-w-xs"
                                aria-label="Seguro de salud"
                                ><SelectValue
                            /></SelectTrigger>
                            <SelectContent>
                                <SelectItem value="essalud">EsSalud</SelectItem>
                                <SelectItem
                                    value="sis"
                                    :disabled="!catalogos.permite_sis"
                                    >SIS (solo microempresa)</SelectItem
                                >
                            </SelectContent>
                        </Select>
                        <InputError :message="form.errors.seguro_salud" />
                    </template>

                    <template v-else-if="form.grupo === 'tipo_contrato'">
                        <Select v-model="form.tipo_contrato">
                            <SelectTrigger
                                class="w-full sm:max-w-xs"
                                aria-label="Tipo de contrato"
                                ><SelectValue
                            /></SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="o in catalogos.tipos_contrato"
                                    :key="o.value"
                                    :value="o.value"
                                    >{{ o.label }}</SelectItem
                                >
                            </SelectContent>
                        </Select>
                        <InputError :message="form.errors.tipo_contrato" />
                    </template>

                    <template v-else-if="form.grupo === 'centro_costo'">
                        <Select v-model="form.centro_costo_id">
                            <SelectTrigger
                                class="w-full sm:max-w-xs"
                                aria-label="Centro de costo"
                                ><SelectValue
                            /></SelectTrigger>
                            <SelectContent>
                                <SelectItem :value="NINGUNO"
                                    >Sin centro de costo</SelectItem
                                >
                                <SelectItem
                                    v-for="o in catalogos.centros_costo"
                                    :key="o.value"
                                    :value="o.value"
                                    >{{ o.label }}</SelectItem
                                >
                            </SelectContent>
                        </Select>
                        <InputError :message="form.errors.centro_costo_id" />
                    </template>
                </fieldset>
            </form>

            <DialogFooter>
                <DialogClose as-child>
                    <Button variant="outline">Cancelar</Button>
                </DialogClose>
                <Button
                    type="submit"
                    form="form-cambio"
                    :disabled="form.processing"
                >
                    <Spinner v-if="form.processing" />
                    Registrar cambio
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <!-- Anular -->
    <Dialog
        :open="porAnular !== null"
        @update:open="(v) => !v && (porAnular = null)"
    >
        <DialogContent>
            <DialogHeader>
                <DialogTitle
                    >¿Anular el cambio de
                    {{ porAnular?.grupo_etiqueta.toLowerCase() }} desde
                    {{ porAnular?.desde }}?</DialogTitle
                >
                <DialogDescription>
                    Se restaura el valor anterior. Ningún periodo cerrado usa
                    este cambio; los periodos abiertos deberán recalcularse.
                </DialogDescription>
            </DialogHeader>
            <DialogFooter>
                <DialogClose as-child>
                    <Button variant="outline">Cancelar</Button>
                </DialogClose>
                <Button variant="destructive" @click="anular"
                    >Anular cambio</Button
                >
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
