<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { rutasPanel } from '@/lib/panel';

const props = defineProps<{ url_estudio: string; vence_demo: string }>();

const form = useForm({
    nombre: '',
    subdominio: '',
    nombre_admin: '',
    correo_admin: '',
    tipo: 'demo' as 'demo' | 'cliente',
    vence_en: props.vence_demo,
});

// Sugerencia de subdominio a partir del nombre (sin tildes, minúsculas y guiones).
const subdominioEditado = ref(false);
watch(
    () => form.nombre,
    (nombre) => {
        if (subdominioEditado.value) {
            return;
        }
        form.subdominio = nombre
            .normalize('NFD')
            .replace(/[̀-ͯ]/g, '')
            .toLowerCase()
            .replace(
                /\b(estudio|contable|contadores|asociados|s\.?a\.?c?|e\.?i\.?r\.?l|s\.?r\.?l|y|de|la|el)\b/g,
                '',
            )
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '')
            .slice(0, 30);
    },
);

watch(
    () => form.tipo,
    (tipo) => {
        form.vence_en =
            tipo === 'demo' ? form.vence_en || props.vence_demo : '';
    },
);

const direccion = computed(() =>
    props.url_estudio.replace('{estudio}', form.subdominio || 'subdominio'),
);

function crear(): void {
    form.post(rutasPanel.cuentas);
}
</script>

<template>
    <Head title="Nueva cuenta" />

    <div class="flex max-w-2xl flex-col gap-6">
        <Link
            :href="rutasPanel.cuentas"
            class="inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground"
        >
            <ArrowLeft class="size-4" />
            Cuentas
        </Link>
        <Heading
            title="Nueva cuenta"
            description="Se crea una base de datos limpia (con los catálogos legales) y se envía al administrador el enlace para crear su contraseña."
        />

        <form
            class="grid gap-5 rounded-lg border bg-background p-5"
            @submit.prevent="crear"
        >
            <fieldset class="grid gap-2">
                <legend class="mb-2 text-sm font-medium">Tipo</legend>
                <div class="flex flex-wrap gap-3">
                    <label
                        v-for="t in [
                            {
                                v: 'demo',
                                t: 'Demo',
                                d: 'Para probar; vence en la fecha indicada.',
                            },
                            {
                                v: 'cliente',
                                t: 'Cliente',
                                d: 'Plan mensual o pago único alojado.',
                            },
                        ]"
                        :key="t.v"
                        class="flex flex-1 cursor-pointer items-start gap-2 rounded-md border p-3 text-sm has-[:checked]:border-primary has-[:checked]:bg-muted/40"
                    >
                        <input
                            v-model="form.tipo"
                            type="radio"
                            name="tipo"
                            :value="t.v"
                            class="mt-1"
                        />
                        <span>
                            <span class="font-medium">{{ t.t }}</span>
                            <span class="block text-muted-foreground">{{
                                t.d
                            }}</span>
                        </span>
                    </label>
                </div>
                <InputError :message="form.errors.tipo" />
            </fieldset>

            <div class="grid gap-2">
                <Label for="nombre">Nombre del estudio o empresa</Label>
                <Input
                    id="nombre"
                    v-model="form.nombre"
                    placeholder="Estudio Contable García"
                    required
                />
                <InputError :message="form.errors.nombre" />
            </div>

            <div class="grid gap-2">
                <Label for="subdominio">Subdominio</Label>
                <Input
                    id="subdominio"
                    v-model="form.subdominio"
                    placeholder="garcia"
                    autocomplete="off"
                    required
                    @input="subdominioEditado = true"
                />
                <p class="text-xs text-muted-foreground">
                    Dirección de ingreso: <b>{{ direccion }}</b>
                </p>
                <InputError :message="form.errors.subdominio" />
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="nombre_admin">Nombre del administrador</Label>
                    <Input
                        id="nombre_admin"
                        v-model="form.nombre_admin"
                        autocomplete="off"
                        required
                    />
                    <InputError :message="form.errors.nombre_admin" />
                </div>
                <div class="grid gap-2">
                    <Label for="correo_admin">Correo del administrador</Label>
                    <Input
                        id="correo_admin"
                        v-model="form.correo_admin"
                        type="email"
                        autocomplete="off"
                        required
                    />
                    <InputError :message="form.errors.correo_admin" />
                </div>
            </div>

            <div v-if="form.tipo === 'demo'" class="grid gap-2 sm:max-w-xs">
                <Label for="vence_en">Vence el</Label>
                <Input
                    id="vence_en"
                    v-model="form.vence_en"
                    type="date"
                    required
                />
                <InputError :message="form.errors.vence_en" />
            </div>

            <div>
                <Button type="submit" :disabled="form.processing">
                    <Spinner v-if="form.processing" />
                    {{
                        form.processing
                            ? 'Creando base de datos…'
                            : 'Crear cuenta'
                    }}
                </Button>
            </div>
        </form>
    </div>
</template>
