<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

/** Plan de una cuenta del modo SaaS: plantilla, nombre y límites (vacío = sin límite). */
export type PlanPlantilla = {
    nombre: string;
    max_empresas: number | null;
    max_usuarios: number | null;
    max_trabajadores: number | null;
    dias: number | null;
};

export type CamposPlanForm = {
    plan: string;
    max_empresas: number | string | null;
    max_usuarios: number | string | null;
    max_trabajadores: number | string | null;
    errors: Partial<Record<string, string>>;
};

const props = defineProps<{
    form: CamposPlanForm;
    planes: Record<string, PlanPlantilla>;
}>();

function aplicar(clave: string): void {
    const p = props.planes[clave];
    if (!p) {
        return;
    }
    props.form.plan = p.nombre;
    props.form.max_empresas = p.max_empresas;
    props.form.max_usuarios = p.max_usuarios;
    props.form.max_trabajadores = p.max_trabajadores;
}

const limites = [
    { campo: 'max_empresas', etiqueta: 'Empresas' },
    { campo: 'max_usuarios', etiqueta: 'Usuarios' },
    { campo: 'max_trabajadores', etiqueta: 'Trabajadores' },
] as const;
</script>

<template>
    <fieldset class="grid gap-3">
        <legend class="mb-1 text-sm font-medium">Plan y límites</legend>
        <div class="flex flex-wrap gap-2">
            <button
                v-for="(p, clave) in planes"
                :key="clave"
                type="button"
                class="rounded-md border px-3 py-1.5 text-sm transition-colors hover:bg-muted"
                :class="
                    form.plan === p.nombre
                        ? 'border-primary bg-muted/60 font-medium'
                        : ''
                "
                @click="aplicar(String(clave))"
            >
                {{ p.nombre }}
            </button>
        </div>
        <div class="grid gap-4 sm:grid-cols-4">
            <div class="grid gap-2">
                <Label for="plan">Nombre del plan</Label>
                <Input id="plan" v-model="form.plan" required />
                <InputError :message="form.errors.plan" />
            </div>
            <div v-for="l in limites" :key="l.campo" class="grid gap-2">
                <Label :for="l.campo">{{ l.etiqueta }}</Label>
                <Input
                    :id="l.campo"
                    :model-value="form[l.campo] ?? ''"
                    @update:model-value="
                        (v) => (form[l.campo] = v === '' ? null : v)
                    "
                    type="number"
                    min="1"
                    placeholder="Sin límite"
                />
                <InputError :message="form.errors[l.campo]" />
            </div>
        </div>
        <p class="text-xs text-muted-foreground">
            Cuentan solo los activos. Vacío = sin límite.
        </p>
    </fieldset>
</template>
