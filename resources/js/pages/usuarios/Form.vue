<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Check, Copy, Eye, EyeOff, RefreshCw, Search } from '@lucide/vue';
import { computed, ref } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { generarContrasena } from '@/lib/contrasena';
import { index, store, update } from '@/routes/usuarios';
import type {
    EmpresaResumen,
    OpcionRol,
    RolUsuario,
    UsuarioEditable,
} from '@/types';

const props = defineProps<{
    usuario: UsuarioEditable | null;
    empresas: EmpresaResumen[];
    roles: OpcionRol[];
}>();

const esNuevo = props.usuario === null;
const titulo = esNuevo ? 'Nuevo usuario' : `Editar a ${props.usuario?.name}`;

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Usuarios', href: index() }],
    },
});

const form = useForm<{
    name: string;
    email: string;
    rol: RolUsuario;
    password: string;
    empresas: number[];
}>({
    name: props.usuario?.name ?? '',
    email: props.usuario?.email ?? '',
    rol: props.usuario?.rol ?? 'asistente',
    password: esNuevo ? generarContrasena() : '',
    empresas: props.usuario?.empresas ?? [],
});

const verContrasena = ref(esNuevo);
const copiada = ref(false);

function sugerirContrasena(): void {
    form.password = generarContrasena();
    verContrasena.value = true;
    copiada.value = false;
}

async function copiarContrasena(): Promise<void> {
    await navigator.clipboard.writeText(form.password);
    copiada.value = true;
    setTimeout(() => (copiada.value = false), 2000);
}

const busqueda = ref('');
const empresasFiltradas = computed(() => {
    const texto = busqueda.value.trim().toLowerCase();

    return texto === ''
        ? props.empresas
        : props.empresas.filter(
              (e) =>
                  e.razon_social.toLowerCase().includes(texto) ||
                  e.ruc.includes(texto),
          );
});

function alternarEmpresa(id: number, marcada: boolean): void {
    form.empresas = marcada
        ? [...form.empresas, id]
        : form.empresas.filter((e) => e !== id);
}

const todasMarcadas = computed(
    () =>
        empresasFiltradas.value.length > 0 &&
        empresasFiltradas.value.every((e) => form.empresas.includes(e.id)),
);

function alternarTodas(): void {
    const visibles = empresasFiltradas.value.map((e) => e.id);
    form.empresas = todasMarcadas.value
        ? form.empresas.filter((id) => !visibles.includes(id))
        : [...new Set([...form.empresas, ...visibles])];
}

const errorEmpresas = computed(
    () =>
        form.errors.empresas ??
        Object.entries(form.errors).find(([campo]) =>
            campo.startsWith('empresas.'),
        )?.[1],
);

function guardar(): void {
    if (props.usuario) {
        form.put(update.url(props.usuario.id), { preserveScroll: true });
    } else {
        form.post(store.url(), { preserveScroll: true });
    }
}
</script>

<template>
    <Head :title="titulo" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <Heading
            :title="titulo"
            :description="
                esNuevo
                    ? 'El usuario ingresará con este correo y la contraseña que le entregues.'
                    : 'Deja la contraseña vacía para mantener la actual.'
            "
        />

        <form class="grid max-w-3xl gap-8" @submit.prevent="guardar">
            <section class="grid gap-5 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="name">Nombre completo</Label>
                    <Input
                        id="name"
                        v-model="form.name"
                        autocomplete="off"
                        required
                    />
                    <InputError :message="form.errors.name" />
                </div>

                <div class="grid gap-2">
                    <Label for="email">Correo electrónico</Label>
                    <Input
                        id="email"
                        v-model="form.email"
                        type="email"
                        autocomplete="off"
                        required
                    />
                    <InputError :message="form.errors.email" />
                </div>

                <div class="grid gap-2 sm:col-span-2">
                    <Label for="password">
                        {{ esNuevo ? 'Contraseña' : 'Nueva contraseña' }}
                    </Label>
                    <div class="flex gap-2">
                        <div class="relative flex-1">
                            <Input
                                id="password"
                                v-model="form.password"
                                :type="verContrasena ? 'text' : 'password'"
                                autocomplete="new-password"
                                class="pr-10 font-mono"
                                :required="esNuevo"
                                :placeholder="esNuevo ? '' : 'Sin cambios'"
                            />
                            <button
                                type="button"
                                class="absolute top-1/2 right-2 -translate-y-1/2 rounded p-1 text-muted-foreground hover:text-foreground"
                                :aria-label="
                                    verContrasena
                                        ? 'Ocultar contraseña'
                                        : 'Mostrar contraseña'
                                "
                                @click="verContrasena = !verContrasena"
                            >
                                <EyeOff v-if="verContrasena" class="size-4" />
                                <Eye v-else class="size-4" />
                            </button>
                        </div>
                        <Button
                            type="button"
                            variant="outline"
                            title="Sugerir una contraseña segura"
                            @click="sugerirContrasena"
                        >
                            <RefreshCw />
                            Sugerir
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            size="icon"
                            :disabled="!form.password"
                            :title="copiada ? 'Copiada' : 'Copiar contraseña'"
                            @click="copiarContrasena"
                        >
                            <Check v-if="copiada" />
                            <Copy v-else />
                            <span class="sr-only">Copiar contraseña</span>
                        </Button>
                    </div>
                    <p class="text-xs text-muted-foreground">
                        Cópiala y entrégasela al usuario; luego podrá cambiarla
                        en Configuración.
                    </p>
                    <InputError :message="form.errors.password" />
                </div>
            </section>

            <section class="grid gap-3">
                <Label>Rol</Label>
                <div class="grid gap-3 sm:grid-cols-2" role="radiogroup">
                    <label
                        v-for="opcion in roles"
                        :key="opcion.value"
                        class="flex cursor-pointer gap-3 rounded-lg border p-4 has-checked:border-primary has-checked:bg-primary/5 has-disabled:cursor-not-allowed has-disabled:opacity-60"
                    >
                        <input
                            v-model="form.rol"
                            type="radio"
                            name="rol"
                            :value="opcion.value"
                            class="mt-1 accent-primary"
                            :disabled="
                                usuario?.es_actual && opcion.value !== 'admin'
                            "
                        />
                        <span class="grid gap-1">
                            <span class="font-medium">{{ opcion.label }}</span>
                            <span class="text-sm text-muted-foreground">
                                {{
                                    opcion.value === 'admin'
                                        ? 'Ve todas las empresas y configura el sistema: usuarios, parámetros, tasas y conceptos.'
                                        : 'Trabaja solo con las empresas que le asignes.'
                                }}
                            </span>
                        </span>
                    </label>
                </div>
                <p
                    v-if="usuario?.es_actual"
                    class="text-xs text-muted-foreground"
                >
                    No puedes quitarte el rol de administrador a ti mismo.
                </p>
                <InputError :message="form.errors.rol" />
            </section>

            <section v-if="form.rol === 'asistente'" class="grid gap-3">
                <div class="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <Label>Empresas asignadas</Label>
                        <p class="text-sm text-muted-foreground">
                            {{ form.empresas.length }} de
                            {{ empresas.length }} seleccionadas
                        </p>
                    </div>
                    <div v-if="empresas.length > 0" class="flex gap-2">
                        <div v-if="empresas.length >= 7" class="relative w-56">
                            <Search
                                class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                            />
                            <Input
                                v-model="busqueda"
                                class="h-9 pl-9"
                                placeholder="Buscar empresa o RUC"
                                aria-label="Buscar empresa"
                            />
                        </div>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            class="h-9"
                            @click="alternarTodas"
                        >
                            {{
                                todasMarcadas ? 'Quitar todas' : 'Marcar todas'
                            }}
                        </Button>
                    </div>
                </div>

                <div
                    v-if="empresas.length === 0"
                    class="rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground"
                >
                    Aún no hay empresas activas. Podrás asignarlas después de
                    registrarlas.
                </div>
                <div
                    v-else
                    class="grid max-h-80 gap-1 overflow-y-auto rounded-lg border p-2"
                >
                    <label
                        v-for="empresa in empresasFiltradas"
                        :key="empresa.id"
                        class="flex cursor-pointer items-center gap-3 rounded-md px-2 py-2 hover:bg-muted/50"
                    >
                        <Checkbox
                            :model-value="form.empresas.includes(empresa.id)"
                            @update:model-value="
                                (v) => alternarEmpresa(empresa.id, v === true)
                            "
                        />
                        <span class="grid leading-tight">
                            <span class="text-sm">{{
                                empresa.razon_social
                            }}</span>
                            <span class="text-xs text-muted-foreground">
                                RUC {{ empresa.ruc }}
                            </span>
                        </span>
                    </label>
                    <p
                        v-if="empresasFiltradas.length === 0"
                        class="px-2 py-3 text-center text-sm text-muted-foreground"
                    >
                        Ninguna empresa coincide.
                    </p>
                </div>
                <InputError :message="errorEmpresas" />
            </section>

            <p
                v-else
                class="rounded-lg bg-muted/50 p-4 text-sm text-muted-foreground"
            >
                Un administrador ve todas las empresas; no necesita
                asignaciones.
            </p>

            <div class="flex gap-3">
                <Button type="submit" :disabled="form.processing">
                    <Spinner v-if="form.processing" />
                    {{ esNuevo ? 'Crear usuario' : 'Guardar cambios' }}
                </Button>
                <Button variant="outline" as-child>
                    <Link :href="index()">Cancelar</Link>
                </Button>
            </div>
        </form>
    </div>
</template>
