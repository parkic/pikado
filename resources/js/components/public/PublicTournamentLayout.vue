<script setup lang="ts">
import type { PublicVenueBranding } from '@/types';

defineProps<{
    theme: PublicVenueBranding['public_theme'];
}>();
</script>

<template>
    <div
        class="public-tournament min-h-screen"
        :class="`public-theme-${theme}`"
    >
        <main
            class="mx-auto flex w-full max-w-full flex-col gap-4 px-3 py-3 md:gap-6 md:px-8 md:py-6"
        >
            <slot />
        </main>
    </div>
</template>

<style>
.public-tournament {
    --public-bg: #09090b;
    --public-fg: #fafafa;
    --public-muted: #a1a1aa;
    --public-subtle: #71717a;
    --public-panel: rgb(255 255 255 / 0.03);
    --public-panel-strong: rgb(0 0 0 / 0.3);
    --public-border: rgb(255 255 255 / 0.1);
    --public-nav: rgb(255 255 255 / 0.04);
    color-scheme: dark;
    background: var(--public-bg);
    color: var(--public-fg);
}

.public-tournament.public-theme-light {
    --public-bg: #f4f4f5;
    --public-fg: #18181b;
    --public-muted: #52525b;
    --public-subtle: #71717a;
    --public-panel: rgb(255 255 255 / 0.82);
    --public-panel-strong: rgb(255 255 255 / 0.95);
    --public-border: rgb(24 24 27 / 0.12);
    --public-nav: rgb(24 24 27 / 0.045);
    color-scheme: light;
}

.public-theme-light .bg-zinc-950,
.public-theme-light.bg-zinc-950 {
    background-color: var(--public-bg) !important;
}

.public-theme-light .bg-zinc-950\/80,
.public-theme-light.bg-zinc-950\/80 {
    background-color: rgb(244 244 245 / 0.88) !important;
}

.public-theme-light .bg-black\/25,
.public-theme-light .bg-black\/30 {
    background-color: var(--public-panel-strong) !important;
}

.public-theme-light .bg-white\/5,
.public-theme-light .bg-white\/\[0\.025\],
.public-theme-light .bg-white\/\[0\.03\],
.public-theme-light .bg-white\/\[0\.035\],
.public-theme-light .bg-white\/\[0\.04\],
.public-theme-light .bg-white\/\[0\.045\],
.public-theme-light .bg-white\/\[0\.06\] {
    background-color: var(--public-panel) !important;
}

.public-theme-light .bg-white\/10 {
    background-color: rgb(24 24 27 / 0.07) !important;
}

.public-theme-light .bg-zinc-700 {
    background-color: #e4e4e7 !important;
}

.public-theme-light .border-white\/10,
.public-theme-light .border-white\/15,
.public-theme-light .border-white\/25,
.public-theme-light .border-white\/30,
.public-theme-light .border-white\/\[0\.07\] {
    border-color: var(--public-border) !important;
}

.public-theme-light .text-zinc-50,
.public-theme-light .text-zinc-100,
.public-theme-light .text-zinc-200 {
    color: var(--public-fg) !important;
}

.public-theme-light .text-zinc-300,
.public-theme-light .text-zinc-400 {
    color: var(--public-muted) !important;
}

.public-theme-light .text-zinc-500,
.public-theme-light .text-zinc-600 {
    color: var(--public-subtle) !important;
}

.public-theme-light .text-white\/20 {
    color: rgb(24 24 27 / 0.2) !important;
}

.public-theme-light .text-emerald-200,
.public-theme-light .text-emerald-300 {
    color: #047857 !important;
}

.public-theme-light .text-amber-100,
.public-theme-light .text-amber-200,
.public-theme-light .text-yellow-200 {
    color: #92400e !important;
}

.public-theme-light .text-sky-200 {
    color: #0369a1 !important;
}

.public-theme-light .text-red-200 {
    color: #b91c1c !important;
}

.public-theme-light .text-orange-100,
.public-theme-light .text-orange-200,
.public-theme-light .text-orange-300 {
    color: #c2410c !important;
}
</style>
