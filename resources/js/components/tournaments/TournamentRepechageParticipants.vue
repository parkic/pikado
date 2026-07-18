<script setup lang="ts">
import type {
    TournamentRepechageParticipant,
} from '@/types/tournament';

defineProps<{
    directQualifiers: TournamentRepechageParticipant[];
    repechageParticipants: TournamentRepechageParticipant[];
    eliminatedParticipants: TournamentRepechageParticipant[];
}>();

const emit = defineEmits<{
    'update-outcome': [
        participant: TournamentRepechageParticipant,
        event: Event,
    ];
}>();

const differenceLabel = (
    difference: number,
): string => {
    if (difference > 0) {
        return `+${difference}`;
    }

    return String(difference);
};

const repechageOutcomeBadgeClasses = (
    status: string | null,
): string => {
    if (status === 'advanced') {
        return 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300';
    }

    if (status === 'eliminated') {
        return 'bg-muted text-muted-foreground';
    }

    return 'bg-yellow-500/10 text-yellow-700 dark:text-yellow-300';
};
</script>

<template>
    <div class="space-y-6">
        <div class="grid gap-6 xl:grid-cols-2">
            <div
                class="rounded-xl border border-yellow-500/30 p-4 dark:border-yellow-500/30"
            >
                <div>
                    <h2 class="text-lg font-medium">
                        Učesnici za repasaž
                    </h2>

                    <p class="mt-1 text-sm text-muted-foreground">
                        Sortirani po učinku iz grupne faze.
                    </p>
                </div>

                <div
                    v-if="repechageParticipants.length"
                    class="mt-4 overflow-hidden rounded-lg border border-sidebar-border/70 dark:border-sidebar-border"
                >
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead
                                class="border-b border-sidebar-border/70 bg-muted/40 dark:border-sidebar-border"
                            >
                                <tr>
                                    <th class="px-3 py-3 font-medium">
                                        #
                                    </th>

                                    <th class="px-3 py-3 font-medium">
                                        Učesnik
                                    </th>

                                    <th class="px-3 py-3 text-center font-medium">
                                        Grupa
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

                                    <th class="px-3 py-3 font-medium">
                                        Ishod
                                    </th>
                                </tr>
                            </thead>

                            <tbody>
                                <tr
                                    v-for="(
                                        participant,
                                        index
                                    ) in repechageParticipants"
                                    :key="participant.participant_id"
                                    class="border-b border-sidebar-border/70 last:border-b-0 dark:border-sidebar-border"
                                >
                                    <td
                                        class="px-3 py-3 text-muted-foreground"
                                    >
                                        {{ index + 1 }}
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

                                    <td class="px-3 py-3">
                                        <div
                                            class="flex min-w-48 flex-col gap-2"
                                        >
                                            <span
                                                class="inline-flex w-fit rounded-full px-2.5 py-1 text-xs font-medium"
                                                :class="
                                                    repechageOutcomeBadgeClasses(
                                                        participant.repechage_outcome_status,
                                                    )
                                                "
                                            >
                                                {{
                                                    participant.repechage_outcome_label
                                                }}
                                            </span>

                                            <select
                                                :value="
                                                    participant.repechage_outcome_status
                                                        ?? ''
                                                "
                                                class="rounded-lg border border-sidebar-border/70 bg-background px-2 py-2 text-xs outline-none transition focus:border-primary dark:border-sidebar-border"
                                                @change="
                                                    emit(
                                                        'update-outcome',
                                                        participant,
                                                        $event,
                                                    )
                                                "
                                            >
                                                <option value="">
                                                    Neodlučeno
                                                </option>

                                                <option value="advanced">
                                                    Prošao iz repasaža
                                                </option>

                                                <option value="eliminated">
                                                    Ispao posle repasaža
                                                </option>
                                            </select>
                                        </div>
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
                    Nema učesnika za repasaž.
                </div>
            </div>

            <div
                class="rounded-xl border border-emerald-500/30 p-4 dark:border-emerald-500/30"
            >
                <div>
                    <h2 class="text-lg font-medium">
                        Direktan prolaz
                    </h2>

                    <p class="mt-1 text-sm text-muted-foreground">
                        Ovi učesnici čekaju sledeću fazu.
                    </p>
                </div>

                <div
                    v-if="directQualifiers.length"
                    class="mt-4 overflow-hidden rounded-lg border border-sidebar-border/70 dark:border-sidebar-border"
                >
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead
                                class="border-b border-sidebar-border/70 bg-muted/40 dark:border-sidebar-border"
                            >
                                <tr>
                                    <th class="px-3 py-3 font-medium">
                                        #
                                    </th>

                                    <th class="px-3 py-3 font-medium">
                                        Učesnik
                                    </th>

                                    <th class="px-3 py-3 text-center font-medium">
                                        Grupa
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
                                    v-for="(
                                        participant,
                                        index
                                    ) in directQualifiers"
                                    :key="participant.participant_id"
                                    class="border-b border-sidebar-border/70 last:border-b-0 dark:border-sidebar-border"
                                >
                                    <td
                                        class="px-3 py-3 text-muted-foreground"
                                    >
                                        {{ index + 1 }}
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
                    Nema direktnih prolaza.
                </div>
            </div>
        </div>

        <div
            class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
        >
            <div>
                <h2 class="text-lg font-medium">
                    Ispali
                </h2>

                <p class="mt-1 text-sm text-muted-foreground">
                    Učesnici koji ne nastavljaju takmičenje.
                </p>
            </div>

            <div
                v-if="eliminatedParticipants.length"
                class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-4"
            >
                <div
                    v-for="participant in eliminatedParticipants"
                    :key="participant.participant_id"
                    class="rounded-lg border border-sidebar-border/70 p-3 text-sm dark:border-sidebar-border"
                >
                    <div class="font-medium">
                        {{ participant.display_name }}
                    </div>

                    <div
                        class="mt-1 text-xs text-muted-foreground"
                    >
                        Grupa {{ participant.group_name }} ·
                        {{ participant.group_position ?? '-' }}
                    </div>
                </div>
            </div>

            <div
                v-else
                class="mt-4 rounded-lg border border-dashed border-sidebar-border/70 p-4 text-sm text-muted-foreground dark:border-sidebar-border"
            >
                Nema eliminisanih učesnika.
            </div>
        </div>
    </div>
</template>
