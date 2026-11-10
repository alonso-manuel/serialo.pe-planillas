<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { rutasPanel } from '@/lib/panel';

defineProps<{ codigos: string[] }>();

defineOptions({
    layout: {
        title: 'Doble factor activado',
        description:
            'Guarda estos códigos en un lugar seguro: sirven para ingresar si pierdes el teléfono. No se volverán a mostrar.',
    },
});
</script>

<template>
    <Head title="Códigos de recuperación" />

    <div class="space-y-5">
        <ul
            class="grid grid-cols-2 gap-2 rounded-lg border bg-muted/40 p-4 font-mono text-sm"
        >
            <li v-for="c in codigos" :key="c">{{ c }}</li>
        </ul>
        <p class="text-xs text-muted-foreground">
            Cada código se puede usar una sola vez.
        </p>
        <Button as-child class="w-full">
            <Link :href="rutasPanel.cuentas">Ya los guardé, continuar</Link>
        </Button>
    </div>
</template>
