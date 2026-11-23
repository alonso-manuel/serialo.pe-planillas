<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Check, Mail, RotateCcw } from '@lucide/vue';
import { computed } from 'vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { rutasPanel } from '@/lib/panel';

type Opinion = {
    id: number;
    fecha: string;
    cuenta: string;
    subdominio: string | null;
    usuario: string;
    correo: string;
    rol: string;
    calificacion: number | null;
    comentario: string;
    leida: boolean;
};

const props = defineProps<{
    opiniones: Opinion[];
    promedio: number | null;
}>();

const etiquetas: Record<number, string> = {
    1: 'Nada útil',
    2: 'Poco útil',
    3: 'Regular',
    4: 'Útil',
    5: 'Muy útil',
};

const sinLeer = computed(() => props.opiniones.filter((o) => !o.leida).length);

function alternarLeida(o: Opinion): void {
    router.patch(
        rutasPanel.comentarioLeido(o.id),
        {},
        { preserveScroll: true },
    );
}
</script>

<template>
    <Head title="Comentarios" />

    <div class="flex flex-col gap-6">
        <Heading
            title="Comentarios"
            description="Lo que los usuarios de cada cuenta opinan del sistema: qué les sirve, qué falla y qué le falta."
        />

        <div class="flex flex-wrap gap-3 text-sm">
            <span class="rounded-md border bg-background px-3 py-1.5"
                ><b class="tabular-nums">{{ opiniones.length }}</b>
                comentarios</span
            >
            <span class="rounded-md border bg-background px-3 py-1.5"
                ><b class="tabular-nums">{{ sinLeer }}</b> sin leer</span
            >
            <span
                v-if="promedio !== null"
                class="rounded-md border bg-background px-3 py-1.5"
                >Utilidad promedio <b class="tabular-nums">{{ promedio }}</b> de
                5</span
            >
        </div>

        <p
            v-if="opiniones.length === 0"
            class="rounded-lg border bg-background px-4 py-10 text-center text-sm text-muted-foreground"
        >
            Todavía no llegan comentarios. A cada usuario se le pide una sola
            vez, después de unos días de uso.
        </p>

        <article
            v-for="o in opiniones"
            :key="o.id"
            class="rounded-lg border bg-background p-4"
            :class="o.leida ? 'opacity-70' : 'border-primary/40'"
        >
            <header
                class="flex flex-wrap items-start justify-between gap-x-4 gap-y-2"
            >
                <div class="min-w-0">
                    <p class="font-medium">
                        {{ o.usuario }}
                        <span class="font-normal text-muted-foreground"
                            >· {{ o.rol }} · {{ o.cuenta }}</span
                        >
                        <span
                            v-if="o.subdominio"
                            class="font-normal text-muted-foreground"
                            >({{ o.subdominio }})</span
                        >
                        <span v-else class="font-normal text-muted-foreground"
                            >(cuenta eliminada)</span
                        >
                    </p>
                    <p
                        class="flex items-center gap-1 text-xs text-muted-foreground"
                    >
                        <Mail class="size-3" />
                        <a
                            :href="`mailto:${o.correo}`"
                            class="hover:underline"
                            >{{ o.correo }}</a
                        >
                        · {{ o.fecha }}
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <span
                        v-if="o.calificacion"
                        class="rounded-full border px-2.5 py-0.5 text-xs font-medium"
                        >{{ o.calificacion }}/5 ·
                        {{ etiquetas[o.calificacion] }}</span
                    >
                    <Button size="sm" variant="ghost" @click="alternarLeida(o)">
                        <RotateCcw v-if="o.leida" />
                        <Check v-else />
                        {{ o.leida ? 'Marcar sin leer' : 'Marcar leído' }}
                    </Button>
                </div>
            </header>
            <p class="mt-3 text-sm whitespace-pre-line">{{ o.comentario }}</p>
        </article>
    </div>
</template>
