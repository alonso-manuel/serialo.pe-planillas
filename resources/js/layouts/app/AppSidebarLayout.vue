<script setup lang="ts">
import EnlacesLegales from '@/components/legal/EnlacesLegales.vue';
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import AppSidebarHeader from '@/components/AppSidebarHeader.vue';
import AvisoLicencia from '@/components/AvisoLicencia.vue';
import AvisoSoporte from '@/components/AvisoSoporte.vue';
import { Toaster } from '@/components/ui/sonner';
import type { BreadcrumbItem } from '@/types';

type Props = {
    breadcrumbs?: BreadcrumbItem[];
};

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});
</script>

<template>
    <AppShell variant="sidebar">
        <AppSidebar />
        <AppContent variant="sidebar" class="min-w-0 overflow-x-clip">
            <AppSidebarHeader :breadcrumbs="breadcrumbs" />
            <AvisoSoporte />
            <AvisoLicencia />
            <slot />
            <footer class="mt-auto border-t px-4 py-3 md:px-6">
                <EnlacesLegales />
            </footer>
        </AppContent>
        <Toaster />
    </AppShell>
</template>
