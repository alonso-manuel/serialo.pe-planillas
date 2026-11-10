<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';

defineProps<{
    registros: {
        id: number;
        fecha: string;
        accion: string;
        descripcion: string;
        cuenta: string | null;
        quien: string | null;
        ip: string | null;
    }[];
}>();
</script>

<template>
    <Head title="Actividad" />

    <div class="flex flex-col gap-6">
        <Heading
            title="Actividad"
            description="Lo que se hizo en el panel central (últimas 200 acciones). No se puede editar ni borrar."
        />

        <div class="overflow-x-auto rounded-lg border bg-background">
            <table class="w-full min-w-[720px] text-sm">
                <thead class="bg-muted/40 text-xs text-muted-foreground">
                    <tr>
                        <th class="px-4 py-2 text-left font-medium">Fecha</th>
                        <th class="px-4 py-2 text-left font-medium">Acción</th>
                        <th class="px-4 py-2 text-left font-medium">Detalle</th>
                        <th class="px-4 py-2 text-left font-medium">Cuenta</th>
                        <th class="px-4 py-2 text-left font-medium">Quién</th>
                        <th class="px-4 py-2 text-left font-medium">IP</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="registros.length === 0">
                        <td
                            colspan="6"
                            class="px-4 py-8 text-center text-muted-foreground"
                        >
                            Sin actividad todavía.
                        </td>
                    </tr>
                    <tr v-for="r in registros" :key="r.id" class="border-t">
                        <td class="px-4 py-2 whitespace-nowrap tabular-nums">
                            {{ r.fecha }}
                        </td>
                        <td class="px-4 py-2 font-medium">{{ r.accion }}</td>
                        <td class="px-4 py-2">{{ r.descripcion }}</td>
                        <td class="px-4 py-2 text-muted-foreground">
                            {{ r.cuenta ?? '—' }}
                        </td>
                        <td class="px-4 py-2 text-muted-foreground">
                            {{ r.quien ?? '—' }}
                        </td>
                        <td
                            class="px-4 py-2 text-muted-foreground tabular-nums"
                        >
                            {{ r.ip ?? '—' }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
