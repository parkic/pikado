<script setup lang="ts">
import type {
    TournamentKnockoutParticipant,
} from '@/types/tournament';

defineProps<{
    participants: TournamentKnockoutParticipant[];
    matchesCount: number;
}>();

const sourceBadgeClasses = (
    source: string,
): string => {
    if (source === 'direct') {
        return 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300';
    }

    return 'bg-yellow-500/10 text-yellow-700 dark:text-yellow-300';
};

const differenceLabel = (
    difference: number,
): string => {
    if (difference > 0) {
        return `+${difference}`;
    }

    return String(difference);
};
</script>

<template>
    <div
        class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
    >
        <div>
            <h2 class="text-lg font-medium">
                Učesnici za nokaut
            </h2>

            <p class="mt-1 text-sm text-muted-foreground">
                Lista učesnika koji ulaze u nokaut.
            </p>
        </div>

        <div
            class="mt-4 rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
        >
            <p class="text-sm text-muted-foreground">
                Nokaut mečevi
            </p>

            <p class="mt-2 text-2xl font-semibold">
                {{ matchesCount }}
            </p>
        </div>

        <div
            v-if="participants.length"
            class="mt-4 overflow-hidden rounded-lg border border-sidebar-border/70 dark:border-sidebar-border"
        >
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead
                        class="border-b border-sidebar-border/70 bg-muted/40 dark:border-sidebar-border"
                    >
                        <tr>
                            <th class="px-3 py-3 font-medium">
                                Seed
                            </th>

                            <th class="px-3 py-3 font-medium">
                                Učesnik
                            </th>

                            <th class="px-3 py-3 text-center font-medium">
                                Grupa
                            </th>

                            <th class="px-3 py-3 text-center font-medium">
                                Izvor
                            </th>

                            <th class="px-3 py-3 text-center font-medium">
                                P
                            </th>

                            <th class="px-3 py-3 text-center font-medium">
                                +/-
                            </th>

                            <th class="px-3 py-3 text-center font-medium">
                                Bod
                            </th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr
                            v-for="participant in participants"
                            :key="participant.participant_id"
                            class="border-b border-sidebar-border/70 last:border-b-0 dark:border-sidebar-border"
                        >
                            <td
                                class="px-3 py-3 text-muted-foreground"
                            >
                                {{ participant.seed }}
                            </td>

                            <td class="px-3 py-3">
                                <div class="font-medium">
                                    {{ participant.display_name }}
                                </div>

                                <div
                                    class="mt-1 text-xs text-muted-foreground"
                                >
                                    {{
                                        participant.group_position
                                            ?? '-'
                                    }}
                                </div>
                            </td>

                            <td
                                class="px-3 py-3 text-center text-muted-foreground"
                            >
                                {{ participant.group_name }}
                                /
                                {{ participant.group_rank }}.
                            </td>

                            <td class="px-3 py-3 text-center">
                                <span
                                    class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium"
                                    :class="
                                        sourceBadgeClasses(
                                            participant.source,
                                        )
                                    "
                                >
                                    {{ participant.source_label }}
                                </span>
                            </td>

                            <td
                                class="px-3 py-3 text-center text-muted-foreground"
                            >
                                {{ participant.wins }}
                            </td>

                            <td
                                class="px-3 py-3 text-center font-medium"
                            >
                                {{
                                    differenceLabel(
                                        participant.points_difference,
                                    )
                                }}
                            </td>

                            <td
                                class="px-3 py-3 text-center font-semibold"
                            >
                                {{ participant.standing_points }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div
            v-else
            class="mt-4 rounded-lg border border-dashed border-sidebar-border/70 p-4 text-sm text-muted-foreground dark:border-sidebar-border"
        >
            Još nema učesnika za nokaut.
        </div>
    </div>
</template>
