<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { rutasPanel } from '@/lib/panel';

defineOptions({
    layout: {
        title: 'Panel central',
        description:
            'Acceso exclusivo de Serialo.pe para administrar las cuentas',
    },
});

const form = useForm({ email: '', password: '', remember: false });

function ingresar(): void {
    form.post(rutasPanel.ingresar, { onFinish: () => form.reset('password') });
}
</script>

<template>
    <Head title="Panel central" />

    <form class="grid gap-5" @submit.prevent="ingresar">
        <div class="grid gap-2">
            <Label for="email">Correo</Label>
            <Input
                id="email"
                v-model="form.email"
                type="email"
                autocomplete="email"
                required
                autofocus
            />
            <InputError :message="form.errors.email" />
        </div>
        <div class="grid gap-2">
            <Label for="password">Contraseña</Label>
            <PasswordInput
                id="password"
                v-model="form.password"
                autocomplete="current-password"
                required
            />
            <InputError :message="form.errors.password" />
        </div>
        <Label for="remember" class="flex items-center gap-3 font-normal">
            <Checkbox id="remember" v-model="form.remember" />
            Recordarme en este equipo
        </Label>
        <Button type="submit" class="w-full" :disabled="form.processing">
            <Spinner v-if="form.processing" />
            Ingresar
        </Button>
    </form>
</template>
