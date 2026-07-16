<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { usePublicTournamentRealtime } from '@/composables/usePublicTournamentRealtime';

type Venue = {
    name: string;
    slug: string;
    logo_path: string | null;
};

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
};

type SeriesParticipant = {
    id: number;
    display_name: string;
    group_position: string | null;
    is_withdrawn: boolean;
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

const props = defineProps<{
    venue: Venue;
    tournament: Tournament;
    rounds: KnockoutRound[];
}>();

const { realtimeStatus, lastRealtimeUpdateAt } = usePublicTournamentRealtime({
    publicCode: props.tournament.public_code,
});

const seriesStatusClasses = (status: string): string => {
    if (status === 'finished') {
        return 'bg-emerald-500/15 text-emerald-200';
    }

    if (status === 'in_progress') {
        return 'bg-sky-500/15 text-sky-200';
    }

    if (status === 'waiting_participants') {
        return 'bg-yellow-500/15 text-yellow-200';
    }

    return 'bg-zinc-700 text-zinc-300';
};

const legStatusClasses = (status: string): string => {
    if (status === 'finished') {
        return 'bg-emerald-500/15 text-emerald-200';
    }

    if (status === 'voided') {
        return 'bg-zinc-700 text-zinc-300';
    }

    if (status === 'in_progress') {
        return 'bg-sky-500/15 text-sky-200';
    }

    return 'bg-yellow-500/15 text-yellow-200';
};

const walkoverLabel = (reason: string | null): string | null => {
    if (reason === 'opponent_withdrew') {
        return 'Walkover';
    }

    if (reason === 'walkover') {
        return 'Walkover';
    }

    return null;
};
</script>

<template>
    <Head :title="`${tournament.name} - Nokaut`" />

    <div class="min-h-screen bg-zinc-950 text-zinc-50">
        <main class="mx-auto flex w-full max-w-full flex-col gap-6 px-4 py-6 md:px-8">
            <header class="rounded-3xl border border-white/10 bg-white/[0.03] p-5 md:p-8">
                <div class="flex flex-col gap-5 md:flex-row md:items-start md:justify-between">
                    <div>
                        <p class="text-sm uppercase tracking-[0.25em] text-zinc-400">
                            {{ venue.name }}
                        </p>

                        <h1 class="mt-3 text-3xl font-bold tracking-tight md:text-5xl">
                            Nokaut
                        </h1>

                        <p class="mt-3 max-w-3xl text-zinc-400">
                            {{ tournament.name }} · {{ tournament.status_label }}
                        </p>

                        <div class="mt-3 flex flex-wrap items-center gap-2 text-xs text-zinc-500">
                            <span
                                class="inline-flex rounded-full px-2.5 py-1"
                                :class="{
                                    'bg-sky-500/15 text-sky-200': realtimeStatus === 'connected',
                                    'bg-emerald-500/15 text-emerald-200': realtimeStatus === 'updated',
                                    'bg-yellow-500/15 text-yellow-200': realtimeStatus === 'connecting',
                                    'bg-red-500/15 text-red-200': realtimeStatus === 'error',
                                }"
                            >
                                Realtime:
                                <template v-if="realtimeStatus === 'connecting'">
                                    povezivanje
                                </template>

                                <template v-else-if="realtimeStatus === 'connected'">
                                    aktivan
                                </template>

                                <template v-else-if="realtimeStatus === 'updated'">
                                    osveženo
                                </template>

                                <template v-else>
                                    greška
                                </template>
                            </span>

                            <span v-if="lastRealtimeUpdateAt">
                                Poslednja promena: {{ lastRealtimeUpdateAt }}
                            </span>
                        </div>
                    </div>

                    <nav class="flex flex-wrap gap-2">
                        <Link
                            :href="`/t/${tournament.public_code}/live`"
                            class="rounded-full border border-white/10 px-4 py-2 text-sm text-zinc-200 transition hover:bg-white/10"
                        >
                            Live
                        </Link>

                        <Link
                            :href="`/t/${tournament.public_code}/groups`"
                            class="rounded-full border border-white/10 px-4 py-2 text-sm text-zinc-200 transition hover:bg-white/10"
                        >
                            Grupe
                        </Link>

                        <Link
                            :href="`/t/${tournament.public_code}/schedule`"
                            class="rounded-full border border-white/10 px-4 py-2 text-sm text-zinc-200 transition hover:bg-white/10"
                        >
                            Raspored
                        </Link>

                        <Link
                            :href="`/t/${tournament.public_code}/knockout`"
                            class="rounded-full bg-white px-4 py-2 text-sm font-medium text-zinc-950"
                        >
                            Nokaut
                        </Link>
                    </nav>
                </div>
            </header>

            <section class="grid gap-4 md:grid-cols-4">
                <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-4">
                    <p class="text-sm text-zinc-400">
                        Veličina nokauta
                    </p>

                    <p class="mt-2 text-2xl font-semibold">
                        {{ tournament.knockout_size ?? '-' }}
                    </p>
                </div>

                <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-4">
                    <p class="text-sm text-zinc-400">
                        Nokaut mečevi
                    </p>

                    <p class="mt-2 text-2xl font-semibold">
                        {{ tournament.knockout_matches_count }}
                    </p>
                </div>

                <div class="rounded-2xl border border-emerald-500/20 bg-emerald-500/5 p-4">
                    <p class="text-sm text-emerald-200">
                        Završeno
                    </p>

                    <p class="mt-2 text-2xl font-semibold text-emerald-200">
                        {{ tournament.finished_knockout_matches_count }}
                    </p>
                </div>

                <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-4">
                    <p class="text-sm text-zinc-400">
                        Anulirano
                    </p>

                    <p class="mt-2 text-2xl font-semibold">
                        {{ tournament.voided_knockout_matches_count }}
                    </p>
                </div>
            </section>

            <section
                v-if="rounds.length"
                class="space-y-8"
            >
                <div
                    v-for="round in rounds"
                    :key="round.round_key"
                    class="rounded-3xl border border-white/10 bg-white/[0.03] p-5"
                >
                    <div class="flex flex-col gap-2 md:flex-row md:items-end md:justify-between">
                        <div>
                            <h2 class="text-2xl font-semibold">
                                {{ round.round_label }}
                            </h2>

                            <p class="mt-1 text-sm text-zinc-400">
                                {{ round.series.length }} serija
                            </p>
                        </div>
                    </div>

                    <div class="mt-5 grid gap-4 xl:grid-cols-2">
                        <article
                            v-for="series in round.series"
                            :key="`${series.round_key}-${series.position}`"
                            class="rounded-2xl border border-white/10 bg-black/30 p-4"
                        >
                            <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                                <div>
                                    <h3 class="font-semibold">
                                        {{ series.title }}
                                    </h3>

                                    <p class="mt-1 text-sm text-zinc-400">
                                        Na {{ series.wins_required }} dobijene · maksimalno {{ series.max_legs }} partija
                                    </p>
                                </div>

                                <span
                                    class="inline-flex w-fit rounded-full px-2.5 py-1 text-xs font-medium"
                                    :class="seriesStatusClasses(series.status)"
                                >
                                    {{ series.status_label }}
                                </span>
                            </div>

                            <div class="mt-4 grid grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)] items-center gap-3 rounded-2xl bg-white/5 p-4">
                                <div>
                                    <p
                                        class="text-lg font-semibold"
                                        :class="[
                                            series.participant_a?.is_withdrawn ? 'text-zinc-500 line-through' : '',
                                            series.winner?.id === series.participant_a?.id ? 'text-emerald-200' : '',
                                        ]"
                                    >
                                        {{ series.participant_a?.display_name ?? 'Čeka učesnika' }}
                                    </p>

                                    <p
                                        v-if="series.participant_a?.group_position"
                                        class="mt-1 text-sm text-zinc-500"
                                    >
                                        {{ series.participant_a.group_position }}
                                    </p>

                                    <span
                                        v-if="series.participant_a?.is_withdrawn"
                                        class="mt-2 inline-flex rounded-full bg-red-500/15 px-2.5 py-1 text-xs font-medium text-red-200"
                                    >
                                        Odustao
                                    </span>
                                </div>

                                <div class="rounded-2xl bg-white px-4 py-2 text-2xl font-bold text-zinc-950">
                                    {{ series.series_score }}
                                </div>

                                <div class="text-right">
                                    <p
                                        class="text-lg font-semibold"
                                        :class="[
                                            series.participant_b?.is_withdrawn ? 'text-zinc-500 line-through' : '',
                                            series.winner?.id === series.participant_b?.id ? 'text-emerald-200' : '',
                                        ]"
                                    >
                                        {{ series.participant_b?.display_name ?? 'Čeka učesnika' }}
                                    </p>

                                    <p
                                        v-if="series.participant_b?.group_position"
                                        class="mt-1 text-sm text-zinc-500"
                                    >
                                        {{ series.participant_b.group_position }}
                                    </p>

                                    <span
                                        v-if="series.participant_b?.is_withdrawn"
                                        class="mt-2 inline-flex rounded-full bg-red-500/15 px-2.5 py-1 text-xs font-medium text-red-200"
                                    >
                                        Odustao
                                    </span>
                                </div>
                            </div>

                            <div
                                v-if="series.winner"
                                class="mt-3 rounded-2xl border border-emerald-500/20 bg-emerald-500/5 p-3 text-sm text-emerald-200"
                            >
                                Pobednik serije:
                                <span class="font-semibold">
                                    {{ series.winner.display_name }}
                                </span>
                            </div>

                            <div class="mt-4 overflow-hidden rounded-2xl border border-white/10">
                                <table class="w-full text-left text-sm">
                                    <thead class="bg-white/5 text-zinc-400">
                                        <tr>
                                            <th class="px-3 py-3 font-medium">Partija</th>
                                            <th class="px-3 py-3 text-center font-medium">Status</th>
                                            <th class="px-3 py-3 text-center font-medium">Rezultat</th>
                                            <th class="px-3 py-3 font-medium">Pobednik</th>
                                            <th class="px-3 py-3 font-medium">Resource</th>
                                        </tr>
                                    </thead>

                                    <tbody>
                                        <tr
                                            v-for="leg in series.legs"
                                            :key="leg.id"
                                            class="border-t border-white/10"
                                        >
                                            <td class="px-3 py-3 text-zinc-400">
                                                Leg {{ leg.leg ?? '-' }}

                                                <span
                                                    v-if="walkoverLabel(leg.win_reason)"
                                                    class="ml-2 inline-flex rounded-full bg-red-500/15 px-2 py-0.5 text-[11px] font-medium text-red-200"
                                                >
                                                    {{ walkoverLabel(leg.win_reason) }}
                                                </span>
                                            </td>

                                            <td class="px-3 py-3 text-center">
                                                <span
                                                    class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium"
                                                    :class="legStatusClasses(leg.status)"
                                                >
                                                    {{ leg.status_label }}
                                                </span>
                                            </td>

                                            <td class="px-3 py-3 text-center">
                                                <span class="rounded-xl bg-white/10 px-3 py-1 font-semibold">
                                                    {{ leg.score ?? '-' }}
                                                </span>
                                            </td>

                                            <td class="px-3 py-3">
                                                {{ leg.winner?.display_name ?? '-' }}
                                            </td>

                                            <td class="px-3 py-3 text-zinc-400">
                                                {{ leg.resource_name ?? '-' }}
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </article>
                    </div>
                </div>
            </section>

            <section
                v-else
                class="rounded-3xl border border-dashed border-white/10 p-6 text-zinc-400"
            >
                Nokaut kostur još nije generisan.
            </section>
        </main>
    </div>
</template>
