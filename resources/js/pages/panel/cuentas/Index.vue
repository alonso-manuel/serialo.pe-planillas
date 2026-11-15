<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import {
    Ban,
    Building2,
    CircleCheck,
    Copy,
    Download,
    ExternalLink,
    KeyRound,
    LifeBuoy,
    MoreHorizontal,
    Pencil,
    Plus,
    Trash2,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { rutasPanel } from '@/lib/panel';

type Cuenta = {
    id: string;
    nombre: string;
    url: string;
    tipo: 'demo' | 'cliente';
    tipo_etiqueta: string;
    plan: string;
    limites: string;
    estado: 'activo' | 'suspendido';
    estado_etiqueta: string;
    correo_admin: string | null;
    vence_en: string | null;
    vencida: boolean;
    creada: string;
    ingresos: number;
    ultimo_ingreso: string | null;
    ultimo_ingreso_hace: string | null;
    ultimo_ingreso_usuario: string | null;
};

defineProps<{ cuentas: Cuenta[] }>();

/** Si el correo no salió, el enlace para crear la contraseña llega por flash. */
const page = usePage();
const invitacion = computed(
    () =>
        (page.flash as Record<string, unknown> | undefined)?.invitacion as
            | { cuenta: string; correo: string; enlace: string }
            | undefined,
);
const copiado = ref(false);

async function copiar(texto: string): Promise<void> {
    await navigator.clipboard.writeText(texto);
    copiado.value = true;
    setTimeout(() => (copiado.value = false), 2000);
}

function cambiarEstado(c: Cuenta): void {
    router.patch(rutasPanel.estado(c.id), {}, { preserveScroll: true });
}

/** Abre el estudio como «Soporte Serialo.pe» (solo si su administrador lo autorizó). */
function entrarSoporte(c: Cuenta): void {
    router.post(rutasPanel.soporte(c.id), {}, { preserveScroll: true });
}

function reenviarAcceso(c: Cuenta): void {
    router.post(rutasPanel.acceso(c.id), {}, { preserveScroll: true });
}

const aEliminar = ref<Cuenta | null>(null);
const formEliminar = useForm({ confirmacion: '' });

function abrirEliminar(c: Cuenta): void {
    aEliminar.value = c;
    formEliminar.reset();
    formEliminar.clearErrors();
}

function eliminar(): void {
    if (aEliminar.value) {
        formEliminar.delete(rutasPanel.cuenta(aEliminar.value.id), {
            preserveScroll: true,
            onSuccess: () => (aEliminar.value = null),
        });
    }
}
</script>

<template>
    <Head title="Cuentas" />

    <div class="flex flex-col gap-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading
                title="Cuentas"
                description="Estudios y empresas que usan Serialo Planillas en este servidor. Cada cuenta tiene su propia base de datos."
            />
            <Button as-child>
                <Link :href="rutasPanel.nuevaCuenta">
                    <Plus />
                    Nueva cuenta
                </Link>
            </Button>
        </div>

        <div
            v-if="invitacion"
            class="grid gap-2 rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-950 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-100"
        >
            <p class="font-medium">
                Envía este enlace a {{ invitacion.correo }} ({{
                    invitacion.cuenta
                }})
            </p>
            <p>
                El correo del servidor no está configurado. Con este enlace el
                administrador crea su contraseña; vence en 24 horas.
            </p>
            <div class="flex flex-wrap items-center gap-2">
                <code
                    class="min-w-0 flex-1 rounded bg-white/70 px-2 py-1 font-mono text-xs break-all dark:bg-black/30"
                    >{{ invitacion.enlace }}</code
                >
                <Button
                    size="sm"
                    variant="outline"
                    @click="copiar(invitacion.enlace)"
                >
                    <Copy />
                    {{ copiado ? 'Copiado' : 'Copiar' }}
                </Button>
            </div>
        </div>

        <div
            v-if="cuentas.length === 0"
            class="flex flex-col items-center gap-3 rounded-lg border border-dashed bg-background px-6 py-14 text-center"
        >
            <Building2 class="size-6 text-muted-foreground" />
            <p class="font-medium">Aún no hay cuentas</p>
            <p class="max-w-sm text-sm text-muted-foreground">
                Crea la primera: el sistema prepara su base de datos y envía al
                administrador el enlace para crear su contraseña.
            </p>
        </div>

        <div v-else class="overflow-x-auto rounded-lg border bg-background">
            <table class="w-full min-w-[760px] text-sm">
                <thead class="bg-muted/40 text-xs text-muted-foreground">
                    <tr>
                        <th class="px-4 py-2 text-left font-medium">Cuenta</th>
                        <th class="px-4 py-2 text-left font-medium">Plan</th>
                        <th class="px-4 py-2 text-left font-medium">Estado</th>
                        <th class="px-4 py-2 text-left font-medium">
                            Último ingreso
                        </th>
                        <th class="px-4 py-2" />
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="c in cuentas"
                        :key="c.id"
                        class="border-t align-top"
                    >
                        <!-- Cuenta: nombre, dirección y administrador -->
                        <td class="px-4 py-3">
                            <p class="font-medium">{{ c.nombre }}</p>
                            <a
                                :href="c.url"
                                target="_blank"
                                rel="noopener"
                                class="inline-flex items-center gap-1 text-xs text-muted-foreground hover:underline"
                            >
                                {{ c.url.replace(/^https?:\/\//, '') }}
                                <ExternalLink class="size-3" />
                            </a>
                            <p class="text-xs text-muted-foreground">
                                {{ c.correo_admin ?? '—' }}
                            </p>
                        </td>
                        <!-- Plan y límites -->
                        <td class="max-w-[220px] px-4 py-3">
                            <div class="flex items-center gap-2">
                                <span class="font-medium">{{ c.plan }}</span>
                                <Badge
                                    v-if="c.tipo === 'demo'"
                                    variant="outline"
                                    class="border-sky-300 text-sky-800 dark:border-sky-800 dark:text-sky-300"
                                    >{{ c.tipo_etiqueta }}</Badge
                                >
                            </div>
                            <p class="mt-0.5 text-xs text-muted-foreground">
                                {{ c.limites }}
                            </p>
                        </td>
                        <!-- Estado, vencimiento y creación -->
                        <td class="px-4 py-3 whitespace-nowrap">
                            <span
                                class="inline-flex items-center gap-1"
                                :class="
                                    c.estado === 'suspendido'
                                        ? 'text-red-700 dark:text-red-400'
                                        : 'text-green-700 dark:text-green-400'
                                "
                            >
                                <Ban
                                    v-if="c.estado === 'suspendido'"
                                    class="size-3.5"
                                />
                                <CircleCheck v-else class="size-3.5" />
                                {{ c.estado_etiqueta }}
                            </span>
                            <p
                                class="text-xs tabular-nums"
                                :class="
                                    c.vencida
                                        ? 'text-red-700 dark:text-red-400'
                                        : 'text-muted-foreground'
                                "
                            >
                                <template v-if="c.vence_en">
                                    Vence {{ c.vence_en
                                    }}{{ c.vencida ? ' (vencida)' : '' }}
                                </template>
                                <template v-else>Sin vencimiento</template>
                            </p>
                            <p
                                class="text-xs text-muted-foreground tabular-nums"
                            >
                                Creada {{ c.creada }}
                            </p>
                        </td>
                        <!-- ¿Entró a probar? -->
                        <td class="px-4 py-3 whitespace-nowrap">
                            <template v-if="c.ultimo_ingreso">
                                <p
                                    :title="c.ultimo_ingreso"
                                    class="tabular-nums"
                                >
                                    {{ c.ultimo_ingreso_hace }}
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    {{ c.ultimo_ingreso_usuario }} ·
                                    {{ c.ingresos }}
                                    {{
                                        c.ingresos === 1
                                            ? 'ingreso'
                                            : 'ingresos'
                                    }}
                                </p>
                            </template>
                            <span v-else class="text-muted-foreground"
                                >Nunca ingresó</span
                            >
                        </td>
                        <!-- Acciones: Editar a la vista, el resto en el menú -->
                        <td class="px-4 py-3">
                            <div class="flex justify-end gap-1">
                                <Button variant="ghost" size="sm" as-child>
                                    <Link :href="rutasPanel.editar(c.id)">
                                        <Pencil />
                                        Editar
                                    </Link>
                                </Button>
                                <DropdownMenu>
                                    <DropdownMenuTrigger as-child>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            :aria-label="
                                                'Más acciones de ' + c.nombre
                                            "
                                        >
                                            <MoreHorizontal />
                                        </Button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent
                                        align="end"
                                        class="w-64"
                                    >
                                        <DropdownMenuItem
                                            :disabled="
                                                c.estado === 'suspendido'
                                            "
                                            @select="entrarSoporte(c)"
                                        >
                                            <LifeBuoy />
                                            Entrar como soporte
                                        </DropdownMenuItem>
                                        <DropdownMenuItem
                                            @select="reenviarAcceso(c)"
                                        >
                                            <KeyRound />
                                            Reenviar acceso al administrador
                                        </DropdownMenuItem>
                                        <DropdownMenuItem as-child>
                                            <a
                                                :href="
                                                    rutasPanel.exportar(c.id)
                                                "
                                                download
                                            >
                                                <Download />
                                                Exportar base (.sql.gz)
                                            </a>
                                        </DropdownMenuItem>
                                        <DropdownMenuSeparator />
                                        <DropdownMenuItem
                                            @select="cambiarEstado(c)"
                                        >
                                            <Ban />
                                            {{
                                                c.estado === 'suspendido'
                                                    ? 'Reactivar cuenta'
                                                    : 'Suspender cuenta'
                                            }}
                                        </DropdownMenuItem>
                                        <DropdownMenuItem
                                            class="text-red-700 focus:text-red-800 dark:text-red-400"
                                            @select="abrirEliminar(c)"
                                        >
                                            <Trash2 class="text-current" />
                                            Eliminar cuenta
                                        </DropdownMenuItem>
                                    </DropdownMenuContent>
                                </DropdownMenu>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <Dialog
        :open="aEliminar !== null"
        @update:open="(v) => !v && (aEliminar = null)"
    >
        <DialogContent>
            <DialogHeader>
                <DialogTitle>¿Eliminar {{ aEliminar?.nombre }}?</DialogTitle>
                <DialogDescription>
                    Se borran su base de datos, sus usuarios, planillas y
                    archivos. No se puede deshacer. Si es un cliente, descarga
                    antes un respaldo.
                </DialogDescription>
            </DialogHeader>
            <form
                id="form-eliminar"
                class="grid gap-2"
                @submit.prevent="eliminar"
            >
                <Label for="confirmacion"
                    >Escribe <b>{{ aEliminar?.id }}</b> para confirmar</Label
                >
                <Input
                    id="confirmacion"
                    v-model="formEliminar.confirmacion"
                    autocomplete="off"
                />
                <InputError :message="formEliminar.errors.confirmacion" />
            </form>
            <DialogFooter>
                <DialogClose as-child>
                    <Button variant="outline">Cancelar</Button>
                </DialogClose>
                <Button
                    type="submit"
                    form="form-eliminar"
                    variant="destructive"
                    :disabled="
                        formEliminar.processing ||
                        formEliminar.confirmacion !== aEliminar?.id
                    "
                >
                    <Spinner v-if="formEliminar.processing" />
                    Eliminar cuenta
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
