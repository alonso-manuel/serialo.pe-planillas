<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { DatabaseBackup, Download, Info } from '@lucide/vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { descargar, index, store } from '@/routes/respaldos';

defineProps<{
    respaldos: { archivo: string; tamano: string; fecha: string }[];
    dias: number;
    hora: string;
    externo: boolean;
    saas: boolean;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Respaldos', href: index() }],
    },
});

const form = useForm({});

function crear(): void {
    form.post(store.url(), { preserveScroll: true });
}
</script>

<template>
    <Head title="Respaldos" />

    <div class="flex max-w-4xl flex-1 flex-col gap-6 p-4 md:p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading
                title="Respaldos"
                description="Copias completas de la base de datos: empresas, trabajadores, planillas y auditoría."
            />
            <Button :disabled="form.processing" @click="crear">
                <Spinner v-if="form.processing" />
                <DatabaseBackup v-else />
                Crear respaldo ahora
            </Button>
        </div>

        <div
            class="flex items-start gap-3 rounded-lg border bg-muted/30 p-4 text-sm"
        >
            <Info class="mt-0.5 size-4 shrink-0 text-muted-foreground" />
            <div class="grid gap-1 text-muted-foreground">
                <p>
                    Se crea uno automáticamente cada día a las {{ hora }} y se
                    conservan los de los últimos {{ dias }} días.
                    <template v-if="saas">
                        Serialo.pe guarda además una copia fuera del servidor.
                    </template>
                    <template v-else-if="externo">
                        Cada respaldo se copia también fuera del servidor.
                    </template>
                    <template v-else>
                        Están en el mismo servidor: descarga uno cada semana y
                        guárdalo en otro lugar.
                    </template>
                </p>
                <p>
                    El archivo contiene datos personales de todos los
                    trabajadores: guárdalo en un lugar seguro. Cada descarga
                    queda registrada en la auditoría.
                </p>
            </div>
        </div>

        <section class="rounded-lg border">
            <h2 class="border-b px-4 py-3 font-medium">
                Respaldos disponibles
            </h2>
            <p
                v-if="respaldos.length === 0"
                class="p-4 text-sm text-muted-foreground"
            >
                Todavía no hay respaldos. El primero se creará esta noche, o
                créalo ahora con el botón de arriba.
            </p>
            <ul v-else class="divide-y">
                <li
                    v-for="r in respaldos"
                    :key="r.archivo"
                    class="flex flex-wrap items-center justify-between gap-3 px-4 py-3 text-sm"
                >
                    <div class="grid gap-0.5">
                        <span class="font-medium tabular-nums">{{
                            r.fecha
                        }}</span>
                        <span class="text-xs break-all text-muted-foreground"
                            >{{ r.archivo }} · {{ r.tamano }}</span
                        >
                    </div>
                    <Button as-child variant="outline" size="sm">
                        <a :href="descargar.url(r.archivo)" download>
                            <Download />
                            Descargar
                        </a>
                    </Button>
                </li>
            </ul>
        </section>
    </div>
</template>
