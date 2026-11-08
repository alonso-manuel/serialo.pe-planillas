<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Check, CircleAlert, Loader2, Plus } from '@lucide/vue';
import { computed, reactive, ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { update } from '@/routes/periodos/lineas';
import { edit as editarTrabajador } from '@/routes/trabajadores';
import type { ConceptoNovedad, EstadoPeriodo, LineaPeriodo } from '@/types';

const props = defineProps<{
    periodoId: number;
    lineas: LineaPeriodo[];
    columnas: number[];
    conceptos: ConceptoNovedad[];
    editable: boolean;
}>();

const emit = defineEmits<{ estado: [EstadoPeriodo] }>();

/* ---------- Columnas ---------- */
const columnasConceptos = ref<number[]>([...props.columnas]);
const conceptosPorId = computed(
    () => new Map(props.conceptos.map((c) => [c.id, c])),
);
const visibles = computed(() =>
    columnasConceptos.value
        .map((id) => conceptosPorId.value.get(id))
        .filter((c): c is ConceptoNovedad => c !== undefined),
);
const agregables = computed(() =>
    props.conceptos.filter((c) => !columnasConceptos.value.includes(c.id)),
);

function agregarColumna(id: number): void {
    columnasConceptos.value.push(id);
}

/** Campos fijos editables, en el orden de las columnas. */
const CAMPOS = [
    'dias_no_laborados',
    'dias_subsidiados',
    'horas_extra_25',
    'horas_extra_35',
] as const;
type Campo = (typeof CAMPOS)[number];

/* ---------- Filas ---------- */
type EstadoFila = 'guardado' | 'cambiado' | 'guardando' | 'error';

type Fila = {
    linea: LineaPeriodo;
    valores: Record<Campo, string>;
    montos: Record<number, string>;
    estado: EstadoFila;
    errores: Record<string, string>;
    mensaje: string | null;
    netoDesactualizado: boolean;
};

function crearFila(l: LineaPeriodo): Fila {
    return {
        linea: { ...l },
        valores: {
            dias_no_laborados: l.dias_no_laborados
                ? String(l.dias_no_laborados)
                : '',
            dias_subsidiados: l.dias_subsidiados
                ? String(l.dias_subsidiados)
                : '',
            horas_extra_25: Number(l.horas_extra_25) ? l.horas_extra_25 : '',
            horas_extra_35: Number(l.horas_extra_35) ? l.horas_extra_35 : '',
        },
        montos: Object.fromEntries(
            Object.entries(l.novedades).map(([id, m]) => [Number(id), m]),
        ),
        estado: 'guardado',
        errores: {},
        mensaje: null,
        netoDesactualizado: false,
    };
}

const filas = reactive<Fila[]>(props.lineas.map(crearFila));

function marcarCambio(fila: Fila): void {
    fila.estado = 'cambiado';
}

/** Días pagados mostrados al instante (el servidor confirma al guardar). */
function diasPagados(fila: Fila): number {
    const n = (v: string) => (/^\d+$/.test(v) ? Number(v) : 0);

    return Math.max(
        0,
        fila.linea.dias_mes -
            n(fila.valores.dias_no_laborados) -
            n(fila.valores.dias_subsidiados),
    );
}

/* ---------- Guardado por fila ---------- */
function xsrf(): string {
    const cookie = document.cookie
        .split('; ')
        .find((c) => c.startsWith('XSRF-TOKEN='));

    return cookie ? decodeURIComponent(cookie.split('=')[1]) : '';
}

async function guardar(fila: Fila): Promise<void> {
    if (fila.estado !== 'cambiado' && fila.estado !== 'error') {
        return;
    }

    fila.estado = 'guardando';
    fila.errores = {};
    fila.mensaje = null;

    const novedades: Record<number, string | null> = {};
    for (const c of visibles.value) {
        novedades[c.id] = fila.montos[c.id] ?? null;
    }

    try {
        const respuesta = await fetch(
            update.url({ periodo: props.periodoId, linea: fila.linea.id }),
            {
                method: 'PUT',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-XSRF-TOKEN': xsrf(),
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ ...fila.valores, novedades }),
            },
        );
        const datos = await respuesta.json();

        if (respuesta.status === 422) {
            fila.estado = 'error';
            fila.errores = Object.fromEntries(
                Object.entries(datos.errors as Record<string, string[]>).map(
                    ([k, v]) => [k, v[0]],
                ),
            );
            fila.mensaje = Object.values(fila.errores)[0] ?? null;

            return;
        }

        if (!respuesta.ok) {
            fila.estado = 'error';
            fila.mensaje =
                datos.message ?? 'No se pudo guardar. Vuelve a intentarlo.';

            return;
        }

        fila.linea.dias_laborados = datos.dias_laborados;
        fila.linea.dias_mes = datos.dias_mes;
        fila.montos = Object.fromEntries(
            Object.entries(datos.novedades as Record<string, string>).map(
                ([id, m]) => [Number(id), m],
            ),
        );
        fila.estado = 'guardado';
        fila.netoDesactualizado = fila.linea.neto_pagar !== null;
        emit('estado', datos.estado_periodo);
    } catch {
        fila.estado = 'error';
        fila.mensaje = 'Sin conexión con el servidor.';
    }
}

/** Guarda todas las filas con cambios. Devuelve false si alguna quedó con error. */
async function guardarPendientes(): Promise<boolean> {
    await Promise.all(
        filas
            .filter((f) => f.estado === 'cambiado' || f.estado === 'error')
            .map((f) => guardar(f)),
    );

    return filas.every((f) => f.estado === 'guardado');
}

defineExpose({ guardarPendientes });

/* ---------- Neto (después de calcular) ---------- */
const mostrarNeto = computed(() =>
    filas.some((f) => f.linea.neto_pagar !== null),
);

/* ---------- Navegación con teclado y guardado al cambiar de fila ---------- */
const filaActiva = ref<number | null>(null);
const tabla = ref<HTMLElement | null>(null);

function alEnfocar(indice: number): void {
    if (filaActiva.value !== null && filaActiva.value !== indice) {
        void guardar(filas[filaActiva.value]);
    }

    filaActiva.value = indice;
}

function alSalirDeLaTabla(evento: FocusEvent): void {
    const destino = evento.relatedTarget as Node | null;

    if (!destino || !tabla.value?.contains(destino)) {
        if (filaActiva.value !== null) {
            void guardar(filas[filaActiva.value]);
        }

        filaActiva.value = null;
    }
}

function mover(evento: KeyboardEvent, fila: number, columna: number): void {
    const destinoFila = evento.key === 'ArrowUp' ? fila - 1 : fila + 1;
    const celda = tabla.value?.querySelector<HTMLInputElement>(
        `[data-fila="${destinoFila}"][data-columna="${columna}"]`,
    );

    if (celda) {
        evento.preventDefault();
        celda.focus();
        celda.select();
    }
}

/* ---------- Totales (en céntimos, sin decimales binarios) ---------- */
function centimos(valor: string | undefined): number {
    if (!valor || !/^\d+([.,]\d{1,2})?$/.test(valor)) {
        return 0;
    }

    const [entero, decimal = ''] = valor.replace(',', '.').split('.');

    return Number(entero) * 100 + Number(decimal.padEnd(2, '0'));
}

const soles = new Intl.NumberFormat('es-PE', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
});

function totalCampo(campo: Campo): string {
    const total = filas.reduce((s, f) => s + centimos(f.valores[campo]), 0);

    return campo.startsWith('dias')
        ? String(total / 100)
        : soles.format(total / 100);
}

function totalConcepto(id: number): string {
    return soles.format(
        filas.reduce((s, f) => s + centimos(f.montos[id]), 0) / 100,
    );
}

const totalNeto = computed(() =>
    soles.format(
        filas.reduce(
            (s, f) => s + centimos(f.linea.neto_pagar ?? undefined),
            0,
        ) / 100,
    ),
);

const totalDiasPagados = computed(() =>
    filas.reduce((s, f) => s + diasPagados(f), 0),
);

const COLUMNAS_FIJAS: { campo: Campo; titulo: string; ancho: string }[] = [
    { campo: 'dias_no_laborados', titulo: 'Faltas', ancho: 'w-20' },
    { campo: 'dias_subsidiados', titulo: 'Subsid.', ancho: 'w-20' },
    { campo: 'horas_extra_25', titulo: 'HE 25 %', ancho: 'w-24' },
    { campo: 'horas_extra_35', titulo: 'HE 35 %', ancho: 'w-24' },
];
</script>

<template>
    <div class="grid gap-3">
        <div
            v-if="editable"
            class="flex flex-wrap items-center justify-between gap-2"
        >
            <p class="text-sm text-muted-foreground">
                Escribe y muévete con
                <kbd class="rounded border px-1 text-xs">↑</kbd>
                <kbd class="rounded border px-1 text-xs">↓</kbd>
                <kbd class="rounded border px-1 text-xs">Enter</kbd> o
                <kbd class="rounded border px-1 text-xs">Tab</kbd>. Cada fila se
                guarda al salir de ella.
            </p>
            <DropdownMenu v-if="agregables.length">
                <DropdownMenuTrigger as-child>
                    <Button variant="outline" size="sm"
                        ><Plus />Agregar columna</Button
                    >
                </DropdownMenuTrigger>
                <DropdownMenuContent
                    align="end"
                    class="max-h-80 w-72 overflow-y-auto"
                >
                    <template
                        v-for="(tipo, i) in ['ingreso', 'descuento']"
                        :key="tipo"
                    >
                        <DropdownMenuSeparator v-if="i > 0" />
                        <DropdownMenuLabel
                            class="text-xs text-muted-foreground"
                        >
                            {{ tipo === 'ingreso' ? 'Ingresos' : 'Descuentos' }}
                        </DropdownMenuLabel>
                        <DropdownMenuItem
                            v-for="c in agregables.filter(
                                (x) => x.tipo === tipo,
                            )"
                            :key="c.id"
                            @select="agregarColumna(c.id)"
                        >
                            <span
                                class="font-mono text-xs text-muted-foreground"
                                >{{ c.codigo_plame }}</span
                            >
                            {{ c.nombre }}
                        </DropdownMenuItem>
                    </template>
                </DropdownMenuContent>
            </DropdownMenu>
        </div>

        <div
            ref="tabla"
            class="overflow-x-auto rounded-lg border"
            @focusout="alSalirDeLaTabla"
        >
            <table class="w-full border-collapse text-sm">
                <thead
                    class="bg-muted/50 text-left text-xs text-muted-foreground"
                >
                    <tr>
                        <th
                            class="sticky left-0 z-10 min-w-56 bg-muted px-3 py-2 font-medium"
                        >
                            Trabajador
                        </th>
                        <th
                            class="w-16 px-2 py-2 text-right font-medium"
                            title="Días que le corresponden en el mes"
                        >
                            Mes
                        </th>
                        <th
                            v-for="c in COLUMNAS_FIJAS"
                            :key="c.campo"
                            class="px-2 py-2 text-right font-medium"
                            :class="c.ancho"
                        >
                            {{ c.titulo }}
                        </th>
                        <th
                            class="w-20 px-2 py-2 text-right font-medium text-foreground"
                        >
                            Días pag.
                        </th>
                        <th
                            v-for="c in visibles"
                            :key="c.id"
                            class="w-32 px-2 py-2 text-right font-medium"
                            :title="`${c.codigo_plame} · ${c.nombre}`"
                        >
                            <span class="block truncate">{{ c.nombre }}</span>
                            <span class="font-mono font-normal">{{
                                c.codigo_plame
                            }}</span>
                        </th>
                        <th
                            v-if="mostrarNeto"
                            class="w-28 px-3 py-2 text-right font-medium text-foreground"
                        >
                            Neto
                        </th>
                        <th class="w-10 px-2 py-2">
                            <span class="sr-only">Estado</span>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="(fila, i) in filas"
                        :key="fila.linea.id"
                        class="border-t"
                        :class="{
                            'bg-primary/5': filaActiva === i,
                            'bg-red-50 dark:bg-red-950/30':
                                fila.estado === 'error',
                        }"
                    >
                        <td
                            class="sticky left-0 z-10 bg-background px-3 py-1.5"
                        >
                            <Link
                                :href="
                                    editarTrabajador(fila.linea.trabajador_id)
                                "
                                class="font-medium hover:underline"
                            >
                                {{ fila.linea.nombre }}
                            </Link>
                            <div
                                class="flex flex-wrap items-center gap-1 text-xs text-muted-foreground"
                            >
                                {{ fila.linea.documento }}
                                <Badge
                                    v-if="fila.linea.ingresa_en_mes"
                                    variant="outline"
                                    class="h-4 px-1 text-[10px]"
                                >
                                    Ingresa {{ fila.linea.ingresa_en_mes }}
                                </Badge>
                                <Badge
                                    v-if="fila.linea.cesa_en_mes"
                                    variant="outline"
                                    class="h-4 px-1 text-[10px]"
                                >
                                    Cesa {{ fila.linea.cesa_en_mes }}
                                </Badge>
                            </div>
                        </td>
                        <td
                            class="px-2 py-1.5 text-right text-muted-foreground tabular-nums"
                        >
                            {{ fila.linea.dias_mes }}
                        </td>

                        <td
                            v-for="(c, j) in COLUMNAS_FIJAS"
                            :key="c.campo"
                            class="px-1 py-1"
                        >
                            <input
                                v-model="fila.valores[c.campo]"
                                :data-fila="i"
                                :data-columna="j"
                                :readonly="!editable"
                                :inputmode="
                                    c.campo.startsWith('dias')
                                        ? 'numeric'
                                        : 'decimal'
                                "
                                class="h-8 w-full rounded-md border border-transparent bg-transparent px-2 text-right tabular-nums outline-none hover:border-input read-only:hover:border-transparent focus:border-ring focus:bg-background focus:ring-2 focus:ring-ring/30"
                                :class="{
                                    'border-red-400!': fila.errores[c.campo],
                                }"
                                :title="fila.errores[c.campo]"
                                :aria-label="`${c.titulo} de ${fila.linea.nombre}`"
                                placeholder="0"
                                @focus="alEnfocar(i)"
                                @input="marcarCambio(fila)"
                                @keydown.enter="mover($event, i, j)"
                                @keydown.down="mover($event, i, j)"
                                @keydown.up="mover($event, i, j)"
                            />
                        </td>

                        <td
                            class="px-2 py-1.5 text-right font-medium tabular-nums"
                        >
                            {{ diasPagados(fila) }}
                        </td>

                        <td
                            v-for="(c, k) in visibles"
                            :key="c.id"
                            class="px-1 py-1"
                        >
                            <input
                                v-model="fila.montos[c.id]"
                                :data-fila="i"
                                :data-columna="COLUMNAS_FIJAS.length + k"
                                :readonly="!editable"
                                inputmode="decimal"
                                class="h-8 w-full rounded-md border border-transparent bg-transparent px-2 text-right tabular-nums outline-none hover:border-input read-only:hover:border-transparent focus:border-ring focus:bg-background focus:ring-2 focus:ring-ring/30"
                                :class="{
                                    'border-red-400!':
                                        fila.errores[`novedades.${c.id}`],
                                    'text-red-700 dark:text-red-400':
                                        c.tipo === 'descuento',
                                }"
                                :title="fila.errores[`novedades.${c.id}`]"
                                :aria-label="`${c.nombre} de ${fila.linea.nombre}`"
                                placeholder="0.00"
                                @focus="alEnfocar(i)"
                                @input="marcarCambio(fila)"
                                @keydown.enter="
                                    mover($event, i, COLUMNAS_FIJAS.length + k)
                                "
                                @keydown.down="
                                    mover($event, i, COLUMNAS_FIJAS.length + k)
                                "
                                @keydown.up="
                                    mover($event, i, COLUMNAS_FIJAS.length + k)
                                "
                            />
                        </td>

                        <td
                            v-if="mostrarNeto"
                            class="px-3 py-1.5 text-right font-medium tabular-nums"
                            :class="{
                                'text-muted-foreground line-through':
                                    fila.netoDesactualizado,
                            }"
                            :title="
                                fila.netoDesactualizado
                                    ? 'Cambiaste novedades: vuelve a calcular'
                                    : undefined
                            "
                        >
                            {{
                                fila.linea.neto_pagar === null
                                    ? '—'
                                    : soles.format(
                                          centimos(fila.linea.neto_pagar) / 100,
                                      )
                            }}
                        </td>

                        <td
                            class="px-2 py-1.5 text-center"
                            :title="fila.mensaje ?? undefined"
                        >
                            <Loader2
                                v-if="fila.estado === 'guardando'"
                                class="mx-auto size-4 animate-spin text-muted-foreground"
                            />
                            <CircleAlert
                                v-else-if="fila.estado === 'error'"
                                class="mx-auto size-4 text-red-600"
                            />
                            <span
                                v-else-if="fila.estado === 'cambiado'"
                                class="mx-auto block size-2 rounded-full bg-amber-500"
                                title="Cambios sin guardar"
                            />
                            <Check
                                v-else
                                class="mx-auto size-4 text-muted-foreground/40"
                            />
                        </td>
                    </tr>
                    <tr v-if="filas.length === 0">
                        <td
                            :colspan="
                                8 + visibles.length + (mostrarNeto ? 1 : 0)
                            "
                            class="px-4 py-10 text-center text-muted-foreground"
                        >
                            Ningún trabajador laboró en este mes. Registra
                            trabajadores y usa «Actualizar trabajadores».
                        </td>
                    </tr>
                </tbody>
                <tfoot v-if="filas.length" class="border-t bg-muted/30 text-xs">
                    <tr>
                        <td
                            class="sticky left-0 z-10 bg-muted px-3 py-2 font-medium"
                        >
                            {{ filas.length }}
                            {{
                                filas.length === 1
                                    ? 'trabajador'
                                    : 'trabajadores'
                            }}
                        </td>
                        <td />
                        <td
                            v-for="c in COLUMNAS_FIJAS"
                            :key="c.campo"
                            class="px-3 py-2 text-right tabular-nums"
                        >
                            {{ totalCampo(c.campo) }}
                        </td>
                        <td
                            class="px-2 py-2 text-right font-medium tabular-nums"
                        >
                            {{ totalDiasPagados }}
                        </td>
                        <td
                            v-for="c in visibles"
                            :key="c.id"
                            class="px-3 py-2 text-right tabular-nums"
                        >
                            {{ totalConcepto(c.id) }}
                        </td>
                        <td
                            v-if="mostrarNeto"
                            class="px-3 py-2 text-right font-medium tabular-nums"
                        >
                            {{ totalNeto }}
                        </td>
                        <td />
                    </tr>
                </tfoot>
            </table>
        </div>

        <p
            v-for="fila in filas.filter(
                (f) => f.estado === 'error' && f.mensaje,
            )"
            :key="fila.linea.id"
            class="flex items-center gap-2 text-sm text-red-700 dark:text-red-400"
        >
            <CircleAlert class="size-4" />
            {{ fila.linea.nombre }}: {{ fila.mensaje }}
        </p>
    </div>
</template>
