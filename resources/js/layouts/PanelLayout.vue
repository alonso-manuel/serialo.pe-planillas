<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { LogOut } from '@lucide/vue';
import { computed } from 'vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { Button } from '@/components/ui/button';
import { Toaster } from '@/components/ui/sonner';
import { rutasPanel } from '@/lib/panel';

/** Layout del panel central de Serialo.pe (superadmin, modo SaaS). */
const page = usePage();
const superadmin = computed(
    () => page.props.superadmin as { name: string; email: string } | null,
);

const enlaces = [
    { titulo: 'Cuentas', href: rutasPanel.cuentas },
    { titulo: 'Actividad', href: rutasPanel.actividad },
];

function activo(href: string): boolean {
    return page.url === href || page.url.startsWith(href + '/');
}

function salir(): void {
    router.post(rutasPanel.salir);
}
</script>

<template>
    <div class="min-h-svh bg-muted/30">
        <header class="border-b bg-background">
            <div
                class="mx-auto flex max-w-6xl flex-wrap items-center gap-x-6 gap-y-2 px-4 py-3 md:px-6"
            >
                <Link
                    :href="rutasPanel.cuentas"
                    class="flex items-center gap-2 font-semibold"
                >
                    <AppLogoIcon class="size-8" />
                    <span class="leading-tight">
                        Serialo Planillas
                        <span
                            class="block text-xs font-normal text-muted-foreground"
                            >Panel central</span
                        >
                    </span>
                </Link>
                <nav class="flex gap-1">
                    <Link
                        v-for="e in enlaces"
                        :key="e.href"
                        :href="e.href"
                        class="rounded-md px-3 py-1.5 text-sm font-medium transition-colors hover:bg-muted"
                        :class="
                            activo(e.href)
                                ? 'bg-muted text-foreground'
                                : 'text-muted-foreground'
                        "
                        >{{ e.titulo }}</Link
                    >
                </nav>
                <div class="ml-auto flex items-center gap-3">
                    <span
                        v-if="superadmin"
                        class="hidden text-sm text-muted-foreground sm:inline"
                        >{{ superadmin.name }}</span
                    >
                    <Button variant="ghost" size="sm" @click="salir">
                        <LogOut />
                        Salir
                    </Button>
                </div>
            </div>
        </header>
        <main class="mx-auto max-w-6xl px-4 py-6 md:px-6">
            <slot />
        </main>
        <Toaster />
    </div>
</template>
