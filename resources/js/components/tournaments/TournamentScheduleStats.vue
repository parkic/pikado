<script setup lang="ts">
defineProps<{
    matchesCount: number;
    groupMatchesCount: number;
    tournamentStatus: string;
}>();
</script>

<template>
    <div class="grid gap-4 md:grid-cols-3">
        <div
            class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
        >
            <p class="text-sm text-muted-foreground">
                Ukupno mečeva
            </p>

            <p class="mt-2 text-lg font-medium">
                {{ matchesCount }}
            </p>
        </div>

        <div
            class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
        >
            <p class="text-sm text-muted-foreground">
                Grupni mečevi
            </p>

            <p class="mt-2 text-lg font-medium">
                {{ groupMatchesCount }}
            </p>
        </div>

        <div
            class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
        >
            <p class="text-sm text-muted-foreground">
                Status turnira
            </p>

            <p class="mt-2 text-lg font-medium">
                {{ tournamentStatus }}
            </p>
        </div>
    </div>
</template>
