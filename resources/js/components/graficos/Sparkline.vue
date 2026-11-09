<script setup lang="ts">
import { computed } from 'vue';

/** Tendencia mínima de una tarjeta: línea de 2px y punto en el último valor. Sin ejes. */
const props = withDefaults(
    defineProps<{
        valores: (number | null)[];
        color?: string;
        ancho?: number;
        alto?: number;
        descripcion: string;
    }>(),
    { color: 'var(--serie-1)', ancho: 120, alto: 36 },
);

const pad = 5;

const puntos = computed(() => {
    const definidos = props.valores.filter((v): v is number => v !== null);
    const max = Math.max(...definidos, 0);
    const min = Math.min(...definidos, max);
    const rango = max - min || 1;
    const n = props.valores.length;

    return props.valores.map((v, i) =>
        v === null
            ? null
            : {
                  x:
                      pad +
                      (n <= 1 ? 0 : (i * (props.ancho - pad * 2)) / (n - 1)),
                  y: pad + (props.alto - pad * 2) * (1 - (v - min) / rango),
              },
    );
});

const trazo = computed(() => {
    let d = '';
    let nuevo = true;

    for (const p of puntos.value) {
        if (p === null) {
            nuevo = true;
            continue;
        }

        d += `${nuevo ? 'M' : 'L'}${p.x.toFixed(1)},${p.y.toFixed(1)}`;
        nuevo = false;
    }

    return d;
});

const ultimo = computed(
    () => [...puntos.value].reverse().find((p) => p !== null) ?? null,
);
</script>

<template>
    <svg
        :width="ancho"
        :height="alto"
        role="img"
        :aria-label="descripcion"
        class="block shrink-0 overflow-visible"
    >
        <path
            :d="trazo"
            fill="none"
            :stroke="color"
            stroke-width="2"
            stroke-linejoin="round"
            stroke-linecap="round"
        />
        <circle
            v-if="ultimo"
            :cx="ultimo.x"
            :cy="ultimo.y"
            r="4"
            :fill="color"
            class="stroke-card"
            stroke-width="2"
        />
    </svg>
</template>
