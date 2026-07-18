<script setup lang="ts">
defineProps<{
    gameTypeLabel: string;
    matchModeLabel: string;
    groupsCount: number;
    participantsCount: number;
    totalSlots: number;
    groupMatchesCount: number;
    finishedGroupMatchesCount: number;
    resourcesCount: number;
}>();
</script>

<template>
    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-7">
        <div class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
            <p class="text-sm text-muted-foreground">
                Igra
            </p>

            <p class="mt-2 text-lg font-medium">
                {{ gameTypeLabel }}
            </p>
        </div>

        <div class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
            <p class="text-sm text-muted-foreground">
                Format
            </p>

            <p class="mt-2 text-lg font-medium">
                {{ matchModeLabel }}
            </p>
        </div>

        <div class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
            <p class="text-sm text-muted-foreground">
                Grupe
            </p>

            <p class="mt-2 text-lg font-medium">
                {{ groupsCount }}
            </p>
        </div>

        <div class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
            <p class="text-sm text-muted-foreground">
                Učesnici
            </p>

            <p class="mt-2 text-lg font-medium">
                {{ participantsCount }} / {{ totalSlots }}
            </p>
        </div>

        <div class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
            <p class="text-sm text-muted-foreground">
                Grupni mečevi
            </p>

            <p class="mt-2 text-lg font-medium">
                {{ groupMatchesCount }}
            </p>
        </div>

        <div class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
            <p class="text-sm text-muted-foreground">
                Završeni grupni
            </p>

            <p class="mt-2 text-lg font-medium">
                {{ finishedGroupMatchesCount }} / {{ groupMatchesCount }}
            </p>
        </div>

        <div class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
            <p class="text-sm text-muted-foreground">
                Resources
            </p>

            <p class="mt-2 text-lg font-medium">
                {{ resourcesCount }}
            </p>
        </div>
    </div>
</template>
