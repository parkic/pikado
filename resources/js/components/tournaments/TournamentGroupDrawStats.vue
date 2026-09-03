<script setup lang="ts">
defineProps<{
    tournamentName: string;
    matchModeLabel: string;
    participantsCount: number;
    totalSlots: number;
}>();
</script>

<template>
    <div class="grid gap-4 md:grid-cols-3">
        <div
            class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
        >
            <p class="text-sm text-muted-foreground">Turnir</p>

            <p class="mt-2 text-lg font-medium">
                {{ tournamentName }}
            </p>
        </div>

        <div
            class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
        >
            <p class="text-sm text-muted-foreground">Format</p>

            <p class="mt-2 text-lg font-medium">
                {{ matchModeLabel }}
            </p>
        </div>

        <div
            class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
        >
            <p class="text-sm text-muted-foreground">Popunjeno</p>

            <p class="mt-2 text-lg font-medium">
                {{ participantsCount }} / {{ totalSlots }}
            </p>
        </div>
    </div>
</template>
