<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { CircleAlert, Search } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import Establecimientos from '@/components/empresas/Establecimientos.vue';
import CentrosCosto from '@/components/empresas/CentrosCosto.vue';
import FirmaDigital from '@/components/empresas/FirmaDigital.vue';
import LogoEmpresa from '@/components/empresas/LogoEmpresa.vue';
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
import { consultaRuc, index, store, update } from '@/routes/empresas';
import type {
    CentroCostoFila,
    DatosRuc,
    EmpresaEditable,
    EstablecimientoFila,
    FirmaDigitalEmpresa,
    Opcion,
    RegimenLaboral,
    SeguroSalud,
} from '@/types';

const props = defineProps<{
    empresa: EmpresaEditable | null;
    establecimientos: EstablecimientoFila[];
    regimenes: Opcion<RegimenLaboral>[];
    seguros: Opcion<SeguroSalud>[];
    firma?: FirmaDigitalEmpresa | null;
    centros_costo?: CentroCostoFila[];
}>();

const esNueva = props.empresa === null;
const titulo = esNueva ? 'Nueva empresa' : props.empresa!.razon_social;

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Empresas', href: index() }],
    },
});

const form = useForm({
    ruc: props.empresa?.ruc ?? '',
    razon_social: props.empresa?.razon_social ?? '',
    nombre_comercial: props.empresa?.nombre_comercial ?? '',
    direccion: props.empresa?.direccion ?? '',
    ubigeo: props.empresa?.ubigeo ?? '',
    regimen_laboral:
        props.empresa?.regimen_laboral ?? ('general' as RegimenLaboral),
    essalud_agrario_mayor: props.empresa?.essalud_agrario_mayor ?? false,
    seguro_salud: props.empresa?.seguro_salud ?? ('essalud' as SeguroSalud),
    representante_legal: props.empresa?.representante_legal ?? '',
});

// El SIS solo aplica a microempresas: si cambia el régimen, se vuelve a EsSalud.
watch(
    () => form.regimen_laboral,
    (regimen) => {
        if (regimen !== 'micro' && form.seguro_salud === 'sis') {
            form.seguro_salud = 'essalud';
        }
    },
);

const consultando = ref(false);
const avisoConsulta = ref<{
    tipo: 'error' | 'alerta' | 'ok';
    texto: string;
} | null>(null);
const rucCompleto = computed(() => /^\d{11}$/.test(form.ruc));

async function buscarEnSunat(): Promise<void> {
    if (!rucCompleto.value || consultando.value) {
        return;
    }

    consultando.value = true;
    avisoConsulta.value = null;
    form.clearErrors('ruc');

    try {
        const respuesta = await fetch(consultaRuc.url(form.ruc), {
            headers: { Accept: 'application/json' },
        });
        const datos = (await respuesta.json()) as Partial<DatosRuc> & {
            message?: string;
        };

        if (!respuesta.ok) {
            avisoConsulta.value = {
                tipo: 'error',
                texto: datos.message ?? 'No se pudo consultar el RUC.',
            };

            return;
        }

        form.razon_social = datos.razon_social ?? form.razon_social;
        form.direccion = datos.direccion ?? form.direccion;
        form.ubigeo = datos.ubigeo ?? form.ubigeo;
        avisoConsulta.value = datos.alerta
            ? { tipo: 'alerta', texto: datos.alerta }
            : {
                  tipo: 'ok',
                  texto: 'Datos completados desde SUNAT. Revísalos antes de guardar.',
              };
    } catch {
        avisoConsulta.value = {
            tipo: 'error',
            texto: 'No se pudo consultar el RUC. Completa los datos a mano.',
        };
    } finally {
        consultando.value = false;
    }
}

function guardar(): void {
    if (props.empresa) {
        form.put(update.url(props.empresa.id), { preserveScroll: true });
    } else {
        form.post(store.url(), { preserveScroll: true });
    }
}
</script>

<template>
    <Head :title="titulo" />

    <div class="flex flex-1 flex-col gap-8 p-4 md:p-6">
        <Heading
            :title="titulo"
            :description="
                esNueva
                    ? 'Escribe el RUC y usa «Buscar en SUNAT» para completar los datos.'
                    : `RUC ${empresa!.ruc}`
            "
        />

        <form class="grid max-w-3xl gap-6" @submit.prevent="guardar">
            <div v-if="esNueva" class="grid gap-2">
                <Label for="ruc">RUC</Label>
                <div class="flex gap-2">
                    <Input
                        id="ruc"
                        v-model="form.ruc"
                        inputmode="numeric"
                        maxlength="11"
                        autocomplete="off"
                        class="max-w-48 font-mono tracking-wider"
                        required
                        @keydown.enter.prevent="buscarEnSunat"
                    />
                    <Button
                        type="button"
                        variant="outline"
                        :disabled="!rucCompleto || consultando"
                        @click="buscarEnSunat"
                    >
                        <Spinner v-if="consultando" />
                        <Search v-else />
                        Buscar en SUNAT
                    </Button>
                </div>
                <InputError :message="form.errors.ruc" />
                <p
                    v-if="avisoConsulta"
                    class="flex items-start gap-2 text-sm"
                    :class="{
                        'text-red-600 dark:text-red-500':
                            avisoConsulta.tipo === 'error',
                        'text-amber-700 dark:text-amber-400':
                            avisoConsulta.tipo === 'alerta',
                        'text-muted-foreground': avisoConsulta.tipo === 'ok',
                    }"
                    role="status"
                >
                    <CircleAlert
                        v-if="avisoConsulta.tipo !== 'ok'"
                        class="mt-0.5 size-4 shrink-0"
                    />
                    {{ avisoConsulta.texto }}
                </p>
            </div>

            <div class="grid gap-5 sm:grid-cols-2">
                <div class="grid gap-2 sm:col-span-2">
                    <Label for="razon_social">Razón social</Label>
                    <Input
                        id="razon_social"
                        v-model="form.razon_social"
                        required
                    />
                    <InputError :message="form.errors.razon_social" />
                </div>

                <div class="grid gap-2">
                    <Label for="nombre_comercial">
                        Nombre comercial
                        <span class="font-normal text-muted-foreground">
                            (opcional)
                        </span>
                    </Label>
                    <Input
                        id="nombre_comercial"
                        v-model="form.nombre_comercial"
                    />
                    <InputError :message="form.errors.nombre_comercial" />
                </div>

                <div class="grid gap-2">
                    <Label for="representante_legal">
                        Representante legal
                        <span class="font-normal text-muted-foreground">
                            (opcional)
                        </span>
                    </Label>
                    <Input
                        id="representante_legal"
                        v-model="form.representante_legal"
                    />
                    <InputError :message="form.errors.representante_legal" />
                </div>

                <div class="grid gap-2 sm:col-span-2">
                    <Label for="direccion">Domicilio fiscal</Label>
                    <Input id="direccion" v-model="form.direccion" />
                    <InputError :message="form.errors.direccion" />
                </div>

                <div class="grid gap-2">
                    <Label for="ubigeo">Ubigeo</Label>
                    <Input
                        id="ubigeo"
                        v-model="form.ubigeo"
                        inputmode="numeric"
                        maxlength="6"
                        class="max-w-32 font-mono"
                    />
                    <InputError :message="form.errors.ubigeo" />
                </div>
            </div>

            <div class="grid gap-5 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="regimen_laboral">Régimen laboral</Label>
                    <Select v-model="form.regimen_laboral">
                        <SelectTrigger id="regimen_laboral" class="w-full">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="opcion in regimenes"
                                :key="opcion.value"
                                :value="opcion.value"
                            >
                                {{ opcion.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="form.errors.regimen_laboral" />
                    <label
                        v-if="form.regimen_laboral === 'agrario'"
                        class="flex items-start gap-2 text-sm"
                    >
                        <Checkbox
                            class="mt-0.5"
                            :model-value="form.essalud_agrario_mayor"
                            @update:model-value="
                                (v) => (form.essalud_agrario_mayor = v === true)
                            "
                        />
                        <span>
                            El año anterior declaró 100 o más trabajadores
                            agrarios o ventas de 1,700 UIT o más
                            <span class="block text-xs text-muted-foreground">
                                Define la tasa de EsSalud agrario. Revísalo cada
                                enero.
                            </span>
                        </span>
                    </label>
                    <InputError :message="form.errors.essalud_agrario_mayor" />
                </div>

                <div class="grid gap-2">
                    <Label for="seguro_salud"
                        >Seguro de salud por defecto</Label
                    >
                    <Select v-model="form.seguro_salud">
                        <SelectTrigger id="seguro_salud" class="w-full">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="opcion in seguros"
                                :key="opcion.value"
                                :value="opcion.value"
                                :disabled="
                                    opcion.value === 'sis' &&
                                    form.regimen_laboral !== 'micro'
                                "
                            >
                                {{ opcion.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <p class="text-xs text-muted-foreground">
                        Se propone al registrar trabajadores; cada uno puede
                        tener el suyo.
                    </p>
                    <InputError :message="form.errors.seguro_salud" />
                </div>
            </div>

            <div class="flex gap-3">
                <Button type="submit" :disabled="form.processing">
                    <Spinner v-if="form.processing" />
                    {{ esNueva ? 'Registrar empresa' : 'Guardar cambios' }}
                </Button>
                <Button variant="outline" as-child>
                    <Link :href="index()">Cancelar</Link>
                </Button>
            </div>
        </form>

        <LogoEmpresa
            v-if="empresa"
            :empresa-id="empresa.id"
            :logo="empresa.logo"
        />

        <FirmaDigital
            v-if="empresa"
            :empresa-id="empresa.id"
            :firma="firma ?? null"
        />

        <CentrosCosto
            v-if="empresa"
            :empresa-id="empresa.id"
            :centros="centros_costo ?? []"
        />

        <Establecimientos
            v-if="empresa"
            :empresa-id="empresa.id"
            :establecimientos="establecimientos"
        />
    </div>
</template>
