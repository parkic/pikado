<script setup lang="ts">
import { computed } from 'vue';

import type { TournamentStatus } from '@/types/tournament';

const props = defineProps<{
    status: TournamentStatus;
    label: string;
}>();

const badgeClasses = computed((): string => {
    if (props.status === 'draft') {
        return 'bg-yellow-500/10 text-yellow-700 dark:text-yellow-300';
    }

    if (props.status === 'finished') {
        return 'bg-muted text-muted-foreground';
    }

    return 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300';
});
</script>

<template>
    <span
        class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium"
        :class="badgeClasses"
    >
        {{ label }}
    </span>
</template>
