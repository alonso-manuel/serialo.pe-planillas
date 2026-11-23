<script setup lang="ts">
import { router, useForm, usePage } from '@inertiajs/vue3';
import { MessageSquareHeart, Send } from '@lucide/vue';
import { onBeforeUnmount, onMounted } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { ventanaOpinionAbierta as abierta } from '@/lib/opinion';

/**
 * Pide al usuario su comentario sobre el sistema (modo SaaS). Se abre sola UNA vez: al abrirse se marca en el
 * servidor y no vuelve a aparecer aunque no responda. Luego se abre desde el menú del usuario.
 */
const page = usePage();

const calificaciones = [
    { valor: 1, texto: 'Nada útil' },
    { valor: 2, texto: 'Poco útil' },
    { valor: 3, texto: 'Regular' },
    { valor: 4, texto: 'Útil' },
    { valor: 5, texto: 'Muy útil' },
];

const form = useForm<{ calificacion: number | null; comentario: string }>({
    calificacion: null,
    comentario: '',
});

let temporizador: ReturnType<typeof setTimeout> | undefined;

onMounted(() => {
    if (!page.props.opinion?.pedir) {
        return;
    }
    // Unos segundos después de entrar, para no tapar la página apenas carga.
    temporizador = setTimeout(() => {
        abierta.value = true;
        router.post(
            '/opinion/mostrada',
            {},
            { preserveScroll: true, preserveState: true, only: ['opinion'] },
        );
    }, 2500);
});

onBeforeUnmount(() => clearTimeout(temporizador));

function enviar(): void {
    form.post('/opinion', {
        preserveScroll: true,
        onSuccess: () => {
            abierta.value = false;
            form.reset();
        },
    });
}
</script>

<template>
    <Dialog v-model:open="abierta">
        <DialogContent class="max-h-[92svh] overflow-y-auto sm:max-w-lg">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2">
                    <MessageSquareHeart class="size-5 text-primary" />
                    ¿Qué tal te va con Serialo Planillas?
                </DialogTitle>
                <DialogDescription class="space-y-2 text-left">
                    <span class="block">
                        Quiero saber cómo te va usando el sistema: si te resulta
                        útil, en qué sientes que falla o va lento, o qué crees
                        que le falta. Tu comentario me ayuda bastante a
                        mejorarlo, para ti y para todos los que lo usan.
                    </span>
                    <span class="block">
                        De antemano, gracias por usar Serialo Planillas. Espero
                        que te esté gustando y que te ahorre tiempo en tus
                        planillas. ¡Bendiciones!
                    </span>
                    <span class="block text-xs">— Alonso, Serialo.pe</span>
                </DialogDescription>
            </DialogHeader>

            <form class="grid gap-4" @submit.prevent="enviar">
                <fieldset class="grid gap-2">
                    <legend class="mb-1 text-sm font-medium">
                        ¿Qué tan útil te resulta?
                        <span class="font-normal text-muted-foreground"
                            >(opcional)</span
                        >
                    </legend>
                    <div class="grid grid-cols-5 gap-1.5">
                        <button
                            v-for="c in calificaciones"
                            :key="c.valor"
                            type="button"
                            class="flex flex-col items-center gap-0.5 rounded-md border px-1 py-2 text-xs transition-colors hover:bg-muted"
                            :class="
                                form.calificacion === c.valor
                                    ? 'border-primary bg-primary/10 font-medium text-foreground'
                                    : 'text-muted-foreground'
                            "
                            :aria-pressed="form.calificacion === c.valor"
                            @click="
                                form.calificacion =
                                    form.calificacion === c.valor
                                        ? null
                                        : c.valor
                            "
                        >
                            <span class="text-base font-semibold">{{
                                c.valor
                            }}</span>
                            {{ c.texto }}
                        </button>
                    </div>
                </fieldset>

                <div class="grid gap-2">
                    <Label for="opinion-comentario">Tu comentario</Label>
                    <textarea
                        id="opinion-comentario"
                        v-model="form.comentario"
                        rows="5"
                        maxlength="3000"
                        class="min-h-28 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                        placeholder="Ej.: me ahorra tiempo con las boletas, pero el cálculo va lento cuando… Me gustaría que el sistema…"
                    />
                    <InputError :message="form.errors.comentario" />
                </div>

                <p class="text-xs text-muted-foreground">
                    Esta ventana no volverá a aparecer sola. Si más adelante
                    quieres escribirme, entra al menú de tu usuario → «Enviar
                    comentario».
                </p>

                <DialogFooter class="gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        @click="abierta = false"
                        >Ahora no</Button
                    >
                    <Button type="submit" :disabled="form.processing">
                        <Spinner v-if="form.processing" />
                        <Send v-else />
                        Enviar comentario
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
