<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { FileKey, ShieldCheck, Trash2, TriangleAlert } from '@lucide/vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { destroy, store } from '@/routes/empresas/firma';
import type { FirmaDigitalEmpresa } from '@/types';

const props = defineProps<{
    empresaId: number;
    firma: FirmaDigitalEmpresa | null;
}>();

const form = useForm<{ certificado: File | null; clave: string }>({
    certificado: null,
    clave: '',
});

function elegir(e: Event): void {
    form.certificado = (e.target as HTMLInputElement).files?.[0] ?? null;
}

function registrar(): void {
    form.post(store.url(props.empresaId), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => form.reset(),
        onFinish: () => form.reset('clave'),
    });
}

function quitar(): void {
    router.delete(destroy.url(props.empresaId), { preserveScroll: true });
}
</script>

<template>
    <section class="grid max-w-3xl gap-4 rounded-lg border p-4">
        <div>
            <h2 class="font-medium">Firma digital</h2>
            <p class="text-sm text-muted-foreground">
                Con el certificado digital del representante legal (.pfx o .p12,
                emitido por RENIEC u otra entidad acreditada), las boletas de
                periodos cerrados, la liquidación, el certificado de trabajo y
                las constancias y cartas de CTS salen firmados digitalmente: el
                lector de PDF muestra quién firmó y avisa si el archivo se
                modificó.
            </p>
        </div>

        <div
            v-if="firma"
            class="grid gap-3 rounded-md border p-3 text-sm"
            :class="
                firma.vigente
                    ? 'border-green-200 bg-green-50/60 dark:border-green-900 dark:bg-green-950/30'
                    : 'border-red-200 bg-red-50/60 dark:border-red-900 dark:bg-red-950/30'
            "
        >
            <div class="flex items-start gap-2">
                <ShieldCheck
                    v-if="firma.vigente"
                    class="mt-0.5 size-4 shrink-0 text-green-700 dark:text-green-400"
                />
                <TriangleAlert
                    v-else
                    class="mt-0.5 size-4 shrink-0 text-red-700 dark:text-red-400"
                />
                <div class="grid gap-0.5">
                    <p class="font-medium">
                        {{ firma.titular }}
                        <span
                            v-if="firma.documento"
                            class="font-normal text-muted-foreground"
                            >· {{ firma.documento }}</span
                        >
                    </p>
                    <p class="text-muted-foreground">
                        Emitido por {{ firma.emisor }} · válido del
                        {{ firma.valido_desde }} al {{ firma.valido_hasta }}
                    </p>
                    <p
                        v-if="!firma.vigente"
                        class="font-medium text-red-700 dark:text-red-400"
                    >
                        Venció: los documentos salen sin firma digital. Registra
                        el certificado renovado.
                    </p>
                    <p
                        v-else-if="firma.dias_para_vencer <= 30"
                        class="font-medium text-amber-700 dark:text-amber-400"
                    >
                        Vence en {{ firma.dias_para_vencer }}
                        {{ firma.dias_para_vencer === 1 ? 'día' : 'días' }}:
                        renuévalo a tiempo.
                    </p>
                    <p class="text-xs break-all text-muted-foreground">
                        Huella SHA-256: {{ firma.huella }}
                    </p>
                </div>
            </div>
            <div>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    @click="quitar"
                >
                    <Trash2 />
                    Quitar firma
                </Button>
            </div>
        </div>

        <div class="grid gap-3">
            <h3 class="text-sm font-medium">
                {{ firma ? 'Reemplazar certificado' : 'Registrar certificado' }}
            </h3>
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="firma-certificado">Archivo .pfx o .p12</Label>
                    <Input
                        id="firma-certificado"
                        type="file"
                        accept=".pfx,.p12"
                        @change="elegir"
                    />
                    <InputError :message="form.errors.certificado" />
                </div>
                <div class="grid gap-2">
                    <Label for="firma-clave">Clave del certificado</Label>
                    <Input
                        id="firma-clave"
                        v-model="form.clave"
                        type="password"
                        autocomplete="off"
                    />
                    <InputError :message="form.errors.clave" />
                </div>
            </div>
            <p class="text-xs text-muted-foreground">
                El certificado y su clave se guardan cifrados y no se vuelven a
                mostrar. Se necesitan para firmar los documentos sin pedir la
                clave cada vez.
            </p>
            <div>
                <Button
                    type="button"
                    :disabled="
                        form.processing || !form.certificado || !form.clave
                    "
                    @click="registrar"
                >
                    <Spinner v-if="form.processing" />
                    <FileKey v-else />
                    {{ firma ? 'Reemplazar' : 'Registrar' }}
                </Button>
            </div>
        </div>
    </section>
</template>
