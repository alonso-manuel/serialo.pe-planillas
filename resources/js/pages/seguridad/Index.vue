<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    DatabaseBackup,
    FileSignature,
    KeyRound,
    Lock,
    ScrollText,
    TriangleAlert,
} from '@lucide/vue';
import Heading from '@/components/Heading.vue';
import EnlacesLegales from '@/components/legal/EnlacesLegales.vue';
import { Badge } from '@/components/ui/badge';
import { index as rutaAuditoria } from '@/routes/auditoria';
import { index as rutaEmpresas } from '@/routes/empresas';
import { index as rutaRespaldos } from '@/routes/respaldos';
import { index } from '@/routes/seguridad';
import type { EstadoFirma, ResumenSeguridad } from '@/types';

const props = defineProps<ResumenSeguridad>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Seguridad y cumplimiento', href: index() }],
    },
});

const ESTADOS: Record<
    EstadoFirma,
    {
        texto: string;
        variante: 'default' | 'secondary' | 'destructive' | 'outline';
    }
> = {
    vigente: { texto: 'Vigente', variante: 'default' },
    por_vencer: { texto: 'Por vencer', variante: 'secondary' },
    vencida: { texto: 'Vencida', variante: 'destructive' },
    sin_certificado: { texto: 'Sin certificado', variante: 'outline' },
};

const firmasPendientes = props.firmas.filter(
    (f) => f.estado !== 'vigente',
).length;
</script>

<template>
    <Head title="Seguridad y cumplimiento" />

    <div class="flex max-w-5xl flex-1 flex-col gap-6 p-4 md:p-6">
        <Heading
            title="Seguridad y cumplimiento"
            description="Cómo protege el sistema la información de tus empresas y trabajadores, con el estado actual de esta instalación."
        />

        <div class="grid gap-4 md:grid-cols-2">
            <!-- Cierres -->
            <section class="grid content-start gap-3 rounded-xl border p-5">
                <h2 class="flex items-center gap-2 font-medium">
                    <Lock class="size-4 text-muted-foreground" />
                    Periodos cerrados, inalterables
                </h2>
                <p class="text-sm text-muted-foreground">
                    Un periodo cerrado queda congelado: nadie puede editar sus
                    novedades ni recalcularlo. Solo un administrador puede
                    reabrirlo, indicando el motivo, y la reapertura queda en la
                    auditoría.
                </p>
                <dl class="grid grid-cols-3 gap-3 text-sm">
                    <div>
                        <dt class="text-muted-foreground">Cerrados</dt>
                        <dd class="text-lg font-semibold tabular-nums">
                            {{ cierres.cerrados }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-muted-foreground">Último cierre</dt>
                        <dd class="font-medium">{{ cierres.ultimo ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-muted-foreground">
                            Reaperturas (90 días)
                        </dt>
                        <dd class="text-lg font-semibold tabular-nums">
                            {{ cierres.reaperturas }}
                        </dd>
                    </div>
                </dl>
            </section>

            <!-- Auditoría -->
            <section class="grid content-start gap-3 rounded-xl border p-5">
                <h2 class="flex items-center gap-2 font-medium">
                    <ScrollText class="size-4 text-muted-foreground" />
                    Trazabilidad y auditoría
                </h2>
                <p class="text-sm text-muted-foreground">
                    Cada acción —cambios de sueldo, cargas masivas, cierres,
                    reaperturas, descargas— queda registrada con fecha, hora,
                    usuario, dirección IP y los valores antes y después. El
                    registro no se puede editar ni borrar.
                </p>
                <dl class="grid grid-cols-2 gap-3 text-sm">
                    <div>
                        <dt class="text-muted-foreground">Registros</dt>
                        <dd class="text-lg font-semibold tabular-nums">
                            {{ auditoria.total }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-muted-foreground">Últimos 30 días</dt>
                        <dd class="text-lg font-semibold tabular-nums">
                            {{ auditoria.ultimos_30 }}
                        </dd>
                    </div>
                </dl>
                <Link
                    :href="rutaAuditoria()"
                    class="text-sm font-medium underline-offset-4 hover:underline"
                    >Ver la auditoría →</Link
                >
            </section>

            <!-- Firma digital -->
            <section
                class="grid content-start gap-3 rounded-xl border p-5 md:col-span-2"
            >
                <h2 class="flex items-center gap-2 font-medium">
                    <FileSignature class="size-4 text-muted-foreground" />
                    Firma digital (D. Leg. 1310)
                </h2>
                <p class="text-sm text-muted-foreground">
                    Con el certificado digital del representante legal, las
                    boletas de periodos cerrados, las liquidaciones, los
                    certificados de trabajo y las constancias de CTS salen
                    firmados: el lector de PDF muestra quién firmó y avisa si el
                    archivo se modificó.
                    <template v-if="firmasPendientes > 0">
                        {{ firmasPendientes }}
                        {{
                            firmasPendientes === 1
                                ? 'empresa necesita'
                                : 'empresas necesitan'
                        }}
                        atención.
                    </template>
                </p>
                <div class="overflow-x-auto rounded-lg border">
                    <table class="w-full text-sm">
                        <thead
                            class="bg-muted/50 text-left text-muted-foreground"
                        >
                            <tr>
                                <th class="px-3 py-2 font-medium">Empresa</th>
                                <th class="px-3 py-2 font-medium">Titular</th>
                                <th class="px-3 py-2 font-medium">Vence</th>
                                <th class="px-3 py-2 font-medium">Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="f in firmas"
                                :key="f.empresa"
                                class="border-t"
                            >
                                <td class="px-3 py-2">{{ f.empresa }}</td>
                                <td class="px-3 py-2 text-muted-foreground">
                                    {{ f.titular ?? '—' }}
                                </td>
                                <td class="px-3 py-2 tabular-nums">
                                    {{ f.vence ?? '—' }}
                                </td>
                                <td class="px-3 py-2">
                                    <Badge
                                        :variant="ESTADOS[f.estado].variante"
                                    >
                                        {{ ESTADOS[f.estado].texto }}
                                    </Badge>
                                </td>
                            </tr>
                            <tr v-if="firmas.length === 0">
                                <td
                                    colspan="4"
                                    class="px-3 py-4 text-center text-muted-foreground"
                                >
                                    No hay empresas activas.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <Link
                    :href="rutaEmpresas()"
                    class="text-sm font-medium underline-offset-4 hover:underline"
                    >Registrar o renovar un certificado en Empresas →</Link
                >
            </section>

            <!-- Respaldos -->
            <section class="grid content-start gap-3 rounded-xl border p-5">
                <h2 class="flex items-center gap-2 font-medium">
                    <DatabaseBackup class="size-4 text-muted-foreground" />
                    Respaldos automáticos
                </h2>
                <p class="text-sm text-muted-foreground">
                    Cada día a las {{ respaldos.hora }} se guarda una copia
                    completa de la base de datos, y se conservan las de los
                    últimos {{ respaldos.dias }} días.
                    <template v-if="respaldos.saas">
                        Serialo.pe guarda además una copia fuera del servidor.
                    </template>
                    <template v-else-if="respaldos.externo">
                        Cada copia se envía también a un almacenamiento externo.
                    </template>
                    <template v-else>
                        Descarga una cada semana y guárdala fuera del servidor.
                    </template>
                </p>
                <dl class="grid grid-cols-2 gap-3 text-sm">
                    <div>
                        <dt class="text-muted-foreground">Último respaldo</dt>
                        <dd class="font-medium">
                            {{ respaldos.ultimo ?? 'Aún no hay respaldos' }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-muted-foreground">Disponibles</dt>
                        <dd class="text-lg font-semibold tabular-nums">
                            {{ respaldos.cantidad }}
                        </dd>
                    </div>
                </dl>
                <Link
                    :href="rutaRespaldos()"
                    class="text-sm font-medium underline-offset-4 hover:underline"
                    >Ver los respaldos →</Link
                >
            </section>

            <!-- Accesos -->
            <section class="grid content-start gap-3 rounded-xl border p-5">
                <h2 class="flex items-center gap-2 font-medium">
                    <KeyRound class="size-4 text-muted-foreground" />
                    Acceso de usuarios
                </h2>
                <p class="text-sm text-muted-foreground">
                    Cada usuario ve solo las empresas que se le asignan. El
                    doble factor (código del celular) protege la cuenta aunque
                    alguien conozca la contraseña.
                </p>
                <p class="text-sm">
                    <span class="font-semibold tabular-nums">{{
                        accesos.activos
                    }}</span>
                    usuarios activos.
                </p>
                <div
                    v-if="accesos.sin_doble_factor.length > 0"
                    class="flex gap-2 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-200"
                >
                    <TriangleAlert class="mt-0.5 size-4 shrink-0" />
                    <p>
                        Sin doble factor:
                        {{ accesos.sin_doble_factor.join(', ') }}. Cada usuario
                        lo activa en su menú → Configuración → Seguridad.
                    </p>
                </div>
                <p v-else class="text-sm text-muted-foreground">
                    Todos los usuarios activos tienen el doble factor activado.
                </p>
            </section>
        </div>

        <section class="grid gap-2 rounded-xl border p-5 text-sm">
            <h2 class="font-medium">Documentos legales</h2>
            <p class="text-muted-foreground">
                Los términos del servicio dejan claro que la revisión y la
                declaración ante SUNAT, AFPnet y Sunafil son responsabilidad de
                la empresa o del estudio, y la política de privacidad explica el
                tratamiento de datos según la Ley N.º 29733. Se enlazan en el
                ingreso y al pie de cada pantalla.
            </p>
            <EnlacesLegales class="justify-start" />
        </section>
    </div>
</template>
