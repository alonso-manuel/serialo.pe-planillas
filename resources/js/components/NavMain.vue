<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuBadge,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import type { GrupoNav } from '@/types';

defineProps<{
    grupos: GrupoNav[];
}>();

const { isCurrentOrParentUrl } = useCurrentUrl();
</script>

<template>
    <SidebarGroup v-for="grupo in grupos" :key="grupo.title" class="py-0">
        <SidebarGroupLabel>{{ grupo.title }}</SidebarGroupLabel>
        <SidebarMenu>
            <SidebarMenuItem v-for="item in grupo.items" :key="item.title">
                <SidebarMenuButton
                    v-if="item.href"
                    as-child
                    :is-active="isCurrentOrParentUrl(item.href)"
                    :tooltip="item.title"
                >
                    <Link :href="item.href">
                        <component :is="item.icon" />
                        <span>{{ item.title }}</span>
                    </Link>
                </SidebarMenuButton>
                <template v-else>
                    <SidebarMenuButton
                        disabled
                        aria-disabled="true"
                        :tooltip="`${item.title} (pronto)`"
                        class="cursor-not-allowed"
                    >
                        <component :is="item.icon" />
                        <span>{{ item.title }}</span>
                    </SidebarMenuButton>
                    <SidebarMenuBadge
                        class="text-[10px] font-normal text-muted-foreground"
                    >
                        Pronto
                    </SidebarMenuBadge>
                </template>
            </SidebarMenuItem>
        </SidebarMenu>
    </SidebarGroup>
</template>
