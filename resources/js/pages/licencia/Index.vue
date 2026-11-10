<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import {
    CircleCheck,
    Info,
    KeyRound,
    LifeBuoy,
    TriangleAlert,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { show, update } from '@/routes/licencia';
import { autorizar, revocar } from '@/routes/soporte';
import type { DatosLicencia, EstadoLicencia, UsoLicencia } from '@/types';

const props = defineProps<{
    estado: EstadoLicencia;
    licencia: DatosLicencia | null;
    uso: UsoLicencia[];
    dominio_actual: string;
    saas: boolean;
    soporte: {
        vigente: boolean;
        vence: string | null;
        autorizado_por: string | null;
    } | null;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Licencia', href: show() }],
    },
});

const form = useForm({ clave: '' });
const page = usePage();
const procesandoSoporte = ref(false);

function cambiarSoporte(permitir: boolean): void {
    const opciones = {
        preserveScroll: true,
        onStart: () => (procesandoSoporte.value = true),
        onFinish: () => (procesandoSoporte.value = false),
    };

    if (permitir) {
        router.post(autorizar.url(), {}, opciones);
    } else {
        router.delete(revocar.url(), opciones);
    }
}

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

        <p
            v-if="saas"
            class="rounded-lg border bg-muted/30 p-4 text-sm text-muted-foreground"
        >
            Tu plan lo administra Serialo.pe. Para ampliarlo, renovarlo o
            cambiarlo, comunícate con nosotros.
        </p>

        <section v-if="soporte" class="rounded-lg border">
            <h2 class="flex items-center gap-2 border-b px-4 py-3 font-medium">
                <LifeBuoy class="size-4" />
                Acceso de soporte
            </h2>
            <div class="grid gap-3 p-4 text-sm">
                <p class="text-muted-foreground">
                    Si necesitas ayuda, permite que Serialo.pe entre a tu cuenta
                    por 24 horas como «Soporte Serialo.pe». Todo lo que haga
                    queda en la auditoría y puedes quitar el acceso cuando
                    quieras.
                </p>
                <p
                    v-if="soporte.vigente"
                    class="rounded-md border border-amber-200 bg-amber-50 p-3 text-amber-900 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-200"
                >
                    Acceso permitido hasta el {{ soporte.vence
                    }}<template v-if="soporte.autorizado_por">
                        (lo autorizó {{ soporte.autorizado_por }})</template
                    >.
                </p>
                <p v-else class="font-medium">
                    Serialo.pe no puede entrar a tu cuenta.
                </p>
                <div v-if="!page.props.auth.esSoporte">
                    <Button
                        v-if="soporte.vigente"
                        variant="outline"
                        :disabled="procesandoSoporte"
                        @click="cambiarSoporte(false)"
                    >
                        Quitar acceso
                    </Button>
                    <Button
                        v-else
                        :disabled="procesandoSoporte"
                        @click="cambiarSoporte(true)"
                    >
                        Permitir acceso por 24 horas
                    </Button>
                </div>
            </div>
        </section>

        <section v-if="!saas" class="rounded-lg border">
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
