<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ChevronRight, ScrollText, X } from '@lucide/vue';
import { computed, reactive, ref, watch } from 'vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { index } from '@/routes/auditoria';
import type {
    FiltrosAuditoria,
    Opcion,
    Paginado,
    RegistroAuditoria,
} from '@/types';

const props = defineProps<{
    registros: Paginado<RegistroAuditoria>;
    filtros: FiltrosAuditoria;
    opciones: {
        empresas: Opcion[];
        usuarios: Opcion[];
        tipos: Opcion[];
        acciones: Opcion[];
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Auditoría', href: index() }],
    },
});

const TODOS = 'todos';

const filtros = reactive({
    empresa: props.filtros.empresa ? String(props.filtros.empresa) : TODOS,
    usuario: props.filtros.usuario ? String(props.filtros.usuario) : TODOS,
    tipo: props.filtros.tipo ?? TODOS,
    accion: props.filtros.accion ?? TODOS,
    desde: props.filtros.desde ?? '',
    hasta: props.filtros.hasta ?? '',
});

const SELECTORES = computed(
    () =>
        [
            {
                clave: 'empresa',
                titulo: 'Empresa',
                todos: 'Todas las empresas',
                opciones: props.opciones.empresas,
            },
            {
                clave: 'usuario',
                titulo: 'Usuario',
                todos: 'Todos los usuarios',
                opciones: props.opciones.usuarios,
            },
            {
                clave: 'tipo',
                titulo: 'Registro',
                todos: 'Todos los registros',
                opciones: props.opciones.tipos,
            },
            {
                clave: 'accion',
                titulo: 'Acción',
                todos: 'Todas las acciones',
                opciones: props.opciones.acciones,
            },
        ] as const,
);

function valor(v: string): string | undefined {
    return v === TODOS || v === '' ? undefined : v;
}

watch(filtros, () => {
    router.get(
        index.url({
            query: {
                empresa: valor(filtros.empresa),
                usuario: valor(filtros.usuario),
                tipo: valor(filtros.tipo),
                accion: valor(filtros.accion),
                desde: valor(filtros.desde),
                hasta: valor(filtros.hasta),
            },
        }),
        {},
        { preserveState: true, preserveScroll: true, replace: true },
    );
});

const hayFiltros = computed(
    () =>
        filtros.empresa !== TODOS ||
        filtros.usuario !== TODOS ||
        filtros.tipo !== TODOS ||
        filtros.accion !== TODOS ||
        filtros.desde !== '' ||
        filtros.hasta !== '',
);

function limpiar(): void {
    Object.assign(filtros, {
        empresa: TODOS,
        usuario: TODOS,
        tipo: TODOS,
        accion: TODOS,
        desde: '',
        hasta: '',
    });
}

const abiertos = ref(new Set<number>());

function alternar(id: number): void {
    if (abiertos.value.has(id)) {
        abiertos.value.delete(id);
    } else {
        abiertos.value.add(id);
    }
}

const COLORES: Record<string, string> = {
    creado: 'border-emerald-300 text-emerald-700 dark:border-emerald-800 dark:text-emerald-400',
    actualizado:
        'border-sky-300 text-sky-700 dark:border-sky-800 dark:text-sky-400',
    eliminado:
        'border-red-300 text-red-700 dark:border-red-900 dark:text-red-400',
    cerrado:
        'border-violet-300 text-violet-700 dark:border-violet-800 dark:text-violet-400',
    reabierto:
        'border-amber-300 text-amber-700 dark:border-amber-800 dark:text-amber-400',
};
</script>

<template>
    <Head title="Auditoría" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <Heading
            title="Auditoría"
            description="Quién creó, cambió o eliminó cada dato, cuándo y desde dónde. Este historial no se puede modificar."
        />

        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
            <div v-for="s in SELECTORES" :key="s.clave" class="grid gap-1.5">
                <Label class="text-xs text-muted-foreground">{{
                    s.titulo
                }}</Label>
                <Select v-model="filtros[s.clave]">
                    <SelectTrigger class="w-full">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem :value="TODOS">{{ s.todos }}</SelectItem>
                        <SelectItem
                            v-for="o in s.opciones"
                            :key="o.value"
                            :value="o.value"
                        >
                            {{ o.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>
            <div class="grid gap-1.5">
                <Label for="desde" class="text-xs text-muted-foreground"
                    >Desde</Label
                >
                <Input id="desde" v-model="filtros.desde" type="date" />
            </div>
            <div class="grid gap-1.5">
                <Label for="hasta" class="text-xs text-muted-foreground"
                    >Hasta</Label
                >
                <Input id="hasta" v-model="filtros.hasta" type="date" />
            </div>
        </div>
        <Button
            v-if="hayFiltros"
            variant="ghost"
            size="sm"
            class="-mt-3 w-fit"
            @click="limpiar"
        >
            <X />
            Quitar filtros
        </Button>

        <div
            v-if="registros.data.length === 0"
            class="flex flex-col items-center gap-2 rounded-lg border border-dashed px-6 py-14 text-center text-muted-foreground"
        >
            <ScrollText class="size-8" />
            <p>
                {{
                    hayFiltros
                        ? 'No hay registros con esos filtros.'
                        : 'Aún no hay cambios registrados.'
                }}
            </p>
        </div>

        <ul v-else class="divide-y rounded-lg border">
            <li v-for="r in registros.data" :key="r.id">
                <button
                    type="button"
                    class="flex w-full items-start gap-3 px-4 py-3 text-left text-sm hover:bg-muted/40 disabled:cursor-default disabled:hover:bg-transparent"
                    :disabled="r.cambios.length === 0"
                    :aria-expanded="abiertos.has(r.id)"
                    @click="alternar(r.id)"
                >
                    <ChevronRight
                        class="mt-0.5 size-4 shrink-0 text-muted-foreground transition-transform"
                        :class="{
                            'rotate-90': abiertos.has(r.id),
                            invisible: r.cambios.length === 0,
                        }"
                    />
                    <div class="grid min-w-0 flex-1 gap-0.5">
                        <div class="flex flex-wrap items-center gap-2">
                            <Badge variant="outline" :class="COLORES[r.accion]">
                                {{ r.accion_etiqueta }}
                            </Badge>
                            <span class="text-muted-foreground">{{
                                r.tipo_etiqueta
                            }}</span>
                            <span class="truncate font-medium">{{
                                r.descripcion
                            }}</span>
                        </div>
                        <p class="text-xs text-muted-foreground">
                            {{ r.fecha }} · {{ r.usuario }}
                            <template v-if="r.empresa">
                                · {{ r.empresa }}</template
                            >
                            <template v-if="r.ip"> · IP {{ r.ip }}</template>
                            <template v-if="r.cambios.length">
                                · {{ r.cambios.length }}
                                {{
                                    r.cambios.length === 1 ? 'campo' : 'campos'
                                }}
                            </template>
                        </p>
                    </div>
                </button>

                <div v-if="abiertos.has(r.id)" class="px-4 pb-4 pl-11">
                    <table
                        class="w-full max-w-3xl overflow-hidden rounded-md border text-xs"
                    >
                        <thead
                            class="bg-muted/50 text-left text-muted-foreground"
                        >
                            <tr>
                                <th class="w-1/3 px-3 py-1.5 font-medium">
                                    Campo
                                </th>
                                <th class="w-1/3 px-3 py-1.5 font-medium">
                                    Antes
                                </th>
                                <th class="w-1/3 px-3 py-1.5 font-medium">
                                    Después
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="c in r.cambios"
                                :key="c.campo"
                                class="border-t align-top"
                            >
                                <td class="px-3 py-1.5 font-medium">
                                    {{ c.campo }}
                                </td>
                                <td
                                    class="px-3 py-1.5 break-all text-muted-foreground"
                                >
                                    {{ c.antes }}
                                </td>
                                <td class="px-3 py-1.5 break-all">
                                    {{ c.despues }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </li>
        </ul>

        <div
            v-if="registros.last_page > 1"
            class="flex items-center justify-between text-sm text-muted-foreground"
        >
            <span>
                Mostrando {{ registros.from }}–{{ registros.to }} de
                {{ registros.total }}
            </span>
            <div class="flex gap-2">
                <template
                    v-for="pagina in [
                        { texto: 'Anterior', url: registros.prev_page_url },
                        { texto: 'Siguiente', url: registros.next_page_url },
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
