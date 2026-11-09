<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { aNumero, formatoCompacto, marcasEje } from '@/lib/graficos';

export type SerieLinea = {
    clave: string;
    nombre: string;
    /** Color de la serie (variable CSS, p. ej. `var(--serie-1)`). */
    color: string;
    valores: (string | null)[];
};

const props = withDefaults(
    defineProps<{
        etiquetas: string[];
        series: SerieLinea[];
        /** Texto completo de un valor (tooltip y tabla). */
        formato: (valor: string) => string;
        /** Prefijo de las marcas del eje (p. ej. "S/"). */
        prefijoEje?: string;
        alto?: number;
        descripcion: string;
    }>(),
    { prefijoEje: '', alto: 260 },
);

const contenedor = ref<HTMLDivElement | null>(null);
const ancho = ref(640);
const activo = ref<number | null>(null);
let observador: ResizeObserver | null = null;

onMounted(() => {
    if (!contenedor.value) {
        return;
    }

    ancho.value = contenedor.value.clientWidth;
    observador = new ResizeObserver(([entrada]) => {
        ancho.value = Math.max(280, entrada.contentRect.width);
    });
    observador.observe(contenedor.value);
});

onBeforeUnmount(() => observador?.disconnect());

const margen = { arriba: 12, derecha: 16, abajo: 28, izquierda: 52 };
const anchoPlot = computed(
    () => ancho.value - margen.izquierda - margen.derecha,
);
const altoPlot = computed(() => props.alto - margen.arriba - margen.abajo);

const numeros = computed(() => props.series.map((s) => s.valores.map(aNumero)));
const marcas = computed(() => {
    const max = Math.max(0, ...numeros.value.flat().map((v) => v ?? 0));

    return marcasEje(max);
});
const tope = computed(() => marcas.value[marcas.value.length - 1] || 1);

function x(i: number): number {
    const n = props.etiquetas.length;

    return (
        margen.izquierda +
        (n <= 1 ? anchoPlot.value / 2 : (i * anchoPlot.value) / (n - 1))
    );
}

function y(v: number): number {
    return margen.arriba + altoPlot.value - (v / tope.value) * altoPlot.value;
}

/** Trazos por serie; un mes sin datos corta la línea (no se dibuja como cero). */
const trazos = computed(() =>
    numeros.value.map((valores) => {
        const tramos: string[] = [];
        const sueltos: { cx: number; cy: number }[] = [];
        let actual: [number, number][] = [];
        const cerrar = () => {
            if (actual.length === 1) {
                sueltos.push({ cx: actual[0][0], cy: actual[0][1] });
            } else if (actual.length > 1) {
                tramos.push(
                    actual
                        .map(
                            ([px, py], k) =>
                                `${k ? 'L' : 'M'}${px.toFixed(1)},${py.toFixed(1)}`,
                        )
                        .join(''),
                );
            }

            actual = [];
        };

        valores.forEach((v, i) =>
            v === null ? cerrar() : actual.push([x(i), y(v)]),
        );
        cerrar();

        return { tramos, sueltos };
    }),
);

/** Mostrar etiqueta del eje X cada `salto` meses para que no se encimen. */
const salto = computed(() =>
    Math.max(
        1,
        Math.ceil(props.etiquetas.length / Math.floor(anchoPlot.value / 56)),
    ),
);

function mover(evento: PointerEvent): void {
    const caja = (
        evento.currentTarget as SVGRectElement
    ).getBoundingClientRect();
    const px = evento.clientX - caja.left;
    const n = props.etiquetas.length;
    const i = n <= 1 ? 0 : Math.round((px / caja.width) * (n - 1));
    activo.value = Math.min(n - 1, Math.max(0, i));
}

function teclado(evento: KeyboardEvent): void {
    const n = props.etiquetas.length;

    if (evento.key === 'ArrowRight' || evento.key === 'ArrowLeft') {
        evento.preventDefault();
        const base = activo.value ?? n - 1;
        activo.value = Math.min(
            n - 1,
            Math.max(0, base + (evento.key === 'ArrowRight' ? 1 : -1)),
        );
    } else if (evento.key === 'Escape') {
        activo.value = null;
    }
}

const tooltip = computed(() => {
    if (activo.value === null) {
        return null;
    }

    const i = activo.value;
    const izquierda = x(i) > ancho.value / 2;

    return {
        etiqueta: props.etiquetas[i],
        estilo: izquierda
            ? { right: `${ancho.value - x(i) + 12}px` }
            : { left: `${x(i) + 12}px` },
        filas: props.series.map((s) => ({
            nombre: s.nombre,
            color: s.color,
            valor: s.valores[i],
        })),
    };
});

const verTabla = ref(false);
</script>

<template>
    <div class="grid min-w-0 gap-3">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <ul
                class="flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted-foreground"
            >
                <li
                    v-for="s in series"
                    :key="s.clave"
                    class="flex items-center gap-1.5"
                >
                    <span
                        class="h-0.5 w-4 rounded-full"
                        :style="{ background: s.color }"
                    />
                    {{ s.nombre }}
                </li>
            </ul>
            <button
                type="button"
                class="text-xs text-muted-foreground underline-offset-2 hover:text-foreground hover:underline"
                @click="verTabla = !verTabla"
            >
                {{ verTabla ? 'Ver gráfico' : 'Ver tabla' }}
            </button>
        </div>

        <div
            v-show="!verTabla"
            ref="contenedor"
            class="relative w-full min-w-0 overflow-hidden"
        >
            <svg
                :width="ancho"
                :height="alto"
                role="img"
                :aria-label="descripcion"
                class="block overflow-visible"
            >
                <!-- Rejilla y eje Y -->
                <g>
                    <template v-for="m in marcas" :key="m">
                        <line
                            :x1="margen.izquierda"
                            :x2="ancho - margen.derecha"
                            :y1="y(m)"
                            :y2="y(m)"
                            class="stroke-border"
                            stroke-width="1"
                        />
                        <text
                            :x="margen.izquierda - 8"
                            :y="y(m)"
                            text-anchor="end"
                            dominant-baseline="middle"
                            class="fill-muted-foreground text-[11px] tabular-nums"
                        >
                            {{
                                m === 0
                                    ? '0'
                                    : `${prefijoEje}${formatoCompacto(m)}`
                            }}
                        </text>
                    </template>
                </g>

                <!-- Eje X -->
                <g>
                    <template v-for="(e, i) in etiquetas" :key="e">
                        <text
                            v-if="i % salto === (etiquetas.length - 1) % salto"
                            :x="x(i)"
                            :y="alto - 8"
                            text-anchor="middle"
                            class="fill-muted-foreground text-[11px]"
                            :class="
                                activo === i
                                    ? 'fill-foreground font-medium'
                                    : ''
                            "
                        >
                            {{ e }}
                        </text>
                    </template>
                </g>

                <!-- Guía vertical del mes activo -->
                <line
                    v-if="activo !== null"
                    :x1="x(activo)"
                    :x2="x(activo)"
                    :y1="margen.arriba"
                    :y2="margen.arriba + altoPlot"
                    class="stroke-muted-foreground/50"
                    stroke-width="1"
                />

                <!-- Series -->
                <g v-for="(s, k) in series" :key="s.clave">
                    <path
                        v-for="(d, j) in trazos[k].tramos"
                        :key="j"
                        :d="d"
                        fill="none"
                        :stroke="s.color"
                        stroke-width="2"
                        stroke-linejoin="round"
                        stroke-linecap="round"
                    />
                    <circle
                        v-for="(p, j) in trazos[k].sueltos"
                        :key="`s${j}`"
                        :cx="p.cx"
                        :cy="p.cy"
                        r="4"
                        :fill="s.color"
                        class="stroke-background"
                        stroke-width="2"
                    />
                    <circle
                        v-if="activo !== null && numeros[k][activo] !== null"
                        :cx="x(activo)"
                        :cy="y(numeros[k][activo] as number)"
                        r="5"
                        :fill="s.color"
                        class="stroke-background"
                        stroke-width="2"
                    />
                </g>

                <!-- Zona de lectura (más grande que las marcas) -->
                <rect
                    :x="margen.izquierda - 8"
                    :y="margen.arriba"
                    :width="anchoPlot + 16"
                    :height="altoPlot"
                    fill="transparent"
                    tabindex="0"
                    class="cursor-crosshair outline-none focus-visible:stroke-ring"
                    :aria-label="`${descripcion}. Usa las flechas para recorrer los meses.`"
                    @pointermove="mover"
                    @pointerdown="mover"
                    @pointerleave="activo = null"
                    @keydown="teclado"
                    @blur="activo = null"
                />
            </svg>

            <div
                v-if="tooltip"
                class="pointer-events-none absolute top-2 z-10 min-w-44 rounded-md border bg-popover px-3 py-2 text-xs text-popover-foreground shadow-md"
                :style="tooltip.estilo"
            >
                <p class="mb-1 font-medium">{{ tooltip.etiqueta }}</p>
                <dl class="grid gap-1">
                    <div
                        v-for="f in tooltip.filas"
                        :key="f.nombre"
                        class="flex items-center justify-between gap-4"
                    >
                        <dt
                            class="flex items-center gap-1.5 text-muted-foreground"
                        >
                            <span
                                class="h-0.5 w-3 rounded-full"
                                :style="{ background: f.color }"
                            />
                            {{ f.nombre }}
                        </dt>
                        <dd class="font-medium tabular-nums">
                            {{
                                f.valor === null
                                    ? 'Sin planilla'
                                    : formato(f.valor)
                            }}
                        </dd>
                    </div>
                </dl>
            </div>
        </div>

        <div v-if="verTabla" class="overflow-x-auto rounded-md border">
            <table class="w-full text-xs">
                <thead class="bg-muted/40 text-muted-foreground">
                    <tr>
                        <th class="px-3 py-2 text-left font-medium">Mes</th>
                        <th
                            v-for="s in series"
                            :key="s.clave"
                            class="px-3 py-2 text-right font-medium"
                        >
                            {{ s.nombre }}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(e, i) in etiquetas" :key="e" class="border-t">
                        <td class="px-3 py-1.5">{{ e }}</td>
                        <td
                            v-for="s in series"
                            :key="s.clave"
                            class="px-3 py-1.5 text-right tabular-nums"
                        >
                            {{
                                s.valores[i] === null
                                    ? '—'
                                    : formato(s.valores[i] as string)
                            }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
