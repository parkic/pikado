<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ChevronDown, CircleDot, Search } from '@lucide/vue';
import { useMediaQuery } from '@vueuse/core';
import { computed, ref, watch } from 'vue';
import PublicTournamentHeader from '@/components/public/PublicTournamentHeader.vue';
import PublicTournamentLayout from '@/components/public/PublicTournamentLayout.vue';
import { usePublicTournamentRealtime } from '@/composables/usePublicTournamentRealtime';
import { normalizeSearchText } from '@/lib/normalizeSearchText';
import type { PublicVenueBranding } from '@/types';

type Tournament = {
    id: number;
    name: string;
    public_code: string;
    game_type: string;
    match_mode: string;
    status: string;
    status_label: string;
    matches_count: number;
    scheduled_matches_count: number;
    in_progress_matches_count: number;
    finished_matches_count: number;
    voided_matches_count: number;
    postponed_matches_count: number;
};

type MatchParticipant = {
    id: number;
    display_name: string;
    qualification_position: string | null;
    is_withdrawn: boolean;
    profile_url: string | null;
} | null;

type PublicMatch = {
    id: number;
    scheduled_order: number | null;
    stage: string;
    stage_label: string;
    group_name: string | null;
    bracket_round: string | null;
    bracket_round_label: string | null;
    bracket_position: number | null;
    round_robin_leg: number | null;
    wins_required: number | null;
    participant_a: MatchParticipant;
    participant_b: MatchParticipant;
    score_a: number | null;
    score_b: number | null;
    winner: MatchParticipant;
    status: string;
    status_label: string;
    resource_name: string | null;
    finished_at: string | null;
};

const props = defineProps<{
    venue: PublicVenueBranding;
    tournament: Tournament;
    matches: PublicMatch[];
}>();

usePublicTournamentRealtime({
    publicCode: props.tournament.public_code,
});

const isMobile = useMediaQuery('(max-width: 767px)');
const search = ref('');
const statusFilter = ref('open');
const groupFilter = ref('all');
const resourceFilter = ref('all');
const visibleLimit = ref(12);

const groups = computed(() =>
    Array.from(
        new Set(
            props.matches
                .map((match) => match.group_name)
                .filter((group): group is string => Boolean(group)),
        ),
    ).sort(),
);

const resources = computed(() =>
    Array.from(
        new Set(
            props.matches
                .map((match) => match.resource_name)
                .filter((resource): resource is string => Boolean(resource)),
        ),
    ).sort(),
);

const filteredMatches = computed(() => {
    const query = normalizeSearchText(search.value);

    const matches = props.matches.filter((match) => {
        if (
            statusFilter.value === 'open' &&
            !['scheduled', 'in_progress', 'postponed'].includes(match.status)
        ) {
            return false;
        }

        if (
            statusFilter.value !== 'all' &&
            statusFilter.value !== 'open' &&
            match.status !== statusFilter.value
        ) {
            return false;
        }

        if (
            groupFilter.value !== 'all' &&
            match.group_name !== groupFilter.value
        ) {
            return false;
        }

        if (
            resourceFilter.value !== 'all' &&
            match.resource_name !== resourceFilter.value
        ) {
            return false;
        }

        if (query === '') {
            return true;
        }

        return [
            match.participant_a?.display_name,
            match.participant_b?.display_name,
            match.group_name ? `grupa ${match.group_name}` : null,
            match.resource_name,
        ]
            .filter(Boolean)
            .some((value) => normalizeSearchText(value!).includes(query));
    });

    if (statusFilter.value === 'finished') {
        return matches.sort((matchA, matchB) => {
            const finishedAtA = matchA.finished_at
                ? new Date(matchA.finished_at).getTime()
                : 0;
            const finishedAtB = matchB.finished_at
                ? new Date(matchB.finished_at).getTime()
                : 0;

            if (finishedAtA !== finishedAtB) {
                return finishedAtB - finishedAtA;
            }

            return (
                (matchB.scheduled_order ?? 0) - (matchA.scheduled_order ?? 0)
            );
        });
    }

    return matches;
});

const displayedMatches = computed(() =>
    isMobile.value
        ? filteredMatches.value.slice(0, visibleLimit.value)
        : filteredMatches.value,
);

watch([search, statusFilter, groupFilter, resourceFilter], () => {
    visibleLimit.value = 12;
});

const matchContextLabel = (match: PublicMatch): string => {
    if (match.group_name) {
        return `Grupa ${match.group_name}`;
    }

    if (match.bracket_round_label) {
        return `${match.bracket_round_label}${match.bracket_position ? ` #${match.bracket_position}` : ''}`;
    }

    return match.stage_label;
};

const participantLabel = (participant: MatchParticipant): string => {
    if (!participant) {
        return '—';
    }

    return participant.display_name;
};

const statusClasses = (status: string): string => {
    if (status === 'finished') {
        return 'bg-emerald-500/15 text-emerald-200';
    }

    if (status === 'in_progress') {
        return 'bg-sky-500/15 text-sky-200';
    }

    if (status === 'postponed') {
        return 'bg-amber-500/15 text-amber-200';
    }

    if (status === 'voided') {
        return 'bg-zinc-700 text-zinc-300';
    }

    if (status === 'cancelled') {
        return 'bg-red-500/15 text-red-200';
    }

    return 'bg-yellow-500/15 text-yellow-200';
};

const stageClasses = (stage: string): string => {
    if (stage === 'group') {
        return 'bg-violet-500/15 text-violet-200';
    }

    if (stage === 'final') {
        return 'bg-amber-500/15 text-amber-200';
    }

    if (stage === 'third_place') {
        return 'bg-orange-500/15 text-orange-200';
    }

    return 'bg-cyan-500/15 text-cyan-200';
};

const matchRowClasses = (match: PublicMatch): string =>
    match.status === 'finished'
        ? 'bg-emerald-500/[0.045] hover:bg-emerald-500/[0.075]'
        : 'hover:bg-white/[0.025]';

const matchCardClasses = (match: PublicMatch): string => {
    if (match.status === 'finished') {
        return 'border-emerald-400/20 bg-emerald-500/[0.045]';
    }

    if (match.status === 'in_progress') {
        return 'border-sky-400/25 bg-sky-500/[0.05]';
    }

    if (match.status === 'postponed') {
        return 'border-amber-400/30 bg-amber-500/[0.07]';
    }

    if (['voided', 'cancelled'].includes(match.status)) {
        return 'border-white/10 bg-zinc-900/70 opacity-75';
    }

    return 'border-white/10 bg-black/25';
};

const participantClasses = (
    match: PublicMatch,
    participant: MatchParticipant,
): string => {
    if (participant?.is_withdrawn) {
        return 'text-zinc-600 line-through';
    }

    if (match.winner?.id === participant?.id) {
        return 'font-bold text-emerald-300';
    }

    return match.status === 'finished' ? 'text-zinc-500' : 'text-zinc-100';
};
</script>

<template>
    <Head :title="`${tournament.name} - Raspored`" />

    <PublicTournamentLayout :theme="venue.public_theme">
        <PublicTournamentHeader
            :venue="venue"
            :tournament="tournament"
            active-page="schedule"
        />

        <details
            class="group rounded-2xl border border-white/10 bg-white/[0.03]"
        >
            <summary
                class="flex cursor-pointer list-none items-center justify-between gap-3 px-3.5 py-3 text-sm font-semibold text-zinc-300 transition hover:text-white md:px-5"
            >
                <span>Statistika mečeva</span>
                <ChevronDown
                    class="size-4 shrink-0 transition group-open:rotate-180"
                />
            </summary>

            <section
                class="grid grid-cols-3 gap-1 border-t border-white/10 p-2 md:grid-cols-6 md:gap-3 md:p-3"
            >
                <div
                    class="min-w-0 rounded-xl border border-white/10 bg-white/[0.03] p-2 md:rounded-2xl md:p-4"
                >
                    <p
                        class="text-[9px] whitespace-nowrap text-zinc-400 md:text-sm"
                    >
                        Ukupno
                    </p>

                    <p class="mt-1 text-lg font-semibold md:mt-2 md:text-2xl">
                        {{ tournament.matches_count }}
                    </p>
                </div>

                <div
                    class="min-w-0 rounded-xl border border-yellow-500/20 bg-yellow-500/5 p-2 md:rounded-2xl md:p-4"
                >
                    <p
                        class="text-[9px] whitespace-nowrap text-yellow-200 md:text-sm"
                    >
                        Zakazano
                    </p>

                    <p
                        class="mt-1 text-lg font-semibold text-yellow-200 md:mt-2 md:text-2xl"
                    >
                        {{ tournament.scheduled_matches_count }}
                    </p>
                </div>

                <div
                    class="min-w-0 rounded-xl border border-sky-500/20 bg-sky-500/5 p-2 md:rounded-2xl md:p-4"
                >
                    <p
                        class="text-[9px] whitespace-nowrap text-sky-200 md:text-sm"
                    >
                        U toku
                    </p>

                    <p
                        class="mt-1 text-lg font-semibold text-sky-200 md:mt-2 md:text-2xl"
                    >
                        {{ tournament.in_progress_matches_count }}
                    </p>
                </div>

                <div
                    class="min-w-0 rounded-xl border border-amber-500/25 bg-amber-500/[0.07] p-2 md:rounded-2xl md:p-4"
                >
                    <p
                        class="text-[9px] leading-tight text-amber-200 md:text-sm"
                    >
                        Preskočeno
                    </p>
                    <p
                        class="mt-1 text-lg font-semibold text-amber-200 md:mt-2 md:text-2xl"
                    >
                        {{ tournament.postponed_matches_count }}
                    </p>
                </div>

                <div
                    class="min-w-0 rounded-xl border border-emerald-500/20 bg-emerald-500/5 p-2 md:rounded-2xl md:p-4"
                >
                    <p
                        class="text-[9px] whitespace-nowrap text-emerald-200 md:text-sm"
                    >
                        Završeno
                    </p>

                    <p
                        class="mt-1 text-lg font-semibold text-emerald-200 md:mt-2 md:text-2xl"
                    >
                        {{ tournament.finished_matches_count }}
                    </p>
                </div>

                <div
                    class="min-w-0 rounded-xl border border-white/10 bg-white/[0.03] p-2 md:rounded-2xl md:p-4"
                >
                    <p
                        class="text-[9px] whitespace-nowrap text-zinc-400 md:text-sm"
                    >
                        Anulirano
                    </p>

                    <p class="mt-1 text-lg font-semibold md:mt-2 md:text-2xl">
                        {{ tournament.voided_matches_count }}
                    </p>
                </div>
            </section>
        </details>

        <section
            class="rounded-2xl border border-white/10 bg-white/[0.03] p-3.5 md:rounded-3xl md:p-5"
        >
            <div
                class="flex flex-col gap-2 md:flex-row md:items-end md:justify-between"
            >
                <div>
                    <h2 class="text-xl font-semibold md:text-2xl 2xl:text-4xl">
                        Svi mečevi
                    </h2>

                    <p class="mt-1 hidden text-sm text-zinc-400 md:block">
                        Redosled je isti kao u admin rasporedu.
                    </p>
                </div>
            </div>

            <div
                class="mt-3 grid gap-2.5 md:mt-5 md:gap-3 lg:grid-cols-[minmax(0,1fr)_auto]"
            >
                <label class="relative block">
                    <Search
                        class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-zinc-500"
                    />
                    <input
                        v-model="search"
                        type="search"
                        class="w-full rounded-xl border border-white/10 bg-black/30 py-2.5 pr-3 pl-9 text-sm text-white transition outline-none placeholder:text-zinc-600 focus:border-orange-400"
                        placeholder="Pronađi učesnika..."
                    />
                </label>

                <div
                    class="grid grid-cols-4 gap-1.5 md:flex md:gap-2 md:overflow-x-auto"
                >
                    <button
                        type="button"
                        class="shrink-0 rounded-xl px-2 py-2 text-xs font-medium md:rounded-full md:px-3 md:text-sm"
                        :class="
                            statusFilter === 'open'
                                ? 'bg-white text-zinc-950'
                                : 'border border-white/10 text-zinc-300'
                        "
                        @click="statusFilter = 'open'"
                    >
                        Sledeći
                    </button>
                    <button
                        type="button"
                        class="shrink-0 rounded-xl px-1.5 py-2 text-[11px] font-medium md:rounded-full md:px-3 md:text-sm"
                        :class="
                            statusFilter === 'postponed'
                                ? 'bg-amber-300 text-zinc-950'
                                : 'border border-white/10 text-zinc-300'
                        "
                        @click="statusFilter = 'postponed'"
                    >
                        Preskočeni
                    </button>
                    <button
                        type="button"
                        class="shrink-0 rounded-xl px-2 py-2 text-xs font-medium md:rounded-full md:px-3 md:text-sm"
                        :class="
                            statusFilter === 'finished'
                                ? 'bg-white text-zinc-950'
                                : 'border border-white/10 text-zinc-300'
                        "
                        @click="statusFilter = 'finished'"
                    >
                        Završeni
                    </button>
                    <button
                        type="button"
                        class="shrink-0 rounded-xl px-2 py-2 text-xs font-medium md:rounded-full md:px-3 md:text-sm"
                        :class="
                            statusFilter === 'all'
                                ? 'bg-white text-zinc-950'
                                : 'border border-white/10 text-zinc-300'
                        "
                        @click="statusFilter = 'all'"
                    >
                        Svi
                    </button>
                </div>
            </div>

            <div class="mt-2.5 grid grid-cols-2 gap-2 md:mt-3 md:gap-3">
                <select
                    v-model="groupFilter"
                    aria-label="Filtriraj po grupi"
                    class="w-full min-w-0 rounded-xl border border-white/10 bg-zinc-950 px-2.5 py-2.5 text-xs text-zinc-200 transition outline-none focus:border-orange-400 md:px-3 md:text-sm"
                >
                    <option value="all">Sve grupe</option>
                    <option v-for="group in groups" :key="group" :value="group">
                        Grupa {{ group }}
                    </option>
                </select>

                <select
                    v-model="resourceFilter"
                    aria-label="Filtriraj po tabli ili stolu"
                    class="w-full min-w-0 rounded-xl border border-white/10 bg-zinc-950 px-2.5 py-2.5 text-xs text-zinc-200 transition outline-none focus:border-orange-400 md:px-3 md:text-sm"
                >
                    <option value="all">Sve table / stolovi</option>
                    <option
                        v-for="resource in resources"
                        :key="resource"
                        :value="resource"
                    >
                        {{ resource }}
                    </option>
                </select>
            </div>

            <div
                v-if="filteredMatches.length && !isMobile"
                class="mt-5 overflow-hidden rounded-2xl border border-white/10"
            >
                <div class="overflow-x-auto">
                    <table
                        class="w-full min-w-[980px] text-left text-sm 2xl:text-lg"
                    >
                        <thead class="bg-white/5 text-zinc-400">
                            <tr>
                                <th class="px-3 py-3 font-medium">#</th>
                                <th class="px-3 py-3 font-medium">Faza</th>
                                <th class="px-3 py-3 font-medium">Kontekst</th>
                                <th class="px-3 py-3 font-medium">Meč</th>
                                <th class="px-3 py-3 font-medium">
                                    Tabla / sto
                                </th>
                                <th class="px-3 py-3 text-center font-medium">
                                    Leg
                                </th>
                                <th class="px-3 py-3 text-center font-medium">
                                    Rezultat
                                </th>
                                <th class="px-3 py-3 text-center font-medium">
                                    Pobednik
                                </th>
                                <th class="px-3 py-3 text-center font-medium">
                                    Status
                                </th>
                            </tr>
                        </thead>

                        <tbody>
                            <tr
                                v-for="match in filteredMatches"
                                :key="match.id"
                                class="border-t border-white/10 transition"
                                :class="matchRowClasses(match)"
                            >
                                <td class="px-3 py-3 text-zinc-400">
                                    {{ match.scheduled_order ?? '-' }}
                                </td>

                                <td class="px-3 py-3">
                                    <span
                                        class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium"
                                        :class="stageClasses(match.stage)"
                                    >
                                        {{ match.stage_label }}
                                    </span>
                                </td>

                                <td class="px-3 py-3 text-zinc-300">
                                    {{ matchContextLabel(match) }}
                                </td>

                                <td class="px-3 py-3">
                                    <div
                                        class="grid grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)] items-center gap-2"
                                    >
                                        <div>
                                            <p
                                                class="font-semibold 2xl:text-xl"
                                                :class="
                                                    participantClasses(
                                                        match,
                                                        match.participant_a,
                                                    )
                                                "
                                            >
                                                {{
                                                    participantLabel(
                                                        match.participant_a,
                                                    )
                                                }}
                                            </p>
                                        </div>

                                        <span class="text-zinc-500"> vs </span>

                                        <div class="text-right">
                                            <p
                                                class="font-semibold 2xl:text-xl"
                                                :class="
                                                    participantClasses(
                                                        match,
                                                        match.participant_b,
                                                    )
                                                "
                                            >
                                                {{
                                                    participantLabel(
                                                        match.participant_b,
                                                    )
                                                }}
                                            </p>
                                        </div>
                                    </div>
                                </td>

                                <td class="px-3 py-3 text-zinc-400">
                                    {{ match.resource_name ?? '-' }}
                                </td>

                                <td class="px-3 py-3 text-center text-zinc-400">
                                    <template v-if="match.round_robin_leg">
                                        {{ match.round_robin_leg }}

                                        <span
                                            v-if="
                                                match.wins_required &&
                                                match.stage !== 'group'
                                            "
                                            class="block text-xs text-zinc-500"
                                        >
                                            na {{ match.wins_required }}
                                        </span>
                                    </template>

                                    <template v-else> - </template>
                                </td>

                                <td class="px-3 py-3 text-center">
                                    <span
                                        class="rounded-xl bg-white/10 px-3 py-1 font-semibold 2xl:text-2xl"
                                    >
                                        {{ match.score_a ?? '-' }} :
                                        {{ match.score_b ?? '-' }}
                                    </span>
                                </td>

                                <td class="px-3 py-3 text-center text-zinc-300">
                                    <span
                                        :class="
                                            match.winner
                                                ? 'font-semibold text-emerald-300'
                                                : ''
                                        "
                                    >
                                        {{ participantLabel(match.winner) }}
                                    </span>
                                </td>

                                <td class="px-3 py-3 text-center">
                                    <span
                                        class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium"
                                        :class="statusClasses(match.status)"
                                    >
                                        {{ match.status_label }}
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div
                v-else-if="filteredMatches.length"
                class="mt-3 space-y-2 md:mt-5 md:space-y-3"
            >
                <article
                    v-for="match in displayedMatches"
                    :key="match.id"
                    class="rounded-xl border p-3 md:rounded-2xl md:p-4"
                    :class="matchCardClasses(match)"
                >
                    <div class="flex items-start justify-between gap-2">
                        <p
                            class="pt-1 text-[10px] font-medium text-zinc-400 md:text-xs 2xl:text-lg"
                        >
                            #{{ match.scheduled_order ?? '-' }} ·
                            {{ matchContextLabel(match) }}
                        </p>
                        <p
                            class="inline-flex max-w-[52%] shrink-0 items-center gap-1 rounded-md bg-orange-500/10 px-1.5 py-1 text-[10px] font-bold text-orange-200 md:text-xs 2xl:text-lg"
                        >
                            <CircleDot class="size-3 shrink-0" />
                            <span class="truncate">
                                {{
                                    match.resource_name ??
                                    'Tabla nije dodeljena'
                                }}
                            </span>
                        </p>
                    </div>

                    <div
                        class="mt-2.5 grid grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)] items-center gap-2 md:mt-4 md:gap-3"
                    >
                        <div>
                            <Link
                                v-if="match.participant_a?.profile_url"
                                :href="match.participant_a.profile_url"
                                class="line-clamp-2 text-xs leading-tight font-semibold md:text-base 2xl:text-2xl"
                                :class="
                                    participantClasses(
                                        match,
                                        match.participant_a,
                                    )
                                "
                            >
                                {{ participantLabel(match.participant_a) }}
                            </Link>
                            <p
                                v-else
                                class="line-clamp-2 text-xs leading-tight font-semibold md:text-base 2xl:text-2xl"
                                :class="
                                    participantClasses(
                                        match,
                                        match.participant_a,
                                    )
                                "
                            >
                                {{ participantLabel(match.participant_a) }}
                            </p>
                        </div>

                        <div
                            class="rounded-lg border border-white/10 bg-white/10 px-2 py-1.5 text-base font-black md:rounded-xl md:px-3 md:py-2 md:text-xl 2xl:text-3xl"
                        >
                            {{ match.score_a ?? '-' }} :
                            {{ match.score_b ?? '-' }}
                        </div>

                        <div class="text-right">
                            <Link
                                v-if="match.participant_b?.profile_url"
                                :href="match.participant_b.profile_url"
                                class="line-clamp-2 text-xs leading-tight font-semibold md:text-base 2xl:text-2xl"
                                :class="
                                    participantClasses(
                                        match,
                                        match.participant_b,
                                    )
                                "
                            >
                                {{ participantLabel(match.participant_b) }}
                            </Link>
                            <p
                                v-else
                                class="line-clamp-2 text-xs leading-tight font-semibold md:text-base 2xl:text-2xl"
                                :class="
                                    participantClasses(
                                        match,
                                        match.participant_b,
                                    )
                                "
                            >
                                {{ participantLabel(match.participant_b) }}
                            </p>
                        </div>
                    </div>
                </article>

                <button
                    v-if="displayedMatches.length < filteredMatches.length"
                    type="button"
                    class="w-full rounded-xl border border-white/15 px-4 py-3 text-sm font-medium text-zinc-200 transition hover:bg-white/5"
                    @click="visibleLimit += 12"
                >
                    Prikaži još ({{
                        filteredMatches.length - displayedMatches.length
                    }})
                </button>
            </div>

            <p
                v-else
                class="mt-5 rounded-2xl border border-dashed border-white/10 p-4 text-sm text-zinc-400"
            >
                Nema mečeva za izabrani filter.
            </p>
        </section>
    </PublicTournamentLayout>
</template>
