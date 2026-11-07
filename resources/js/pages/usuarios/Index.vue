<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Pencil, Plus, Search, UserCheck, UserX } from '@lucide/vue';
import { useDebounceFn } from '@vueuse/core';
import { ref, watch } from 'vue';
import Heading from '@/components/Heading.vue';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { create, edit, estado, index } from '@/routes/usuarios';
import type { OpcionRol, Paginado, UsuarioFila } from '@/types';

type Filtros = {
    buscar: string;
    rol: string | null;
    estado: 'activos' | 'inactivos' | null;
};

const props = defineProps<{
    usuarios: Paginado<UsuarioFila>;
    filtros: Filtros;
    roles: OpcionRol[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Usuarios', href: index() }],
    },
});

const TODOS = 'todos';

const buscar = ref(props.filtros.buscar);
const rol = ref(props.filtros.rol ?? TODOS);
const estadoFiltro = ref<string>(props.filtros.estado ?? TODOS);

function aplicarFiltros(): void {
    router.get(
        index.url({
            query: {
                buscar: buscar.value || undefined,
                rol: rol.value === TODOS ? undefined : rol.value,
                estado:
                    estadoFiltro.value === TODOS
                        ? undefined
                        : estadoFiltro.value,
            },
        }),
        {},
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

const buscarConPausa = useDebounceFn(aplicarFiltros, 350);
watch(buscar, buscarConPausa);
watch([rol, estadoFiltro], aplicarFiltros);

const porDesactivar = ref<UsuarioFila | null>(null);

function alternarEstado(usuario: UsuarioFila): void {
    router.patch(estado.url(usuario.id), {}, { preserveScroll: true });
}

function confirmarDesactivacion(): void {
    if (porDesactivar.value) {
        alternarEstado(porDesactivar.value);
        porDesactivar.value = null;
    }
}
</script>

<template>
    <Head title="Usuarios" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading
                title="Usuarios"
                description="Personas que ingresan al sistema y las empresas con las que trabajan."
            />
            <Button as-child>
                <Link :href="create()">
                    <Plus />
                    Nuevo usuario
                </Link>
            </Button>
        </div>

        <div class="flex flex-wrap gap-3">
            <div class="relative w-full sm:max-w-xs">
                <Search
                    class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                />
                <Input
                    v-model="buscar"
                    class="pl-9"
                    placeholder="Buscar por nombre o correo"
                    aria-label="Buscar usuario"
                />
            </div>
            <Select v-model="rol">
                <SelectTrigger class="w-44" aria-label="Filtrar por rol">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem :value="TODOS">Todos los roles</SelectItem>
                    <SelectItem
                        v-for="opcion in roles"
                        :key="opcion.value"
                        :value="opcion.value"
                    >
                        {{ opcion.label }}
                    </SelectItem>
                </SelectContent>
            </Select>
            <Select v-model="estadoFiltro">
                <SelectTrigger class="w-40" aria-label="Filtrar por estado">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem :value="TODOS">Todos</SelectItem>
                    <SelectItem value="activos">Activos</SelectItem>
                    <SelectItem value="inactivos">Inactivos</SelectItem>
                </SelectContent>
            </Select>
        </div>

        <div class="overflow-x-auto rounded-lg border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left text-muted-foreground">
                    <tr>
                        <th class="px-4 py-3 font-medium">Nombre</th>
                        <th class="px-4 py-3 font-medium">Rol</th>
                        <th class="px-4 py-3 font-medium">Empresas</th>
                        <th class="px-4 py-3 font-medium">Estado</th>
                        <th class="px-4 py-3 text-right font-medium">
                            <span class="sr-only">Acciones</span>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="usuario in usuarios.data"
                        :key="usuario.id"
                        class="border-t"
                        :class="{ 'text-muted-foreground': !usuario.activo }"
                    >
                        <td class="px-4 py-3">
                            <div class="font-medium text-foreground">
                                {{ usuario.name }}
                                <span
                                    v-if="usuario.es_actual"
                                    class="ml-1 text-xs font-normal text-muted-foreground"
                                >
                                    (tú)
                                </span>
                            </div>
                            <div class="text-xs text-muted-foreground">
                                {{ usuario.email }}
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            <Badge
                                :variant="
                                    usuario.rol === 'admin'
                                        ? 'default'
                                        : 'secondary'
                                "
                            >
                                {{ usuario.rol_etiqueta }}
                            </Badge>
                        </td>
                        <td class="px-4 py-3">
                            <span v-if="usuario.rol === 'admin'">Todas</span>
                            <span v-else-if="usuario.empresas_count === 0">
                                Ninguna
                            </span>
                            <span v-else>{{ usuario.empresas_count }}</span>
                        </td>
                        <td class="px-4 py-3">
                            <Badge
                                :variant="
                                    usuario.activo ? 'outline' : 'destructive'
                                "
                            >
                                {{ usuario.activo ? 'Activo' : 'Inactivo' }}
                            </Badge>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex justify-end gap-1">
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    as-child
                                    :title="`Editar a ${usuario.name}`"
                                >
                                    <Link :href="edit(usuario.id)">
                                        <Pencil />
                                        <span class="sr-only">Editar</span>
                                    </Link>
                                </Button>
                                <template v-if="!usuario.es_actual">
                                    <Button
                                        v-if="usuario.activo"
                                        variant="ghost"
                                        size="icon"
                                        :title="`Desactivar a ${usuario.name}`"
                                        @click="porDesactivar = usuario"
                                    >
                                        <UserX />
                                        <span class="sr-only">Desactivar</span>
                                    </Button>
                                    <Button
                                        v-else
                                        variant="ghost"
                                        size="icon"
                                        :title="`Reactivar a ${usuario.name}`"
                                        @click="alternarEstado(usuario)"
                                    >
                                        <UserCheck />
                                        <span class="sr-only">Reactivar</span>
                                    </Button>
                                </template>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="usuarios.data.length === 0">
                        <td
                            colspan="5"
                            class="px-4 py-10 text-center text-muted-foreground"
                        >
                            No hay usuarios que coincidan con la búsqueda.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div
            v-if="usuarios.last_page > 1"
            class="flex items-center justify-between text-sm text-muted-foreground"
        >
            <span>
                Mostrando {{ usuarios.from }}–{{ usuarios.to }} de
                {{ usuarios.total }}
            </span>
            <div class="flex gap-2">
                <template
                    v-for="pagina in [
                        { texto: 'Anterior', url: usuarios.prev_page_url },
                        { texto: 'Siguiente', url: usuarios.next_page_url },
                    ]"
                    :key="pagina.texto"
                >
                    <Button
                        v-if="pagina.url"
                        variant="outline"
                        size="sm"
                        as-child
                    >
                        <Link :href="pagina.url" preserve-state preserve-scroll>
                            {{ pagina.texto }}
                        </Link>
                    </Button>
                    <Button v-else variant="outline" size="sm" disabled>
                        {{ pagina.texto }}
                    </Button>
                </template>
            </div>
        </div>
    </div>

    <Dialog
        :open="porDesactivar !== null"
        @update:open="(abierto) => !abierto && (porDesactivar = null)"
    >
        <DialogContent>
            <DialogHeader>
                <DialogTitle
                    >¿Desactivar a {{ porDesactivar?.name }}?</DialogTitle
                >
                <DialogDescription>
                    No podrá ingresar al sistema y, si tiene una sesión abierta,
                    se cerrará. Su historial se conserva y puedes reactivarlo
                    cuando quieras.
                </DialogDescription>
            </DialogHeader>
            <DialogFooter>
                <DialogClose as-child>
                    <Button variant="outline">Cancelar</Button>
                </DialogClose>
                <Button variant="destructive" @click="confirmarDesactivacion">
                    Desactivar
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
