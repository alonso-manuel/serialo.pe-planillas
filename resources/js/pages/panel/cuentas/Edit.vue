<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import CamposPlan from '@/components/panel/CamposPlan.vue';
import type { PlanPlantilla } from '@/components/panel/CamposPlan.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { rutasPanel } from '@/lib/panel';

const props = defineProps<{
    cuenta: {
        id: string;
        nombre: string;
        url: string;
        correo_admin: string | null;
        tipo: 'demo' | 'cliente';
        plan: string;
        max_empresas: number | null;
        max_usuarios: number | null;
        max_trabajadores: number | null;
        vence_en: string | null;
    };
    planes: Record<string, PlanPlantilla>;
}>();

const form = useForm({
    nombre: props.cuenta.nombre,
    tipo: props.cuenta.tipo,
    plan: props.cuenta.plan,
    max_empresas: props.cuenta.max_empresas as number | string | null,
    max_usuarios: props.cuenta.max_usuarios as number | string | null,
    max_trabajadores: props.cuenta.max_trabajadores as number | string | null,
    vence_en: props.cuenta.vence_en ?? '',
});

const descripcion = `${props.cuenta.url.replace(/^https?:\/\//, '')} · administrador: ${props.cuenta.correo_admin ?? '—'}`;

function guardar(): void {
    form.put(rutasPanel.cuenta(props.cuenta.id));
}
</script>

<template>
    <Head :title="`Editar ${cuenta.nombre}`" />

    <div class="flex max-w-3xl flex-col gap-6">
        <Link
            :href="rutasPanel.cuentas"
            class="inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground"
        >
            <ArrowLeft class="size-4" />
            Cuentas
        </Link>
        <Heading :title="cuenta.nombre" :description="descripcion" />

        <form
            class="grid gap-5 rounded-lg border bg-background p-5"
            @submit.prevent="guardar"
        >
            <div class="grid gap-4 sm:grid-cols-[1fr_auto]">
                <div class="grid gap-2">
                    <Label for="nombre">Nombre de la cuenta</Label>
                    <Input id="nombre" v-model="form.nombre" required />
                    <InputError :message="form.errors.nombre" />
                </div>
                <div class="grid gap-2">
                    <Label for="tipo">Tipo</Label>
                    <select
                        id="tipo"
                        v-model="form.tipo"
                        class="h-9 rounded-md border border-input bg-background px-3 text-sm"
                    >
                        <option value="demo">Demo</option>
                        <option value="cliente">Cliente</option>
                    </select>
                </div>
            </div>

            <CamposPlan :form="form" :planes="planes" />

            <div class="grid gap-2 sm:max-w-xs">
                <Label for="vence_en">Vence el</Label>
                <Input id="vence_en" v-model="form.vence_en" type="date" />
                <p class="text-xs text-muted-foreground">
                    Vacío = sin vencimiento. Al vencer, la cuenta queda en solo
                    lectura (sus datos se conservan) y se avisa por correo.
                </p>
                <InputError :message="form.errors.vence_en" />
            </div>

            <div>
                <Button type="submit" :disabled="form.processing">
                    <Spinner v-if="form.processing" />
                    Guardar cambios
                </Button>
            </div>
        </form>
    </div>
</template>
