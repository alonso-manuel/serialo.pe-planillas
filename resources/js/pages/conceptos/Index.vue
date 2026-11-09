<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    Check,
    CircleCheck,
    CircleOff,
    Info,
    Lock,
    Pencil,
    Plus,
    Search,
    Trash2,
} from '@lucide/vue';
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
import { Spinner } from '@/components/ui/spinner';
import { destroy, estado, index, store, update } from '@/routes/conceptos';
import type { ConceptoFila, GrupoConceptos } from '@/types';

const props = defineProps<{
    grupos: GrupoConceptos[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Conceptos', href: index() }],
    },
});

const AFECTACIONES = [
    { campo: 'afecto_essalud', titulo: 'EsSalud' },
    { campo: 'afecto_onp', titulo: 'ONP' },
    { campo: 'afecto_afp', titulo: 'AFP' },
    { campo: 'afecto_quinta', titulo: '5.ª cat.' },
] as const;

/* ---------- Búsqueda ---------- */
const buscar = ref('');
const gruposFiltrados = computed(() => {
    const texto = buscar.value.trim().toLowerCase();

    return props.grupos
        .map((g) => ({
            ...g,
            conceptos: texto
                ? g.conceptos.filter(
                      (c) =>
                          c.codigo_plame.includes(texto) ||
                          c.nombre.toLowerCase().includes(texto),
                  )
                : g.conceptos,
        }))
        .filter((g) => g.conceptos.length > 0 || !texto);
});

/* ---------- Crear / editar ---------- */
const abierto = ref(false);
const editando = ref<ConceptoFila | null>(null);
const form = useForm({
    codigo_plame: '',
    nombre: '',
    afecto_essalud: false,
    afecto_onp: false,
    afecto_afp: false,
    afecto_quinta: false,
    cuenta_cargo: '',
    cuenta_abono: '',
});

/** Tipo según el código (misma regla que el servidor). */
const tipoDelCodigo = computed(() => {
    const prefijo = form.codigo_plame.slice(0, 2);

    if (!/^\d{4}$/.test(form.codigo_plame)) {
        return null;
    }

    if (prefijo === '07') {
        return 'Descuento';
    }

    if (prefijo === '06' || prefijo === '08') {
        return 'Aporte (no se puede crear)';
    }

    return ['01', '02', '03', '04', '05', '09', '10'].includes(prefijo)
        ? 'Ingreso'
        : 'Código fuera de la tabla 22';
});

const muestraAfectaciones = computed(() =>
    editando.value
        ? editando.value.tipo === 'ingreso'
        : tipoDelCodigo.value === 'Ingreso',
);

function nuevo(): void {
    editando.value = null;
    form.reset();
    form.clearErrors();
    abierto.value = true;
}

function editar(c: ConceptoFila): void {
    editando.value = c;
    form.clearErrors();
    form.codigo_plame = c.codigo_plame;
    form.nombre = c.nombre;
    form.afecto_essalud = c.afecto_essalud;
    form.afecto_onp = c.afecto_onp;
    form.afecto_afp = c.afecto_afp;
    form.afecto_quinta = c.afecto_quinta;
    form.cuenta_cargo = c.cuenta_cargo ?? '';
    form.cuenta_abono = c.cuenta_abono ?? '';
    abierto.value = true;
}

const cambiaAfectaciones = computed(
    () =>
        editando.value !== null &&
        AFECTACIONES.some((a) => form[a.campo] !== editando.value![a.campo]),
);

function guardar(): void {
    const opciones = {
        preserveScroll: true,
        onSuccess: () => (abierto.value = false),
    };

    if (editando.value) {
        form.put(update.url(editando.value.id), opciones);
    } else {
        form.post(store.url(), opciones);
    }
}

/* ---------- Estado / eliminar ---------- */
function alternarEstado(c: ConceptoFila): void {
    router.patch(estado.url(c.id), {}, { preserveScroll: true });
}

const porEliminar = ref<ConceptoFila | null>(null);

function confirmarEliminacion(): void {
    if (porEliminar.value) {
        router.delete(destroy.url(porEliminar.value.id), {
            preserveScroll: true,
        });
        porEliminar.value = null;
    }
}
</script>

<template>
    <Head title="Conceptos" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading
                title="Conceptos"
                description="Pagos y descuentos de la tabla 22 de SUNAT y a qué aportes afectan."
            />
            <Button @click="nuevo">
                <Plus />
                Nuevo concepto
            </Button>
        </div>

        <div class="relative w-full sm:max-w-xs">
            <Search
                class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
            />
            <Input
                v-model="buscar"
                class="pl-9"
                placeholder="Buscar por código o nombre"
                aria-label="Buscar concepto"
            />
        </div>

        <section
            v-for="grupo in gruposFiltrados"
            :key="grupo.tipo"
            class="grid gap-2"
        >
            <h2 class="text-sm font-semibold text-muted-foreground">
                {{ grupo.etiqueta }}
                <span class="font-normal">({{ grupo.conceptos.length }})</span>
            </h2>

            <div class="overflow-x-auto rounded-lg border">
                <table class="w-full text-sm">
                    <thead class="bg-muted/50 text-left text-muted-foreground">
                        <tr>
                            <th class="w-20 px-4 py-2 font-medium">Código</th>
                            <th class="px-4 py-2 font-medium">Nombre</th>
                            <template v-if="grupo.tipo === 'ingreso'">
                                <th
                                    v-for="a in AFECTACIONES"
                                    :key="a.campo"
                                    class="w-20 px-2 py-2 text-center font-medium"
                                >
                                    {{ a.titulo }}
                                </th>
                            </template>
                            <th class="w-24 px-4 py-2 font-medium">Estado</th>
                            <th class="w-28 px-2 py-2">
                                <span class="sr-only">Acciones</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="c in grupo.conceptos"
                            :key="c.id"
                            class="border-t"
                            :class="{ 'text-muted-foreground': !c.activo }"
                        >
                            <td class="px-4 py-2 font-mono">
                                {{ c.codigo_plame }}
                            </td>
                            <td class="px-4 py-2">
                                {{ c.nombre }}
                                <span
                                    v-if="c.sistema"
                                    class="ml-1 inline-flex items-center gap-1 text-xs text-muted-foreground"
                                    title="Lo calcula el motor: sus afectaciones son fijas."
                                >
                                    <Lock class="size-3" />
                                    sistema
                                </span>
                            </td>
                            <template v-if="grupo.tipo === 'ingreso'">
                                <td
                                    v-for="a in AFECTACIONES"
                                    :key="a.campo"
                                    class="px-2 py-2 text-center"
                                >
                                    <Check
                                        v-if="c[a.campo]"
                                        class="mx-auto size-4"
                                        :aria-label="`Afecto a ${a.titulo}`"
                                    />
                                    <span
                                        v-else
                                        class="text-muted-foreground/50"
                                        >—</span
                                    >
                                </td>
                            </template>
                            <td class="px-4 py-2">
                                <Badge
                                    :variant="
                                        c.activo ? 'outline' : 'secondary'
                                    "
                                >
                                    {{ c.activo ? 'Activo' : 'Inactivo' }}
                                </Badge>
                            </td>
                            <td class="px-2 py-2">
                                <div class="flex justify-end gap-1">
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        class="size-8"
                                        :title="`Editar ${c.codigo_plame}`"
                                        @click="editar(c)"
                                    >
                                        <Pencil />
                                    </Button>
                                    <template v-if="!c.sistema">
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            class="size-8"
                                            :title="
                                                c.activo
                                                    ? 'Desactivar'
                                                    : 'Activar'
                                            "
                                            @click="alternarEstado(c)"
                                        >
                                            <CircleOff v-if="c.activo" />
                                            <CircleCheck v-else />
                                        </Button>
                                        <Button
                                            v-if="!c.usado"
                                            variant="ghost"
                                            size="icon"
                                            class="size-8 text-destructive hover:text-destructive"
                                            :title="`Eliminar ${c.codigo_plame}`"
                                            @click="porEliminar = c"
                                        >
                                            <Trash2 />
                                        </Button>
                                    </template>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="grupo.conceptos.length === 0">
                            <td
                                colspan="8"
                                class="px-4 py-6 text-center text-muted-foreground"
                            >
                                Sin conceptos.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <!-- Crear / editar -->
    <Dialog v-model:open="abierto">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>
                    {{
                        editando
                            ? `Concepto ${editando.codigo_plame}`
                            : 'Nuevo concepto'
                    }}
                </DialogTitle>
                <DialogDescription v-if="!editando">
                    Usa el código y el nombre de la tabla 22 de SUNAT. El tipo
                    se deduce del código.
                </DialogDescription>
            </DialogHeader>

            <form
                id="form-concepto"
                class="grid gap-4"
                @submit.prevent="guardar"
            >
                <div class="grid grid-cols-[7rem_1fr] gap-3">
                    <div class="grid gap-2">
                        <Label for="codigo_plame">Código</Label>
                        <Input
                            id="codigo_plame"
                            v-model="form.codigo_plame"
                            maxlength="4"
                            inputmode="numeric"
                            class="font-mono"
                            :disabled="editando !== null"
                            required
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label for="nombre">Nombre</Label>
                        <Input id="nombre" v-model="form.nombre" required />
                    </div>
                </div>
                <p
                    v-if="!editando && tipoDelCodigo"
                    class="-mt-2 text-xs text-muted-foreground"
                >
                    Tipo: {{ tipoDelCodigo }}
                </p>
                <InputError
                    :message="form.errors.codigo_plame || form.errors.nombre"
                />

                <fieldset v-if="muestraAfectaciones" class="grid gap-3">
                    <legend class="mb-2 text-sm font-medium">
                        Afecto a
                        <span
                            v-if="editando?.sistema"
                            class="ml-1 inline-flex items-center gap-1 text-xs font-normal text-muted-foreground"
                        >
                            <Lock class="size-3" /> fijo en el motor de cálculo
                        </span>
                    </legend>
                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                        <label
                            v-for="a in AFECTACIONES"
                            :key="a.campo"
                            class="flex items-center gap-2 text-sm"
                        >
                            <Checkbox
                                v-model="form[a.campo]"
                                :disabled="editando?.sistema"
                            />
                            {{ a.titulo }}
                        </label>
                    </div>
                    <InputError
                        :message="
                            (form.errors as Record<string, string>).afectaciones
                        "
                    />
                    <p
                        v-if="cambiaAfectaciones"
                        class="flex gap-2 rounded-md bg-amber-50 p-3 text-xs text-amber-900 dark:bg-amber-950/40 dark:text-amber-300"
                    >
                        <Info class="mt-0.5 size-3.5 shrink-0" />
                        Aplica a los cálculos nuevos y a los periodos abiertos
                        que se recalculen; los periodos cerrados no cambian.
                    </p>
                </fieldset>
                <fieldset class="grid gap-3">
                    <legend class="mb-2 text-sm font-medium">
                        Cuentas contables
                        <span
                            class="ml-1 text-xs font-normal text-muted-foreground"
                            >(PCGE, para la centralización)</span
                        >
                    </legend>
                    <div class="grid grid-cols-2 gap-3">
                        <div class="grid gap-1.5">
                            <Label for="cuenta_cargo" class="text-xs"
                                >Cargo (gasto)</Label
                            >
                            <Input
                                id="cuenta_cargo"
                                v-model="form.cuenta_cargo"
                                inputmode="numeric"
                                maxlength="8"
                                class="font-mono"
                                placeholder="6211"
                            />
                        </div>
                        <div class="grid gap-1.5">
                            <Label for="cuenta_abono" class="text-xs"
                                >Abono (por pagar)</Label
                            >
                            <Input
                                id="cuenta_abono"
                                v-model="form.cuenta_abono"
                                inputmode="numeric"
                                maxlength="8"
                                class="font-mono"
                                placeholder="4032"
                            />
                        </div>
                    </div>
                    <InputError
                        :message="
                            form.errors.cuenta_cargo || form.errors.cuenta_abono
                        "
                    />
                </fieldset>
            </form>

            <DialogFooter>
                <DialogClose as-child>
                    <Button variant="outline">Cancelar</Button>
                </DialogClose>
                <Button
                    type="submit"
                    form="form-concepto"
                    :disabled="form.processing"
                >
                    <Spinner v-if="form.processing" />
                    {{ editando ? 'Guardar' : 'Registrar concepto' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <!-- Eliminar -->
    <Dialog
        :open="porEliminar !== null"
        @update:open="(v) => !v && (porEliminar = null)"
    >
        <DialogContent>
            <DialogHeader>
                <DialogTitle>
                    ¿Eliminar {{ porEliminar?.codigo_plame }} ·
                    {{ porEliminar?.nombre }}?
                </DialogTitle>
                <DialogDescription>
                    Nunca se usó en una planilla, así que se puede eliminar sin
                    afectar cálculos.
                </DialogDescription>
            </DialogHeader>
            <DialogFooter>
                <DialogClose as-child>
                    <Button variant="outline">Cancelar</Button>
                </DialogClose>
                <Button variant="destructive" @click="confirmarEliminacion">
                    Eliminar
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
