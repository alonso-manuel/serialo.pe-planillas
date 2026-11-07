<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { Check, Pencil, Plus, Trash2, X } from '@lucide/vue';
import { ref } from 'vue';
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
import { destroy, store, update } from '@/routes/empresas/establecimientos';
import type { EstablecimientoFila } from '@/types';

const props = defineProps<{
    empresaId: number;
    establecimientos: EstablecimientoFila[];
}>();

/* ---------- Agregar ---------- */
const agregando = ref(false);
const nuevo = useForm({ codigo: '', nombre: '', direccion: '' });

function siguienteCodigo(): string {
    const max = Math.max(
        0,
        ...props.establecimientos.map((e) => Number(e.codigo)),
    );

    return String(max + 1).padStart(4, '0');
}

function abrirNuevo(): void {
    nuevo.reset();
    nuevo.clearErrors();
    nuevo.codigo = siguienteCodigo();
    agregando.value = true;
}

function guardarNuevo(): void {
    nuevo.post(store.url(props.empresaId), {
        preserveScroll: true,
        onSuccess: () => {
            agregando.value = false;
            nuevo.reset();
        },
    });
}

/* ---------- Editar en línea ---------- */
const editandoId = ref<number | null>(null);
const edicion = useForm({ codigo: '', nombre: '', direccion: '' });

function editar(e: EstablecimientoFila): void {
    edicion.clearErrors();
    edicion.codigo = e.codigo;
    edicion.nombre = e.nombre;
    edicion.direccion = e.direccion ?? '';
    editandoId.value = e.id;
}

function guardarEdicion(id: number): void {
    edicion.put(update.url({ empresa: props.empresaId, establecimiento: id }), {
        preserveScroll: true,
        onSuccess: () => (editandoId.value = null),
    });
}

/* ---------- Eliminar ---------- */
const porEliminar = ref<EstablecimientoFila | null>(null);

function confirmarEliminacion(): void {
    if (!porEliminar.value) {
        return;
    }

    router.delete(
        destroy.url({
            empresa: props.empresaId,
            establecimiento: porEliminar.value.id,
        }),
        { preserveScroll: true },
    );
    porEliminar.value = null;
}
</script>

<template>
    <section class="grid max-w-3xl gap-4">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <Heading
                variant="small"
                title="Establecimientos"
                description="Locales registrados en SUNAT. Se usan en T-Registro y en la ficha de cada trabajador."
            />
            <Button
                v-if="!agregando"
                variant="outline"
                size="sm"
                @click="abrirNuevo"
            >
                <Plus />
                Agregar establecimiento
            </Button>
        </div>

        <div class="overflow-x-auto rounded-lg border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left text-muted-foreground">
                    <tr>
                        <th class="w-24 px-4 py-2 font-medium">Código</th>
                        <th class="px-4 py-2 font-medium">Nombre</th>
                        <th class="px-4 py-2 font-medium">Dirección</th>
                        <th class="w-24 px-4 py-2">
                            <span class="sr-only">Acciones</span>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <template v-for="e in establecimientos" :key="e.id">
                        <tr
                            v-if="editandoId === e.id"
                            class="border-t align-top"
                        >
                            <td class="px-2 py-2">
                                <Input
                                    v-model="edicion.codigo"
                                    maxlength="4"
                                    class="h-8 font-mono"
                                    aria-label="Código"
                                    :disabled="e.es_principal"
                                />
                                <InputError :message="edicion.errors.codigo" />
                            </td>
                            <td class="px-2 py-2">
                                <Input
                                    v-model="edicion.nombre"
                                    class="h-8"
                                    aria-label="Nombre"
                                    @keydown.enter.prevent="
                                        guardarEdicion(e.id)
                                    "
                                />
                                <InputError :message="edicion.errors.nombre" />
                            </td>
                            <td class="px-2 py-2">
                                <Input
                                    v-model="edicion.direccion"
                                    class="h-8"
                                    aria-label="Dirección"
                                    @keydown.enter.prevent="
                                        guardarEdicion(e.id)
                                    "
                                />
                                <InputError
                                    :message="edicion.errors.direccion"
                                />
                            </td>
                            <td class="px-2 py-2">
                                <div class="flex justify-end gap-1">
                                    <Button
                                        size="icon"
                                        class="size-8"
                                        title="Guardar"
                                        :disabled="edicion.processing"
                                        @click="guardarEdicion(e.id)"
                                    >
                                        <Check />
                                    </Button>
                                    <Button
                                        size="icon"
                                        variant="ghost"
                                        class="size-8"
                                        title="Cancelar"
                                        @click="editandoId = null"
                                    >
                                        <X />
                                    </Button>
                                </div>
                            </td>
                        </tr>
                        <tr v-else class="border-t">
                            <td class="px-4 py-2 font-mono">{{ e.codigo }}</td>
                            <td class="px-4 py-2">
                                {{ e.nombre }}
                                <Badge
                                    v-if="e.es_principal"
                                    variant="secondary"
                                    class="ml-1"
                                >
                                    Principal
                                </Badge>
                            </td>
                            <td class="px-4 py-2 text-muted-foreground">
                                {{ e.direccion ?? '—' }}
                            </td>
                            <td class="px-2 py-2">
                                <div class="flex justify-end gap-1">
                                    <Button
                                        size="icon"
                                        variant="ghost"
                                        class="size-8"
                                        :title="`Editar ${e.nombre}`"
                                        @click="editar(e)"
                                    >
                                        <Pencil />
                                    </Button>
                                    <Button
                                        v-if="!e.es_principal"
                                        size="icon"
                                        variant="ghost"
                                        class="size-8 text-destructive hover:text-destructive"
                                        :title="`Eliminar ${e.nombre}`"
                                        @click="porEliminar = e"
                                    >
                                        <Trash2 />
                                    </Button>
                                </div>
                            </td>
                        </tr>
                    </template>

                    <tr v-if="agregando" class="border-t bg-muted/30 align-top">
                        <td class="px-2 py-2">
                            <Input
                                v-model="nuevo.codigo"
                                maxlength="4"
                                class="h-8 font-mono"
                                aria-label="Código del nuevo establecimiento"
                            />
                            <InputError :message="nuevo.errors.codigo" />
                        </td>
                        <td class="px-2 py-2">
                            <Input
                                v-model="nuevo.nombre"
                                class="h-8"
                                placeholder="Ej.: Local Arequipa"
                                aria-label="Nombre del nuevo establecimiento"
                                v-focus
                                @keydown.enter.prevent="guardarNuevo"
                            />
                            <InputError :message="nuevo.errors.nombre" />
                        </td>
                        <td class="px-2 py-2">
                            <Input
                                v-model="nuevo.direccion"
                                class="h-8"
                                aria-label="Dirección del nuevo establecimiento"
                                @keydown.enter.prevent="guardarNuevo"
                            />
                            <InputError :message="nuevo.errors.direccion" />
                        </td>
                        <td class="px-2 py-2">
                            <div class="flex justify-end gap-1">
                                <Button
                                    size="icon"
                                    class="size-8"
                                    title="Agregar"
                                    :disabled="nuevo.processing"
                                    @click="guardarNuevo"
                                >
                                    <Check />
                                </Button>
                                <Button
                                    size="icon"
                                    variant="ghost"
                                    class="size-8"
                                    title="Cancelar"
                                    @click="agregando = false"
                                >
                                    <X />
                                </Button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>

    <Dialog
        :open="porEliminar !== null"
        @update:open="(abierto) => !abierto && (porEliminar = null)"
    >
        <DialogContent>
            <DialogHeader>
                <DialogTitle
                    >¿Eliminar «{{ porEliminar?.nombre }}»?</DialogTitle
                >
                <DialogDescription>
                    <template v-if="porEliminar?.trabajadores_count">
                        {{ porEliminar.trabajadores_count }} trabajador(es)
                        quedarán sin establecimiento asignado.
                    </template>
                    <template v-else>
                        Ningún trabajador está asignado a este establecimiento.
                    </template>
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
