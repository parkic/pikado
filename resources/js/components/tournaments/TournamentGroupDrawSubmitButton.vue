<script setup lang="ts">
defineProps<{
    processing: boolean;
    activeGroupPosition: string | null;
}>();
</script>

<template>
    <button
        type="submit"
        :disabled="processing || !activeGroupPosition"
        class="mt-6 inline-flex w-full items-center justify-center rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground transition hover:opacity-90 disabled:opacity-50"
    >
        <span v-if="activeGroupPosition">
            {{ processing ? 'Dodajem...' : `Dodaj u ${activeGroupPosition}` }}
        </span>

        <span v-else> Grupe su popunjene </span>
    </button>
</template>
