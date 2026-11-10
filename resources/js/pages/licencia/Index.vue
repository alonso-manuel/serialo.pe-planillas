<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { CircleCheck, Info, KeyRound, TriangleAlert } from '@lucide/vue';
import { computed } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { show, update } from '@/routes/licencia';
import type { DatosLicencia, EstadoLicencia, UsoLicencia } from '@/types';

const props = defineProps<{
    estado: EstadoLicencia;
    licencia: DatosLicencia | null;
    uso: UsoLicencia[];
    dominio_actual: string;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Licencia', href: show() }],
    },
});

const form = useForm({ clave: '' });

function registrar(): void {
    form.put(update.url(), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
}

const tonoEstado = computed(() =>
    props.estado.solo_lectura
        ? 'border-red-200 bg-red-50 text-red-900 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200'
        : props.estado.tipo === 'prueba'
          ? 'border-sky-200 bg-sky-50 text-sky-900 dark:border-sky-900 dark:bg-sky-950/40 dark:text-sky-200'
          : 'border-green-200 bg-green-50 text-green-900 dark:border-green-900 dark:bg-green-950/40 dark:text-green-200',
);

/** Porcentaje usado (para la barra); sin límite no hay barra. */
function porcentaje(u: UsoLicencia): number {
    return u.limite === null
        ? 0
        : Math.min(100, Math.round((u.usados / u.limite) * 100));
}
</script>

<template>
    <Head title="Licencia" />

    <div class="flex max-w-4xl flex-1 flex-col gap-6 p-4 md:p-6">
        <Heading
            title="Licencia"
            description="Plan contratado, uso frente a sus límites y registro de una clave nueva."
        />

        <div
            class="flex items-start gap-3 rounded-lg border p-4 text-sm"
            :class="tonoEstado"
        >
            <TriangleAlert
                v-if="estado.solo_lectura"
                class="mt-0.5 size-4 shrink-0"
            />
            <Info
                v-else-if="estado.tipo === 'prueba'"
                class="mt-0.5 size-4 shrink-0"
            />
            <CircleCheck v-else class="mt-0.5 size-4 shrink-0" />
            <div class="grid gap-1">
                <p class="font-medium">
                    {{ estado.etiqueta }}
                    <span v-if="estado.solo_lectura"> · solo lectura</span>
                </p>
                <p>{{ estado.mensaje }}</p>
            </div>
        </div>

        <section v-if="licencia" class="rounded-lg border">
            <h2 class="border-b px-4 py-3 font-medium">Licencia registrada</h2>
            <dl class="grid gap-4 p-4 text-sm sm:grid-cols-2">
                <div>
                    <dt class="text-muted-foreground">Cliente</dt>
                    <dd class="font-medium">{{ licencia.cliente }}</dd>
                </div>
                <div>
                    <dt class="text-muted-foreground">Plan</dt>
                    <dd class="font-medium">{{ licencia.plan }}</dd>
                </div>
                <div>
                    <dt class="text-muted-foreground">Dominio</dt>
                    <dd class="font-medium break-all">
                        {{ licencia.dominio }}
                    </dd>
                </div>
                <div>
                    <dt class="text-muted-foreground">Emitida</dt>
                    <dd class="font-medium tabular-nums">
                        {{ licencia.emitida }}
                    </dd>
                </div>
                <div class="sm:col-span-2">
                    <dt class="text-muted-foreground">Actualizaciones</dt>
                    <dd class="flex flex-wrap items-center gap-2 font-medium">
                        <span class="tabular-nums"
                            >Hasta el {{ licencia.actualizaciones_hasta }}</span
                        >
                        <Badge
                            v-if="licencia.con_actualizaciones"
                            variant="outline"
                            class="border-green-300 text-green-800 dark:border-green-800 dark:text-green-300"
                            >Vigentes</Badge
                        >
                        <Badge v-else variant="outline">Vencidas</Badge>
                    </dd>
                    <dd
                        v-if="!licencia.con_actualizaciones"
                        class="mt-1 text-muted-foreground"
                    >
                        El sistema sigue funcionando completo; solo deja de
                        recibir versiones nuevas. Renueva el plan anual con
                        Serialo.pe para volver a recibirlas.
                    </dd>
                </div>
            </dl>
        </section>

        <section class="rounded-lg border">
            <h2 class="border-b px-4 py-3 font-medium">Uso del plan</h2>
            <ul class="grid gap-4 p-4">
                <li v-for="u in uso" :key="u.recurso" class="grid gap-1.5">
                    <div class="flex items-baseline justify-between text-sm">
                        <span>{{ u.etiqueta }}</span>
                        <span class="font-medium tabular-nums">
                            {{ u.usados }}
                            <span class="text-muted-foreground">
                                {{
                                    u.limite === null
                                        ? 'sin límite'
                                        : `de ${u.limite}`
                                }}</span
                            >
                        </span>
                    </div>
                    <div
                        v-if="u.limite !== null"
                        class="h-2 overflow-hidden rounded-full bg-muted"
                        role="progressbar"
                        :aria-label="u.etiqueta"
                        :aria-valuenow="u.usados"
                        :aria-valuemax="u.limite"
                    >
                        <div
                            class="h-full rounded-full"
                            :class="
                                u.usados > u.limite
                                    ? 'bg-red-600'
                                    : 'bg-primary'
                            "
                            :style="{ width: porcentaje(u) + '%' }"
                        />
                    </div>
                    <p
                        v-if="u.limite !== null && u.usados > u.limite"
                        class="text-xs text-red-700 dark:text-red-400"
                    >
                        Supera el límite: no se pueden agregar más hasta
                        desactivar alguno o ampliar el plan.
                    </p>
                </li>
            </ul>
            <p class="border-t px-4 py-3 text-xs text-muted-foreground">
                Solo cuenta lo activo: desactivar una empresa o un usuario, o
                registrar el cese de un trabajador, libera cupo.
            </p>
        </section>

        <section class="rounded-lg border">
            <h2 class="border-b px-4 py-3 font-medium">
                {{ licencia ? 'Cambiar la clave' : 'Registrar la licencia' }}
            </h2>
            <form class="grid gap-3 p-4" @submit.prevent="registrar">
                <Label for="clave">Clave de licencia</Label>
                <textarea
                    id="clave"
                    v-model="form.clave"
                    rows="4"
                    required
                    spellcheck="false"
                    autocomplete="off"
                    placeholder="SERIALO-…"
                    class="w-full rounded-md border border-input bg-transparent px-3 py-2 font-mono text-xs break-all"
                />
                <InputError :message="form.errors.clave" />
                <p class="text-xs text-muted-foreground">
                    Pega la clave que te envió Serialo.pe. Debe ser para el
                    dominio <b>{{ dominio_actual }}</b
                    >. Una clave nueva reemplaza a la anterior (renovación,
                    cambio de plan o de dominio).
                </p>
                <div>
                    <Button type="submit" :disabled="form.processing">
                        <Spinner v-if="form.processing" />
                        <KeyRound v-else />
                        Registrar clave
                    </Button>
                </div>
            </form>
        </section>
    </div>
</template>
