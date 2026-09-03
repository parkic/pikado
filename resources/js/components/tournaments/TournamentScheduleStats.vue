<script setup lang="ts">
defineProps<{
    matchesCount: number;
    finishedMatchesCount: number;
    tournamentStatus: string;
}>();
</script>

<template>
    <div class="grid grid-cols-2 gap-3 xl:grid-cols-4">
        <div
            class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
        >
            <p class="text-sm text-muted-foreground">Ukupno mečeva</p>

            <p class="mt-2 text-xl font-semibold">
                {{ matchesCount }}
            </p>
        </div>

        <div
            class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
        >
            <p class="text-sm text-muted-foreground">Završeno</p>

            <p class="mt-2 text-xl font-semibold">
                {{ finishedMatchesCount }}
            </p>
        </div>

        <div
            class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
        >
            <p class="text-sm text-muted-foreground">Preostalo</p>

            <p class="mt-2 text-xl font-semibold">
                {{ matchesCount - finishedMatchesCount }}
            </p>
        </div>

        <div
            class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
        >
            <p class="text-sm text-muted-foreground">Status turnira</p>

            <p class="mt-2 text-base font-semibold">
                {{ tournamentStatus }}
            </p>
        </div>
    </div>
</template>
