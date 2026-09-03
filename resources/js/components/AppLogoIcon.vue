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

<template>
    <svg
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 48 48"
        :class="className"
        v-bind="$attrs"
        fill="none"
        aria-hidden="true"
    >
        <circle
            cx="22"
            cy="26"
            r="15.5"
            stroke="currentColor"
            stroke-width="3"
        />
        <circle
            cx="22"
            cy="26"
            r="9.5"
            stroke="currentColor"
            stroke-width="3"
        />
        <circle cx="22" cy="26" r="3" fill="currentColor" />
        <path
            d="M23.5 24.5 37.5 10.5M34 9l5.5-1.5L38 13M36.8 11.2l4.7 4.7"
            stroke="currentColor"
            stroke-width="3"
            stroke-linecap="round"
            stroke-linejoin="round"
        />
    </svg>
</template>
