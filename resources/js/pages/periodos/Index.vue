<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { CalendarDays, ChevronRight, Plus } from '@lucide/vue';
import { computed, ref } from 'vue';
import EstadoPeriodo from '@/components/periodos/EstadoPeriodo.vue';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { index, show, store } from '@/routes/periodos';
import type { PeriodoFila } from '@/types';

const props = defineProps<{
    periodos: PeriodoFila[];
    siguiente: { anio: number; mes: number } | null;
    hoy: { anio: number; mes: number };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Periodos', href: index() }],
    },
});

const MESES = [
    'Enero',
    'Febrero',
    'Marzo',
    'Abril',
    'Mayo',
    'Junio',
    'Julio',
    'Agosto',
    'Septiembre',
    'Octubre',
    'Noviembre',
    'Diciembre',
];

const page = usePage();
const empresa = computed(() => page.props.empresaActiva?.razon_social);

const propuesto = computed(() => props.siguiente ?? props.hoy);
const etiquetaPropuesta = computed(
    () => `${MESES[propuesto.value.mes - 1]} ${propuesto.value.anio}`,
);

/* ---------- Abrir ---------- */
const abierto = ref(false);
const mesElegido = ref('');
const form = useForm({ anio: 0, mes: 0 });

function abrirDialogo(): void {
    form.clearErrors();
    mesElegido.value = `${propuesto.value.anio}-${String(propuesto.value.mes).padStart(2, '0')}`;
    abierto.value = true;
}

function abrirPeriodo(): void {
    const [anio, mes] = mesElegido.value.split('-').map(Number);
    form.anio = anio;
    form.mes = mes;
    form.post(store.url(), { onSuccess: () => (abierto.value = false) });
}

const soles = new Intl.NumberFormat('es-PE', {
    style: 'currency',
    currency: 'PEN',
});
</script>

<template>
    <Head title="Periodos" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading
                title="Periodos"
                :description="`Planillas mensuales de ${empresa ?? 'la empresa activa'}.`"
            />
            <Button @click="abrirDialogo">
                <Plus />
                Abrir {{ etiquetaPropuesta }}
            </Button>
        </div>

        <div
            v-if="periodos.length === 0"
            class="flex flex-col items-center gap-3 rounded-lg border border-dashed px-6 py-14 text-center"
        >
            <div
                class="flex size-12 items-center justify-center rounded-full bg-muted"
            >
                <CalendarDays class="size-6 text-muted-foreground" />
            </div>
            <p class="font-medium">Aún no hay periodos</p>
            <p class="max-w-sm text-sm text-muted-foreground">
                Abre el primer mes que vas a trabajar en el sistema. Después se
                abren en orden, mes a mes.
            </p>
            <Button class="mt-2" @click="abrirDialogo"
                ><Plus />Abrir primer periodo</Button
            >
        </div>

        <ul v-else class="divide-y rounded-lg border">
            <li v-for="p in periodos" :key="p.id">
                <Link
                    :href="show(p.id)"
                    class="flex items-center gap-4 px-4 py-3 text-sm hover:bg-muted/40"
                >
                    <div class="w-40 font-medium">{{ p.etiqueta }}</div>
                    <EstadoPeriodo
                        :estado="p.estado"
                        :etiqueta="p.estado_etiqueta"
                    />
                    <div class="text-muted-foreground">
                        {{ p.trabajadores }}
                        {{
                            p.trabajadores === 1 ? 'trabajador' : 'trabajadores'
                        }}
                    </div>
                    <div class="ml-auto text-right tabular-nums">
                        <template v-if="p.neto !== null">
                            <span class="text-xs text-muted-foreground"
                                >Neto
                            </span>
                            {{ soles.format(Number(p.neto)) }}
                        </template>
                        <span v-else class="text-xs text-muted-foreground"
                            >Sin calcular</span
                        >
                    </div>
                    <ChevronRight class="size-4 text-muted-foreground" />
                </Link>
            </li>
        </ul>
    </div>

    <Dialog v-model:open="abierto">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Abrir periodo</DialogTitle>
                <DialogDescription>
                    Se crea la planilla del mes con los trabajadores que
                    laboraron en él y sus días propuestos (mes de 30 días).
                    <template v-if="siguiente">
                        Solo puede abrirse el mes siguiente al último periodo.
                    </template>
                </DialogDescription>
            </DialogHeader>

            <form
                id="form-abrir"
                class="grid gap-2 sm:max-w-56"
                @submit.prevent="abrirPeriodo"
            >
                <Label for="mes">Mes</Label>
                <Input
                    id="mes"
                    v-model="mesElegido"
                    type="month"
                    :readonly="siguiente !== null"
                    required
                />
                <InputError :message="form.errors.mes || form.errors.anio" />
            </form>

            <DialogFooter>
                <DialogClose as-child>
                    <Button variant="outline">Cancelar</Button>
                </DialogClose>
                <Button
                    type="submit"
                    form="form-abrir"
                    :disabled="form.processing"
                >
                    <Spinner v-if="form.processing" />
                    Abrir periodo
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
