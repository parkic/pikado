<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

import type {
    TournamentKnockoutRound,
    TournamentKnockoutSeries,
    TournamentKnockoutSeriesParticipant,
} from '@/types/tournament';

defineProps<{
    rounds: TournamentKnockoutRound[];
    scheduleUrl: string;
    canApplyWalkover: (
        series: TournamentKnockoutSeries,
    ) => boolean;
}>();

const emit = defineEmits<{
    'apply-walkover': [
        series: TournamentKnockoutSeries,
        participant: TournamentKnockoutSeriesParticipant,
    ];
}>();

const seriesStatusClasses = (
    status: string,
): string => {
    if (status === 'finished') {
        return 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300';
    }

    if (status === 'in_progress') {
        return 'bg-primary/10 text-primary';
    }

    if (status === 'waiting_participants') {
        return 'bg-yellow-500/10 text-yellow-700 dark:text-yellow-300';
    }

    return 'bg-muted text-muted-foreground';
};

const legStatusClasses = (
    status: string,
): string => {
    if (status === 'finished') {
        return 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300';
    }

    if (status === 'voided') {
        return 'bg-muted text-muted-foreground';
    }

    if (status === 'in_progress') {
        return 'bg-primary/10 text-primary';
    }

    return 'bg-yellow-500/10 text-yellow-700 dark:text-yellow-300';
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
                    Nokaut serije
                </h2>

                <p class="mt-1 text-sm text-muted-foreground">
                    Pregled nokaut duela po rundama. Rezultati
                    se i dalje unose kroz raspored, a ovde se
                    vidi stanje cele serije.
                </p>
            </div>

            <Link
                :href="scheduleUrl"
                class="inline-flex items-center justify-center rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground transition hover:opacity-90"
            >
                Unesi rezultate
            </Link>
        </div>

        <div class="mt-6 space-y-8">
            <section
                v-for="round in rounds"
                :key="round.round_key"
            >
                <div
                    class="mb-3 flex items-center justify-between gap-4"
                >
                    <h3 class="text-base font-semibold">
                        {{ round.round_label }}
                    </h3>

                    <span class="text-sm text-muted-foreground">
                        {{ round.series.length }} serija
                    </span>
                </div>

                <div class="grid gap-4 xl:grid-cols-2">
                    <article
                        v-for="series in round.series"
                        :key="
                            `${series.round_key}-${series.position}`
                        "
                        class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
                    >
                        <div
                            class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between"
                        >
                            <div>
                                <h4 class="font-medium">
                                    {{ series.title }}
                                </h4>

                                <p
                                    class="mt-1 text-sm text-muted-foreground"
                                >
                                    Na
                                    {{ series.wins_required }}
                                    dobijene · maksimalno
                                    {{ series.max_legs }}
                                    partija
                                </p>
                            </div>

                            <span
                                class="inline-flex w-fit rounded-full px-2.5 py-1 text-xs font-medium"
                                :class="
                                    seriesStatusClasses(
                                        series.status,
                                    )
                                "
                            >
                                {{ series.status_label }}
                            </span>
                        </div>

                        <div
                            class="mt-4 grid grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)] items-center gap-3 rounded-lg bg-muted/40 p-3"
                        >
                            <div>
                                <p
                                    class="font-medium"
                                    :class="
                                        series.participant_a
                                            ?.is_withdrawn
                                            ? 'text-muted-foreground line-through'
                                            : ''
                                    "
                                >
                                    {{
                                        series.participant_a
                                            ?.display_name
                                            ?? 'Čeka učesnika'
                                    }}
                                </p>

                                <span
                                    v-if="
                                        series.participant_a
                                            ?.is_withdrawn
                                    "
                                    class="mt-1 inline-flex rounded-full bg-red-500/10 px-2 py-0.5 text-[11px] font-medium text-red-700 dark:text-red-300"
                                >
                                    Odustao
                                </span>

                                <p
                                    v-if="
                                        series.participant_a
                                            ?.group_position
                                    "
                                    class="mt-1 text-xs text-muted-foreground"
                                >
                                    {{
                                        series.participant_a
                                            .group_position
                                    }}
                                </p>
                            </div>

                            <div
                                class="rounded-lg bg-background px-3 py-2 text-center text-xl font-semibold"
                            >
                                {{ series.series_score }}
                            </div>

                            <div class="text-right">
                                <p
                                    class="font-medium"
                                    :class="
                                        series.participant_b
                                            ?.is_withdrawn
                                            ? 'text-muted-foreground line-through'
                                            : ''
                                    "
                                >
                                    {{
                                        series.participant_b
                                            ?.display_name
                                            ?? 'Čeka učesnika'
                                    }}
                                </p>

                                <span
                                    v-if="
                                        series.participant_b
                                            ?.is_withdrawn
                                    "
                                    class="mt-1 inline-flex rounded-full bg-red-500/10 px-2 py-0.5 text-[11px] font-medium text-red-700 dark:text-red-300"
                                >
                                    Odustao
                                </span>

                                <p
                                    v-if="
                                        series.participant_b
                                            ?.group_position
                                    "
                                    class="mt-1 text-xs text-muted-foreground"
                                >
                                    {{
                                        series.participant_b
                                            .group_position
                                    }}
                                </p>
                            </div>
                        </div>

                        <div
                            v-if="canApplyWalkover(series)"
                            class="mt-3 rounded-lg border border-red-500/20 bg-red-500/5 p-3"
                        >
                            <p
                                class="text-xs font-medium text-red-700 dark:text-red-300"
                            >
                                Walkover / odustajanje
                            </p>

                            <div
                                class="mt-2 flex flex-col gap-2 sm:flex-row"
                            >
                                <button
                                    v-if="series.participant_a"
                                    type="button"
                                    class="inline-flex items-center justify-center rounded-lg border border-red-500/30 px-3 py-2 text-xs font-medium text-red-700 transition hover:bg-red-500/10 dark:text-red-300"
                                    @click="
                                        emit(
                                            'apply-walkover',
                                            series,
                                            series.participant_a,
                                        )
                                    "
                                >
                                    {{
                                        series.participant_a
                                            .display_name
                                    }}
                                    odustao
                                </button>

                                <button
                                    v-if="series.participant_b"
                                    type="button"
                                    class="inline-flex items-center justify-center rounded-lg border border-red-500/30 px-3 py-2 text-xs font-medium text-red-700 transition hover:bg-red-500/10 dark:text-red-300"
                                    @click="
                                        emit(
                                            'apply-walkover',
                                            series,
                                            series.participant_b,
                                        )
                                    "
                                >
                                    {{
                                        series.participant_b
                                            .display_name
                                    }}
                                    odustao
                                </button>
                            </div>
                        </div>

                        <div
                            class="mt-4 overflow-hidden rounded-lg border border-sidebar-border/70 dark:border-sidebar-border"
                        >
                            <table class="w-full text-left text-sm">
                                <thead
                                    class="border-b border-sidebar-border/70 bg-muted/40 dark:border-sidebar-border"
                                >
                                    <tr>
                                        <th
                                            class="px-3 py-2 font-medium"
                                        >
                                            Partija
                                        </th>

                                        <th
                                            class="px-3 py-2 text-center font-medium"
                                        >
                                            Status
                                        </th>

                                        <th
                                            class="px-3 py-2 text-center font-medium"
                                        >
                                            Rezultat
                                        </th>

                                        <th
                                            class="px-3 py-2 font-medium"
                                        >
                                            Pobednik
                                        </th>

                                        <th
                                            class="px-3 py-2 font-medium"
                                        >
                                            Resource
                                        </th>
                                    </tr>
                                </thead>

                                <tbody>
                                    <tr
                                        v-for="leg in series.legs"
                                        :key="leg.id"
                                        class="border-b border-sidebar-border/70 last:border-b-0 dark:border-sidebar-border"
                                    >
                                        <td
                                            class="px-3 py-2 text-muted-foreground"
                                        >
                                            Leg {{ leg.leg }}
                                        </td>

                                        <td
                                            class="px-3 py-2 text-center"
                                        >
                                            <span
                                                class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium"
                                                :class="
                                                    legStatusClasses(
                                                        leg.status,
                                                    )
                                                "
                                            >
                                                {{
                                                    leg.status_label
                                                }}
                                            </span>
                                        </td>

                                        <td
                                            class="px-3 py-2 text-center font-medium"
                                        >
                                            {{ leg.score ?? '-' }}
                                        </td>

                                        <td class="px-3 py-2">
                                            {{
                                                leg.winner
                                                    ?.display_name
                                                    ?? '-'
                                            }}
                                        </td>

                                        <td
                                            class="px-3 py-2 text-muted-foreground"
                                        >
                                            {{
                                                leg.resource_name
                                                    ?? '-'
                                            }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </article>
                </div>
            </section>
        </div>
    </div>
</template>
