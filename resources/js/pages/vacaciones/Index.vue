<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    CalendarPlus,
    ChevronRight,
    Info,
    Trash2,
    TriangleAlert,
} from '@lucide/vue';
import { computed, ref } from 'vue';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { destroy, index, store } from '@/routes/vacaciones';

type Periodo = {
    numero: number;
    desde: string;
    hasta: string;
    cumplido: boolean;
    derecho: number;
    gozados: number;
    saldo: number;
    vence: string;
    vencido: boolean;
    dias_no_efectivos: number;
    aviso_record: boolean;
};

type Fila = {
    id: number;
    nombre: string;
    documento: string;
    ingreso: string;
    cesado: boolean;
    saldo: number;
    vencidos: number;
    proximo_vencimiento: string | null;
    cumple_anio: string | null;
    aviso_record: boolean;
    periodos: Periodo[];
    goces: {
        id: number;
        inicio: string;
        fin: string;
        dias: number;
        observacion: string | null;
        puede_eliminar: boolean;
    }[];
};

const props = defineProps<{ empresa: string; trabajadores: Fila[] }>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Vacaciones', href: index() }],
    },
});

const busqueda = ref('');
const filtradas = computed(() => {
    const q = busqueda.value.trim().toLowerCase();

    return q
        ? props.trabajadores.filter(
              (t) =>
                  t.nombre.toLowerCase().includes(q) ||
                  t.documento.toLowerCase().includes(q),
          )
        : props.trabajadores;
});

const abiertos = ref(new Set<number>());

function alternar(id: number): void {
    if (abiertos.value.has(id)) {
        abiertos.value.delete(id);
    } else {
        abiertos.value.add(id);
    }
}

/* ---------- Registrar vacaciones ---------- */
const seleccionado = ref<Fila | null>(null);
const form = useForm({ fecha_inicio: '', dias: '', observacion: '' });

function abrirRegistro(t: Fila): void {
    seleccionado.value = t;
    form.reset();
    form.clearErrors();
    form.dias = String(Math.min(t.saldo, 30) || '');
}

function registrar(): void {
    if (!seleccionado.value) {
        return;
    }

    form.post(store.url(seleccionado.value.id), {
        preserveScroll: true,
        onSuccess: () => (seleccionado.value = null),
    });
}

function eliminar(goceId: number): void {
    router.delete(destroy.url(goceId), { preserveScroll: true });
}
</script>

<template>
    <Head title="Vacaciones" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading
                title="Vacaciones"
                :description="`Récord, saldos y goce del personal de ${empresa}.`"
            />
            <Input
                v-model="busqueda"
                placeholder="Buscar trabajador"
                class="w-64"
                aria-label="Buscar trabajador"
            />
        </div>

        <p
            class="flex items-start gap-2 rounded-md bg-muted/50 p-3 text-sm text-muted-foreground"
        >
            <Info class="mt-0.5 size-4 shrink-0" />
            Cada año de servicios da derecho a descanso (30 días en régimen
            general, 15 en REMYPE), que debe gozarse dentro del año siguiente.
            Las vacaciones registradas pasan solas a la planilla del mes como
            remuneración vacacional.
        </p>

        <div class="overflow-x-auto rounded-lg border">
            <table class="w-full min-w-[760px] text-sm">
                <thead class="bg-muted/40 text-xs text-muted-foreground">
                    <tr>
                        <th class="w-8 px-2 py-2" />
                        <th class="px-3 py-2 text-left font-medium">
                            Trabajador
                        </th>
                        <th class="px-3 py-2 text-left font-medium">Ingreso</th>
                        <th class="px-3 py-2 text-right font-medium">Saldo</th>
                        <th class="px-3 py-2 text-left font-medium">
                            Gozar hasta
                        </th>
                        <th class="px-3 py-2 text-left font-medium">
                            Próximo derecho
                        </th>
                        <th class="w-40 px-3 py-2" />
                    </tr>
                </thead>
                <tbody>
                    <template v-for="t in filtradas" :key="t.id">
                        <tr class="border-t">
                            <td class="px-2 py-2">
                                <button
                                    type="button"
                                    class="text-muted-foreground"
                                    :aria-expanded="abiertos.has(t.id)"
                                    :aria-label="`Detalle de ${t.nombre}`"
                                    @click="alternar(t.id)"
                                >
                                    <ChevronRight
                                        class="size-4 transition-transform"
                                        :class="{
                                            'rotate-90': abiertos.has(t.id),
                                        }"
                                    />
                                </button>
                            </td>
                            <td class="px-3 py-2">
                                <p class="font-medium">
                                    {{ t.nombre }}
                                    <Badge
                                        v-if="t.cesado"
                                        variant="outline"
                                        class="ml-1"
                                        >Cesado</Badge
                                    >
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    {{ t.documento }}
                                </p>
                            </td>
                            <td class="px-3 py-2 tabular-nums">
                                {{ t.ingreso }}
                            </td>
                            <td
                                class="px-3 py-2 text-right font-medium tabular-nums"
                            >
                                {{ t.saldo }} días
                            </td>
                            <td class="px-3 py-2 tabular-nums">
                                <span
                                    v-if="t.vencidos"
                                    class="flex items-center gap-1 text-red-700 dark:text-red-400"
                                >
                                    <TriangleAlert class="size-3.5" />
                                    {{ t.vencidos }}
                                    {{
                                        t.vencidos === 1
                                            ? 'periodo vencido'
                                            : 'periodos vencidos'
                                    }}
                                </span>
                                <span v-else>{{
                                    t.proximo_vencimiento ?? '—'
                                }}</span>
                            </td>
                            <td
                                class="px-3 py-2 text-muted-foreground tabular-nums"
                            >
                                {{ t.cumple_anio ?? '—' }}
                            </td>
                            <td class="px-3 py-2 text-right">
                                <Button
                                    v-if="!t.cesado"
                                    variant="outline"
                                    size="sm"
                                    :disabled="t.saldo <= 0"
                                    :title="
                                        t.saldo <= 0
                                            ? 'No tiene días pendientes'
                                            : undefined
                                    "
                                    @click="abrirRegistro(t)"
                                >
                                    <CalendarPlus />
                                    Registrar
                                </Button>
                            </td>
                        </tr>
                        <tr v-if="abiertos.has(t.id)" class="bg-muted/20">
                            <td />
                            <td colspan="6" class="px-3 pt-1 pb-4">
                                <p
                                    v-if="t.aviso_record"
                                    class="mb-2 flex items-start gap-2 rounded-md bg-amber-50 p-2 text-xs text-amber-900 dark:bg-amber-950/40 dark:text-amber-300"
                                >
                                    <TriangleAlert
                                        class="mt-0.5 size-3.5 shrink-0"
                                    />
                                    Más de 40 días no efectivos (faltas,
                                    licencias sin goce o subsidio por enfermedad
                                    sobre 60 días) en un año: revisa si cumple
                                    el récord de 260 días (jornada de 6 días) o
                                    210 (5 días), art. 10 del D.Leg. 713.
                                </p>
                                <div class="grid gap-4 lg:grid-cols-2">
                                    <table class="w-full text-xs">
                                        <thead class="text-muted-foreground">
                                            <tr>
                                                <th class="py-1 text-left">
                                                    Año de servicios
                                                </th>
                                                <th
                                                    class="py-1 pl-4 text-right"
                                                >
                                                    Derecho
                                                </th>
                                                <th
                                                    class="py-1 pl-4 text-right"
                                                >
                                                    Gozados
                                                </th>
                                                <th
                                                    class="py-1 pl-4 text-right"
                                                >
                                                    Saldo
                                                </th>
                                                <th class="py-1 pl-3 text-left">
                                                    Gozar hasta
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr
                                                v-for="p in t.periodos"
                                                :key="p.numero"
                                                class="border-t"
                                            >
                                                <td class="py-1 tabular-nums">
                                                    {{ p.desde }} –
                                                    {{ p.hasta }}
                                                    <span
                                                        v-if="!p.cumplido"
                                                        class="text-muted-foreground"
                                                        >(en curso)</span
                                                    >
                                                </td>
                                                <td
                                                    class="py-1 pl-4 text-right tabular-nums"
                                                >
                                                    {{ p.derecho }}
                                                </td>
                                                <td
                                                    class="py-1 pl-4 text-right tabular-nums"
                                                >
                                                    {{ p.gozados }}
                                                </td>
                                                <td
                                                    class="py-1 pl-4 text-right font-medium tabular-nums"
                                                >
                                                    {{ p.saldo }}
                                                </td>
                                                <td
                                                    class="py-1 pl-3 tabular-nums"
                                                    :class="{
                                                        'text-red-700 dark:text-red-400':
                                                            p.vencido,
                                                    }"
                                                >
                                                    {{
                                                        p.cumplido
                                                            ? p.vence
                                                            : '—'
                                                    }}
                                                    <template v-if="p.vencido">
                                                        (vencido)</template
                                                    >
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>

                                    <div>
                                        <p
                                            class="mb-1 text-xs font-medium text-muted-foreground"
                                        >
                                            Vacaciones registradas
                                        </p>
                                        <ul
                                            v-if="t.goces.length"
                                            class="divide-y rounded-md border bg-background text-xs"
                                        >
                                            <li
                                                v-for="g in t.goces"
                                                :key="g.id"
                                                class="flex items-center gap-2 px-3 py-1.5"
                                            >
                                                <span class="tabular-nums"
                                                    >{{ g.inicio }} al
                                                    {{ g.fin }}</span
                                                >
                                                <span class="font-medium"
                                                    >· {{ g.dias }} días</span
                                                >
                                                <span
                                                    v-if="g.observacion"
                                                    class="truncate text-muted-foreground"
                                                    >· {{ g.observacion }}</span
                                                >
                                                <button
                                                    v-if="g.puede_eliminar"
                                                    type="button"
                                                    class="ml-auto text-muted-foreground hover:text-destructive"
                                                    :aria-label="`Eliminar vacaciones del ${g.inicio}`"
                                                    @click="eliminar(g.id)"
                                                >
                                                    <Trash2 class="size-3.5" />
                                                </button>
                                            </li>
                                        </ul>
                                        <p
                                            v-else
                                            class="text-xs text-muted-foreground"
                                        >
                                            Ninguna todavía.
                                        </p>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    </template>
                    <tr v-if="filtradas.length === 0">
                        <td
                            colspan="7"
                            class="px-4 py-10 text-center text-muted-foreground"
                        >
                            No hay trabajadores.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <Dialog
        :open="seleccionado !== null"
        @update:open="(v) => !v && (seleccionado = null)"
    >
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Registrar vacaciones</DialogTitle>
                <DialogDescription>
                    {{ seleccionado?.nombre }} · saldo
                    {{ seleccionado?.saldo }} días. Se descuentan primero del
                    periodo más antiguo y pasan solas a la planilla de cada mes.
                </DialogDescription>
            </DialogHeader>
            <form
                id="form-vacaciones"
                class="grid gap-4 sm:grid-cols-2"
                @submit.prevent="registrar"
            >
                <div class="grid gap-2">
                    <Label for="fecha_inicio">Desde</Label>
                    <Input
                        id="fecha_inicio"
                        v-model="form.fecha_inicio"
                        type="date"
                        required
                    />
                    <InputError :message="form.errors.fecha_inicio" />
                </div>
                <div class="grid gap-2">
                    <Label for="dias">Días calendario</Label>
                    <Input
                        id="dias"
                        v-model="form.dias"
                        type="number"
                        min="1"
                        :max="seleccionado?.saldo"
                        required
                    />
                    <InputError :message="form.errors.dias" />
                </div>
                <div class="grid gap-2 sm:col-span-2">
                    <Label for="observacion"
                        >Observación
                        <span class="text-muted-foreground"
                            >(opcional)</span
                        ></Label
                    >
                    <Input
                        id="observacion"
                        v-model="form.observacion"
                        maxlength="255"
                    />
                </div>
            </form>
            <DialogFooter>
                <DialogClose as-child>
                    <Button variant="outline">Cancelar</Button>
                </DialogClose>
                <Button
                    type="submit"
                    form="form-vacaciones"
                    :disabled="form.processing"
                >
                    <Spinner v-if="form.processing" />
                    Registrar
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
