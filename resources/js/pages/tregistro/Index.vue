<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { CircleCheck, Download, Info, TriangleAlert } from '@lucide/vue';
import { ref } from 'vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { edit } from '@/routes/trabajadores';
import { index, zip } from '@/routes/tregistro';

type Fila = {
    id: number;
    nombre: string;
    documento: string;
    fecha: string | null;
    motivo: string | null;
    problemas: string[];
};

const props = defineProps<{
    empresa: string;
    ruc: string;
    desde: string;
    hasta: string;
    altas: Fila[];
    bajas: Fila[];
    listo: boolean;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'T-Registro', href: index() }],
    },
});

const desde = ref(props.desde);
const hasta = ref(props.hasta);

function consultar(): void {
    router.get(
        index.url(),
        { desde: desde.value, hasta: hasta.value },
        { preserveScroll: true },
    );
}
</script>

<template>
    <Head title="T-Registro" />

    <div class="flex max-w-5xl flex-1 flex-col gap-6 p-4 md:p-6">
        <Heading
            title="T-Registro"
            :description="`Altas y bajas de ${empresa} (RUC ${ruc}) para la carga masiva en SUNAT.`"
        />

        <form
            class="flex flex-wrap items-end gap-3"
            @submit.prevent="consultar"
        >
            <div class="grid gap-1.5">
                <Label for="desde">Desde</Label>
                <Input id="desde" v-model="desde" type="date" class="w-40" />
            </div>
            <div class="grid gap-1.5">
                <Label for="hasta">Hasta</Label>
                <Input id="hasta" v-model="hasta" type="date" class="w-40" />
            </div>
            <Button type="submit" variant="outline">Consultar</Button>
            <Button v-if="listo" as-child class="ml-auto">
                <a :href="zip.url({ query: { desde, hasta } })">
                    <Download />
                    Descargar archivos
                </a>
            </Button>
        </form>

        <div
            class="flex items-start gap-3 rounded-lg border bg-muted/30 p-4 text-sm text-muted-foreground"
        >
            <Info class="mt-0.5 size-4 shrink-0" />
            <p>
                El zip trae <code>RP_{{ ruc }}.ide</code>, <code>.tra</code>,
                <code>.per</code> y <code>.est</code>. Valídalo en el
                <strong>PVS T-Registro</strong> de SUNAT y sube los archivos que
                genere en SUNAT Operaciones en Línea → T-Registro → Carga
                masiva. Altas: ingreso dentro de las fechas. Bajas: cese dentro
                de las fechas.
            </p>
        </div>

        <section
            v-for="grupo in [
                {
                    titulo: 'Altas',
                    filas: altas,
                    vacio: 'Nadie ingresó en esas fechas.',
                },
                {
                    titulo: 'Bajas',
                    filas: bajas,
                    vacio: 'Nadie cesó en esas fechas.',
                },
            ]"
            :key="grupo.titulo"
            class="rounded-lg border"
        >
            <h2 class="border-b px-4 py-3 font-medium">
                {{ grupo.titulo }}
                <span class="text-muted-foreground"
                    >· {{ grupo.filas.length }}</span
                >
            </h2>
            <p
                v-if="grupo.filas.length === 0"
                class="p-4 text-sm text-muted-foreground"
            >
                {{ grupo.vacio }}
            </p>
            <ul v-else class="divide-y">
                <li
                    v-for="f in grupo.filas"
                    :key="f.id"
                    class="flex flex-wrap items-start justify-between gap-3 px-4 py-3 text-sm"
                >
                    <div class="grid gap-1">
                        <span class="flex items-center gap-2 font-medium">
                            <CircleCheck
                                v-if="f.problemas.length === 0"
                                class="size-4 text-green-700 dark:text-green-400"
                            />
                            <TriangleAlert
                                v-else
                                class="size-4 text-amber-700 dark:text-amber-400"
                            />
                            {{ f.nombre }}
                        </span>
                        <span class="text-xs text-muted-foreground">
                            {{ f.documento }} · {{ f.fecha }}
                            <template v-if="f.motivo">
                                · {{ f.motivo }}</template
                            >
                        </span>
                        <ul
                            v-if="f.problemas.length"
                            class="list-disc pl-5 text-xs text-amber-800 dark:text-amber-300"
                        >
                            <li v-for="p in f.problemas" :key="p">{{ p }}</li>
                        </ul>
                    </div>
                    <Button
                        v-if="f.problemas.length"
                        variant="outline"
                        size="sm"
                        as-child
                    >
                        <Link :href="edit(f.id)">Completar datos</Link>
                    </Button>
                </li>
            </ul>
        </section>
    </div>
</template>
