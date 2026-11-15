<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { BookOpen, Download, PlayCircle, Search } from '@lucide/vue';
import { computed, ref } from 'vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { index, manual as rutaManual } from '@/routes/guia';
import type { SeccionGuia, VideoGuia } from '@/types';

const props = defineProps<{
    secciones: SeccionGuia[];
    manual: boolean;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Guía', href: index() }],
    },
});

const busqueda = ref('');
const actual = ref<VideoGuia | null>(null);

const normalizar = (texto: string) =>
    texto
        .normalize('NFD')
        .replace(/[̀-ͯ]/g, '')
        .toLowerCase();

const visibles = computed(() => {
    const q = normalizar(busqueda.value.trim());
    if (q === '') {
        return props.secciones;
    }

    return props.secciones
        .map((s) => ({
            ...s,
            videos: s.videos.filter((v) =>
                normalizar(`${s.titulo} ${v.titulo} ${v.descripcion}`).includes(
                    q,
                ),
            ),
        }))
        .filter((s) => s.videos.length > 0);
});

const abierto = computed({
    get: () => actual.value !== null,
    set: (valor: boolean) => {
        if (!valor) {
            actual.value = null;
        }
    },
});
</script>

<template>
    <Head title="Guía" />

    <div class="flex max-w-6xl flex-1 flex-col gap-6 p-4 md:p-6">
        <Heading
            title="Guía del sistema"
            description="Videos cortos de cada módulo y el manual de usuario. Mira el que necesites, cuando lo necesites."
        />

        <section
            class="flex flex-col gap-4 rounded-xl border p-5 sm:flex-row sm:items-center sm:justify-between"
        >
            <div class="flex items-start gap-3">
                <BookOpen
                    class="mt-0.5 size-5 shrink-0 text-muted-foreground"
                />
                <div>
                    <h2 class="font-medium">Manual de usuario</h2>
                    <p class="text-sm text-muted-foreground">
                        Todo el sistema paso a paso, en un PDF para leer o
                        imprimir.
                    </p>
                </div>
            </div>
            <Button v-if="manual" as-child>
                <a :href="rutaManual().url" download>
                    <Download class="size-4" />
                    Descargar manual (PDF)
                </a>
            </Button>
            <Badge v-else variant="outline">Próximamente</Badge>
        </section>

        <div class="relative max-w-sm">
            <Search
                class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
            />
            <Input
                v-model="busqueda"
                class="pl-9"
                placeholder="Buscar un tema (p. ej. CTS, PLAME, boletas)"
                aria-label="Buscar en la guía"
            />
        </div>

        <p v-if="visibles.length === 0" class="text-sm text-muted-foreground">
            No hay videos sobre «{{ busqueda }}».
        </p>

        <section
            v-for="seccion in visibles"
            :key="seccion.titulo"
            class="grid gap-3"
        >
            <h2 class="text-sm font-medium text-muted-foreground">
                {{ seccion.titulo }}
            </h2>
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <button
                    v-for="video in seccion.videos"
                    :key="video.clave"
                    type="button"
                    class="group flex items-start gap-3 rounded-xl border p-4 text-left transition-colors enabled:hover:bg-muted/50 disabled:cursor-not-allowed disabled:opacity-60"
                    :disabled="!video.disponible"
                    @click="actual = video"
                >
                    <PlayCircle
                        class="mt-0.5 size-5 shrink-0 text-muted-foreground group-enabled:group-hover:text-primary"
                    />
                    <span class="grid gap-1">
                        <span class="font-medium">{{ video.titulo }}</span>
                        <span class="text-sm text-muted-foreground">
                            {{ video.descripcion }}
                        </span>
                        <Badge
                            v-if="!video.disponible"
                            variant="outline"
                            class="w-fit"
                        >
                            Próximamente
                        </Badge>
                    </span>
                </button>
            </div>
        </section>
    </div>

    <Dialog v-model:open="abierto">
        <DialogContent class="sm:max-w-4xl">
            <DialogHeader>
                <DialogTitle>{{ actual?.titulo }}</DialogTitle>
                <DialogDescription>{{ actual?.descripcion }}</DialogDescription>
            </DialogHeader>
            <video
                v-if="actual"
                :key="actual.clave"
                :src="actual.url"
                class="aspect-video w-full rounded-lg bg-black"
                controls
                autoplay
                playsinline
                preload="metadata"
            />
        </DialogContent>
    </Dialog>
</template>
