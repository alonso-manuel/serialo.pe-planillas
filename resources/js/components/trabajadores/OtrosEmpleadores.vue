<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { FileText, FolderArchive, Plus, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { formatoMonto } from '@/lib/montos';
import { lote } from '@/routes/certificados-quinta';
import { certificadoQuinta } from '@/routes/trabajadores';
import { destroy, store } from '@/routes/trabajadores/otros-empleadores';
import type { RentaOtroEmpleador, TrabajadorEditable } from '@/types';

/**
 * Renta de quinta: certificados de otros empleadores del año. Sin <form> propio porque vive
 * dentro del formulario de la ficha.
 */
const props = defineProps<{
    trabajador: TrabajadorEditable;
    rentas: RentaOtroEmpleador[];
}>();

const form = useForm({
    anio: String(new Date().getFullYear()),
    ruc_empleador: '',
    empleador: '',
    renta: '',
    retencion: '0',
});

function registrar(): void {
    form.post(store.url(props.trabajador.id), {
        preserveScroll: true,
        onSuccess: () =>
            form.reset('ruc_empleador', 'empleador', 'renta', 'retencion'),
    });
}

const anioActual = new Date().getFullYear();
const anios = [anioActual, anioActual - 1, anioActual - 2];
/** En enero–febrero se entrega el del año anterior. */
const anioCertificado = ref(
    new Date().getMonth() < 2 ? anioActual - 1 : anioActual,
);

function quitar(r: RentaOtroEmpleador): void {
    router.delete(
        destroy.url({ trabajador: props.trabajador.id, renta: r.id }),
        {
            preserveScroll: true,
        },
    );
}
</script>

<template>
    <div class="grid gap-6">
        <section class="grid gap-3 rounded-lg border p-4">
            <h3 class="font-medium">Certificado de rentas y retenciones</h3>
            <p class="text-sm text-muted-foreground">
                Rentas de quinta pagadas por la empresa y retenciones del
                ejercicio (planillas cerradas). Se entrega cada año y al cese.
            </p>
            <div class="flex flex-wrap items-end gap-3">
                <div class="grid gap-1.5">
                    <Label for="cq-anio">Ejercicio</Label>
                    <select
                        id="cq-anio"
                        v-model="anioCertificado"
                        class="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
                    >
                        <option v-for="a in anios" :key="a" :value="a">
                            {{ a }}
                        </option>
                    </select>
                </div>
                <Button type="button" variant="outline" as-child>
                    <a
                        :href="
                            certificadoQuinta.url({
                                trabajador: trabajador.id,
                                anio: anioCertificado,
                            })
                        "
                        target="_blank"
                        rel="noopener"
                    >
                        <FileText />
                        Certificado de este trabajador
                    </a>
                </Button>
                <Button type="button" variant="ghost" as-child>
                    <a :href="lote.url(anioCertificado)">
                        <FolderArchive />
                        De todos (zip)
                    </a>
                </Button>
            </div>
        </section>

        <p class="text-sm text-muted-foreground">
            Si el trabajador tuvo otro empleador en el año, registra lo que dice
            su <strong>certificado de rentas y retenciones</strong>. Se suma a
            la proyección de la renta de quinta y lo retenido se descuenta, para
            no retener de más ni de menos.
        </p>

        <div v-if="rentas.length" class="overflow-x-auto rounded-lg border">
            <table class="w-full text-sm">
                <thead
                    class="bg-muted/50 text-left text-xs text-muted-foreground"
                >
                    <tr>
                        <th class="px-3 py-2 font-medium">Año</th>
                        <th class="px-3 py-2 font-medium">Empleador</th>
                        <th class="px-3 py-2 text-right font-medium">Renta</th>
                        <th class="px-3 py-2 text-right font-medium">
                            Retención
                        </th>
                        <th class="px-3 py-2">
                            <span class="sr-only">Acciones</span>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="r in rentas" :key="r.id" class="border-t">
                        <td class="px-3 py-2 tabular-nums">{{ r.anio }}</td>
                        <td class="px-3 py-2">
                            {{ r.empleador || '—' }}
                            <span
                                v-if="r.ruc_empleador"
                                class="block text-xs text-muted-foreground tabular-nums"
                                >RUC {{ r.ruc_empleador }}</span
                            >
                        </td>
                        <td class="px-3 py-2 text-right tabular-nums">
                            {{ formatoMonto(r.renta) }}
                        </td>
                        <td class="px-3 py-2 text-right tabular-nums">
                            {{ formatoMonto(r.retencion) }}
                        </td>
                        <td class="px-3 py-2 text-right">
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                :aria-label="`Quitar la renta de ${r.empleador || 'otro empleador'} ${r.anio}`"
                                @click="quitar(r)"
                            >
                                <Trash2 />
                            </Button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="grid gap-4 rounded-lg border p-4">
            <h3 class="font-medium">Agregar certificado</h3>
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="oe-anio">Año</Label>
                    <Input
                        id="oe-anio"
                        v-model="form.anio"
                        inputmode="numeric"
                        class="tabular-nums"
                    />
                    <InputError :message="form.errors.anio" />
                </div>
                <div class="grid gap-2">
                    <Label for="oe-ruc">RUC del empleador (opcional)</Label>
                    <Input
                        id="oe-ruc"
                        v-model="form.ruc_empleador"
                        inputmode="numeric"
                        maxlength="11"
                        class="tabular-nums"
                    />
                    <InputError :message="form.errors.ruc_empleador" />
                </div>
                <div class="grid gap-2 sm:col-span-2">
                    <Label for="oe-empleador">Empleador (opcional)</Label>
                    <Input id="oe-empleador" v-model="form.empleador" />
                    <InputError :message="form.errors.empleador" />
                </div>
                <div class="grid gap-2">
                    <Label for="oe-renta">Renta percibida (S/)</Label>
                    <Input
                        id="oe-renta"
                        v-model="form.renta"
                        inputmode="decimal"
                        class="tabular-nums"
                        placeholder="0.00"
                    />
                    <InputError :message="form.errors.renta" />
                </div>
                <div class="grid gap-2">
                    <Label for="oe-retencion">Retención de quinta (S/)</Label>
                    <Input
                        id="oe-retencion"
                        v-model="form.retencion"
                        inputmode="decimal"
                        class="tabular-nums"
                        placeholder="0.00"
                    />
                    <InputError :message="form.errors.retencion" />
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
                    Agregar
                </Button>
            </div>
        </div>
    </div>
</template>
