<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { privacidad, terminos } from '@/routes/legal';
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
    </nav>
</template>
