<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    InputOTP,
    InputOTPGroup,
    InputOTPSlot,
} from '@/components/ui/input-otp';
import { Spinner } from '@/components/ui/spinner';
import { rutasPanel } from '@/lib/panel';

defineProps<{ qr: string; clave: string }>();

defineOptions({
    layout: {
        title: 'Activa el doble factor',
        description:
            'El panel central administra todas las cuentas: el doble factor es obligatorio.',
    },
});

const form = useForm({ code: '' });

function confirmar(): void {
    form.post(rutasPanel.dobleFactor, { onError: () => form.reset('code') });
}
</script>

<template>
    <Head title="Doble factor" />

    <div class="space-y-5">
        <ol class="list-decimal space-y-1 pl-5 text-sm text-muted-foreground">
            <li>
                Abre tu aplicación de autenticación (Google Authenticator,
                Microsoft Authenticator, 1Password…).
            </li>
            <li>Escanea el código QR o escribe la clave.</li>
            <li>Escribe el código de 6 dígitos que te muestra.</li>
        </ol>

        <div
            class="mx-auto w-fit rounded-lg border bg-white p-3"
            aria-label="Código QR para la aplicación de autenticación"
            v-html="qr"
        />
        <p class="text-center text-xs text-muted-foreground">
            Clave:
            <code class="font-mono break-all text-foreground">{{ clave }}</code>
        </p>

        <form class="space-y-4" @submit.prevent="confirmar">
            <div class="flex flex-col items-center gap-3">
                <InputOTP
                    v-model="form.code"
                    :maxlength="6"
                    :disabled="form.processing"
                    autofocus
                >
                    <InputOTPGroup>
                        <InputOTPSlot v-for="i in 6" :key="i" :index="i - 1" />
                    </InputOTPGroup>
                </InputOTP>
                <InputError :message="form.errors.code" />
            </div>
            <Button type="submit" class="w-full" :disabled="form.processing">
                <Spinner v-if="form.processing" />
                Activar doble factor
            </Button>
        </form>
        <Button
            variant="ghost"
            class="w-full"
            @click="router.post(rutasPanel.salir)"
            >Salir</Button
        >
    </div>
</template>
