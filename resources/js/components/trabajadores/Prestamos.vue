<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { Plus, Trash2 } from '@lucide/vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { formatoMonto } from '@/lib/montos';
import { destroy, store } from '@/routes/trabajadores/prestamos';
import type { PrestamoEstado, TrabajadorEditable } from '@/types';

/** Préstamos en cuotas: cronograma y saldo. Sin <form> propio: vive dentro de la ficha. */
const props = defineProps<{
    trabajador: TrabajadorEditable;
    prestamos: PrestamoEstado[];
}>();

const hoy = new Date().toISOString().slice(0, 10);
const proximoMes = (() => {
    const d = new Date();
    d.setMonth(d.getMonth() + 1);

    return d.toISOString().slice(0, 7);
})();

const form = useForm({
    fecha: hoy,
    monto: '',
    numero_cuotas: '3',
    mes_inicio: proximoMes,
    glosa: '',
});

function registrar(): void {
    form.post(store.url(props.trabajador.id), {
        preserveScroll: true,
        onSuccess: () => form.reset('monto', 'glosa'),
    });
}

function eliminar(id: number): void {
    router.delete(
        destroy.url({ trabajador: props.trabajador.id, prestamo: id }),
        {
            preserveScroll: true,
        },
    );
}
</script>

<template>
    <div class="grid gap-6">
        <p class="text-sm text-muted-foreground">
            Cada cuota se descuenta sola en la planilla de su mes (concepto
            0706). Una cuota cuenta como pagada cuando esa planilla se cierra.
        </p>

        <section
            v-for="p in prestamos"
            :key="p.id"
            class="grid gap-3 rounded-lg border p-4 text-sm"
        >
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p class="font-medium">
                        S/ {{ formatoMonto(p.monto) }} · {{ p.cuotas.length }}
                        {{ p.cuotas.length === 1 ? 'cuota' : 'cuotas' }}
                        <span class="font-normal text-muted-foreground"
                            >· {{ p.fecha }}</span
                        >
                    </p>
                    <p v-if="p.glosa" class="text-muted-foreground">
                        {{ p.glosa }}
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <Badge
                        :variant="p.saldo === '0.00' ? 'outline' : 'secondary'"
                        >Saldo S/ {{ formatoMonto(p.saldo) }}</Badge
                    >
                    <Button
                        v-if="p.eliminable"
                        type="button"
                        variant="ghost"
                        size="icon"
                        aria-label="Eliminar el préstamo"
                        @click="eliminar(p.id)"
                    >
                        <Trash2 />
                    </Button>
                </div>
            </div>
            <ul class="flex flex-wrap gap-2">
                <li
                    v-for="c in p.cuotas"
                    :key="c.numero"
                    class="rounded-md border px-2 py-1 text-xs tabular-nums"
                    :class="
                        c.descontada
                            ? 'border-green-200 bg-green-50 text-green-800 dark:border-green-900 dark:bg-green-950/40 dark:text-green-300'
                            : ''
                    "
                >
                    {{ c.mes }} · {{ formatoMonto(c.monto) }}
                </li>
            </ul>
        </section>

        <div class="grid gap-4 rounded-lg border p-4">
            <h3 class="font-medium">Nuevo préstamo</h3>
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="pr-monto">Monto (S/)</Label>
                    <Input
                        id="pr-monto"
                        v-model="form.monto"
                        inputmode="decimal"
                        class="tabular-nums"
                        placeholder="0.00"
                    />
                    <InputError :message="form.errors.monto" />
                </div>
                <div class="grid gap-2">
                    <Label for="pr-cuotas">Número de cuotas</Label>
                    <Input
                        id="pr-cuotas"
                        v-model="form.numero_cuotas"
                        inputmode="numeric"
                    />
                    <InputError :message="form.errors.numero_cuotas" />
                </div>
                <div class="grid gap-2">
                    <Label for="pr-fecha">Fecha del préstamo</Label>
                    <Input id="pr-fecha" v-model="form.fecha" type="date" />
                    <InputError :message="form.errors.fecha" />
                </div>
                <div class="grid gap-2">
                    <Label for="pr-inicio">Mes de la primera cuota</Label>
                    <Input
                        id="pr-inicio"
                        v-model="form.mes_inicio"
                        type="month"
                    />
                    <InputError :message="form.errors.mes_inicio" />
                </div>
                <div class="grid gap-2 sm:col-span-2">
                    <Label for="pr-glosa">Detalle (opcional)</Label>
                    <Input id="pr-glosa" v-model="form.glosa" maxlength="150" />
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
                    Registrar préstamo
                </Button>
            </div>
        </div>
    </div>
</template>
