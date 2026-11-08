<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Pencil, Plus, Search, Upload, Users } from '@lucide/vue';
import { useDebounceFn } from '@vueuse/core';
import { computed, ref, watch } from 'vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { create, edit, importar, index } from '@/routes/trabajadores';
import type { Opcion, Paginado, TrabajadorFila } from '@/types';

type Filtros = {
    buscar: string;
    estado: 'activos' | 'cesados' | 'todos';
    pension: string | null;
    establecimiento: number | null;
};

const props = defineProps<{
    trabajadores: Paginado<TrabajadorFila>;
    filtros: Filtros;
    establecimientos: Opcion[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Trabajadores', href: index() }],
    },
});

const page = usePage();
const empresa = computed(() => page.props.empresaActiva?.razon_social);

const TODOS = 'todos';
const buscar = ref(props.filtros.buscar);
const estado = ref<string>(props.filtros.estado);
const pension = ref(props.filtros.pension ?? TODOS);
const establecimiento = ref(
    props.filtros.establecimiento
        ? String(props.filtros.establecimiento)
        : TODOS,
);

function aplicar(): void {
    router.get(
        index.url({
            query: {
                buscar: buscar.value || undefined,
                estado: estado.value === 'activos' ? undefined : estado.value,
                pension: pension.value === TODOS ? undefined : pension.value,
                establecimiento:
                    establecimiento.value === TODOS
                        ? undefined
                        : establecimiento.value,
            },
        }),
        {},
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

watch(buscar, useDebounceFn(aplicar, 350));
watch([estado, pension, establecimiento], aplicar);

const soles = new Intl.NumberFormat('es-PE', {
    style: 'currency',
    currency: 'PEN',
});
const hayFiltros = computed(
    () =>
        buscar.value !== '' ||
        estado.value !== 'activos' ||
        pension.value !== TODOS ||
        establecimiento.value !== TODOS,
);
</script>

<template>
    <Head title="Trabajadores" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading
                title="Trabajadores"
                :description="`Personal de ${empresa ?? 'la empresa activa'}.`"
            />
            <div class="flex gap-2">
                <Button variant="outline" as-child>
                    <Link :href="importar()">
                        <Upload />
                        Importar desde Excel
                    </Link>
                </Button>
                <Button as-child>
                    <Link :href="create()">
                        <Plus />
                        Nuevo trabajador
                    </Link>
                </Button>
            </div>
        </div>

        <div class="flex flex-wrap gap-3">
            <div class="relative w-full sm:max-w-xs">
                <Search
                    class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                />
                <Input
                    v-model="buscar"
                    class="pl-9"
                    placeholder="Buscar por documento o nombre"
                    aria-label="Buscar trabajador"
                />
            </div>
            <Select v-model="estado">
                <SelectTrigger class="w-36" aria-label="Estado">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="activos">Activos</SelectItem>
                    <SelectItem value="cesados">Cesados</SelectItem>
                    <SelectItem value="todos">Todos</SelectItem>
                </SelectContent>
            </Select>
            <Select v-model="pension">
                <SelectTrigger class="w-40" aria-label="Sistema de pensiones">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem :value="TODOS">ONP y AFP</SelectItem>
                    <SelectItem value="onp">Solo ONP</SelectItem>
                    <SelectItem value="afp">Solo AFP</SelectItem>
                </SelectContent>
            </Select>
            <Select
                v-if="establecimientos.length > 1"
                v-model="establecimiento"
            >
                <SelectTrigger class="w-52" aria-label="Establecimiento">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem :value="TODOS">Todos los locales</SelectItem>
                    <SelectItem
                        v-for="e in establecimientos"
                        :key="e.value"
                        :value="e.value"
                    >
                        {{ e.label }}
                    </SelectItem>
                </SelectContent>
            </Select>
        </div>

        <div
            v-if="trabajadores.total === 0 && !hayFiltros"
            class="flex flex-col items-center gap-3 rounded-lg border border-dashed px-6 py-14 text-center"
        >
            <div
                class="flex size-12 items-center justify-center rounded-full bg-muted"
            >
                <Users class="size-6 text-muted-foreground" />
            </div>
            <p class="font-medium">Aún no hay trabajadores registrados</p>
            <p class="max-w-sm text-sm text-muted-foreground">
                Registra el primero con su ficha completa: datos personales,
                laborales, pensión y cuentas.
            </p>
            <div class="mt-2 flex gap-2">
                <Button variant="outline" as-child>
                    <Link :href="importar()"
                        ><Upload />Importar desde Excel</Link
                    >
                </Button>
                <Button as-child>
                    <Link :href="create()"><Plus />Nuevo trabajador</Link>
                </Button>
            </div>
        </div>

        <div v-else class="overflow-x-auto rounded-lg border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left text-muted-foreground">
                    <tr>
                        <th class="px-4 py-3 font-medium">Trabajador</th>
                        <th class="px-4 py-3 font-medium">Cargo</th>
                        <th class="px-4 py-3 font-medium">Ingreso</th>
                        <th class="px-4 py-3 text-right font-medium">Básico</th>
                        <th class="px-4 py-3 font-medium">Pensión</th>
                        <th class="px-4 py-3">
                            <span class="sr-only">Acciones</span>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="t in trabajadores.data"
                        :key="t.id"
                        class="border-t"
                        :class="{ 'text-muted-foreground': t.cesado }"
                    >
                        <td class="px-4 py-3">
                            <Link
                                :href="edit(t.id)"
                                class="font-medium text-foreground hover:underline"
                            >
                                {{ t.nombre_completo }}
                            </Link>
                            <div class="text-xs text-muted-foreground">
                                {{ t.documento }}
                                <template v-if="t.establecimiento">
                                    · {{ t.establecimiento }}
                                </template>
                            </div>
                        </td>
                        <td class="px-4 py-3">{{ t.cargo ?? '—' }}</td>
                        <td class="px-4 py-3 tabular-nums">
                            {{ t.fecha_ingreso }}
                            <Badge
                                v-if="t.cesado"
                                variant="secondary"
                                class="ml-1"
                            >
                                Cesado {{ t.fecha_cese }}
                            </Badge>
                            <Badge
                                v-else-if="t.fecha_cese"
                                variant="outline"
                                class="ml-1"
                            >
                                Cesa el {{ t.fecha_cese }}
                            </Badge>
                        </td>
                        <td class="px-4 py-3 text-right tabular-nums">
                            {{ soles.format(Number(t.remuneracion_basica)) }}
                        </td>
                        <td class="px-4 py-3">{{ t.pension }}</td>
                        <td class="px-4 py-3">
                            <Button
                                variant="ghost"
                                size="icon"
                                as-child
                                :title="`Editar a ${t.nombre_completo}`"
                            >
                                <Link :href="edit(t.id)">
                                    <Pencil />
                                    <span class="sr-only">Editar</span>
                                </Link>
                            </Button>
                        </td>
                    </tr>
                    <tr v-if="trabajadores.data.length === 0">
                        <td
                            colspan="6"
                            class="px-4 py-10 text-center text-muted-foreground"
                        >
                            No hay trabajadores que coincidan con la búsqueda.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div
            v-if="trabajadores.last_page > 1"
            class="flex items-center justify-between text-sm text-muted-foreground"
        >
            <span>
                Mostrando {{ trabajadores.from }}–{{ trabajadores.to }} de
                {{ trabajadores.total }}
            </span>
            <div class="flex gap-2">
                <template
                    v-for="pagina in [
                        { texto: 'Anterior', url: trabajadores.prev_page_url },
                        { texto: 'Siguiente', url: trabajadores.next_page_url },
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
</template>
