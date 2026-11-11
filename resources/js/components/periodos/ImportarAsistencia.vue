<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Download, FileUp, Upload } from '@lucide/vue';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { importar, plantilla } from '@/routes/periodos/asistencia';

/** Asistencia del mes desde Excel: plantilla con los trabajadores del periodo e importación todo o nada. */
const props = defineProps<{ periodoId: number; etiqueta: string }>();

const abierto = ref(false);
const form = useForm<{ archivo: File | null }>({ archivo: null });

function elegir(e: Event): void {
    form.archivo = (e.target as HTMLInputElement).files?.[0] ?? null;
}

function subir(): void {
    form.post(importar.url(props.periodoId), {
        forceFormData: true,
        preserveScroll: true,
        // Si se importó, la grilla y el estado del periodo se recargan del servidor; con errores, el diálogo sigue abierto.
        preserveState: 'errors',
        onSuccess: () => {
            abierto.value = false;
            form.reset();
        },
    });
}
</script>

<template>
    <Button variant="outline" @click="abierto = true">
        <FileUp />
        Importar asistencia
    </Button>

    <Dialog v-model:open="abierto">
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>Importar asistencia de {{ etiqueta }}</DialogTitle>
                <DialogDescription>
                    Faltas, licencias sin goce, días de subsidio y horas extra
                    de cada trabajador. Si una fila tiene un error no se guarda
                    nada y se listan todos los errores.
                </DialogDescription>
            </DialogHeader>

            <ol class="grid gap-3 text-sm">
                <li>
                    1. Descarga la plantilla: ya trae a los trabajadores del
                    periodo con sus valores actuales.
                    <Button variant="link" as-child class="h-auto px-1">
                        <a :href="plantilla.url(periodoId)"
                            ><Download class="size-4" /> Plantilla Excel</a
                        >
                    </Button>
                </li>
                <li class="grid gap-2">
                    <span>2. Complétala y súbela (.xlsx o .csv).</span>
                    <Label for="archivo-asistencia" class="sr-only"
                        >Archivo</Label
                    >
                    <Input
                        id="archivo-asistencia"
                        type="file"
                        accept=".xlsx,.csv"
                        @change="elegir"
                    />
                </li>
            </ol>

            <p
                v-if="form.errors.archivo"
                class="max-h-60 overflow-y-auto rounded-md border border-red-200 bg-red-50 p-3 text-xs whitespace-pre-line text-red-800 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200"
            >
                {{ form.errors.archivo }}
            </p>

            <DialogFooter>
                <Button
                    :disabled="form.processing || !form.archivo"
                    @click="subir"
                >
                    <Spinner v-if="form.processing" />
                    <Upload v-else />
                    Importar
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
