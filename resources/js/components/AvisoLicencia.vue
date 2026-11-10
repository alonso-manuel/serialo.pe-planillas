<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { Clock, Lock } from '@lucide/vue';
import { computed } from 'vue';
import { show as licencia } from '@/routes/licencia';

/** Franja superior: modo solo lectura o días de prueba que quedan. */
const page = usePage();
const aviso = computed(() => page.props.avisoLicencia);
const esAdmin = computed(() => page.props.auth.esAdmin);
</script>

<template>
    <div
        v-if="aviso"
        role="status"
        class="flex flex-wrap items-center gap-x-3 gap-y-1 border-b px-4 py-2 text-sm md:px-6"
        :class="
            aviso.solo_lectura
                ? 'border-red-200 bg-red-50 text-red-900 dark:border-red-900 dark:bg-red-950/50 dark:text-red-200'
                : 'border-sky-200 bg-sky-50 text-sky-900 dark:border-sky-900 dark:bg-sky-950/50 dark:text-sky-200'
        "
    >
        <span class="flex items-center gap-2 font-medium">
            <Lock v-if="aviso.solo_lectura" class="size-4 shrink-0" />
            <Clock v-else class="size-4 shrink-0" />
            {{ aviso.solo_lectura ? 'Solo lectura' : 'Versión de prueba' }}
        </span>
        <span class="min-w-0 flex-1">{{ aviso.mensaje }}</span>
        <Link
            v-if="esAdmin"
            :href="licencia()"
            class="font-medium underline underline-offset-4"
        >
            {{
                aviso.solo_lectura ? 'Registrar licencia' : 'Ingresar licencia'
            }}
        </Link>
        <span v-else class="text-xs opacity-80"
            >Avisa al administrador del sistema.</span
        >
    </div>
</template>
