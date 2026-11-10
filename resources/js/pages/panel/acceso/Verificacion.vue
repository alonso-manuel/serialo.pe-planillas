<script setup lang="ts">
import { Head, setLayoutProps, useForm } from '@inertiajs/vue3';
import { ref, watchEffect } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    InputOTP,
    InputOTPGroup,
    InputOTPSlot,
} from '@/components/ui/input-otp';
import { Spinner } from '@/components/ui/spinner';
import { rutasPanel } from '@/lib/panel';

const recuperacion = ref(false);
const form = useForm({ code: '', recovery_code: '' });

watchEffect(() => {
    setLayoutProps({
        title: recuperacion.value
            ? 'Código de recuperación'
            : 'Código de autenticación',
        description: recuperacion.value
            ? 'Escribe uno de tus códigos de recuperación.'
            : 'Escribe el código de 6 dígitos de tu aplicación de autenticación.',
    });
});

function cambiarModo(): void {
    recuperacion.value = !recuperacion.value;
    form.reset();
    form.clearErrors();
}

function verificar(): void {
    form.post(rutasPanel.verificacion, {
        onError: () => form.reset('code'),
    });
}
</script>

<template>
    <Head title="Verificación en dos pasos" />

    <form class="space-y-4" @submit.prevent="verificar">
        <div v-if="!recuperacion" class="flex flex-col items-center gap-3">
            <InputOTP
                id="otp"
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
        <div v-else class="grid gap-2">
            <Input
                v-model="form.recovery_code"
                placeholder="Código de recuperación"
                autocomplete="off"
                required
            />
            <InputError :message="form.errors.recovery_code" />
        </div>

        <Button type="submit" class="w-full" :disabled="form.processing">
            <Spinner v-if="form.processing" />
            Continuar
        </Button>
        <p class="text-center text-sm text-muted-foreground">
            o puedes
            <button
                type="button"
                class="text-foreground underline underline-offset-4"
                @click="cambiarModo"
            >
                {{
                    recuperacion
                        ? 'usar el código de la aplicación'
                        : 'usar un código de recuperación'
                }}
            </button>
        </p>
    </form>
</template>
