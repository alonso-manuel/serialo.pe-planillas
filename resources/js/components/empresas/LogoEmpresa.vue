<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { ImageUp, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { destroy, show, store } from '@/routes/empresas/logo';

const props = defineProps<{
    empresaId: number;
    /** Versión del logo actual (para no mostrar uno anterior en caché) o null si no tiene. */
    logo: string | null;
}>();

const archivo = ref<HTMLInputElement | null>(null);
const form = useForm<{ logo: File | null }>({ logo: null });

function elegir(e: Event): void {
    const input = e.target as HTMLInputElement;
    form.logo = input.files?.[0] ?? null;

    if (form.logo) {
        form.post(store.url(props.empresaId), {
            forceFormData: true,
            preserveScroll: true,
            onFinish: () => {
                form.reset();
                input.value = '';
            },
        });
    }
}

function quitar(): void {
    router.delete(destroy.url(props.empresaId), { preserveScroll: true });
}
</script>

<template>
    <section class="grid max-w-3xl gap-3 rounded-lg border p-4">
        <div>
            <h2 class="font-medium">Logo</h2>
            <p class="text-sm text-muted-foreground">
                Aparece en las boletas, las constancias de CTS y los reportes en
                PDF. PNG o JPG de hasta 1 MB; se reduce a 600 × 200 px.
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-4">
            <div
                class="flex h-20 w-56 items-center justify-center rounded-md border border-dashed bg-muted/30 p-2"
            >
                <img
                    v-if="logo"
                    :src="show.url(empresaId, { query: { v: logo } })"
                    alt="Logo de la empresa"
                    class="max-h-full max-w-full object-contain"
                />
                <span v-else class="text-xs text-muted-foreground"
                    >Sin logo</span
                >
            </div>

            <div class="flex flex-wrap gap-2">
                <input
                    ref="archivo"
                    type="file"
                    accept="image/png,image/jpeg"
                    class="hidden"
                    @change="elegir"
                />
                <Button
                    variant="outline"
                    :disabled="form.processing"
                    @click="archivo?.click()"
                >
                    <Spinner v-if="form.processing" />
                    <ImageUp v-else />
                    {{ logo ? 'Cambiar logo' : 'Subir logo' }}
                </Button>
                <Button
                    v-if="logo"
                    variant="ghost"
                    class="text-destructive hover:text-destructive"
                    @click="quitar"
                >
                    <Trash2 />
                    Quitar
                </Button>
            </div>
        </div>
        <InputError :message="form.errors.logo" />
    </section>
</template>
