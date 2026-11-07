<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { Building, Check, ChevronsUpDown, Search } from '@lucide/vue';
import { computed, ref } from 'vue';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import {
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    useSidebar,
} from '@/components/ui/sidebar';
import { update } from '@/routes/empresa-activa';
import type { EmpresaResumen } from '@/types';

/** A partir de cuántas empresas se muestra el buscador. */
const MINIMO_PARA_BUSCAR = 7;

const page = usePage();
const { isMobile, state } = useSidebar();

const empresaActiva = computed(() => page.props.empresaActiva);
const empresas = computed(() => page.props.empresas);

const busqueda = ref('');
const cambiando = ref(false);

const filtradas = computed(() => {
    const texto = busqueda.value.trim().toLowerCase();

    if (texto === '') {
        return empresas.value;
    }

    return empresas.value.filter(
        (e) =>
            e.razon_social.toLowerCase().includes(texto) ||
            e.ruc.includes(texto),
    );
});

function activar(empresa: EmpresaResumen): void {
    if (empresa.id === empresaActiva.value?.id) {
        return;
    }

    router.visit(update(), {
        data: { empresa_id: empresa.id },
        preserveScroll: true,
        onStart: () => (cambiando.value = true),
        onFinish: () => (cambiando.value = false),
    });
}

function alAbrir(abierto: boolean): void {
    if (!abierto) {
        busqueda.value = '';
    }
}
</script>

<template>
    <SidebarMenu>
        <SidebarMenuItem>
            <DropdownMenu @update:open="alAbrir">
                <DropdownMenuTrigger as-child :disabled="empresas.length === 0">
                    <SidebarMenuButton
                        size="lg"
                        class="data-[state=open]:bg-sidebar-accent data-[state=open]:text-sidebar-accent-foreground"
                        :tooltip="empresaActiva?.razon_social ?? 'Sin empresas'"
                        data-test="empresa-selector"
                    >
                        <div
                            class="flex aspect-square size-8 shrink-0 items-center justify-center rounded-md border border-sidebar-border bg-sidebar"
                        >
                            <Building class="size-4" />
                        </div>
                        <div
                            v-if="empresaActiva"
                            class="grid flex-1 text-left text-sm leading-tight"
                            :class="{ 'opacity-60': cambiando }"
                        >
                            <span class="truncate font-medium">
                                {{ empresaActiva.razon_social }}
                            </span>
                            <span
                                class="truncate text-xs text-muted-foreground"
                            >
                                RUC {{ empresaActiva.ruc }}
                            </span>
                        </div>
                        <div
                            v-else
                            class="grid flex-1 text-left text-sm leading-tight text-muted-foreground"
                        >
                            <span class="truncate">Sin empresas asignadas</span>
                        </div>
                        <ChevronsUpDown
                            v-if="empresas.length > 0"
                            class="ml-auto size-4"
                        />
                    </SidebarMenuButton>
                </DropdownMenuTrigger>

                <DropdownMenuContent
                    class="w-(--reka-dropdown-menu-trigger-width) min-w-64 rounded-lg"
                    :side="
                        isMobile
                            ? 'bottom'
                            : state === 'collapsed'
                              ? 'right'
                              : 'bottom'
                    "
                    align="start"
                    :side-offset="4"
                >
                    <DropdownMenuLabel class="text-xs text-muted-foreground">
                        Empresa activa
                    </DropdownMenuLabel>

                    <div
                        v-if="empresas.length >= MINIMO_PARA_BUSCAR"
                        class="relative px-1 pb-1"
                    >
                        <Search
                            class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                        />
                        <Input
                            v-model="busqueda"
                            placeholder="Buscar por razón social o RUC"
                            class="h-8 pl-8"
                            aria-label="Buscar empresa"
                            @keydown.stop
                        />
                    </div>
                    <DropdownMenuSeparator />

                    <div class="max-h-80 overflow-y-auto">
                        <DropdownMenuItem
                            v-for="empresa in filtradas"
                            :key="empresa.id"
                            class="gap-2"
                            @select="activar(empresa)"
                        >
                            <div class="grid flex-1 leading-tight">
                                <span class="truncate">
                                    {{ empresa.razon_social }}
                                </span>
                                <span class="text-xs text-muted-foreground">
                                    RUC {{ empresa.ruc }}
                                </span>
                            </div>
                            <Check
                                v-if="empresa.id === empresaActiva?.id"
                                class="size-4"
                            />
                        </DropdownMenuItem>
                        <p
                            v-if="filtradas.length === 0"
                            class="px-2 py-3 text-center text-sm text-muted-foreground"
                        >
                            Ninguna empresa coincide.
                        </p>
                    </div>
                </DropdownMenuContent>
            </DropdownMenu>
        </SidebarMenuItem>
    </SidebarMenu>
</template>
