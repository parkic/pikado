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
    matches_count: number;
    finished_matches_count: number;
    voided_matches_count: number;
    done_matches_count: number;
    progress_percent: number;
    finished_at: string | null;
};

type MatchParticipant = {
    id: number;
    display_name: string;
    group_position: string | null;
    is_withdrawn: boolean;
} | null;

type PublicMatch = {
    id: number;
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
};

type PodiumParticipant = {
    id: number;
    display_name: string;
    group_position: string | null;
    is_withdrawn: boolean;
} | null;

type TournamentPodium = {
    champion: PodiumParticipant;
    second_place: PodiumParticipant;
    third_place: PodiumParticipant;
    fourth_place: PodiumParticipant;
    final_score: string | null;
    third_place_score: string | null;
    is_complete: boolean;
};

const props = defineProps<{
    venue: Venue;
    tournament: Tournament;
    active_matches: PublicMatch[];
    next_matches: PublicMatch[];
    recent_matches: PublicMatch[];
    podium: TournamentPodium;
}>();

const { realtimeStatus, lastRealtimeUpdateAt } = usePublicTournamentRealtime({
    publicCode: props.tournament.public_code,
    only: [
        'tournament',
        'active_matches',
        'next_matches',
        'recent_matches',
        'podium',
    ],
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

const statusClasses = (status: string): string => {
    if (status === 'finished') {
        return 'bg-emerald-500/15 text-emerald-200';
    }

    if (status === 'in_progress') {
        return 'bg-sky-500/15 text-sky-200';
    }

    if (status === 'voided') {
        return 'bg-zinc-700 text-zinc-300';
    }

    return 'bg-yellow-500/15 text-yellow-200';
};

const podiumCardClasses = (place: number): string => {
    if (place === 1) {
        return 'border-amber-400/40 bg-amber-400/10 text-amber-100';
    }

    if (place === 2) {
        return 'border-zinc-300/30 bg-zinc-300/10 text-zinc-100';
    }

    if (place === 3) {
        return 'border-orange-400/30 bg-orange-400/10 text-orange-100';
    }

    return 'border-white/10 bg-white/[0.03] text-zinc-100';
};
</script>

<template>
    <Head :title="`${tournament.name} - Live`" />

    <div class="min-h-screen bg-zinc-950 text-zinc-50">
        <main class="mx-auto flex w-full max-w-full flex-col gap-6 px-4 py-6 md:px-8">
            <header class="rounded-3xl border border-white/10 bg-white/[0.03] p-5 md:p-8">
                <div class="flex flex-col gap-5 md:flex-row md:items-start md:justify-between">
                    <div>
                        <p class="text-sm uppercase tracking-[0.25em] text-zinc-400">
                            {{ venue.name }}
                        </p>

                        <h1 class="mt-3 text-3xl font-bold tracking-tight md:text-5xl">
                            {{ tournament.name }}
                        </h1>

                        <div class="mt-4 flex flex-wrap items-center gap-2">
                            <span class="rounded-full bg-white/10 px-3 py-1 text-sm text-zinc-200">
                                {{ tournament.status_label }}
                            </span>

                            <span class="rounded-full bg-white/10 px-3 py-1 text-sm text-zinc-200">
                                {{ tournament.game_type }}
                            </span>

                            <span class="rounded-full bg-white/10 px-3 py-1 text-sm text-zinc-200">
                                {{ tournament.match_mode }}
                            </span>
                        </div>

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

                    <div class="rounded-2xl border border-white/10 bg-black/30 p-4 md:min-w-72">
                        <p class="text-sm text-zinc-400">
                            Progress turnira
                        </p>

                        <p class="mt-2 text-3xl font-semibold">
                            {{ tournament.done_matches_count }} / {{ tournament.matches_count }}
                        </p>

                        <div class="mt-4 h-3 overflow-hidden rounded-full bg-white/10">
                            <div
                                class="h-full rounded-full bg-white"
                                :style="{ width: `${tournament.progress_percent}%` }"
                            />
                        </div>

                        <p class="mt-2 text-sm text-zinc-400">
                            {{ tournament.progress_percent }}% završeno
                        </p>

                        <p
                            v-if="tournament.finished_at"
                            class="mt-2 text-sm text-emerald-200"
                        >
                            Završeno: {{ tournament.finished_at }}
                        </p>
                    </div>

                    <nav class="flex flex-wrap gap-2 md:justify-end">
                        <Link
                            :href="`/t/${tournament.public_code}/live`"
                            class="rounded-full bg-white px-4 py-2 text-sm font-medium text-zinc-950"
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
                            class="rounded-full border border-white/10 px-4 py-2 text-sm text-zinc-200 transition hover:bg-white/10"
                        >
                            Nokaut
                        </Link>
                    </nav>
                </div>
            </header>

            <section
                v-if="podium.is_complete"
                class="rounded-3xl border border-amber-400/30 bg-amber-400/5 p-5 md:p-6"
            >
                <div class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
                    <div>
                        <p class="text-sm uppercase tracking-[0.25em] text-amber-200/80">
                            Finalni plasman
                        </p>

                        <h2 class="mt-2 text-3xl font-bold tracking-tight md:text-4xl">
                            Turnir je završen
                        </h2>
                    </div>

                    <div class="text-sm text-zinc-400">
                        <p v-if="podium.final_score">
                            Finale: <span class="font-semibold text-zinc-100">{{ podium.final_score }}</span>
                        </p>

                        <p v-if="podium.third_place_score">
                            Treće mesto: <span class="font-semibold text-zinc-100">{{ podium.third_place_score }}</span>
                        </p>
                    </div>
                </div>

                <div class="mt-6 grid gap-4 md:grid-cols-4">
                    <article
                        class="rounded-2xl border p-4"
                        :class="podiumCardClasses(1)"
                    >
                        <p class="text-sm opacity-80">
                            🏆 1. mesto
                        </p>

                        <h3 class="mt-3 text-xl font-bold">
                            {{ podium.champion?.display_name }}
                        </h3>

                        <p class="mt-1 text-sm opacity-70">
                            {{ podium.champion?.group_position ?? '-' }}
                        </p>
                    </article>

                    <article
                        class="rounded-2xl border p-4"
                        :class="podiumCardClasses(2)"
                    >
                        <p class="text-sm opacity-80">
                            🥈 2. mesto
                        </p>

                        <h3 class="mt-3 text-xl font-bold">
                            {{ podium.second_place?.display_name }}
                        </h3>

                        <p class="mt-1 text-sm opacity-70">
                            {{ podium.second_place?.group_position ?? '-' }}
                        </p>
                    </article>

                    <article
                        class="rounded-2xl border p-4"
                        :class="podiumCardClasses(3)"
                    >
                        <p class="text-sm opacity-80">
                            🥉 3. mesto
                        </p>

                        <h3 class="mt-3 text-xl font-bold">
                            {{ podium.third_place?.display_name }}
                        </h3>

                        <p class="mt-1 text-sm opacity-70">
                            {{ podium.third_place?.group_position ?? '-' }}
                        </p>
                    </article>

                    <article
                        class="rounded-2xl border p-4"
                        :class="podiumCardClasses(4)"
                    >
                        <p class="text-sm opacity-80">
                            4. mesto
                        </p>

                        <h3 class="mt-3 text-xl font-bold">
                            {{ podium.fourth_place?.display_name }}
                        </h3>

                        <p class="mt-1 text-sm opacity-70">
                            {{ podium.fourth_place?.group_position ?? '-' }}
                        </p>
                    </article>
                </div>
            </section>

            <section
                v-if="active_matches.length"
                class="rounded-3xl border border-sky-500/20 bg-sky-500/5 p-5"
            >
                <div class="flex items-center justify-between gap-4">
                    <h2 class="text-2xl font-semibold">
                        Trenutno se igra
                    </h2>

                    <span class="rounded-full bg-sky-500/15 px-3 py-1 text-sm text-sky-200">
                        Live
                    </span>
                </div>

                <div class="mt-4 grid gap-4 lg:grid-cols-2">
                    <article
                        v-for="match in active_matches"
                        :key="match.id"
                        class="rounded-2xl border border-white/10 bg-black/30 p-4"
                    >
                        <div class="flex items-center justify-between gap-3">
                            <p class="text-sm text-zinc-400">
                                {{ matchContextLabel(match) }}
                            </p>

                            <span
                                class="rounded-full px-2.5 py-1 text-xs font-medium"
                                :class="statusClasses(match.status)"
                            >
                                {{ match.status_label }}
                            </span>
                        </div>

                        <div class="mt-4 grid grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)] items-center gap-3">
                            <div>
                                <p
                                    class="text-lg font-semibold"
                                    :class="match.participant_a?.is_withdrawn ? 'text-zinc-500 line-through' : ''"
                                >
                                    {{ match.participant_a?.display_name ?? 'TBD' }}
                                </p>

                                <p class="mt-1 text-sm text-zinc-500">
                                    {{ match.participant_a?.group_position ?? '-' }}
                                </p>
                            </div>

                            <div class="rounded-2xl bg-white px-4 py-2 text-2xl font-bold text-zinc-950">
                                {{ match.score_a ?? '-' }} : {{ match.score_b ?? '-' }}
                            </div>

                            <div class="text-right">
                                <p
                                    class="text-lg font-semibold"
                                    :class="match.participant_b?.is_withdrawn ? 'text-zinc-500 line-through' : ''"
                                >
                                    {{ match.participant_b?.display_name ?? 'TBD' }}
                                </p>

                                <p class="mt-1 text-sm text-zinc-500">
                                    {{ match.participant_b?.group_position ?? '-' }}
                                </p>
                            </div>
                        </div>

                        <p class="mt-3 text-sm text-zinc-500">
                            {{ match.resource_name ?? 'Bez resource-a' }}
                        </p>
                    </article>
                </div>
            </section>

            <div class="grid gap-6 lg:grid-cols-2">
                <section class="rounded-3xl border border-white/10 bg-white/[0.03] p-5">
                    <h2 class="text-2xl font-semibold">
                        Sledeći mečevi
                    </h2>

                    <div
                        v-if="next_matches.length"
                        class="mt-4 space-y-3"
                    >
                        <article
                            v-for="match in next_matches"
                            :key="match.id"
                            class="rounded-2xl border border-white/10 bg-black/30 p-4"
                        >
                            <div class="flex items-center justify-between gap-3">
                                <p class="text-sm text-zinc-400">
                                    {{ matchContextLabel(match) }}
                                </p>

                                <p class="text-sm text-zinc-400">
                                    {{ match.resource_name ?? '-' }}
                                </p>
                            </div>

                            <div class="mt-3 flex items-center justify-between gap-4">
                                <p class="font-semibold">
                                    {{ match.participant_a?.display_name ?? 'TBD' }}
                                </p>

                                <span class="text-zinc-500">vs</span>

                                <p class="text-right font-semibold">
                                    {{ match.participant_b?.display_name ?? 'TBD' }}
                                </p>
                            </div>
                        </article>
                    </div>

                    <p
                        v-else
                        class="mt-4 rounded-2xl border border-dashed border-white/10 p-4 text-sm text-zinc-400"
                    >
                        Trenutno nema sledećih zakazanih mečeva.
                    </p>
                </section>

                <section class="rounded-3xl border border-white/10 bg-white/[0.03] p-5">
                    <h2 class="text-2xl font-semibold">
                        Poslednji završeni
                    </h2>

                    <div
                        v-if="recent_matches.length"
                        class="mt-4 space-y-3"
                    >
                        <article
                            v-for="match in recent_matches"
                            :key="match.id"
                            class="rounded-2xl border border-white/10 bg-black/30 p-4"
                        >
                            <div class="flex items-center justify-between gap-3">
                                <p class="text-sm text-zinc-400">
                                    {{ matchContextLabel(match) }}
                                </p>

                                <span
                                    class="rounded-full px-2.5 py-1 text-xs font-medium"
                                    :class="statusClasses(match.status)"
                                >
                                    {{ match.status_label }}
                                </span>
                            </div>

                            <div class="mt-3 grid grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)] items-center gap-3">
                                <p
                                    class="font-semibold"
                                    :class="match.winner?.id === match.participant_a?.id ? 'text-emerald-200' : ''"
                                >
                                    {{ match.participant_a?.display_name ?? 'TBD' }}
                                </p>

                                <div class="rounded-xl bg-white/10 px-3 py-1 text-lg font-semibold">
                                    {{ match.score_a ?? '-' }} : {{ match.score_b ?? '-' }}
                                </div>

                                <p
                                    class="text-right font-semibold"
                                    :class="match.winner?.id === match.participant_b?.id ? 'text-emerald-200' : ''"
                                >
                                    {{ match.participant_b?.display_name ?? 'TBD' }}
                                </p>
                            </div>

                            <p
                                v-if="match.winner"
                                class="mt-2 text-sm text-emerald-200"
                            >
                                Pobednik: {{ match.winner.display_name }}
                            </p>
                        </article>
                    </div>

                    <p
                        v-else
                        class="mt-4 rounded-2xl border border-dashed border-white/10 p-4 text-sm text-zinc-400"
                    >
                        Još nema završenih mečeva.
                    </p>
                </section>
            </div>
        </main>
    </div>
</template>
