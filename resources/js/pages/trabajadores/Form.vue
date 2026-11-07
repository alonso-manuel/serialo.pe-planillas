<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { Search, TriangleAlert, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
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
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import {
    consultaDni,
    destroy,
    index,
    store,
    update,
} from '@/routes/trabajadores';
import type { CatalogosTrabajador, TrabajadorEditable } from '@/types';

const props = defineProps<{
    trabajador: TrabajadorEditable | null;
    catalogos: CatalogosTrabajador;
}>();

const esNuevo = props.trabajador === null;
const titulo = esNuevo ? 'Nuevo trabajador' : props.trabajador!.nombre_completo;

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Trabajadores', href: index() }],
    },
});

/** Valor centinela para "sin selección" en los Select (no admiten cadena vacía). */
const NINGUNO = 'ninguno';
const t = props.trabajador;

const form = useForm({
    tipo_documento: t?.tipo_documento ?? '01',
    numero_documento: t?.numero_documento ?? '',
    apellido_paterno: t?.apellido_paterno ?? '',
    apellido_materno: t?.apellido_materno ?? '',
    nombres: t?.nombres ?? '',
    fecha_nacimiento: t?.fecha_nacimiento ?? '',
    sexo: t?.sexo ?? NINGUNO,
    email: t?.email ?? '',
    telefono: t?.telefono ?? '',
    establecimiento_id: t?.establecimiento_id
        ? String(t.establecimiento_id)
        : (props.catalogos.establecimientos[0]?.value ?? NINGUNO),
    cargo: t?.cargo ?? '',
    fecha_ingreso: t?.fecha_ingreso ?? '',
    tipo_contrato: t?.tipo_contrato ?? 'indeterminado',
    remuneracion_basica: t?.remuneracion_basica ?? '',
    asignacion_familiar: t?.asignacion_familiar ?? false,
    sistema_pension: t?.sistema_pension ?? 'onp',
    afp_id: t?.afp_id ? String(t.afp_id) : NINGUNO,
    cuspp: t?.cuspp ?? '',
    tipo_comision_afp: t?.tipo_comision_afp ?? 'flujo',
    seguro_salud: t?.seguro_salud ?? props.catalogos.seguro_por_defecto,
    cuenta_sueldo: t?.cuenta_sueldo ?? '',
    cuenta_cts: t?.cuenta_cts ?? '',
});

/* ---------- Pestañas y errores ---------- */
const PESTANAS = {
    personales: [
        'tipo_documento',
        'numero_documento',
        'apellido_paterno',
        'apellido_materno',
        'nombres',
        'fecha_nacimiento',
        'sexo',
        'email',
        'telefono',
    ],
    laborales: [
        'establecimiento_id',
        'cargo',
        'fecha_ingreso',
        'tipo_contrato',
        'remuneracion_basica',
        'asignacion_familiar',
    ],
    pension: [
        'sistema_pension',
        'afp_id',
        'cuspp',
        'tipo_comision_afp',
        'seguro_salud',
    ],
    cuentas: ['cuenta_sueldo', 'cuenta_cts'],
} as const;

type Pestana = keyof typeof PESTANAS;

const pestana = ref<Pestana>('personales');

function errores(p: Pestana): number {
    return PESTANAS[p].filter((campo) => campo in form.errors).length;
}

const esAfp = computed(() => form.sistema_pension === 'afp');

function guardar(): void {
    const opciones = {
        preserveScroll: true,
        onError: () => {
            const conError = (Object.keys(PESTANAS) as Pestana[]).find(
                (p) => errores(p) > 0,
            );
            if (conError) {
                pestana.value = conError;
            }
        },
    };

    form.transform((d) => ({
        ...d,
        sexo: d.sexo === NINGUNO ? null : d.sexo,
        establecimiento_id:
            d.establecimiento_id === NINGUNO ? null : d.establecimiento_id,
        afp_id: d.afp_id === NINGUNO ? null : d.afp_id,
    }));

    if (props.trabajador) {
        form.put(update.url(props.trabajador.id), opciones);
    } else {
        form.post(store.url(), opciones);
    }
}

/* ---------- Consultar DNI (RENIEC) ---------- */
const consultando = ref(false);
const avisoDni = ref<{ ok: boolean; texto: string } | null>(null);
const puedeConsultar = computed(
    () =>
        props.catalogos.consulta_dni &&
        form.tipo_documento === '01' &&
        /^\d{8}$/.test(form.numero_documento),
);

async function consultarDni(): Promise<void> {
    if (!puedeConsultar.value || consultando.value) {
        return;
    }

    consultando.value = true;
    avisoDni.value = null;
    form.clearErrors('numero_documento');

    try {
        const respuesta = await fetch(consultaDni.url(form.numero_documento), {
            headers: { Accept: 'application/json' },
        });
        const datos = (await respuesta.json()) as {
            apellido_paterno?: string;
            apellido_materno?: string | null;
            nombres?: string;
            message?: string;
        };

        if (!respuesta.ok) {
            avisoDni.value = {
                ok: false,
                texto: datos.message ?? 'No se pudo consultar el DNI.',
            };

            return;
        }

        form.apellido_paterno = datos.apellido_paterno ?? form.apellido_paterno;
        form.apellido_materno = datos.apellido_materno ?? '';
        form.nombres = datos.nombres ?? form.nombres;
        avisoDni.value = {
            ok: true,
            texto: 'Apellidos y nombres completados desde RENIEC.',
        };
    } catch {
        avisoDni.value = {
            ok: false,
            texto: 'No se pudo consultar el DNI. Completa los datos a mano.',
        };
    } finally {
        consultando.value = false;
    }
}

/* ---------- Avisos (no bloquean) ---------- */
const avisoRmv = computed(() => {
    const rmv = props.catalogos.rmv;
    const basico = form.remuneracion_basica;

    if (!rmv || !/^\d+(\.\d{1,2})?$/.test(basico)) {
        return null;
    }

    // Comparación solo para el aviso (el cálculo usa BigDecimal en el servidor).
    return Number(basico) < Number(rmv)
        ? `Es menor que la RMV vigente (S/ ${rmv.replace(/\.?0+$/, '')}). Revisa si es tiempo parcial.`
        : null;
});

const avisoEdad = computed(() => {
    if (!form.fecha_nacimiento) {
        return null;
    }

    const nacimiento = new Date(`${form.fecha_nacimiento}T00:00:00`);
    const hoy = new Date();
    let edad = hoy.getFullYear() - nacimiento.getFullYear();
    const cumplio =
        hoy.getMonth() > nacimiento.getMonth() ||
        (hoy.getMonth() === nacimiento.getMonth() &&
            hoy.getDate() >= nacimiento.getDate());
    if (!cumplio) {
        edad--;
    }

    return edad >= 0 && edad < 18
        ? `Tiene ${edad} años: un menor de edad requiere autorización de trabajo.`
        : null;
});

/* ---------- Eliminar ---------- */
const confirmarEliminar = ref(false);

function eliminar(): void {
    if (props.trabajador) {
        router.delete(destroy.url(props.trabajador.id));
    }
}
</script>

<template>
    <Head :title="titulo" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading
                :title="titulo"
                :description="
                    esNuevo
                        ? `Se registrará en ${catalogos.empresa}.`
                        : catalogos.empresa
                "
            />
            <Badge v-if="trabajador?.cesado" variant="secondary">Cesado</Badge>
        </div>

        <form class="grid max-w-3xl gap-6" @submit.prevent="guardar">
            <Tabs v-model="pestana">
                <TabsList class="w-full sm:w-fit">
                    <TabsTrigger
                        v-for="(nombre, clave) in {
                            personales: 'Personales',
                            laborales: 'Laborales',
                            pension: 'Pensión y salud',
                            cuentas: 'Cuentas',
                        }"
                        :key="clave"
                        :value="clave"
                        class="gap-1.5"
                    >
                        {{ nombre }}
                        <span
                            v-if="errores(clave)"
                            class="inline-flex size-4 items-center justify-center rounded-full bg-red-600 text-[10px] text-white"
                            :aria-label="`${errores(clave)} errores`"
                        >
                            {{ errores(clave) }}
                        </span>
                    </TabsTrigger>
                </TabsList>

                <!-- Personales -->
                <TabsContent
                    value="personales"
                    class="grid gap-5 pt-4 sm:grid-cols-2"
                >
                    <div class="grid gap-2">
                        <Label for="tipo_documento">Tipo de documento</Label>
                        <Select v-model="form.tipo_documento">
                            <SelectTrigger id="tipo_documento" class="w-full"
                                ><SelectValue
                            /></SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="o in catalogos.tipos_documento"
                                    :key="o.value"
                                    :value="o.value"
                                >
                                    {{ o.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="form.errors.tipo_documento" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="numero_documento">Número</Label>
                        <div class="flex gap-2">
                            <Input
                                id="numero_documento"
                                v-model="form.numero_documento"
                                :inputmode="
                                    form.tipo_documento === '01'
                                        ? 'numeric'
                                        : 'text'
                                "
                                :maxlength="
                                    form.tipo_documento === '01' ? 8 : 12
                                "
                                class="font-mono"
                                autocomplete="off"
                                required
                                @keydown.enter.prevent="consultarDni"
                            />
                            <Button
                                v-if="form.tipo_documento === '01'"
                                type="button"
                                variant="outline"
                                :disabled="!puedeConsultar || consultando"
                                :title="
                                    catalogos.consulta_dni
                                        ? 'Completar apellidos y nombres desde RENIEC'
                                        : 'La consulta de DNI no está configurada'
                                "
                                @click="consultarDni"
                            >
                                <Spinner v-if="consultando" />
                                <Search v-else />
                                Consultar
                            </Button>
                        </div>
                        <InputError :message="form.errors.numero_documento" />
                        <p
                            v-if="avisoDni"
                            class="flex gap-1.5 text-xs"
                            :class="
                                avisoDni.ok
                                    ? 'text-muted-foreground'
                                    : 'text-amber-700 dark:text-amber-400'
                            "
                            role="status"
                        >
                            <TriangleAlert
                                v-if="!avisoDni.ok"
                                class="size-3.5 shrink-0"
                            />{{ avisoDni.texto }}
                        </p>
                    </div>
                    <div class="grid gap-2">
                        <Label for="apellido_paterno">Apellido paterno</Label>
                        <Input
                            id="apellido_paterno"
                            v-model="form.apellido_paterno"
                            required
                        />
                        <InputError :message="form.errors.apellido_paterno" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="apellido_materno">Apellido materno</Label>
                        <Input
                            id="apellido_materno"
                            v-model="form.apellido_materno"
                        />
                        <InputError :message="form.errors.apellido_materno" />
                    </div>
                    <div class="grid gap-2 sm:col-span-2">
                        <Label for="nombres">Nombres</Label>
                        <Input id="nombres" v-model="form.nombres" required />
                        <InputError :message="form.errors.nombres" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="fecha_nacimiento"
                            >Fecha de nacimiento</Label
                        >
                        <Input
                            id="fecha_nacimiento"
                            v-model="form.fecha_nacimiento"
                            type="date"
                        />
                        <InputError :message="form.errors.fecha_nacimiento" />
                        <p
                            v-if="avisoEdad"
                            class="flex gap-1.5 text-xs text-amber-700 dark:text-amber-400"
                        >
                            <TriangleAlert class="size-3.5 shrink-0" />{{
                                avisoEdad
                            }}
                        </p>
                    </div>
                    <div class="grid gap-2">
                        <Label for="sexo">Sexo</Label>
                        <Select v-model="form.sexo">
                            <SelectTrigger id="sexo" class="w-full"
                                ><SelectValue
                            /></SelectTrigger>
                            <SelectContent>
                                <SelectItem :value="NINGUNO"
                                    >Sin indicar</SelectItem
                                >
                                <SelectItem value="F">Femenino</SelectItem>
                                <SelectItem value="M">Masculino</SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="form.errors.sexo" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="email">Correo electrónico</Label>
                        <Input id="email" v-model="form.email" type="email" />
                        <InputError :message="form.errors.email" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="telefono">Teléfono</Label>
                        <Input
                            id="telefono"
                            v-model="form.telefono"
                            inputmode="tel"
                        />
                        <InputError :message="form.errors.telefono" />
                    </div>
                </TabsContent>

                <!-- Laborales -->
                <TabsContent
                    value="laborales"
                    class="grid gap-5 pt-4 sm:grid-cols-2"
                >
                    <div class="grid gap-2">
                        <Label for="establecimiento_id">Establecimiento</Label>
                        <Select v-model="form.establecimiento_id">
                            <SelectTrigger
                                id="establecimiento_id"
                                class="w-full"
                                ><SelectValue
                            /></SelectTrigger>
                            <SelectContent>
                                <SelectItem :value="NINGUNO"
                                    >Sin asignar</SelectItem
                                >
                                <SelectItem
                                    v-for="o in catalogos.establecimientos"
                                    :key="o.value"
                                    :value="o.value"
                                >
                                    {{ o.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="form.errors.establecimiento_id" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="cargo">Cargo</Label>
                        <Input id="cargo" v-model="form.cargo" />
                        <InputError :message="form.errors.cargo" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="fecha_ingreso">Fecha de ingreso</Label>
                        <Input
                            id="fecha_ingreso"
                            v-model="form.fecha_ingreso"
                            type="date"
                            required
                        />
                        <InputError :message="form.errors.fecha_ingreso" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="tipo_contrato">Tipo de contrato</Label>
                        <Select v-model="form.tipo_contrato">
                            <SelectTrigger id="tipo_contrato" class="w-full"
                                ><SelectValue
                            /></SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="o in catalogos.tipos_contrato"
                                    :key="o.value"
                                    :value="o.value"
                                >
                                    {{ o.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="form.errors.tipo_contrato" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="remuneracion_basica"
                            >Remuneración básica</Label
                        >
                        <div class="relative">
                            <span
                                class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-sm text-muted-foreground"
                                >S/</span
                            >
                            <Input
                                id="remuneracion_basica"
                                v-model="form.remuneracion_basica"
                                inputmode="decimal"
                                class="pl-9 tabular-nums"
                                required
                            />
                        </div>
                        <InputError
                            :message="form.errors.remuneracion_basica"
                        />
                        <p
                            v-if="avisoRmv"
                            class="flex gap-1.5 text-xs text-amber-700 dark:text-amber-400"
                        >
                            <TriangleAlert class="size-3.5 shrink-0" />{{
                                avisoRmv
                            }}
                        </p>
                    </div>
                    <label
                        class="flex items-center gap-2 self-end pb-2 text-sm"
                    >
                        <Checkbox v-model="form.asignacion_familiar" />
                        Percibe asignación familiar
                    </label>
                </TabsContent>

                <!-- Pensión y salud -->
                <TabsContent value="pension" class="grid gap-5 pt-4">
                    <div class="grid gap-2">
                        <Label>Sistema de pensiones</Label>
                        <div
                            class="grid gap-3 sm:grid-cols-3"
                            role="radiogroup"
                        >
                            <label
                                v-for="o in [
                                    {
                                        value: 'onp',
                                        label: 'ONP',
                                        detalle:
                                            'Sistema Nacional de Pensiones',
                                    },
                                    {
                                        value: 'afp',
                                        label: 'AFP',
                                        detalle: 'Sistema Privado de Pensiones',
                                    },
                                    {
                                        value: 'ninguno',
                                        label: 'Ninguno',
                                        detalle: 'Caso excepcional',
                                    },
                                ]"
                                :key="o.value"
                                class="flex cursor-pointer gap-3 rounded-lg border p-3 has-checked:border-primary has-checked:bg-primary/5"
                            >
                                <input
                                    v-model="form.sistema_pension"
                                    type="radio"
                                    :value="o.value"
                                    class="mt-1 accent-primary"
                                />
                                <span class="grid">
                                    <span class="font-medium">{{
                                        o.label
                                    }}</span>
                                    <span
                                        class="text-xs text-muted-foreground"
                                        >{{ o.detalle }}</span
                                    >
                                </span>
                            </label>
                        </div>
                        <InputError :message="form.errors.sistema_pension" />
                    </div>

                    <div
                        v-if="esAfp"
                        class="grid gap-5 rounded-lg border p-4 sm:grid-cols-3"
                    >
                        <div class="grid gap-2">
                            <Label for="afp_id">AFP</Label>
                            <Select v-model="form.afp_id">
                                <SelectTrigger id="afp_id" class="w-full"
                                    ><SelectValue placeholder="Elige"
                                /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="o in catalogos.afps"
                                        :key="o.value"
                                        :value="o.value"
                                    >
                                        {{ o.label }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError :message="form.errors.afp_id" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="cuspp">CUSPP</Label>
                            <Input
                                id="cuspp"
                                v-model="form.cuspp"
                                maxlength="12"
                                class="font-mono uppercase"
                            />
                            <InputError :message="form.errors.cuspp" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="tipo_comision_afp">Comisión</Label>
                            <Select v-model="form.tipo_comision_afp">
                                <SelectTrigger
                                    id="tipo_comision_afp"
                                    class="w-full"
                                    ><SelectValue
                                /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="o in catalogos.tipos_comision"
                                        :key="o.value"
                                        :value="o.value"
                                    >
                                        {{ o.label }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError
                                :message="form.errors.tipo_comision_afp"
                            />
                        </div>
                    </div>

                    <div class="grid gap-2 sm:max-w-xs">
                        <Label for="seguro_salud">Seguro de salud</Label>
                        <Select v-model="form.seguro_salud">
                            <SelectTrigger id="seguro_salud" class="w-full"
                                ><SelectValue
                            /></SelectTrigger>
                            <SelectContent>
                                <SelectItem value="essalud">EsSalud</SelectItem>
                                <SelectItem
                                    value="sis"
                                    :disabled="!catalogos.permite_sis"
                                >
                                    SIS (solo microempresa)
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="form.errors.seguro_salud" />
                    </div>
                </TabsContent>

                <!-- Cuentas -->
                <TabsContent
                    value="cuentas"
                    class="grid gap-5 pt-4 sm:grid-cols-2"
                >
                    <div class="grid gap-2">
                        <Label for="cuenta_sueldo">Cuenta de sueldo</Label>
                        <Input
                            id="cuenta_sueldo"
                            v-model="form.cuenta_sueldo"
                            inputmode="numeric"
                            class="font-mono"
                        />
                        <InputError :message="form.errors.cuenta_sueldo" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="cuenta_cts">Cuenta CTS</Label>
                        <Input
                            id="cuenta_cts"
                            v-model="form.cuenta_cts"
                            inputmode="numeric"
                            class="font-mono"
                        />
                        <InputError :message="form.errors.cuenta_cts" />
                    </div>
                </TabsContent>
            </Tabs>

            <div class="flex flex-wrap items-center gap-3">
                <Button type="submit" :disabled="form.processing">
                    <Spinner v-if="form.processing" />
                    {{ esNuevo ? 'Registrar trabajador' : 'Guardar cambios' }}
                </Button>
                <Button variant="outline" as-child>
                    <Link :href="index()">Cancelar</Link>
                </Button>
                <Button
                    v-if="trabajador && !trabajador.en_planilla"
                    type="button"
                    variant="ghost"
                    class="ml-auto text-destructive hover:text-destructive"
                    @click="confirmarEliminar = true"
                >
                    <Trash2 />
                    Eliminar
                </Button>
            </div>
        </form>
    </div>

    <Dialog v-model:open="confirmarEliminar">
        <DialogContent>
            <DialogHeader>
                <DialogTitle
                    >¿Eliminar a {{ trabajador?.nombre_completo }}?</DialogTitle
                >
                <DialogDescription>
                    Nunca estuvo en una planilla, así que se puede eliminar por
                    completo (por ejemplo, si se registró por error).
                </DialogDescription>
            </DialogHeader>
            <DialogFooter>
                <DialogClose as-child>
                    <Button variant="outline">Cancelar</Button>
                </DialogClose>
                <Button variant="destructive" @click="eliminar"
                    >Eliminar</Button
                >
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
