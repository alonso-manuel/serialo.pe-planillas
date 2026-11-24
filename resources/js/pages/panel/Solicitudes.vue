<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Check, Mail, MessageCircle, Phone, RotateCcw } from '@lucide/vue';
import { computed } from 'vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { rutasPanel } from '@/lib/panel';

type Solicitud = {
    id: number;
    fecha: string;
    nombre: string;
    tipo: string;
    organizacion: string;
    celular: string;
    whatsapp: string | null;
    correo: string;
    empresas: string | null;
    mensaje: string | null;
    atendida: boolean;
};

const props = defineProps<{ solicitudes: Solicitud[] }>();

const pendientes = computed(
    () => props.solicitudes.filter((s) => !s.atendida).length,
);

function enlaceWhatsapp(s: Solicitud): string {
    const texto = `Hola, ${s.nombre.split(' ')[0]}. Te escribo de Serialo Planillas por tu solicitud de cuenta de prueba para ${s.organizacion}.`;

    return `https://wa.me/${s.whatsapp}?text=${encodeURIComponent(texto)}`;
}

function alternar(s: Solicitud): void {
    router.patch(
        rutasPanel.solicitudAtendida(s.id),
        {},
        { preserveScroll: true },
    );
}
</script>

<template>
    <Head title="Solicitudes" />

    <div class="flex flex-col gap-6">
        <Heading
            title="Solicitudes"
            description="Pedidos de cuenta de prueba desde la web. Al crear la cuenta, márcala como atendida."
        />

        <div class="flex flex-wrap gap-3 text-sm">
            <span class="rounded-md border bg-background px-3 py-1.5"
                ><b class="tabular-nums">{{ solicitudes.length }}</b>
                solicitudes</span
            >
            <span class="rounded-md border bg-background px-3 py-1.5"
                ><b class="tabular-nums">{{ pendientes }}</b> sin atender</span
            >
        </div>

        <p
            v-if="solicitudes.length === 0"
            class="rounded-lg border bg-background px-4 py-10 text-center text-sm text-muted-foreground"
        >
            Todavía no llegan solicitudes desde la web.
        </p>

        <article
            v-for="s in solicitudes"
            :key="s.id"
            class="rounded-lg border bg-background p-4"
            :class="s.atendida ? 'opacity-70' : 'border-primary/40'"
        >
            <header
                class="flex flex-wrap items-start justify-between gap-x-4 gap-y-2"
            >
                <div class="min-w-0">
                    <p class="font-medium">
                        {{ s.organizacion }}
                        <span class="font-normal text-muted-foreground"
                            >· {{ s.tipo }}</span
                        >
                    </p>
                    <p class="text-sm">
                        {{ s.nombre }}
                        <span class="text-muted-foreground"
                            >· {{ s.fecha }}</span
                        >
                    </p>
                    <p
                        class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-muted-foreground"
                    >
                        <span class="flex items-center gap-1"
                            ><Phone class="size-3" />{{ s.celular }}</span
                        >
                        <a
                            :href="`mailto:${s.correo}`"
                            class="flex items-center gap-1 hover:underline"
                            ><Mail class="size-3" />{{ s.correo }}</a
                        >
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <Button
                        v-if="s.whatsapp"
                        as="a"
                        :href="enlaceWhatsapp(s)"
                        target="_blank"
                        rel="noopener"
                        size="sm"
                        variant="outline"
                    >
                        <MessageCircle />
                        WhatsApp
                    </Button>
                    <Button size="sm" variant="ghost" @click="alternar(s)">
                        <RotateCcw v-if="s.atendida" />
                        <Check v-else />
                        {{
                            s.atendida ? 'Marcar pendiente' : 'Marcar atendida'
                        }}
                    </Button>
                </div>
            </header>
            <p v-if="s.empresas" class="mt-3 text-sm">
                <span class="text-muted-foreground">Maneja:</span>
                {{ s.empresas }}
            </p>
            <p v-if="s.mensaje" class="mt-2 text-sm whitespace-pre-line">
                {{ s.mensaje }}
            </p>
        </article>
    </div>
</template>
