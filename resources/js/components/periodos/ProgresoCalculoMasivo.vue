<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Check, CircleAlert, CircleMinus, Loader2, X } from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { show } from '@/routes/calculos';
import { index } from '@/routes/periodos';
import type { CalculoMasivoEstado } from '@/types';

const props = defineProps<{ calculo: string }>();

const estado = ref<CalculoMasivoEstado | null>(null);
const error = ref<string | null>(null);
let temporizador: ReturnType<typeof setTimeout> | null = null;

async function consultar(): Promise<void> {
    try {
        const respuesta = await fetch(show.url(props.calculo), {
            headers: { Accept: 'application/json' },
        });

        if (!respuesta.ok) {
            error.value =
                respuesta.status === 404
                    ? 'El cálculo ya no está disponible.'
                    : 'No se pudo consultar el avance.';

            return;
        }

        estado.value = (await respuesta.json()) as CalculoMasivoEstado;

        if (estado.value.terminado) {
            // Refresca el listado de periodos (netos de la empresa activa).
            router.reload({ only: ['periodos'] });

            return;
        }
    } catch {
        error.value = 'No se pudo consultar el avance.';

        return;
    }

    temporizador = setTimeout(consultar, 2000);
}

onMounted(consultar);
onBeforeUnmount(() => {
    if (temporizador) {
        clearTimeout(temporizador);
    }
});

const avance = computed(() =>
    estado.value && estado.value.total > 0
        ? Math.round(
              ((estado.value.total - estado.value.pendientes) /
                  estado.value.total) *
                  100,
          )
        : 0,
);

const soles = new Intl.NumberFormat('es-PE', {
    style: 'currency',
    currency: 'PEN',
});

function cerrar(): void {
    router.get(index.url(), {}, { preserveScroll: true });
}
</script>

<template>
    <section class="grid gap-3 rounded-lg border p-4" aria-live="polite">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h2 class="font-medium">
                    Cálculo de todas las empresas
                    <template v-if="estado">· {{ estado.mes }}</template>
                </h2>
                <p class="text-sm text-muted-foreground">
                    <template v-if="error">{{ error }}</template>
                    <template v-else-if="!estado">Consultando…</template>
                    <template v-else-if="estado.terminado">Terminado.</template>
                    <template v-else>
                        {{ estado.total - estado.pendientes }} de
                        {{ estado.total }} empresas. Se procesa en segundo
                        plano: puedes seguir trabajando.
                    </template>
                </p>
            </div>
            <Button
                variant="ghost"
                size="icon"
                aria-label="Cerrar avance"
                @click="cerrar"
            >
                <X />
            </Button>
        </div>

        <div
            v-if="estado"
            class="h-2 overflow-hidden rounded-full bg-muted"
            role="progressbar"
            :aria-valuenow="avance"
            aria-valuemin="0"
            aria-valuemax="100"
        >
            <div
                class="h-full bg-primary transition-all"
                :style="{ width: `${avance}%` }"
            />
        </div>

        <ul v-if="estado" class="divide-y rounded-md border text-sm">
            <li
                v-for="e in estado.empresas"
                :key="e.empresa"
                class="flex items-start gap-3 px-3 py-2"
            >
                <Loader2
                    v-if="e.estado === 'pendiente'"
                    class="mt-0.5 size-4 shrink-0 animate-spin text-muted-foreground"
                />
                <Check
                    v-else-if="e.estado === 'calculado'"
                    class="mt-0.5 size-4 shrink-0 text-green-600"
                />
                <CircleAlert
                    v-else-if="e.estado === 'con_problemas'"
                    class="mt-0.5 size-4 shrink-0 text-red-600"
                />
                <CircleMinus
                    v-else
                    class="mt-0.5 size-4 shrink-0 text-muted-foreground"
                />

                <div class="min-w-0 flex-1">
                    <p class="font-medium">{{ e.empresa }}</p>
                    <p v-if="e.detalle" class="text-xs text-muted-foreground">
                        {{ e.detalle }}
                    </p>
                    <ul
                        v-if="e.problemas?.length"
                        class="mt-1 ml-4 list-disc text-xs text-red-700 dark:text-red-400"
                    >
                        <li v-for="p in e.problemas" :key="p">{{ p }}</li>
                    </ul>
                </div>

                <div
                    v-if="e.estado === 'calculado'"
                    class="text-right text-xs tabular-nums"
                >
                    {{ e.trabajadores }}
                    {{ e.trabajadores === 1 ? 'trabajador' : 'trabajadores' }}
                    <br />
                    <span class="text-sm font-medium">{{
                        soles.format(Number(e.neto))
                    }}</span>
                </div>
            </li>
        </ul>
    </section>
</template>
