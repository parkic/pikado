<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{
    gameTypeLabel: string;
    matchModeLabel: string;
    groupsCount: number;
    participantsCount: number;
    totalSlots: number;
    groupMatchesCount: number;
    finishedGroupMatchesCount: number;
    resourcesCount: number;
}>();

const progress = computed(() => {
    if (props.groupMatchesCount < 1) {
        return 0;
    }

    return Math.round(
        (props.finishedGroupMatchesCount / props.groupMatchesCount) * 100,
    );
});
</script>

<template>
    <div class="grid grid-cols-2 gap-3 xl:grid-cols-4">
        <div
            class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
        >
            <p class="text-xs text-muted-foreground">Format</p>
            <p class="mt-2 text-lg font-semibold">
                {{ gameTypeLabel }} · {{ matchModeLabel }}
            </p>
        </div>

        <div
            class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
        >
            <p class="text-xs text-muted-foreground">Učesnici</p>
            <p class="mt-2 text-lg font-semibold">
                {{ participantsCount }} / {{ totalSlots }}
            </p>
        </div>

        <div
            class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
        >
            <div class="flex items-center justify-between gap-2">
                <p class="text-xs text-muted-foreground">Grupni mečevi</p>
                <span class="text-xs font-medium">{{ progress }}%</span>
            </div>
            <p class="mt-2 text-lg font-semibold">
                {{ finishedGroupMatchesCount }} / {{ groupMatchesCount }}
            </p>
            <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-muted">
                <div
                    class="h-full rounded-full bg-primary"
                    :style="{ width: `${progress}%` }"
                />
            </div>
        </div>

        <div
            class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
        >
            <p class="text-xs text-muted-foreground">Postavka</p>
            <p class="mt-2 text-lg font-semibold">
                {{ groupsCount }} grupa · {{ resourcesCount }} komada opreme
            </p>
        </div>
    </div>
</template>
