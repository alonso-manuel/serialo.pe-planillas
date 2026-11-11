<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { Plus, Trash2 } from '@lucide/vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { destroy, store, update } from '@/routes/empresas/centros-costo';
import type { CentroCostoFila } from '@/types';

/** Centros de costo de la empresa: agrupan el costo de la planilla y el asiento contable. */
const props = defineProps<{
    empresaId: number;
    centros: CentroCostoFila[];
}>();

const form = useForm({ codigo: '', nombre: '' });

function agregar(): void {
    form.post(store.url(props.empresaId), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
}

function alternar(c: CentroCostoFila): void {
    router.put(
        update.url({ empresa: props.empresaId, centro: c.id }),
        { codigo: c.codigo, nombre: c.nombre, activo: !c.activo },
        { preserveScroll: true },
    );
}

function quitar(c: CentroCostoFila): void {
    router.delete(destroy.url({ empresa: props.empresaId, centro: c.id }), {
        preserveScroll: true,
    });
}
</script>

<template>
    <section class="grid max-w-3xl gap-4 rounded-lg border p-4">
        <div>
            <h2 class="font-medium">Centros de costo</h2>
            <p class="text-sm text-muted-foreground">
                Áreas, obras o sedes. Se asignan en la ficha del trabajador (con
                vigencia en su historial) y agrupan el costo de la planilla y el
                asiento contable.
            </p>
        </div>

        <ul v-if="centros.length" class="divide-y rounded-md border text-sm">
            <li
                v-for="c in centros"
                :key="c.id"
                class="flex flex-wrap items-center justify-between gap-2 px-3 py-2"
                :class="c.activo ? '' : 'text-muted-foreground'"
            >
                <span>
                    <span class="font-medium tabular-nums">{{ c.codigo }}</span>
                    · {{ c.nombre }}
                    <span class="text-xs text-muted-foreground"
                        >· {{ c.trabajadores }}
                        {{
                            c.trabajadores === 1 ? 'trabajador' : 'trabajadores'
                        }}</span
                    >
                    <Badge v-if="!c.activo" variant="outline" class="ml-2"
                        >Inactivo</Badge
                    >
                </span>
                <span class="flex gap-1">
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        @click="alternar(c)"
                    >
                        {{ c.activo ? 'Desactivar' : 'Activar' }}
                    </Button>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        :aria-label="`Eliminar ${c.codigo}`"
                        @click="quitar(c)"
                    >
                        <Trash2 />
                    </Button>
                </span>
            </li>
        </ul>

        <form
            class="grid gap-3 sm:grid-cols-[8rem_1fr_auto] sm:items-end"
            @submit.prevent="agregar"
        >
            <div class="grid gap-2">
                <Label for="cc-codigo">Código</Label>
                <Input id="cc-codigo" v-model="form.codigo" maxlength="10" />
            </div>
            <div class="grid gap-2">
                <Label for="cc-nombre">Nombre</Label>
                <Input id="cc-nombre" v-model="form.nombre" maxlength="100" />
            </div>
            <Button type="submit" variant="outline" :disabled="form.processing">
                <Spinner v-if="form.processing" />
                <Plus v-else />
                Agregar
            </Button>
            <InputError
                class="sm:col-span-3"
                :message="form.errors.codigo || form.errors.nombre"
            />
        </form>
    </section>
</template>
