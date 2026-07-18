<script setup lang="ts">
import type {
    TournamentStandingGroup,
    TournamentStandingRow,
    TournamentStatus,
} from '@/types/tournament';

defineProps<{
    group: TournamentStandingGroup;
    canManageWithdrawals: boolean;
    tournamentStatus: TournamentStatus;
}>();

const emit = defineEmits<{
    'update-qualification-override': [
        row: TournamentStandingRow,
        event: Event,
    ];
    'withdraw-participant': [
        row: TournamentStandingRow,
    ];
    'restore-participant': [
        row: TournamentStandingRow,
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

const qualificationBadgeClasses = (
    status: string,
): string => {
    if (status === 'direct') {
        return 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300';
    }

    if (status === 'repechage') {
        return 'bg-yellow-500/10 text-yellow-700 dark:text-yellow-300';
    }

    if (status === 'withdrawn') {
        return 'bg-red-500/10 text-red-700 dark:text-red-300';
    }

    return 'bg-muted text-muted-foreground';
};
</script>

<template>
    <div
        class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
    >
        <div
            class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between"
        >
            <div>
                <h2 class="text-lg font-medium">
                    Grupa {{ group.name }}
                </h2>

                <p class="mt-1 text-sm text-muted-foreground">
                    Odigrano
                    {{ group.finished_matches_count }}
                    /
                    {{ group.matches_count }}
                    mečeva.
                </p>

                <div class="mt-3 flex flex-wrap gap-2 text-xs">
                    <span
                        class="inline-flex rounded-full bg-emerald-500/10 px-2.5 py-1 font-medium text-emerald-700 dark:text-emerald-300"
                    >
                        Direktan prolaz
                    </span>

                    <span
                        class="inline-flex rounded-full bg-yellow-500/10 px-2.5 py-1 font-medium text-yellow-700 dark:text-yellow-300"
                    >
                        Repasaž
                    </span>

                    <span
                        class="inline-flex rounded-full bg-muted px-2.5 py-1 font-medium text-muted-foreground"
                    >
                        Ispao
                    </span>

                    <span
                        class="inline-flex rounded-full bg-red-500/10 px-2.5 py-1 font-medium text-red-700 dark:text-red-300"
                    >
                        Odustao
                    </span>
                </div>
            </div>
        </div>

        <div
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
                                O
                            </th>

                            <th class="px-3 py-3 text-center font-medium">
                                P
                            </th>

                            <th class="px-3 py-3 text-center font-medium">
                                I
                            </th>

                            <th class="px-3 py-3 text-center font-medium">
                                Za
                            </th>

                            <th class="px-3 py-3 text-center font-medium">
                                Protiv
                            </th>

                            <th class="px-3 py-3 text-center font-medium">
                                +/-
                            </th>

                            <th class="px-3 py-3 text-center font-medium">
                                Bod
                            </th>

                            <th class="px-3 py-3 text-center font-medium">
                                Status
                            </th>

                            <th class="px-3 py-3 text-center font-medium">
                                Akcije
                            </th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr
                            v-for="row in group.rows"
                            :key="row.participant_id"
                            class="border-b border-sidebar-border/70 last:border-b-0 dark:border-sidebar-border"
                        >
                            <td
                                class="px-3 py-3 text-muted-foreground"
                            >
                                {{ row.position ?? '-' }}
                            </td>

                            <td class="px-3 py-3">
                                <div
                                    class="font-medium"
                                    :class="
                                        row.is_withdrawn
                                            ? 'text-muted-foreground line-through'
                                            : ''
                                    "
                                >
                                    {{ row.display_name }}
                                </div>

                                <div
                                    class="mt-1 text-xs text-muted-foreground"
                                >
                                    {{ row.group_position ?? '-' }}
                                </div>

                                <div
                                    v-if="
                                        row.is_withdrawn
                                            && row.withdrawn_at
                                    "
                                    class="mt-1 text-xs text-red-600 dark:text-red-300"
                                >
                                    Odustao:
                                    {{ row.withdrawn_at }}
                                </div>
                            </td>

                            <td
                                class="px-3 py-3 text-center text-muted-foreground"
                            >
                                {{ row.played }}
                            </td>

                            <td
                                class="px-3 py-3 text-center text-muted-foreground"
                            >
                                {{ row.wins }}
                            </td>

                            <td
                                class="px-3 py-3 text-center text-muted-foreground"
                            >
                                {{ row.losses }}
                            </td>

                            <td
                                class="px-3 py-3 text-center text-muted-foreground"
                            >
                                {{ row.points_for }}
                            </td>

                            <td
                                class="px-3 py-3 text-center text-muted-foreground"
                            >
                                {{ row.points_against }}
                            </td>

                            <td
                                class="px-3 py-3 text-center font-medium"
                            >
                                {{
                                    differenceLabel(
                                        row.points_difference,
                                    )
                                }}
                            </td>

                            <td
                                class="px-3 py-3 text-center font-semibold"
                            >
                                {{ row.standing_points }}
                            </td>

                            <td class="px-3 py-3">
                                <div
                                    class="flex min-w-44 flex-col gap-2"
                                >
                                    <span
                                        class="inline-flex w-fit rounded-full px-2.5 py-1 text-xs font-medium"
                                        :class="
                                            qualificationBadgeClasses(
                                                row.qualification_status,
                                            )
                                        "
                                    >
                                        {{ row.qualification_label }}
                                    </span>

                                    <select
                                        :value="
                                            row.qualification_override_status
                                                ?? ''
                                        "
                                        :disabled="row.is_withdrawn"
                                        class="rounded-lg border border-sidebar-border/70 bg-background px-2 py-2 text-xs outline-none transition focus:border-primary disabled:opacity-50 dark:border-sidebar-border"
                                        @change="
                                            emit(
                                                'update-qualification-override',
                                                row,
                                                $event,
                                            )
                                        "
                                    >
                                        <option value="">
                                            Automatski
                                        </option>

                                        <option value="direct">
                                            Direktan prolaz
                                        </option>

                                        <option value="repechage">
                                            Repasaž
                                        </option>

                                        <option value="eliminated">
                                            Ispao
                                        </option>
                                    </select>

                                    <span
                                        v-if="
                                            row.qualification_is_manual
                                        "
                                        class="text-xs text-primary"
                                    >
                                        Ručno podešeno
                                    </span>
                                </div>
                            </td>

                            <td class="px-3 py-3">
                                <div
                                    v-if="
                                        canManageWithdrawals
                                            && tournamentStatus
                                                === 'group_stage'
                                    "
                                    class="flex min-w-32 flex-col gap-2"
                                >
                                    <button
                                        v-if="!row.is_withdrawn"
                                        type="button"
                                        class="inline-flex items-center justify-center rounded-lg border border-red-500/30 px-3 py-2 text-xs font-medium text-red-700 transition hover:bg-red-500/10 dark:text-red-300"
                                        @click="
                                            emit(
                                                'withdraw-participant',
                                                row,
                                            )
                                        "
                                    >
                                        Označi odustao
                                    </button>

                                    <button
                                        v-else
                                        type="button"
                                        class="inline-flex items-center justify-center rounded-lg border border-sidebar-border/70 px-3 py-2 text-xs font-medium transition hover:bg-muted dark:border-sidebar-border"
                                        @click="
                                            emit(
                                                'restore-participant',
                                                row,
                                            )
                                        "
                                    >
                                        Vrati
                                    </button>
                                </div>

                                <span
                                    v-else
                                    class="text-xs text-muted-foreground"
                                >
                                    -
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <p class="mt-3 text-xs text-muted-foreground">
            Sortiranje: pobeda nosi 1 bod, zatim razlika, poeni za, ime.
        </p>
    </div>
</template>
