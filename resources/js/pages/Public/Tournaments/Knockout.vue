<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    ChevronLeft,
    ChevronRight,
    GitFork,
    Radio,
    Trophy,
    Tv,
    Users,
} from '@lucide/vue';
import { useElementSize, useMediaQuery } from '@vueuse/core';
import { computed, ref, watch } from 'vue';
import PublicKnockoutDraw from '@/components/public/PublicKnockoutDraw.vue';
import PublicTournamentHeader from '@/components/public/PublicTournamentHeader.vue';
import PublicTournamentLayout from '@/components/public/PublicTournamentLayout.vue';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { usePublicTournamentRealtime } from '@/composables/usePublicTournamentRealtime';
import type { PublicVenueBranding } from '@/types';
import type { TournamentKnockoutDraw } from '@/types/tournament';

type Tournament = {
    id: number;
    name: string;
    public_code: string;
    game_type: string;
    match_mode: string;
    status: string;
    status_label: string;
    knockout_size: number | null;
    knockout_matches_count: number;
    finished_knockout_matches_count: number;
    voided_knockout_matches_count: number;
    repechage_qualifiers_count: number;
};

type RepechageParticipant = {
    participant_id: number;
    display_name: string;
    group_name: string;
    group_rank: number | null;
    qualification_position: string | null;
    played: number;
    wins: number;
    losses: number;
    points_difference: number;
    standing_points: number;
    repechage_outcome_status: 'advanced' | 'eliminated' | null;
    repechage_outcome_label: string;
    profile_url: string | null;
};

type SeriesParticipant = {
    id: number;
    display_name: string;
    qualification_position: string | null;
    is_withdrawn: boolean;
    profile_url: string | null;
} | null;

type KnockoutLeg = {
    id: number;
    leg: number | null;
    status: string;
    status_label: string;
    score: string | null;
    winner: SeriesParticipant;
    resource_name: string | null;
    win_reason: string | null;
};

type KnockoutSeries = {
    round_key: string;
    round_label: string;
    round_sort: number;
    position: number;
    title: string;
    wins_required: number;
    max_legs: number;
    participant_a: SeriesParticipant;
    participant_b: SeriesParticipant;
    participant_a_wins: number;
    participant_b_wins: number;
    series_score: string;
    winner: SeriesParticipant;
    status: string;
    status_label: string;
    legs: KnockoutLeg[];
};

type KnockoutRound = {
    round_key: string;
    round_label: string;
    round_sort: number;
    series: KnockoutSeries[];
};

type KnockoutViewMode = 'bracket' | 'tv';

const props = defineProps<{
    venue: PublicVenueBranding;
    tournament: Tournament;
    rounds: KnockoutRound[];
    knockout_draw: TournamentKnockoutDraw;
    repechage_participants: RepechageParticipant[];
}>();

usePublicTournamentRealtime({
    publicCode: props.tournament.public_code,
});
const isMobile = useMediaQuery('(max-width: 767px)');
const isTvViewport = useMediaQuery('(min-width: 1536px)');
const repechageAdvancedCount = computed(
    () =>
        props.repechage_participants.filter(
            (participant) =>
                participant.repechage_outcome_status === 'advanced',
        ).length,
);
const repechageOutcomeLabel = (participant: RepechageParticipant): string => {
    if (participant.repechage_outcome_status === 'advanced') {
        return 'Prolazi dalje';
    }

    if (participant.repechage_outcome_status === 'eliminated') {
        return 'Ispao';
    }

    return 'Čeka ishod';
};
const repechageOutcomeClasses = (participant: RepechageParticipant): string => {
    if (participant.repechage_outcome_status === 'advanced') {
        return 'border-emerald-400/35 bg-emerald-400/10 text-emerald-200';
    }

    if (participant.repechage_outcome_status === 'eliminated') {
        return 'border-red-400/30 bg-red-500/10 text-red-200';
    }

    return 'border-amber-300/30 bg-amber-300/10 text-amber-100';
};

const defaultVisibleRounds = 3;
const minimumBracketColumnWidth = computed(() =>
    isTvViewport.value ? 340 : 260,
);
const preferredBracketColumnWidth = computed(() =>
    isTvViewport.value ? 380 : 280,
);
const maximumBracketColumnWidth = computed(() =>
    isTvViewport.value ? 420 : 320,
);
const minimumBracketColumnGap = computed(() => (isTvViewport.value ? 64 : 48));
const preferredBracketColumnGap = computed(() =>
    isTvViewport.value ? 96 : 76,
);
const bracketCardHeight = computed(() => (isTvViewport.value ? 152 : 112));
const bracketRowUnit = computed(() =>
    isMobile.value ? 124 : isTvViewport.value ? 176 : 136,
);
const bracketHeaderHeight = computed(() =>
    isMobile.value ? 48 : isTvViewport.value ? 68 : 56,
);
const bracketViewport = ref<HTMLElement | null>(null);
const { width: bracketViewportWidth } = useElementSize(bracketViewport);

const mainRounds = computed(() =>
    props.rounds.filter((round) => round.round_key !== 'third_place'),
);
const tvRounds = computed(() => props.rounds);
const thirdPlaceRound = computed(() =>
    props.rounds.find((round) => round.round_key === 'third_place'),
);

const activeRoundIndex = computed(() => {
    const inProgressIndex = mainRounds.value.findIndex((round) =>
        round.series.some((series) => series.status === 'in_progress'),
    );

    if (inProgressIndex >= 0) {
        return inProgressIndex;
    }

    const readyIndex = mainRounds.value.findIndex((round) =>
        round.series.some(
            (series) =>
                series.status === 'scheduled' &&
                series.participant_a &&
                series.participant_b,
        ),
    );

    if (readyIndex >= 0) {
        return readyIndex;
    }

    const waitingIndex = mainRounds.value.findIndex((round) =>
        round.series.some((series) => series.status !== 'finished'),
    );

    return waitingIndex >= 0
        ? waitingIndex
        : Math.max(mainRounds.value.length - 1, 0);
});

const activeTvRoundIndex = computed(() =>
    Math.max(
        tvRounds.value.findIndex(
            (round) =>
                round.round_key ===
                mainRounds.value[activeRoundIndex.value]?.round_key,
        ),
        0,
    ),
);
const viewMode = ref<KnockoutViewMode>('bracket');
const selectedBracketStartIndex = ref<number | null>(null);
const selectedTvRoundIndex = ref(activeTvRoundIndex.value);
const selectedTvRound = computed(
    () =>
        tvRounds.value[selectedTvRoundIndex.value] ??
        tvRounds.value[activeTvRoundIndex.value],
);
const selectedTvRoundFinishedCount = computed(
    () =>
        selectedTvRound.value?.series.filter(
            (series) => series.status === 'finished',
        ).length ?? 0,
);

watch(
    isMobile,
    (mobile) => {
        if (mobile) {
            viewMode.value = 'bracket';
        }
    },
    { immediate: true },
);

const bracketRoundCapacity = computed(() => {
    if (!mainRounds.value.length) {
        return 0;
    }

    if (!bracketViewportWidth.value) {
        return Math.min(defaultVisibleRounds, mainRounds.value.length);
    }

    const fittingRounds = Math.floor(
        (bracketViewportWidth.value + minimumBracketColumnGap.value) /
            (minimumBracketColumnWidth.value + minimumBracketColumnGap.value),
    );

    return Math.min(mainRounds.value.length, Math.max(fittingRounds, 1));
});

const maxWindowStart = computed(() =>
    Math.max(mainRounds.value.length - bracketRoundCapacity.value, 0),
);
const initialWindowStart = (): number =>
    Math.min(
        Math.max(
            activeRoundIndex.value -
                Math.floor(Math.max(bracketRoundCapacity.value - 1, 0) / 2),
            0,
        ),
        maxWindowStart.value,
    );
const windowStartIndex = ref(initialWindowStart());
const visibleWindowStartIndex = computed(
    () => selectedBracketStartIndex.value ?? windowStartIndex.value,
);
const visibleRoundsCount = computed(() =>
    Math.min(
        bracketRoundCapacity.value,
        Math.max(mainRounds.value.length - visibleWindowStartIndex.value, 0),
    ),
);
const visibleRounds = computed(() =>
    mainRounds.value.slice(
        visibleWindowStartIndex.value,
        visibleWindowStartIndex.value + visibleRoundsCount.value,
    ),
);
const isFinalRoundSelected = computed(() => {
    if (selectedBracketStartIndex.value === null) {
        return false;
    }

    return (
        mainRounds.value[selectedBracketStartIndex.value]?.round_key === 'final'
    );
});
const selectedFinalSeries = computed(() =>
    isFinalRoundSelected.value
        ? (mainRounds.value.find((round) => round.round_key === 'final')
              ?.series[0] ?? null)
        : null,
);
const selectedSeries = ref<KnockoutSeries | null>(null);

watch([activeRoundIndex, bracketRoundCapacity], ([nextIndex]) => {
    if (selectedBracketStartIndex.value !== null) {
        return;
    }

    windowStartIndex.value = Math.min(
        windowStartIndex.value,
        maxWindowStart.value,
    );

    const lastVisibleIndex =
        windowStartIndex.value + bracketRoundCapacity.value - 1;

    if (nextIndex < windowStartIndex.value || nextIndex > lastVisibleIndex) {
        windowStartIndex.value = initialWindowStart();
    }
});

watch(
    () => tvRounds.value.length,
    (roundCount) => {
        selectedTvRoundIndex.value = Math.min(
            selectedTvRoundIndex.value,
            Math.max(roundCount - 1, 0),
        );
    },
);

const moveRoundWindow = (direction: number) => {
    windowStartIndex.value = Math.min(
        Math.max(windowStartIndex.value + direction, 0),
        maxWindowStart.value,
    );
};

const showAllRounds = () => {
    selectedBracketStartIndex.value = null;
    windowStartIndex.value = initialWindowStart();
};

const showFromRound = (index: number) => {
    selectedBracketStartIndex.value = index;
};

const showTvRound = (index: number) => {
    selectedTvRoundIndex.value = index;
};

const bracketColumnWidth = computed(() => {
    const roundCount = visibleRounds.value.length;

    if (!roundCount || !bracketViewportWidth.value) {
        return preferredBracketColumnWidth.value;
    }

    const distributedWidth =
        (bracketViewportWidth.value -
            Math.max(roundCount - 1, 0) * preferredBracketColumnGap.value) /
        roundCount;

    return Math.min(
        maximumBracketColumnWidth.value,
        Math.max(minimumBracketColumnWidth.value, distributedWidth),
    );
});

const bracketColumnGap = computed(() => {
    const roundCount = visibleRounds.value.length;

    if (roundCount <= 1) {
        return 0;
    }

    if (!bracketViewportWidth.value) {
        return preferredBracketColumnGap.value;
    }

    return Math.max(
        minimumBracketColumnGap.value,
        (bracketViewportWidth.value - roundCount * bracketColumnWidth.value) /
            (roundCount - 1),
    );
});

const bracketBoardWidth = computed(
    () =>
        visibleRounds.value.length * bracketColumnWidth.value +
        Math.max(visibleRounds.value.length - 1, 0) * bracketColumnGap.value,
);

const bracketBoardHeight = computed(
    () =>
        bracketHeaderHeight.value +
        Math.max(
            (visibleRounds.value[0]?.series.length ?? 1) * bracketRowUnit.value,
            isFinalRoundSelected.value ? bracketCardHeight.value + 20 : 320,
        ),
);

const seriesCardStyle = (
    columnIndex: number,
    seriesIndex: number,
): Record<string, string> => ({
    left: `${
        columnIndex * (bracketColumnWidth.value + bracketColumnGap.value)
    }px`,
    top: `${
        bracketHeaderHeight.value +
        ((2 ** columnIndex - 1) * bracketRowUnit.value) / 2 +
        seriesIndex * 2 ** columnIndex * bracketRowUnit.value
    }px`,
    width: `${bracketColumnWidth.value}px`,
    height: `${bracketCardHeight.value}px`,
});

const roundHeadingStyle = (columnIndex: number): Record<string, string> => ({
    left: `${
        columnIndex * (bracketColumnWidth.value + bracketColumnGap.value)
    }px`,
    width: `${bracketColumnWidth.value}px`,
});

const connectorPaths = computed(() => {
    const paths: string[] = [];

    for (
        let columnIndex = 0;
        columnIndex < visibleRounds.value.length - 1;
        columnIndex++
    ) {
        const sourceRound = visibleRounds.value[columnIndex];
        const targetRound = visibleRounds.value[columnIndex + 1];
        const sourceX =
            columnIndex * (bracketColumnWidth.value + bracketColumnGap.value) +
            bracketColumnWidth.value;
        const middleX = sourceX + bracketColumnGap.value / 2;
        const targetX =
            (columnIndex + 1) *
            (bracketColumnWidth.value + bracketColumnGap.value);

        targetRound.series.forEach((_, targetIndex) => {
            const firstSourceIndex = targetIndex * 2;
            const secondSourceIndex = firstSourceIndex + 1;

            if (!sourceRound.series[firstSourceIndex]) {
                return;
            }

            const firstSourceY =
                bracketHeaderHeight.value +
                ((2 ** columnIndex - 1) * bracketRowUnit.value) / 2 +
                firstSourceIndex * 2 ** columnIndex * bracketRowUnit.value +
                bracketCardHeight.value / 2;
            const targetY =
                bracketHeaderHeight.value +
                ((2 ** (columnIndex + 1) - 1) * bracketRowUnit.value) / 2 +
                targetIndex * 2 ** (columnIndex + 1) * bracketRowUnit.value +
                bracketCardHeight.value / 2;

            if (!sourceRound.series[secondSourceIndex]) {
                paths.push(`M ${sourceX} ${firstSourceY} H ${targetX}`);

                return;
            }

            const secondSourceY =
                bracketHeaderHeight.value +
                ((2 ** columnIndex - 1) * bracketRowUnit.value) / 2 +
                secondSourceIndex * 2 ** columnIndex * bracketRowUnit.value +
                bracketCardHeight.value / 2;

            paths.push(
                `M ${sourceX} ${firstSourceY} H ${middleX} V ${secondSourceY} H ${sourceX} M ${middleX} ${targetY} H ${targetX}`,
            );
        });
    }

    return paths;
});

const participantClasses = (
    series: KnockoutSeries,
    participant: SeriesParticipant,
): string => {
    if (participant?.is_withdrawn) {
        return 'text-zinc-600 line-through';
    }

    if (isSeriesWinner(series, participant)) {
        return 'font-bold text-emerald-300';
    }

    return series.status === 'finished' ? 'text-zinc-500' : 'text-zinc-100';
};

const isSeriesWinner = (
    series: KnockoutSeries,
    participant: SeriesParticipant,
): boolean =>
    Boolean(
        series.winner && participant && series.winner.id === participant.id,
    );

const isLegWinner = (
    leg: KnockoutLeg,
    participant: SeriesParticipant,
): boolean =>
    Boolean(leg.winner && participant && leg.winner.id === participant.id);

const legScores = (leg: KnockoutLeg): [string, string] => {
    if (!leg.score) {
        return ['—', '—'];
    }

    const scores = leg.score.split(':').map((score) => score.trim());

    return scores.length === 2 ? [scores[0], scores[1]] : [leg.score, '—'];
};

const seriesCardClasses = (series: KnockoutSeries): string => {
    if (series.round_key === 'final') {
        if (series.status === 'finished') {
            return 'border-amber-300/55 bg-gradient-to-br from-amber-400/[0.14] via-emerald-500/[0.07] to-black/40 ring-1 ring-amber-300/25 shadow-xl shadow-amber-950/35 hover:border-amber-200/75';
        }

        if (series.status === 'in_progress') {
            return 'border-amber-300/65 bg-gradient-to-br from-amber-400/[0.16] via-sky-500/[0.08] to-black/40 ring-1 ring-amber-300/30 shadow-xl shadow-amber-950/40 hover:border-amber-200/80';
        }

        return 'border-amber-300/45 bg-gradient-to-br from-amber-400/[0.12] via-orange-500/[0.04] to-black/40 ring-1 ring-amber-300/20 shadow-xl shadow-amber-950/35 hover:border-amber-200/70';
    }

    if (series.status === 'in_progress') {
        return 'border-sky-400/35 bg-sky-500/[0.07] hover:border-sky-300/60';
    }

    if (series.status === 'finished') {
        return 'border-emerald-400/20 bg-emerald-500/[0.045] hover:border-emerald-300/40';
    }

    return 'border-white/10 bg-black/30 hover:border-white/25 hover:bg-white/[0.045]';
};

const bracketPlayerNameClasses = (series: KnockoutSeries): string => {
    if (series.round_key === 'final') {
        return 'truncate text-base font-bold md:text-lg 2xl:text-3xl';
    }

    if (series.round_key === 'semi_final') {
        return 'truncate text-base font-semibold md:text-lg 2xl:text-2xl';
    }

    return 'truncate text-sm font-medium 2xl:text-xl';
};

const bracketScoreClasses = (series: KnockoutSeries): string =>
    series.round_key === 'final' || series.round_key === 'semi_final'
        ? 'flex min-w-7 items-center justify-end gap-1 text-lg font-black 2xl:text-3xl'
        : 'flex min-w-6 items-center justify-end gap-1 text-base font-black 2xl:text-2xl';

const walkoverLabel = (reason: string | null): string | null =>
    reason === 'opponent_withdrew' || reason === 'walkover' ? 'Walkover' : null;
</script>

<template>
    <Head :title="`${tournament.name} - Nokaut`" />

    <PublicTournamentLayout :theme="venue.public_theme">
        <PublicTournamentHeader
            :venue="venue"
            :tournament="tournament"
            active-page="knockout"
        />

        <section
            v-if="tournament.status === 'repechage'"
            class="relative overflow-hidden rounded-2xl border border-amber-300/35 bg-gradient-to-br from-amber-400/[0.16] via-orange-500/[0.07] to-white/[0.025] p-3.5 shadow-2xl shadow-amber-950/25 md:rounded-3xl md:p-6 2xl:p-8"
        >
            <div
                class="pointer-events-none absolute -top-20 -right-20 size-64 rounded-full bg-orange-400/10 blur-3xl"
            />

            <div
                class="relative flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between"
            >
                <div>
                    <div
                        class="inline-flex items-center gap-2 rounded-full border border-red-300/25 bg-red-500/15 px-3 py-1.5 text-xs font-black tracking-[0.18em] text-red-100 uppercase 2xl:text-sm"
                    >
                        <span class="relative flex size-2.5">
                            <span
                                class="absolute inline-flex size-full animate-ping rounded-full bg-red-300 opacity-75"
                            />
                            <span
                                class="relative inline-flex size-2.5 rounded-full bg-red-400"
                            />
                        </span>
                        Repasaž uživo
                    </div>

                    <h2
                        class="mt-3 text-2xl font-black tracking-tight text-white md:text-4xl 2xl:text-5xl"
                    >
                        Borba za prolaz u nokaut
                    </h2>
                    <p
                        class="mt-2 max-w-3xl text-sm text-zinc-300 md:text-base 2xl:text-lg"
                    >
                        Promene koje administrator unese prikazuju se ovde
                        automatski, bez osvežavanja stranice.
                    </p>
                </div>

                <div class="flex flex-wrap gap-2 text-sm 2xl:text-base">
                    <span
                        class="inline-flex items-center gap-2 rounded-xl border border-white/10 bg-black/25 px-3 py-2 font-semibold text-zinc-100"
                    >
                        <Users class="size-4 text-amber-300" />
                        {{ repechage_participants.length }} učesnika
                    </span>
                    <span
                        class="inline-flex items-center gap-2 rounded-xl border border-white/10 bg-black/25 px-3 py-2 font-semibold text-zinc-100"
                    >
                        <Radio class="size-4 text-emerald-300" />
                        {{ repechageAdvancedCount }} /
                        {{ tournament.repechage_qualifiers_count }} prolazi
                    </span>
                </div>
            </div>

            <div
                v-if="repechage_participants.length"
                class="relative mt-5 grid gap-2.5 sm:grid-cols-2 xl:grid-cols-3 2xl:mt-7 2xl:gap-4"
            >
                <article
                    v-for="participant in repechage_participants"
                    :key="participant.participant_id"
                    class="flex min-w-0 items-center gap-3 rounded-2xl border border-white/10 bg-black/30 p-3.5 backdrop-blur-sm 2xl:p-5"
                >
                    <div
                        class="flex size-11 shrink-0 flex-col items-center justify-center rounded-xl border border-amber-300/25 bg-amber-300/10 text-amber-100 2xl:size-14"
                    >
                        <span
                            class="text-[10px] font-bold uppercase opacity-70"
                        >
                            Grupa
                        </span>
                        <span class="text-base font-black 2xl:text-xl">
                            {{ participant.group_name }}
                        </span>
                    </div>

                    <div class="min-w-0 flex-1">
                        <Link
                            v-if="participant.profile_url"
                            :href="participant.profile_url"
                            class="block text-base font-bold break-words text-white transition hover:text-amber-200 2xl:text-xl"
                        >
                            {{ participant.display_name }}
                        </Link>
                        <p
                            v-else
                            class="text-base font-bold break-words text-white 2xl:text-xl"
                        >
                            {{ participant.display_name }}
                        </p>
                        <p class="mt-0.5 text-xs text-zinc-400 2xl:text-sm">
                            {{ participant.qualification_position }} ·
                            {{ participant.wins }}P / {{ participant.losses }}I
                            · +/-
                            {{ participant.points_difference }}
                        </p>
                    </div>

                    <span
                        class="shrink-0 rounded-full border px-2.5 py-1 text-[11px] font-bold 2xl:px-3 2xl:text-sm"
                        :class="repechageOutcomeClasses(participant)"
                    >
                        {{ repechageOutcomeLabel(participant) }}
                    </span>
                </article>
            </div>

            <p
                v-else
                class="relative mt-5 rounded-2xl border border-dashed border-white/15 bg-black/20 p-5 text-center text-zinc-300"
            >
                Spisak učesnika repasaža biće prikazan čim grupna tabela bude
                zaključena.
            </p>
        </section>

        <PublicKnockoutDraw
            v-if="tournament.status === 'knockout_draw'"
            :draw="knockout_draw"
        />

        <section
            v-if="mainRounds.length"
            class="overflow-hidden rounded-2xl border border-white/10 bg-white/[0.03] md:rounded-3xl"
        >
            <div class="border-b border-white/10 p-3.5 md:p-6 2xl:p-8">
                <div
                    class="flex flex-col gap-3 md:gap-5 xl:flex-row xl:items-start xl:justify-between"
                >
                    <div>
                        <p
                            class="text-xs font-semibold tracking-[0.22em] text-orange-300 uppercase 2xl:text-sm"
                        >
                            Eliminacije
                        </p>
                        <h2
                            class="mt-0.5 text-xl font-bold md:mt-1 md:text-3xl 2xl:text-4xl"
                        >
                            {{
                                viewMode === 'bracket'
                                    ? 'Nokaut kostur'
                                    : 'TV pregled nokauta'
                            }}
                        </h2>
                    </div>

                    <div
                        class="flex min-w-0 flex-col gap-2.5 md:gap-3 xl:items-end"
                    >
                        <div
                            class="hidden w-fit rounded-xl border border-white/10 bg-black/25 p-1 md:inline-flex"
                            aria-label="Tip prikaza nokaut faze"
                        >
                            <button
                                type="button"
                                class="inline-flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-semibold transition 2xl:px-4 2xl:py-2.5 2xl:text-base"
                                :class="
                                    viewMode === 'bracket'
                                        ? 'bg-white text-zinc-950 shadow-sm'
                                        : 'text-zinc-400 hover:bg-white/[0.06] hover:text-zinc-100'
                                "
                                :aria-pressed="viewMode === 'bracket'"
                                @click="viewMode = 'bracket'"
                            >
                                <GitFork class="size-4" />
                                Kostur
                            </button>
                            <button
                                type="button"
                                class="inline-flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-semibold transition 2xl:px-4 2xl:py-2.5 2xl:text-base"
                                :class="
                                    viewMode === 'tv'
                                        ? 'bg-white text-zinc-950 shadow-sm'
                                        : 'text-zinc-400 hover:bg-white/[0.06] hover:text-zinc-100'
                                "
                                :aria-pressed="viewMode === 'tv'"
                                @click="viewMode = 'tv'"
                            >
                                <Tv class="size-4" />
                                TV pregled
                            </button>
                        </div>

                        <div
                            v-if="viewMode === 'bracket'"
                            class="flex w-full min-w-0 items-center gap-1.5 md:gap-2 xl:w-auto"
                        >
                            <button
                                v-if="
                                    selectedBracketStartIndex === null &&
                                    maxWindowStart > 0
                                "
                                type="button"
                                :disabled="windowStartIndex === 0"
                                class="inline-flex size-9 shrink-0 items-center justify-center rounded-full border border-white/15 text-zinc-100 transition hover:border-white/30 hover:bg-white/10 disabled:cursor-not-allowed disabled:opacity-25 md:size-10"
                                aria-label="Pomeri kostur ulevo"
                                @click="moveRoundWindow(-1)"
                            >
                                <ChevronLeft class="size-5" />
                            </button>

                            <div
                                class="flex min-w-0 flex-1 items-center gap-2 overflow-x-auto px-1 py-1 xl:max-w-[72vw]"
                            >
                                <span
                                    class="hidden shrink-0 text-xs font-medium text-zinc-500 sm:inline"
                                >
                                    Prikaži od
                                </span>
                                <button
                                    type="button"
                                    class="shrink-0 rounded-xl px-2.5 py-2 text-[11px] font-semibold transition md:rounded-full md:px-3 md:text-xs 2xl:px-4 2xl:text-sm"
                                    :class="
                                        selectedBracketStartIndex === null
                                            ? 'bg-white text-zinc-950'
                                            : 'border border-white/10 text-zinc-400 hover:text-zinc-100'
                                    "
                                    :aria-pressed="
                                        selectedBracketStartIndex === null
                                    "
                                    @click="showAllRounds"
                                >
                                    Sve
                                </button>
                                <button
                                    v-for="(round, index) in mainRounds"
                                    :key="round.round_key"
                                    type="button"
                                    class="relative shrink-0 rounded-xl px-2.5 py-2 text-[11px] font-medium transition md:rounded-full md:px-3 md:text-xs 2xl:px-4 2xl:text-sm"
                                    :class="
                                        selectedBracketStartIndex === index
                                            ? 'bg-white text-zinc-950'
                                            : 'border border-white/10 text-zinc-400 hover:text-zinc-100'
                                    "
                                    :aria-pressed="
                                        selectedBracketStartIndex === index
                                    "
                                    @click="showFromRound(index)"
                                >
                                    {{ round.round_label }}
                                    <span
                                        v-if="activeRoundIndex === index"
                                        class="absolute -top-0.5 -right-0.5 size-2 rounded-full bg-orange-400 ring-2 ring-zinc-950"
                                    />
                                </button>
                            </div>

                            <button
                                v-if="
                                    selectedBracketStartIndex === null &&
                                    maxWindowStart > 0
                                "
                                type="button"
                                :disabled="windowStartIndex === maxWindowStart"
                                class="inline-flex size-9 shrink-0 items-center justify-center rounded-full border border-white/15 text-zinc-100 transition hover:border-white/30 hover:bg-white/10 disabled:cursor-not-allowed disabled:opacity-25 md:size-10"
                                aria-label="Pomeri kostur udesno"
                                @click="moveRoundWindow(1)"
                            >
                                <ChevronRight class="size-5" />
                            </button>
                        </div>

                        <div
                            v-else
                            class="flex w-full min-w-0 items-center gap-2 xl:w-auto"
                        >
                            <span
                                class="shrink-0 text-xs font-medium text-zinc-500"
                            >
                                Runda
                            </span>
                            <div
                                class="flex min-w-0 flex-1 gap-2 overflow-x-auto px-1 py-1 xl:max-w-[72vw]"
                            >
                                <button
                                    v-for="(round, index) in tvRounds"
                                    :key="round.round_key"
                                    type="button"
                                    class="relative shrink-0 rounded-full px-3 py-2 text-xs font-medium transition 2xl:px-4 2xl:text-sm"
                                    :class="
                                        selectedTvRoundIndex === index
                                            ? 'bg-white text-zinc-950'
                                            : 'border border-white/10 text-zinc-400 hover:text-zinc-100'
                                    "
                                    :aria-pressed="
                                        selectedTvRoundIndex === index
                                    "
                                    @click="showTvRound(index)"
                                >
                                    {{ round.round_label }}
                                    <span
                                        v-if="activeTvRoundIndex === index"
                                        class="absolute -top-0.5 -right-0.5 size-2 rounded-full bg-orange-400 ring-2 ring-zinc-950"
                                    />
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div
                    class="mt-3 flex flex-wrap items-center gap-x-3 gap-y-1 text-[11px] text-zinc-400 md:mt-5 md:gap-x-5 md:gap-y-2 md:text-sm 2xl:text-base"
                >
                    <span>
                        {{ tournament.finished_knockout_matches_count }} /
                        {{ tournament.knockout_matches_count }} partija završeno
                    </span>
                    <span v-if="tournament.knockout_size">
                        Top {{ tournament.knockout_size }}
                    </span>
                    <span v-if="viewMode === 'tv' && selectedTvRound">
                        {{ selectedTvRoundFinishedCount }} /
                        {{ selectedTvRound.series.length }} duela završeno u
                        rundi {{ selectedTvRound.round_label }}
                    </span>
                </div>
            </div>

            <div
                v-if="viewMode === 'bracket'"
                class="overflow-x-auto p-2.5 md:p-6 2xl:p-8"
                :class="
                    isFinalRoundSelected
                        ? 'bg-gradient-to-b from-amber-400/[0.065] via-transparent to-transparent'
                        : ''
                "
            >
                <div
                    v-if="selectedFinalSeries"
                    class="mx-auto flex min-h-[260px] max-w-8xl flex-col items-center justify-center px-2 py-5 md:min-h-[300px] md:px-6 2xl:min-h-[400px] 2xl:py-8"
                >
                    <div class="mb-4 text-center md:mb-5 2xl:mb-8">
                        <p
                            class="text-xs font-bold tracking-[0.28em] text-amber-300 uppercase md:text-sm 2xl:text-lg"
                        >
                            Veliko finale
                        </p>
                        <p class="mt-2 text-sm text-zinc-400 2xl:text-lg">
                            Na {{ selectedFinalSeries.wins_required }} dobijene
                            · {{ selectedFinalSeries.status_label }}
                        </p>
                    </div>

                    <div
                        class="grid w-full grid-cols-1 items-stretch gap-3 md:grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)] md:gap-5 2xl:gap-8"
                    >
                        <div
                            class="flex min-h-28 min-w-0 flex-col items-center justify-center rounded-2xl border px-4 py-5 text-center md:min-h-36 md:rounded-3xl md:px-7 2xl:min-h-56"
                            :class="
                                isSeriesWinner(
                                    selectedFinalSeries,
                                    selectedFinalSeries.participant_a,
                                )
                                    ? 'border-emerald-300/35 bg-emerald-500/[0.12] shadow-2xl shadow-emerald-950/25'
                                    : 'border-white/10 bg-black/25'
                            "
                        >
                            <Trophy
                                v-if="
                                    isSeriesWinner(
                                        selectedFinalSeries,
                                        selectedFinalSeries.participant_a,
                                    )
                                "
                                class="mb-3 size-7 text-amber-300 md:size-9 2xl:size-12"
                            />
                            <Link
                                v-if="
                                    selectedFinalSeries.participant_a
                                        ?.profile_url
                                "
                                :href="
                                    selectedFinalSeries.participant_a
                                        .profile_url
                                "
                                class="line-clamp-2 text-2xl leading-tight font-black transition hover:text-orange-200 md:text-3xl 2xl:text-6xl"
                                :class="
                                    participantClasses(
                                        selectedFinalSeries,
                                        selectedFinalSeries.participant_a,
                                    )
                                "
                            >
                                {{
                                    selectedFinalSeries.participant_a
                                        .display_name
                                }}
                            </Link>
                            <p
                                v-else
                                class="line-clamp-2 text-2xl leading-tight font-black md:text-3xl 2xl:text-6xl"
                                :class="
                                    participantClasses(
                                        selectedFinalSeries,
                                        selectedFinalSeries.participant_a,
                                    )
                                "
                            >
                                {{
                                    selectedFinalSeries.participant_a
                                        ?.display_name ?? '—'
                                }}
                            </p>
                        </div>

                        <div
                            class="flex min-w-32 flex-row items-center justify-center gap-3 rounded-2xl border border-amber-300/25 bg-gradient-to-br from-amber-400/[0.16] to-orange-500/[0.06] px-5 py-4 md:min-w-44 md:flex-col md:rounded-3xl md:px-7 2xl:min-w-64"
                        >
                            <strong
                                class="text-5xl font-black tracking-tight whitespace-nowrap text-white md:text-5xl 2xl:text-8xl"
                            >
                                {{ selectedFinalSeries.participant_a_wins }} :
                                {{ selectedFinalSeries.participant_b_wins }}
                            </strong>
                            <button
                                type="button"
                                class="rounded-full border border-white/15 bg-black/25 px-3 py-2 text-xs font-bold text-zinc-200 transition hover:border-white/30 hover:bg-white/10 md:px-4 md:text-sm 2xl:text-base"
                                @click="selectedSeries = selectedFinalSeries"
                            >
                                Detalji partija
                            </button>
                        </div>

                        <div
                            class="flex min-h-28 min-w-0 flex-col items-center justify-center rounded-2xl border px-4 py-5 text-center md:min-h-36 md:rounded-3xl md:px-7 2xl:min-h-56"
                            :class="
                                isSeriesWinner(
                                    selectedFinalSeries,
                                    selectedFinalSeries.participant_b,
                                )
                                    ? 'border-emerald-300/35 bg-emerald-500/[0.12] shadow-2xl shadow-emerald-950/25'
                                    : 'border-white/10 bg-black/25'
                            "
                        >
                            <Trophy
                                v-if="
                                    isSeriesWinner(
                                        selectedFinalSeries,
                                        selectedFinalSeries.participant_b,
                                    )
                                "
                                class="mb-3 size-7 text-amber-300 md:size-9 2xl:size-12"
                            />
                            <Link
                                v-if="
                                    selectedFinalSeries.participant_b
                                        ?.profile_url
                                "
                                :href="
                                    selectedFinalSeries.participant_b
                                        .profile_url
                                "
                                class="line-clamp-2 text-2xl leading-tight font-black transition hover:text-orange-200 md:text-3xl 2xl:text-6xl"
                                :class="
                                    participantClasses(
                                        selectedFinalSeries,
                                        selectedFinalSeries.participant_b,
                                    )
                                "
                            >
                                {{
                                    selectedFinalSeries.participant_b
                                        .display_name
                                }}
                            </Link>
                            <p
                                v-else
                                class="line-clamp-2 text-2xl leading-tight font-black md:text-3xl 2xl:text-6xl"
                                :class="
                                    participantClasses(
                                        selectedFinalSeries,
                                        selectedFinalSeries.participant_b,
                                    )
                                "
                            >
                                {{
                                    selectedFinalSeries.participant_b
                                        ?.display_name ?? '—'
                                }}
                            </p>
                        </div>
                    </div>
                </div>

                <div v-else ref="bracketViewport" class="w-full">
                    <div
                        class="relative mx-auto"
                        :style="{
                            width: `${bracketBoardWidth}px`,
                            height: `${bracketBoardHeight}px`,
                        }"
                    >
                        <svg
                            class="pointer-events-none absolute inset-0 size-full text-white/20"
                            aria-hidden="true"
                        >
                            <path
                                v-for="(path, index) in connectorPaths"
                                :key="index"
                                :d="path"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.5"
                                vector-effect="non-scaling-stroke"
                            />
                        </svg>

                        <div
                            v-for="(round, columnIndex) in visibleRounds"
                            :key="round.round_key"
                            class="absolute top-0 flex items-center justify-between gap-2"
                            :style="roundHeadingStyle(columnIndex)"
                        >
                            <div class="flex min-w-0 items-center gap-2">
                                <h3
                                    class="truncate font-bold"
                                    :class="
                                        round.round_key === 'final'
                                            ? 'text-lg text-amber-100 md:text-xl 2xl:text-3xl'
                                            : 'text-base md:text-lg 2xl:text-2xl'
                                    "
                                >
                                    {{
                                        round.round_key === 'final'
                                            ? 'Veliko finale'
                                            : round.round_label
                                    }}
                                </h3>
                            </div>
                            <span
                                v-if="
                                    activeRoundIndex ===
                                    visibleWindowStartIndex + columnIndex
                                "
                                class="inline-flex shrink-0 items-center gap-1.5 rounded-full bg-orange-500/15 px-2 py-1 text-[10px] font-semibold text-orange-200 uppercase 2xl:px-3 2xl:text-xs"
                            >
                                <span
                                    class="size-1.5 rounded-full bg-orange-400"
                                />
                                Aktivna
                            </span>
                        </div>

                        <template
                            v-for="(round, columnIndex) in visibleRounds"
                            :key="`${round.round_key}-cards`"
                        >
                            <button
                                v-for="(series, seriesIndex) in round.series"
                                :key="`${series.round_key}-${series.position}`"
                                type="button"
                                class="group absolute z-10 overflow-hidden rounded-xl border text-left shadow-lg shadow-black/20 transition md:rounded-2xl"
                                :class="seriesCardClasses(series)"
                                :style="
                                    seriesCardStyle(columnIndex, seriesIndex)
                                "
                                @click="selectedSeries = series"
                            >
                                <div
                                    class="flex h-9 items-center justify-between gap-2 border-b border-white/10 px-3 2xl:h-11 2xl:px-4"
                                    :class="
                                        series.round_key === 'final'
                                            ? 'border-amber-300/20 bg-amber-400/[0.09]'
                                            : ''
                                    "
                                >
                                    <span
                                        class="flex min-w-0 items-center gap-1.5 truncate text-[11px] font-medium 2xl:text-sm"
                                        :class="
                                            series.round_key === 'final'
                                                ? 'text-amber-100'
                                                : 'text-zinc-400'
                                        "
                                    >
                                        <span class="truncate">
                                            {{
                                                series.round_key === 'final'
                                                    ? 'Finale za šampiona'
                                                    : series.title
                                            }}
                                        </span>
                                    </span>
                                </div>

                                <div
                                    class="grid h-[36px] grid-cols-[minmax(0,1fr)_auto] items-center gap-2 border-b border-white/[0.07] px-3 2xl:h-[52px] 2xl:px-4"
                                    :class="
                                        isSeriesWinner(
                                            series,
                                            series.participant_a,
                                        )
                                            ? 'bg-emerald-500/10'
                                            : 'bg-white/[0.035]'
                                    "
                                >
                                    <span
                                        :class="[
                                            bracketPlayerNameClasses(series),
                                            participantClasses(
                                                series,
                                                series.participant_a,
                                            ),
                                        ]"
                                    >
                                        {{
                                            series.participant_a
                                                ?.display_name ?? '—'
                                        }}
                                    </span>
                                    <span
                                        :class="[
                                            bracketScoreClasses(series),
                                            isSeriesWinner(
                                                series,
                                                series.participant_a,
                                            )
                                                ? 'text-emerald-300'
                                                : 'text-zinc-300',
                                        ]"
                                    >
                                        {{ series.participant_a_wins }}
                                    </span>
                                </div>

                                <div
                                    class="grid h-[36px] grid-cols-[minmax(0,1fr)_auto] items-center gap-2 px-3 2xl:h-[52px] 2xl:px-4"
                                    :class="
                                        isSeriesWinner(
                                            series,
                                            series.participant_b,
                                        )
                                            ? 'bg-emerald-500/10'
                                            : 'bg-white/[0.035]'
                                    "
                                >
                                    <span
                                        :class="[
                                            bracketPlayerNameClasses(series),
                                            participantClasses(
                                                series,
                                                series.participant_b,
                                            ),
                                        ]"
                                    >
                                        {{
                                            series.participant_b
                                                ?.display_name ?? '—'
                                        }}
                                    </span>
                                    <span
                                        :class="[
                                            bracketScoreClasses(series),
                                            isSeriesWinner(
                                                series,
                                                series.participant_b,
                                            )
                                                ? 'text-emerald-300'
                                                : 'text-zinc-300',
                                        ]"
                                    >
                                        {{ series.participant_b_wins }}
                                    </span>
                                </div>
                            </button>
                        </template>
                    </div>
                </div>
            </div>

            <div v-else-if="selectedTvRound" class="p-4 md:p-6 2xl:p-8">
                <div
                    class="mb-5 flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between"
                >
                    <div>
                        <p
                            class="text-xs font-semibold tracking-[0.18em] text-orange-300 uppercase 2xl:text-sm"
                        >
                            Izabrana runda
                        </p>
                        <h3
                            class="mt-1 text-2xl font-bold md:text-3xl 2xl:text-4xl"
                        >
                            {{ selectedTvRound.round_label }}
                        </h3>
                    </div>
                    <p class="text-sm text-zinc-400 2xl:text-lg">
                        {{ selectedTvRoundFinishedCount }} /
                        {{ selectedTvRound.series.length }} duela završeno
                    </p>
                </div>

                <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3 2xl:gap-5">
                    <button
                        v-for="series in selectedTvRound.series"
                        :key="`${series.round_key}-${series.position}`"
                        type="button"
                        class="group min-h-40 overflow-hidden rounded-2xl border text-left shadow-lg shadow-black/20 transition 2xl:min-h-56 2xl:rounded-3xl"
                        :class="seriesCardClasses(series)"
                        @click="selectedSeries = series"
                    >
                        <div
                            class="flex min-h-11 items-center justify-between gap-3 border-b border-white/10 px-4 py-2 2xl:min-h-14 2xl:px-5"
                        >
                            <span
                                class="truncate text-xs font-semibold text-zinc-400 2xl:text-base"
                            >
                                {{ series.title }}
                            </span>
                        </div>

                        <div
                            class="grid min-h-[58px] grid-cols-[minmax(0,1fr)_auto] items-center gap-4 border-b border-white/[0.07] px-4 py-2 2xl:min-h-[82px] 2xl:px-5"
                            :class="
                                isSeriesWinner(series, series.participant_a)
                                    ? 'bg-emerald-500/[0.12]'
                                    : 'bg-white/[0.025]'
                            "
                        >
                            <span
                                class="truncate text-lg font-semibold md:text-xl 2xl:text-2xl"
                                :class="
                                    participantClasses(
                                        series,
                                        series.participant_a,
                                    )
                                "
                            >
                                {{ series.participant_a?.display_name ?? '—' }}
                            </span>
                            <span
                                class="flex min-w-8 items-center justify-end gap-2 text-2xl font-black 2xl:text-4xl"
                                :class="
                                    isSeriesWinner(series, series.participant_a)
                                        ? 'text-emerald-300'
                                        : 'text-zinc-200'
                                "
                            >
                                {{ series.participant_a_wins }}
                            </span>
                        </div>

                        <div
                            class="grid min-h-[58px] grid-cols-[minmax(0,1fr)_auto] items-center gap-4 px-4 py-2 2xl:min-h-[82px] 2xl:px-5"
                            :class="
                                isSeriesWinner(series, series.participant_b)
                                    ? 'bg-emerald-500/[0.12]'
                                    : 'bg-white/[0.025]'
                            "
                        >
                            <span
                                class="truncate text-lg font-semibold md:text-xl 2xl:text-2xl"
                                :class="
                                    participantClasses(
                                        series,
                                        series.participant_b,
                                    )
                                "
                            >
                                {{ series.participant_b?.display_name ?? '—' }}
                            </span>
                            <span
                                class="flex min-w-8 items-center justify-end gap-2 text-2xl font-black 2xl:text-4xl"
                                :class="
                                    isSeriesWinner(series, series.participant_b)
                                        ? 'text-emerald-300'
                                        : 'text-zinc-200'
                                "
                            >
                                {{ series.participant_b_wins }}
                            </span>
                        </div>
                    </button>
                </div>
            </div>

            <div
                v-if="
                    viewMode === 'bracket' &&
                    isFinalRoundSelected &&
                    thirdPlaceRound?.series.length
                "
                class="border-t border-white/10 bg-gradient-to-b from-orange-500/[0.04] to-transparent p-3.5 md:p-6 2xl:p-8"
            >
                <div class="mx-auto max-w-[320px] 2xl:max-w-[420px]">
                    <div
                        class="mb-3 flex items-center justify-between gap-3 px-1"
                    >
                        <div>
                            <p
                                class="text-[10px] font-semibold tracking-[0.18em] text-orange-300 uppercase 2xl:text-xs"
                            >
                                Borba za bronzu
                            </p>
                            <h3 class="mt-0.5 text-base font-bold 2xl:text-xl">
                                Meč za treće mesto
                            </h3>
                        </div>
                    </div>

                    <button
                        v-for="series in thirdPlaceRound.series"
                        :key="`${series.round_key}-${series.position}`"
                        type="button"
                        class="w-full overflow-hidden rounded-xl border text-left shadow-lg shadow-black/20 transition md:rounded-2xl"
                        :class="seriesCardClasses(series)"
                        @click="selectedSeries = series"
                    >
                        <div
                            class="flex h-9 items-center justify-between gap-2 border-b border-white/10 px-3 2xl:h-11 2xl:px-4"
                        >
                            <span
                                class="truncate text-[11px] text-zinc-400 2xl:text-sm"
                            >
                                {{ series.title }}
                            </span>
                        </div>
                        <div
                            class="grid h-9 grid-cols-[minmax(0,1fr)_auto] items-center gap-2 border-b border-white/[0.07] px-3 2xl:h-[52px] 2xl:px-4"
                            :class="
                                isSeriesWinner(series, series.participant_a)
                                    ? 'bg-emerald-500/10'
                                    : 'bg-white/[0.035]'
                            "
                        >
                            <span
                                class="truncate text-sm font-medium 2xl:text-lg"
                                :class="
                                    participantClasses(
                                        series,
                                        series.participant_a,
                                    )
                                "
                            >
                                {{ series.participant_a?.display_name ?? '—' }}
                            </span>
                            <strong
                                class="flex items-center gap-1 2xl:text-xl"
                                :class="
                                    isSeriesWinner(series, series.participant_a)
                                        ? 'text-emerald-300'
                                        : 'text-zinc-300'
                                "
                            >
                                {{ series.participant_a_wins }}
                            </strong>
                        </div>
                        <div
                            class="grid h-9 grid-cols-[minmax(0,1fr)_auto] items-center gap-2 px-3 2xl:h-[52px] 2xl:px-4"
                            :class="
                                isSeriesWinner(series, series.participant_b)
                                    ? 'bg-emerald-500/10'
                                    : 'bg-white/[0.035]'
                            "
                        >
                            <span
                                class="truncate text-sm font-medium 2xl:text-lg"
                                :class="
                                    participantClasses(
                                        series,
                                        series.participant_b,
                                    )
                                "
                            >
                                {{ series.participant_b?.display_name ?? '—' }}
                            </span>
                            <strong
                                class="flex items-center gap-1 2xl:text-xl"
                                :class="
                                    isSeriesWinner(series, series.participant_b)
                                        ? 'text-emerald-300'
                                        : 'text-zinc-300'
                                "
                            >
                                {{ series.participant_b_wins }}
                            </strong>
                        </div>
                    </button>
                </div>
            </div>
        </section>

        <section
            v-else
            class="rounded-3xl border border-dashed border-white/10 p-6 text-zinc-400"
        >
            Nokaut kostur još nije generisan.
        </section>
        <Dialog
            :open="selectedSeries !== null"
            @update:open="
                (open) => {
                    if (!open) selectedSeries = null;
                }
            "
        >
            <DialogContent
                :class="[
                    'public-tournament max-h-[90vh] overflow-y-auto border-white/10 bg-zinc-950 text-zinc-50 sm:max-w-2xl 2xl:max-w-4xl',
                    `public-theme-${venue.public_theme}`,
                ]"
            >
                <template v-if="selectedSeries">
                    <DialogHeader>
                        <div
                            class="flex flex-wrap items-center justify-between gap-2 pr-7"
                        >
                            <DialogTitle
                                class="text-2xl text-zinc-50 2xl:text-4xl"
                            >
                                {{ selectedSeries.title }}
                            </DialogTitle>
                            <span
                                class="rounded-full border border-white/10 bg-white/[0.06] px-2.5 py-1 text-xs font-semibold text-zinc-300 2xl:px-4 2xl:py-2 2xl:text-base"
                            >
                                Na {{ selectedSeries.wins_required }} dobijene
                            </span>
                        </div>
                        <DialogDescription class="sr-only">
                            Rezultati partija za {{ selectedSeries.title }}.
                        </DialogDescription>
                    </DialogHeader>

                    <div
                        class="grid grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)] items-stretch gap-2 rounded-2xl border border-white/10 bg-white/[0.025] p-2 sm:gap-3 sm:p-3"
                    >
                        <div
                            class="flex min-w-0 flex-col justify-center rounded-xl px-2.5 py-3 sm:px-3"
                            :class="
                                isSeriesWinner(
                                    selectedSeries,
                                    selectedSeries.participant_a,
                                )
                                    ? 'border border-emerald-400/25 bg-emerald-500/[0.12]'
                                    : 'bg-white/[0.035]'
                            "
                        >
                            <p
                                class="line-clamp-2 text-sm font-bold sm:text-lg 2xl:text-2xl"
                                :class="
                                    participantClasses(
                                        selectedSeries,
                                        selectedSeries.participant_a,
                                    )
                                "
                            >
                                {{
                                    selectedSeries.participant_a
                                        ?.display_name ?? '—'
                                }}
                            </p>
                        </div>

                        <div
                            class="self-center rounded-xl bg-white px-2.5 py-2 text-lg font-black text-zinc-950 sm:px-3 sm:text-xl 2xl:px-5 2xl:py-3 2xl:text-3xl"
                        >
                            {{ selectedSeries.series_score }}
                        </div>

                        <div
                            class="flex min-w-0 flex-col justify-center rounded-xl px-2.5 py-3 text-right sm:px-3"
                            :class="
                                isSeriesWinner(
                                    selectedSeries,
                                    selectedSeries.participant_b,
                                )
                                    ? 'border border-emerald-400/25 bg-emerald-500/[0.12]'
                                    : 'bg-white/[0.035]'
                            "
                        >
                            <p
                                class="line-clamp-2 text-sm font-bold sm:text-lg 2xl:text-2xl"
                                :class="
                                    participantClasses(
                                        selectedSeries,
                                        selectedSeries.participant_b,
                                    )
                                "
                            >
                                {{
                                    selectedSeries.participant_b
                                        ?.display_name ?? '—'
                                }}
                            </p>
                        </div>
                    </div>

                    <div class="space-y-3">
                        <h3 class="text-sm font-bold text-zinc-100 2xl:text-xl">
                            Partije
                        </h3>
                        <div
                            v-for="leg in selectedSeries.legs"
                            :key="leg.id"
                            class="overflow-hidden rounded-xl border"
                            :class="
                                leg.winner
                                    ? 'border-emerald-400/15 bg-emerald-500/[0.035]'
                                    : 'border-white/10 bg-white/[0.02]'
                            "
                        >
                            <div
                                class="flex min-h-9 items-center justify-between gap-3 border-b border-white/[0.07] px-3 py-1.5 2xl:min-h-12 2xl:px-5"
                            >
                                <div
                                    class="flex flex-wrap items-center gap-2 text-sm"
                                >
                                    <span
                                        class="text-xs font-bold text-zinc-300 2xl:text-base"
                                    >
                                        Partija {{ leg.leg ?? '—' }}
                                    </span>
                                    <span
                                        v-if="walkoverLabel(leg.win_reason)"
                                        class="rounded-full bg-red-500/15 px-2 py-0.5 text-[11px] font-medium text-red-200"
                                    >
                                        {{ walkoverLabel(leg.win_reason) }}
                                    </span>
                                </div>
                                <span
                                    v-if="leg.resource_name"
                                    class="truncate text-[11px] text-zinc-500 2xl:text-sm"
                                >
                                    {{ leg.resource_name }}
                                </span>
                            </div>

                            <div
                                class="grid min-h-11 grid-cols-[minmax(0,1fr)_auto] items-center gap-3 border-b border-white/[0.07] px-3 py-2 2xl:min-h-16 2xl:px-5"
                                :class="
                                    isLegWinner(
                                        leg,
                                        selectedSeries.participant_a,
                                    )
                                        ? 'bg-emerald-500/[0.13] text-emerald-200'
                                        : leg.winner
                                          ? 'text-zinc-500'
                                          : 'text-zinc-200'
                                "
                            >
                                <span class="min-w-0">
                                    <span
                                        class="truncate text-sm font-semibold 2xl:text-xl"
                                    >
                                        {{
                                            selectedSeries.participant_a
                                                ?.display_name ?? '—'
                                        }}
                                    </span>
                                </span>
                                <strong class="text-base 2xl:text-2xl">
                                    {{ legScores(leg)[0] }}
                                </strong>
                            </div>

                            <div
                                class="grid min-h-11 grid-cols-[minmax(0,1fr)_auto] items-center gap-3 px-3 py-2 2xl:min-h-16 2xl:px-5"
                                :class="
                                    isLegWinner(
                                        leg,
                                        selectedSeries.participant_b,
                                    )
                                        ? 'bg-emerald-500/[0.13] text-emerald-200'
                                        : leg.winner
                                          ? 'text-zinc-500'
                                          : 'text-zinc-200'
                                "
                            >
                                <span class="min-w-0">
                                    <span
                                        class="truncate text-sm font-semibold 2xl:text-xl"
                                    >
                                        {{
                                            selectedSeries.participant_b
                                                ?.display_name ?? '—'
                                        }}
                                    </span>
                                </span>
                                <strong class="text-base 2xl:text-2xl">
                                    {{ legScores(leg)[1] }}
                                </strong>
                            </div>
                        </div>
                    </div>
                </template>
            </DialogContent>
        </Dialog>
    </PublicTournamentLayout>
</template>
