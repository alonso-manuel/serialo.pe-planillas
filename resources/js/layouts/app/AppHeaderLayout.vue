<script setup lang="ts">
import AppContent from '@/components/AppContent.vue';
import AppHeader from '@/components/AppHeader.vue';
import AppShell from '@/components/AppShell.vue';
import AvisoLicencia from '@/components/AvisoLicencia.vue';
import AvisoSoporte from '@/components/AvisoSoporte.vue';
import { Toaster } from '@/components/ui/sonner';
import VentanaOpinion from '@/components/VentanaOpinion.vue';
import type { BreadcrumbItem } from '@/types';

type Props = {
    breadcrumbs?: BreadcrumbItem[];
};

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});
</script>

<template>
    <AppShell variant="header">
        <AppHeader :breadcrumbs="breadcrumbs" />
        <AvisoSoporte />
        <AvisoLicencia />
        <AppContent variant="header">
            <slot />
        </AppContent>
        <VentanaOpinion v-if="$page.props.opinion?.habilitada" />
        <Toaster />
    </AppShell>
</template>
