<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

type Props = {
    className?: HTMLAttributes['class'];
};

defineProps<Props>();
</script>

<!-- Ícono de Serialo.pe (versión simplificada del logo: engranaje y código de barras). Mismo dibujo que public/favicon.svg. -->
<template>
    <svg
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 32 32"
        role="img"
        aria-label="Serialo.pe"
        :class="className"
        v-bind="$attrs"
    >
        <rect width="32" height="32" rx="7" fill="#2558a8" />
        <g fill="#7fcbe8">
            <rect
                x="9.4"
                y="7.6"
                width="3.2"
                height="4.4"
                rx="0.7"
                transform="rotate(0 11 16)"
            />
            <rect
                x="9.4"
                y="7.6"
                width="3.2"
                height="4.4"
                rx="0.7"
                transform="rotate(45 11 16)"
            />
            <rect
                x="9.4"
                y="7.6"
                width="3.2"
                height="4.4"
                rx="0.7"
                transform="rotate(90 11 16)"
            />
            <rect
                x="9.4"
                y="7.6"
                width="3.2"
                height="4.4"
                rx="0.7"
                transform="rotate(135 11 16)"
            />
            <rect
                x="9.4"
                y="7.6"
                width="3.2"
                height="4.4"
                rx="0.7"
                transform="rotate(180 11 16)"
            />
            <rect
                x="9.4"
                y="7.6"
                width="3.2"
                height="4.4"
                rx="0.7"
                transform="rotate(225 11 16)"
            />
            <rect
                x="9.4"
                y="7.6"
                width="3.2"
                height="4.4"
                rx="0.7"
                transform="rotate(270 11 16)"
            />
            <rect
                x="9.4"
                y="7.6"
                width="3.2"
                height="4.4"
                rx="0.7"
                transform="rotate(315 11 16)"
            />
            <circle cx="11" cy="16" r="5.4" />
        </g>
        <circle cx="11" cy="16" r="2.3" fill="#2558a8" />
        <g fill="#ffffff">
            <rect x="20.4" y="9.5" width="1.6" height="13" rx="0.4" />
            <rect x="23" y="9.5" width="0.9" height="13" rx="0.3" />
            <rect x="24.8" y="9.5" width="1.9" height="13" rx="0.4" />
            <rect x="27.5" y="9.5" width="0.9" height="13" rx="0.3" />
        </g>
    </svg>
</template>
