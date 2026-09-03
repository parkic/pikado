<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    CalendarDays,
    MapPin,
    Medal,
    Target,
    Trophy,
    Users,
} from '@lucide/vue';

import PublicTournamentLayout from '@/components/public/PublicTournamentLayout.vue';

type AppBranding = {
    name: string;
    public_theme: 'dark' | 'light';
};

type Player = {
    id: number;
    display_name: string;
    first_name: string;
    last_name: string;
    nickname: string | null;
    tournaments_count: number;
    matches_count: number;
    wins_count: number;
    losses_count: number;
    win_rate: number;
};

type TournamentHistory = {
    id: number;
    name: string;
    venue_name: string;
    date: string | null;
    participants_count: number;
    result_label: string;
    matches_played: number;
    wins: number;
    losses: number;
    public_url: string;
};

type MatchHistory = {
    id: number;
    tournament_name: string;
    tournament_url: string;
    venue_name: string;
    date: string | null;
    stage_label: string;
    opponent_name: string;
    score_for: number | null;
    score_against: number | null;
    won: boolean;
};

defineProps<{
    app: AppBranding;
    player: Player;
    tournaments: TournamentHistory[];
    matches: MatchHistory[];
}>();
</script>

<template>
    <Head :title="`${player.display_name} - profil igrača`" />

    <PublicTournamentLayout :theme="app.public_theme">
        <header
            class="overflow-hidden rounded-2xl border border-white/10 bg-gradient-to-br from-orange-500/[0.12] via-white/[0.035] to-transparent p-5 md:rounded-3xl md:p-8"
        >
            <div class="flex flex-col gap-5 sm:flex-row sm:items-center">
                <div
                    class="flex size-16 shrink-0 items-center justify-center rounded-2xl bg-orange-500/15 text-orange-200 md:size-20"
                >
                    <Target class="size-8 md:size-10" />
                </div>
                <div class="min-w-0 flex-1">
                    <p
                        class="text-xs font-bold tracking-[0.2em] text-orange-300 uppercase"
                    >
                        {{ app.name }} · globalni profil igrača
                    </p>
                    <h1 class="mt-1 text-3xl font-black md:text-5xl">
                        {{ player.display_name }}
                    </h1>
                    <p class="mt-2 text-sm text-zinc-400 md:text-base">
                        Rezultati i istorija nastupa kroz sve lokale i javne
                        turnire.
                    </p>
                </div>
            </div>
        </header>

        <section class="grid grid-cols-2 gap-3 md:grid-cols-4 md:gap-4">
            <article
                class="rounded-2xl border border-white/10 bg-white/[0.035] p-4 md:p-5"
            >
                <Trophy class="size-5 text-orange-300" />
                <p class="mt-3 text-3xl font-black">
                    {{ player.tournaments_count }}
                </p>
                <p class="text-sm text-zinc-400">Turnira</p>
            </article>
            <article
                class="rounded-2xl border border-white/10 bg-white/[0.035] p-4 md:p-5"
            >
                <Target class="size-5 text-sky-300" />
                <p class="mt-3 text-3xl font-black">
                    {{ player.matches_count }}
                </p>
                <p class="text-sm text-zinc-400">Odigranih mečeva</p>
            </article>
            <article
                class="rounded-2xl border border-emerald-400/20 bg-emerald-400/[0.06] p-4 md:p-5"
            >
                <Medal class="size-5 text-emerald-300" />
                <p class="mt-3 text-3xl font-black text-emerald-200">
                    {{ player.wins_count }}
                </p>
                <p class="text-sm text-zinc-400">Pobeda</p>
            </article>
            <article
                class="rounded-2xl border border-white/10 bg-white/[0.035] p-4 md:p-5"
            >
                <span class="text-lg font-black text-orange-300">%</span>
                <p class="mt-3 text-3xl font-black">{{ player.win_rate }}%</p>
                <p class="text-sm text-zinc-400">Uspešnost</p>
            </article>
        </section>

        <section
            class="overflow-hidden rounded-2xl border border-white/10 bg-white/[0.025] md:rounded-3xl"
        >
            <div class="border-b border-white/10 p-4 md:p-6">
                <h2 class="text-xl font-black md:text-3xl">
                    Nastupi na turnirima
                </h2>
            </div>
            <div v-if="tournaments.length" class="divide-y divide-white/10">
                <Link
                    v-for="tournament in tournaments"
                    :key="tournament.id"
                    :href="tournament.public_url"
                    class="grid gap-3 p-4 transition hover:bg-white/[0.04] md:grid-cols-[minmax(0,1.6fr)_repeat(3,minmax(0,0.7fr))] md:items-center md:p-5"
                >
                    <div class="min-w-0">
                        <h3 class="truncate text-lg font-bold">
                            {{ tournament.name }}
                        </h3>
                        <div
                            class="mt-1 flex flex-wrap gap-x-4 gap-y-1 text-xs text-zinc-500"
                        >
                            <span class="inline-flex items-center gap-1">
                                <MapPin class="size-3.5" />
                                {{ tournament.venue_name }}
                            </span>
                            <span class="inline-flex items-center gap-1">
                                <CalendarDays class="size-3.5" />
                                {{ tournament.date ?? '—' }}
                            </span>
                        </div>
                    </div>
                    <div>
                        <p class="text-xs text-zinc-500">Plasman</p>
                        <p class="font-bold text-orange-200">
                            {{ tournament.result_label }}
                        </p>
                    </div>
                    <div>
                        <p class="text-xs text-zinc-500">Učinak</p>
                        <p class="font-bold">
                            {{ tournament.wins }}–{{ tournament.losses }}
                        </p>
                    </div>
                    <div>
                        <p class="text-xs text-zinc-500">Turnir</p>
                        <p class="inline-flex items-center gap-1 font-bold">
                            <Users class="size-4" />
                            {{ tournament.participants_count }} igrača
                        </p>
                    </div>
                </Link>
            </div>
            <p v-else class="p-6 text-zinc-400">Još nema javnih nastupa.</p>
        </section>

        <section
            class="overflow-hidden rounded-2xl border border-white/10 bg-white/[0.025] md:rounded-3xl"
        >
            <div class="border-b border-white/10 p-4 md:p-6">
                <h2 class="text-xl font-black md:text-3xl">Istorija mečeva</h2>
            </div>
            <div v-if="matches.length" class="divide-y divide-white/10">
                <article
                    v-for="match in matches"
                    :key="match.id"
                    class="grid grid-cols-[1fr_auto] gap-3 p-4 md:grid-cols-[1fr_1fr_auto] md:items-center md:p-5"
                >
                    <div class="min-w-0">
                        <Link
                            :href="match.tournament_url"
                            class="truncate font-bold hover:text-orange-200"
                        >
                            {{ match.tournament_name }}
                        </Link>
                        <p class="mt-0.5 text-xs text-zinc-500">
                            {{ match.stage_label }} · {{ match.date ?? '—' }}
                        </p>
                    </div>
                    <div class="min-w-0 md:text-center">
                        <p class="text-xs text-zinc-500">Protiv</p>
                        <p class="truncate font-semibold">
                            {{ match.opponent_name }}
                        </p>
                    </div>
                    <div
                        class="row-span-2 flex items-center gap-3 self-center md:row-span-1"
                    >
                        <span
                            class="rounded-full px-2.5 py-1 text-xs font-black"
                            :class="
                                match.won
                                    ? 'bg-emerald-400/15 text-emerald-300'
                                    : 'bg-red-400/15 text-red-300'
                            "
                        >
                            {{ match.won ? 'Pobeda' : 'Poraz' }}
                        </span>
                        <strong class="text-xl">
                            {{ match.score_for }} : {{ match.score_against }}
                        </strong>
                    </div>
                </article>
            </div>
            <p v-else class="p-6 text-zinc-400">Još nema završenih mečeva.</p>
        </section>
    </PublicTournamentLayout>
</template>
