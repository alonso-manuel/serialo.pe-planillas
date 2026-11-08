<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { LogOut, Undo2 } from '@lucide/vue';
import { ref } from 'vue';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { destroy, store } from '@/routes/trabajadores/cese';
import type { Opcion, TrabajadorEditable } from '@/types';

const props = defineProps<{
    trabajador: TrabajadorEditable;
    motivos: Opcion[];
}>();

const abierto = ref(false);
const confirmarRevertir = ref(false);
const form = useForm({ fecha_cese: '', motivo_cese: '01' });

function abrir(): void {
    form.reset();
    form.clearErrors();
    abierto.value = true;
}

function registrar(): void {
    form.post(store.url(props.trabajador.id), {
        preserveScroll: true,
        onSuccess: () => (abierto.value = false),
    });
}

function revertir(): void {
    router.delete(destroy.url(props.trabajador.id), { preserveScroll: true });
    confirmarRevertir.value = false;
}
</script>

<template>
    <div class="flex flex-wrap items-center gap-2">
        <template v-if="trabajador.cese">
            <Badge :variant="trabajador.cesado ? 'secondary' : 'outline'">
                {{ trabajador.cesado ? 'Cesado el' : 'Cesa el' }}
                {{ trabajador.cese.fecha }} ·
                {{ trabajador.cese.motivo_etiqueta }}
            </Badge>
            <Button
                type="button"
                variant="ghost"
                size="sm"
                @click="confirmarRevertir = true"
            >
                <Undo2 />
                Revertir cese
            </Button>
        </template>
        <Button v-else type="button" variant="outline" size="sm" @click="abrir">
            <LogOut />
            Registrar cese
        </Button>
    </div>

    <Dialog v-model:open="abierto">
        <DialogContent>
            <DialogHeader>
                <DialogTitle
                    >Registrar cese de
                    {{ trabajador.nombre_completo }}</DialogTitle
                >
                <DialogDescription>
                    Puede ser una fecha futura (por ejemplo, fin de contrato):
                    seguirá activo hasta ese día. La liquidación de beneficios
                    sociales llega en una próxima versión.
                </DialogDescription>
            </DialogHeader>

            <form id="form-cese" class="grid gap-4" @submit.prevent="registrar">
                <div class="grid gap-2 sm:max-w-48">
                    <Label for="fecha_cese">Fecha de cese</Label>
                    <Input
                        id="fecha_cese"
                        v-model="form.fecha_cese"
                        type="date"
                        required
                    />
                    <InputError :message="form.errors.fecha_cese" />
                </div>
                <div class="grid gap-2">
                    <Label for="motivo_cese"
                        >Motivo (tabla 17 de T-Registro)</Label
                    >
                    <Select v-model="form.motivo_cese">
                        <SelectTrigger id="motivo_cese" class="w-full"
                            ><SelectValue
                        /></SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="m in motivos"
                                :key="m.value"
                                :value="m.value"
                            >
                                {{ m.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="form.errors.motivo_cese" />
                </div>
            </form>

            <DialogFooter>
                <DialogClose as-child>
                    <Button variant="outline">Cancelar</Button>
                </DialogClose>
                <Button
                    type="submit"
                    form="form-cese"
                    :disabled="form.processing"
                >
                    <Spinner v-if="form.processing" />
                    Registrar cese
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <Dialog v-model:open="confirmarRevertir">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>¿Revertir el cese?</DialogTitle>
                <DialogDescription>
                    Úsalo solo si el cese se registró por error: el trabajador
                    vuelve a quedar activo. El cambio queda en la auditoría.
                </DialogDescription>
            </DialogHeader>
            <DialogFooter>
                <DialogClose as-child>
                    <Button variant="outline">Cancelar</Button>
                </DialogClose>
                <Button @click="revertir">Revertir cese</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
