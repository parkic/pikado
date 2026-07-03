<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';

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
};

type StandingRow = {
    participant_id: number;
    group_position: string | null;
    display_name: string;
    played: number;
    wins: number;
    losses: number;
    points_for: number;
    points_against: number;
    points_difference: number;
    standing_points: number;
    position: number | null;
    participant_status?: string;
    is_withdrawn?: boolean;
    withdrawn_at?: string | null;
    qualification_status: string;
    qualification_label: string;
    qualification_is_manual?: boolean;
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

type PublicGroup = {
    id: number;
    name: string;
    matches_count: number;
    finished_matches_count: number;
    rows: StandingRow[];
    matches: PublicMatch[];
};

defineProps<{
    venue: Venue;
    tournament: Tournament;
    groups: PublicGroup[];
}>();

const differenceLabel = (difference: number): string => {
    if (difference > 0) {
        return `+${difference}`;
    }

    return String(difference);
};

const qualificationClasses = (status: string): string => {
    if (status === 'direct') {
        return 'bg-emerald-500/15 text-emerald-200';
    }

    if (status === 'repechage') {
        return 'bg-yellow-500/15 text-yellow-200';
    }

    if (status === 'withdrawn') {
        return 'bg-red-500/15 text-red-200';
    }

    return 'bg-zinc-700 text-zinc-300';
};

const matchStatusClasses = (status: string): string => {
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
</script>

<template>
    <Head :title="`${tournament.name} - Grupe`" />

    <div class="min-h-screen bg-zinc-950 text-zinc-50">
        <main class="mx-auto flex w-full max-w-full flex-col gap-6 px-4 py-6 md:px-8">
            <header class="rounded-3xl border border-white/10 bg-white/[0.03] p-5 md:p-8">
                <div class="flex flex-col gap-5 md:flex-row md:items-start md:justify-between">
                    <div>
                        <p class="text-sm uppercase tracking-[0.25em] text-zinc-400">
                            {{ venue.name }}
                        </p>

                        <h1 class="mt-3 text-3xl font-bold tracking-tight md:text-5xl">
                            Grupe
                        </h1>

                        <p class="mt-3 max-w-3xl text-zinc-400">
                            {{ tournament.name }} · {{ tournament.status_label }}
                        </p>
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
                            class="rounded-full bg-white px-4 py-2 text-sm font-medium text-zinc-950"
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
                v-if="groups.length"
                class="grid gap-6 xl:grid-cols-2"
            >
                <article
                    v-for="group in groups"
                    :key="group.id"
                    class="rounded-3xl border border-white/10 bg-white/[0.03] p-5"
                >
                    <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                        <div>
                            <h2 class="text-2xl font-semibold">
                                Grupa {{ group.name }}
                            </h2>

                            <p class="mt-1 text-sm text-zinc-400">
                                Odigrano {{ group.finished_matches_count }} / {{ group.matches_count }} mečeva
                            </p>
                        </div>

                        <div class="flex flex-wrap gap-2 text-xs">
                            <span class="rounded-full bg-emerald-500/15 px-2.5 py-1 font-medium text-emerald-200">
                                Direktno
                            </span>

                            <span class="rounded-full bg-yellow-500/15 px-2.5 py-1 font-medium text-yellow-200">
                                Repasaž
                            </span>

                            <span class="rounded-full bg-zinc-700 px-2.5 py-1 font-medium text-zinc-300">
                                Ispao
                            </span>
                        </div>
                    </div>

                    <div class="mt-5 overflow-hidden rounded-2xl border border-white/10">
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-sm">
                                <thead class="bg-white/5 text-zinc-400">
                                    <tr>
                                        <th class="px-3 py-3 font-medium">#</th>
                                        <th class="px-3 py-3 font-medium">Učesnik</th>
                                        <th class="px-3 py-3 text-center font-medium">O</th>
                                        <th class="px-3 py-3 text-center font-medium">P</th>
                                        <th class="px-3 py-3 text-center font-medium">I</th>
                                        <th class="px-3 py-3 text-center font-medium">+/-</th>
                                        <th class="px-3 py-3 text-center font-medium">Bod</th>
                                        <th class="px-3 py-3 text-center font-medium">Status</th>
                                    </tr>
                                </thead>

                                <tbody>
                                    <tr
                                        v-for="row in group.rows"
                                        :key="row.participant_id"
                                        class="border-t border-white/10"
                                    >
                                        <td class="px-3 py-3 text-zinc-400">
                                            {{ row.position ?? '-' }}
                                        </td>

                                        <td class="px-3 py-3">
                                            <div
                                                class="font-medium"
                                                :class="row.is_withdrawn ? 'text-zinc-500 line-through' : ''"
                                            >
                                                {{ row.display_name }}
                                            </div>

                                            <div class="mt-1 text-xs text-zinc-500">
                                                {{ row.group_position ?? '-' }}
                                            </div>
                                        </td>

                                        <td class="px-3 py-3 text-center text-zinc-300">
                                            {{ row.played }}
                                        </td>

                                        <td class="px-3 py-3 text-center text-zinc-300">
                                            {{ row.wins }}
                                        </td>

                                        <td class="px-3 py-3 text-center text-zinc-300">
                                            {{ row.losses }}
                                        </td>

                                        <td class="px-3 py-3 text-center font-medium">
                                            {{ differenceLabel(row.points_difference) }}
                                        </td>

                                        <td class="px-3 py-3 text-center font-semibold">
                                            {{ row.standing_points }}
                                        </td>

                                        <td class="px-3 py-3 text-center">
                                            <span
                                                class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium"
                                                :class="qualificationClasses(row.qualification_status)"
                                            >
                                                {{ row.qualification_label }}
                                            </span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="mt-6">
                        <h3 class="text-lg font-semibold">
                            Mečevi grupe {{ group.name }}
                        </h3>

                        <div
                            v-if="group.matches.length"
                            class="mt-3 space-y-3"
                        >
                            <div
                                v-for="match in group.matches"
                                :key="match.id"
                                class="rounded-2xl border border-white/10 bg-black/30 p-4"
                            >
                                <div class="flex items-center justify-between gap-3">
                                    <p class="text-sm text-zinc-400">
                                        {{ match.resource_name ?? 'Bez resource-a' }}
                                    </p>

                                    <span
                                        class="rounded-full px-2.5 py-1 text-xs font-medium"
                                        :class="matchStatusClasses(match.status)"
                                    >
                                        {{ match.status_label }}
                                    </span>
                                </div>

                                <div class="mt-3 grid grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)] items-center gap-3">
                                    <div>
                                        <p
                                            class="font-semibold"
                                            :class="match.winner?.id === match.participant_a?.id ? 'text-emerald-200' : ''"
                                        >
                                            {{ match.participant_a?.display_name ?? 'TBD' }}
                                        </p>

                                        <p class="mt-1 text-xs text-zinc-500">
                                            {{ match.participant_a?.group_position ?? '-' }}
                                        </p>
                                    </div>

                                    <div class="rounded-xl bg-white/10 px-3 py-1 text-lg font-semibold">
                                        {{ match.score_a ?? '-' }} : {{ match.score_b ?? '-' }}
                                    </div>

                                    <div class="text-right">
                                        <p
                                            class="font-semibold"
                                            :class="match.winner?.id === match.participant_b?.id ? 'text-emerald-200' : ''"
                                        >
                                            {{ match.participant_b?.display_name ?? 'TBD' }}
                                        </p>

                                        <p class="mt-1 text-xs text-zinc-500">
                                            {{ match.participant_b?.group_position ?? '-' }}
                                        </p>
                                    </div>
                                </div>

                                <p
                                    v-if="match.winner"
                                    class="mt-2 text-sm text-emerald-200"
                                >
                                    Pobednik: {{ match.winner.display_name }}
                                </p>
                            </div>
                        </div>

                        <p
                            v-else
                            class="mt-3 rounded-2xl border border-dashed border-white/10 p-4 text-sm text-zinc-400"
                        >
                            Još nema generisanih mečeva za ovu grupu.
                        </p>
                    </div>
                </article>
            </section>

            <section
                v-else
                class="rounded-3xl border border-dashed border-white/10 p-6 text-zinc-400"
            >
                Još nema grupa za prikaz.
            </section>
        </main>
    </div>
</template>
