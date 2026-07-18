<script setup lang="ts">
import type { TournamentGroupSummary } from '@/types/tournament';

defineProps<{
    groups: TournamentGroupSummary[];
}>();
</script>

<template>
    <div class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
        <h2 class="text-lg font-medium">
            Trenutne grupe
        </h2>

        <div
            v-if="groups.length"
            class="mt-4 grid gap-3 md:grid-cols-4"
        >
            <div
                v-for="group in groups"
                :key="group.id"
                class="rounded-lg border border-sidebar-border/70 p-3 dark:border-sidebar-border"
            >
                <p class="text-sm text-muted-foreground">
                    Grupa
                </p>

                <p class="mt-1 text-lg font-medium">
                    {{ group.name }}
                </p>
            </div>
        </div>

        <div
            v-else
            class="mt-4 rounded-lg border border-dashed border-sidebar-border/70 p-4 text-sm text-muted-foreground dark:border-sidebar-border"
        >
            Grupe još nisu napravljene.
        </div>
    </div>
</template>
