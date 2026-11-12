<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import EnlacesLegales from '@/components/legal/EnlacesLegales.vue';
import { home } from '@/routes';
</script>

<template>
    <div class="min-h-svh bg-background text-foreground">
        <header class="border-b">
            <div
                class="mx-auto flex max-w-3xl items-center justify-between gap-4 px-4 py-4"
            >
                <Link
                    :href="home()"
                    class="flex items-center gap-2 font-medium"
                >
                    <AppLogoIcon class="size-7 fill-current" />
                    Serialo Planillas
                </Link>
                <Link
                    :href="home()"
                    class="flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground"
                >
                    <ArrowLeft class="size-4" /> Volver al sistema
                </Link>
            </div>
        </header>
        <main class="mx-auto max-w-3xl px-4 py-8">
            <slot />
        </main>
        <footer class="border-t py-6">
            <EnlacesLegales />
        </footer>
    </div>
</template>
