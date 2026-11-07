<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    Building,
    CircleCheck,
    CircleOff,
    Pencil,
    Plus,
    Search,
} from '@lucide/vue';
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
import { create, edit, estado, index } from '@/routes/empresas';
import type { EmpresaFila, Opcion, Paginado } from '@/types';

type Filtros = {
    buscar: string;
    regimen: string | null;
    estado: 'activas' | 'inactivas' | null;
};

const props = defineProps<{
    empresas: Paginado<EmpresaFila>;
    filtros: Filtros;
    regimenes: Opcion[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Empresas', href: index() }],
    },
});

const TODOS = 'todos';

const buscar = ref(props.filtros.buscar);
const regimen = ref(props.filtros.regimen ?? TODOS);
const estadoFiltro = ref<string>(props.filtros.estado ?? TODOS);

function aplicarFiltros(): void {
    router.get(
        index.url({
            query: {
                buscar: buscar.value || undefined,
                regimen: regimen.value === TODOS ? undefined : regimen.value,
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
watch([regimen, estadoFiltro], aplicarFiltros);

const porDesactivar = ref<EmpresaFila | null>(null);

function alternarEstado(empresa: EmpresaFila): void {
    router.patch(estado.url(empresa.id), {}, { preserveScroll: true });
}

function confirmarDesactivacion(): void {
    if (porDesactivar.value) {
        alternarEstado(porDesactivar.value);
        porDesactivar.value = null;
    }
}

const hayFiltros = () =>
    buscar.value !== '' ||
    regimen.value !== TODOS ||
    estadoFiltro.value !== TODOS;
</script>

<template>
    <Head title="Empresas" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading
                title="Empresas"
                description="Empleadores cuya planilla se calcula en este sistema."
            />
            <Button as-child>
                <Link :href="create()">
                    <Plus />
                    Nueva empresa
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
                    placeholder="Buscar por RUC o razón social"
                    aria-label="Buscar empresa"
                />
            </div>
            <Select v-model="regimen">
                <SelectTrigger class="w-56" aria-label="Filtrar por régimen">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem :value="TODOS">Todos los regímenes</SelectItem>
                    <SelectItem
                        v-for="opcion in regimenes"
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
                    <SelectItem :value="TODOS">Todas</SelectItem>
                    <SelectItem value="activas">Activas</SelectItem>
                    <SelectItem value="inactivas">Inactivas</SelectItem>
                </SelectContent>
            </Select>
        </div>

        <div
            v-if="empresas.total === 0 && !hayFiltros()"
            class="flex flex-col items-center gap-3 rounded-lg border border-dashed px-6 py-14 text-center"
        >
            <div
                class="flex size-12 items-center justify-center rounded-full bg-muted"
            >
                <Building class="size-6 text-muted-foreground" />
            </div>
            <p class="font-medium">Aún no hay empresas registradas</p>
            <p class="max-w-sm text-sm text-muted-foreground">
                Registra la primera con su RUC; los datos se completan desde
                SUNAT.
            </p>
            <Button as-child class="mt-2">
                <Link :href="create()"><Plus />Nueva empresa</Link>
            </Button>
        </div>

        <div v-else class="overflow-x-auto rounded-lg border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left text-muted-foreground">
                    <tr>
                        <th class="px-4 py-3 font-medium">Empresa</th>
                        <th class="px-4 py-3 font-medium">Régimen</th>
                        <th class="px-4 py-3 text-right font-medium">
                            Trabajadores
                        </th>
                        <th class="px-4 py-3 text-right font-medium">
                            Establecimientos
                        </th>
                        <th class="px-4 py-3 font-medium">Estado</th>
                        <th class="px-4 py-3">
                            <span class="sr-only">Acciones</span>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="empresa in empresas.data"
                        :key="empresa.id"
                        class="border-t"
                        :class="{ 'text-muted-foreground': !empresa.activo }"
                    >
                        <td class="px-4 py-3">
                            <div class="font-medium text-foreground">
                                {{ empresa.razon_social }}
                            </div>
                            <div class="text-xs text-muted-foreground">
                                RUC {{ empresa.ruc }}
                                <template v-if="empresa.nombre_comercial">
                                    · {{ empresa.nombre_comercial }}
                                </template>
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            {{ empresa.regimen_etiqueta }}
                            <Badge
                                v-if="empresa.seguro_salud === 'sis'"
                                variant="secondary"
                                class="ml-1"
                            >
                                SIS
                            </Badge>
                        </td>
                        <td class="px-4 py-3 text-right tabular-nums">
                            {{ empresa.trabajadores_count }}
                        </td>
                        <td class="px-4 py-3 text-right tabular-nums">
                            {{ empresa.establecimientos_count }}
                        </td>
                        <td class="px-4 py-3">
                            <Badge
                                :variant="
                                    empresa.activo ? 'outline' : 'destructive'
                                "
                            >
                                {{ empresa.activo ? 'Activa' : 'Inactiva' }}
                            </Badge>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex justify-end gap-1">
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    as-child
                                    :title="`Editar ${empresa.razon_social}`"
                                >
                                    <Link :href="edit(empresa.id)">
                                        <Pencil />
                                        <span class="sr-only">Editar</span>
                                    </Link>
                                </Button>
                                <Button
                                    v-if="empresa.activo"
                                    variant="ghost"
                                    size="icon"
                                    :title="`Desactivar ${empresa.razon_social}`"
                                    @click="porDesactivar = empresa"
                                >
                                    <CircleOff />
                                    <span class="sr-only">Desactivar</span>
                                </Button>
                                <Button
                                    v-else
                                    variant="ghost"
                                    size="icon"
                                    :title="`Reactivar ${empresa.razon_social}`"
                                    @click="alternarEstado(empresa)"
                                >
                                    <CircleCheck />
                                    <span class="sr-only">Reactivar</span>
                                </Button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="empresas.data.length === 0">
                        <td
                            colspan="6"
                            class="px-4 py-10 text-center text-muted-foreground"
                        >
                            No hay empresas que coincidan con la búsqueda.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div
            v-if="empresas.last_page > 1"
            class="flex items-center justify-between text-sm text-muted-foreground"
        >
            <span>
                Mostrando {{ empresas.from }}–{{ empresas.to }} de
                {{ empresas.total }}
            </span>
            <div class="flex gap-2">
                <template
                    v-for="pagina in [
                        { texto: 'Anterior', url: empresas.prev_page_url },
                        { texto: 'Siguiente', url: empresas.next_page_url },
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
                <DialogTitle>
                    ¿Desactivar {{ porDesactivar?.razon_social }}?
                </DialogTitle>
                <DialogDescription>
                    Dejará de aparecer en el selector de empresas y no se podrá
                    trabajar su planilla. Su información e historial se
                    conservan; puedes reactivarla cuando quieras.
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
