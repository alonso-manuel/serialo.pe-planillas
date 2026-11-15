<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { privacidad, terminos } from '@/routes/legal';

const page = usePage();
</script>

<template>
    <nav
        aria-label="Documentos legales"
        class="flex flex-wrap items-center justify-center gap-x-3 gap-y-1 text-xs text-muted-foreground"
    >
        <Link :href="terminos()" class="hover:text-foreground hover:underline">
            Términos del servicio
        </Link>
        <span aria-hidden="true">·</span>
        <Link
            :href="privacidad()"
            class="hover:text-foreground hover:underline"
        >
            Política de privacidad
        </Link>
        <template v-if="page.props.version">
            <span aria-hidden="true">·</span>
            <span>Versión {{ page.props.version }}</span>
        </template>
    </nav>
</template>
