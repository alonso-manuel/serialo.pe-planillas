<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Check, CircleAlert, Clock, Info, Mail, RotateCw } from '@lucide/vue';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import { correo as enviarTodas } from '@/routes/periodos/boletas';
import { correo as reenviar } from '@/routes/periodos/lineas/boleta';
import type { EnviosBoletas } from '@/types';

const props = defineProps<{
    envios: EnviosBoletas;
    periodoId: number;
    mes: string;
}>();

const dialogo = ref(false);
const enviando = ref(false);
const reenviando = ref<number | null>(null);

/** Quedan por enviar: los que tienen correo y su último envío no salió. */
const porEnviar = computed(
    () =>
        props.envios.filas.filter((f) => f.email && f.estado !== 'enviado')
            .length,
);

function enviar(): void {
    enviando.value = true;
    router.post(
        enviarTodas.url(props.periodoId),
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                enviando.value = false;
                dialogo.value = false;
            },
        },
    );
}

function reenviarA(lineaId: number): void {
    reenviando.value = lineaId;
    router.post(
        reenviar.url([props.periodoId, lineaId]),
        {},
        { preserveScroll: true, onFinish: () => (reenviando.value = null) },
    );
}

/* Mientras haya envíos pendientes, se consulta el avance cada 5 s. */
let temporizador: ReturnType<typeof setInterval> | null = null;

watch(
    () => props.envios.pendientes,
    (pendientes) => {
        if (pendientes > 0 && temporizador === null) {
            temporizador = setInterval(
                () => router.reload({ only: ['envios'] }),
                5000,
            );
        } else if (pendientes === 0 && temporizador !== null) {
            clearInterval(temporizador);
            temporizador = null;
        }
    },
    { immediate: true },
);

onBeforeUnmount(() => {
    if (temporizador !== null) {
        clearInterval(temporizador);
    }
});
</script>

<template>
    <section class="grid gap-3 rounded-lg border p-4">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="flex items-center gap-2 font-medium">
                    <Mail class="size-4" />
                    Boletas por correo
                </h2>
                <p class="text-sm text-muted-foreground">
                    Cada boleta va en PDF protegido: la clave es el número de
                    documento del trabajador.
                </p>
            </div>
            <Button
                :disabled="!envios.puede_enviar || porEnviar === 0"
                :title="
                    envios.puede_enviar
                        ? undefined
                        : 'Disponible cuando el periodo esté cerrado'
                "
                @click="dialogo = true"
            >
                <Mail />
                Enviar boletas por correo
            </Button>
        </div>

        <p
            v-if="!envios.puede_enviar"
            class="flex items-start gap-2 rounded-md bg-muted/50 p-3 text-xs text-muted-foreground"
        >
            <Info class="mt-0.5 size-3.5 shrink-0" />
            Las boletas se envían cuando el periodo está cerrado, para que nunca
            salga una versión «BORRADOR» o con montos por revisar.
        </p>

        <div
            v-if="envios.enviados + envios.pendientes + envios.fallidos > 0"
            class="flex flex-wrap gap-4 text-sm"
        >
            <span class="flex items-center gap-1.5">
                <Check class="size-4 text-green-600" />
                {{ envios.enviados }} enviadas
            </span>
            <span v-if="envios.pendientes" class="flex items-center gap-1.5">
                <Spinner class="size-4" />
                {{ envios.pendientes }} en cola
            </span>
            <span
                v-if="envios.fallidos"
                class="flex items-center gap-1.5 text-red-700 dark:text-red-400"
            >
                <CircleAlert class="size-4" />
                {{ envios.fallidos }} con error
            </span>
        </div>

        <div class="overflow-x-auto rounded-md border">
            <table class="w-full min-w-[640px] text-sm">
                <thead class="bg-muted/40 text-xs text-muted-foreground">
                    <tr>
                        <th class="px-3 py-2 text-left font-medium">
                            Trabajador
                        </th>
                        <th class="px-3 py-2 text-left font-medium">Correo</th>
                        <th class="px-3 py-2 text-left font-medium">Estado</th>
                        <th class="w-28 px-3 py-2" />
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="f in envios.filas"
                        :key="f.linea_id"
                        class="border-t"
                    >
                        <td class="px-3 py-2">{{ f.nombre }}</td>
                        <td class="px-3 py-2">
                            <span v-if="f.email">{{ f.email }}</span>
                            <span v-else class="text-xs text-muted-foreground"
                                >Sin correo en su ficha</span
                            >
                        </td>
                        <td class="px-3 py-2">
                            <span
                                v-if="f.estado === 'enviado'"
                                class="flex items-center gap-1.5 text-green-700 dark:text-green-400"
                            >
                                <Check class="size-4" />
                                Enviada {{ f.enviado_en }}
                            </span>
                            <span
                                v-else-if="f.estado === 'pendiente'"
                                class="flex items-center gap-1.5 text-muted-foreground"
                            >
                                <Clock class="size-4" />
                                En cola
                            </span>
                            <span
                                v-else-if="f.estado === 'fallido'"
                                class="flex items-center gap-1.5 text-red-700 dark:text-red-400"
                                :title="f.error ?? undefined"
                            >
                                <CircleAlert class="size-4 shrink-0" />
                                <span class="truncate">{{
                                    f.error ?? 'Error al enviar'
                                }}</span>
                            </span>
                            <span v-else class="text-xs text-muted-foreground"
                                >Sin enviar</span
                            >
                        </td>
                        <td class="px-3 py-2 text-right">
                            <Button
                                v-if="
                                    envios.puede_enviar &&
                                    f.email &&
                                    (f.estado === 'enviado' ||
                                        f.estado === 'fallido')
                                "
                                variant="ghost"
                                size="sm"
                                :disabled="reenviando === f.linea_id"
                                @click="reenviarA(f.linea_id)"
                            >
                                <RotateCw
                                    :class="{
                                        'animate-spin':
                                            reenviando === f.linea_id,
                                    }"
                                />
                                Reenviar
                            </Button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>

    <Dialog v-model:open="dialogo">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Enviar boletas de {{ mes }}</DialogTitle>
                <DialogDescription>
                    Se enviarán {{ porEnviar }}
                    {{ porEnviar === 1 ? 'boleta' : 'boletas' }} en segundo
                    plano, respetando el límite de correos del servidor. Quien
                    ya la recibió no la vuelve a recibir; para eso usa
                    «Reenviar».
                </DialogDescription>
            </DialogHeader>
            <p
                v-if="envios.sin_correo.length"
                class="flex items-start gap-2 rounded-md border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-300"
            >
                <Info class="mt-0.5 size-4 shrink-0" />
                <span>
                    Sin correo en su ficha (no se les enviará):
                    {{ envios.sin_correo.join(', ') }}.
                </span>
            </p>
            <DialogFooter>
                <DialogClose as-child>
                    <Button variant="outline">Cancelar</Button>
                </DialogClose>
                <Button :disabled="enviando" @click="enviar">
                    <Spinner v-if="enviando" />
                    <Mail v-else />
                    Enviar
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
