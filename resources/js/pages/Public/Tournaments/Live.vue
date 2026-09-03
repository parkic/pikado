<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { CircleDot } from '@lucide/vue';
import PublicTournamentHeader from '@/components/public/PublicTournamentHeader.vue';
import PublicTournamentLayout from '@/components/public/PublicTournamentLayout.vue';
import { usePublicTournamentRealtime } from '@/composables/usePublicTournamentRealtime';
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
    finished_matches_count: number;
    voided_matches_count: number;
    done_matches_count: number;
    progress_percent: number;
    finished_at: string | null;
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
    scheduled_order: number;
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
    qualification_position: string | null;
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
    venue: PublicVenueBranding;
    tournament: Tournament;
    current_matches: PublicMatch[];
    postponed_matches: PublicMatch[];
    next_matches: PublicMatch[];
    recent_matches: PublicMatch[];
    podium: TournamentPodium;
}>();

usePublicTournamentRealtime({
    publicCode: props.tournament.public_code,
    only: [
        'tournament',
        'current_matches',
        'postponed_matches',
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

const participantLabel = (participant: MatchParticipant): string => {
    if (!participant) {
        return '—';
    }

    return participant.display_name;
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
    <Head :title="`${tournament.name} - Uživo`" />

    <PublicTournamentLayout :theme="venue.public_theme">
        <PublicTournamentHeader
            :venue="venue"
            :tournament="tournament"
            active-page="live"
        />

        <section
            class="rounded-2xl border border-white/10 bg-white/[0.03] p-3 md:flex md:items-center md:gap-4 md:px-5 md:py-4"
        >
            <div
                class="flex items-center justify-between gap-3 md:min-w-52 md:justify-start"
            >
                <span class="text-xs text-zinc-400 md:text-sm 2xl:text-lg"
                    >Napredak turnira</span
                >
                <strong class="text-base md:text-2xl 2xl:text-3xl">
                    {{ tournament.done_matches_count }} /
                    {{ tournament.matches_count }}
                </strong>
                <strong class="text-xs md:hidden">
                    {{ tournament.progress_percent }}%
                </strong>
            </div>
            <div class="mt-2 flex-1 md:mt-0">
                <div
                    class="h-1.5 overflow-hidden rounded-full bg-white/10 md:h-2.5"
                >
                    <div
                        class="h-full rounded-full bg-orange-500 transition-all"
                        :style="{
                            width: `${tournament.progress_percent}%`,
                        }"
                    />
                </div>
            </div>
            <div
                class="hidden items-center justify-between gap-3 md:block md:text-right"
            >
                <strong class="text-sm">
                    {{ tournament.progress_percent }}% završeno
                </strong>
                <p
                    v-if="tournament.finished_at"
                    class="text-xs text-emerald-200"
                >
                    {{ tournament.finished_at }}
                </p>
            </div>
        </section>

        <section
            v-if="podium.is_complete"
            class="rounded-2xl border border-amber-400/30 bg-amber-400/5 p-4 md:rounded-3xl md:p-6"
        >
            <div
                class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between"
            >
                <div>
                    <p
                        class="text-sm tracking-[0.25em] text-amber-200/80 uppercase"
                    >
                        Finalni plasman
                    </p>

                    <h2
                        class="mt-1 text-2xl font-bold tracking-tight md:mt-2 md:text-4xl"
                    >
                        Turnir je završen
                    </h2>
                </div>

                <div class="text-sm text-zinc-400">
                    <p v-if="podium.final_score">
                        Finale:
                        <span class="font-semibold text-zinc-100">{{
                            podium.final_score
                        }}</span>
                    </p>

                    <p v-if="podium.third_place_score">
                        Treće mesto:
                        <span class="font-semibold text-zinc-100">{{
                            podium.third_place_score
                        }}</span>
                    </p>
                </div>
            </div>

            <div
                class="mt-4 grid grid-cols-2 gap-2 md:mt-6 md:grid-cols-4 md:gap-4"
            >
                <article
                    class="rounded-xl border p-3 md:rounded-2xl md:p-4"
                    :class="podiumCardClasses(1)"
                >
                    <p class="text-sm opacity-80">🏆 1. mesto</p>

                    <h3
                        class="mt-2 text-sm leading-tight font-bold md:mt-3 md:text-xl"
                    >
                        {{ podium.champion?.display_name }}
                    </h3>
                </article>

                <article
                    class="rounded-xl border p-3 md:rounded-2xl md:p-4"
                    :class="podiumCardClasses(2)"
                >
                    <p class="text-sm opacity-80">🥈 2. mesto</p>

                    <h3
                        class="mt-2 text-sm leading-tight font-bold md:mt-3 md:text-xl"
                    >
                        {{ podium.second_place?.display_name }}
                    </h3>
                </article>

                <article
                    class="rounded-xl border p-3 md:rounded-2xl md:p-4"
                    :class="podiumCardClasses(3)"
                >
                    <p class="text-sm opacity-80">🥉 3. mesto</p>

                    <h3
                        class="mt-2 text-sm leading-tight font-bold md:mt-3 md:text-xl"
                    >
                        {{ podium.third_place?.display_name }}
                    </h3>
                </article>

                <article
                    class="rounded-xl border p-3 md:rounded-2xl md:p-4"
                    :class="podiumCardClasses(4)"
                >
                    <p class="text-sm opacity-80">4. mesto</p>

                    <h3
                        class="mt-2 text-sm leading-tight font-bold md:mt-3 md:text-xl"
                    >
                        {{ podium.fourth_place?.display_name }}
                    </h3>
                </article>
            </div>
        </section>

        <section
            v-if="current_matches.length"
            class="rounded-2xl border border-orange-500/25 bg-orange-500/[0.06] p-3.5 md:rounded-3xl md:p-6"
        >
            <div
                class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between"
            >
                <div>
                    <p
                        class="text-xs font-semibold tracking-[0.2em] text-orange-300 uppercase"
                    >
                        Trenutni redosled
                    </p>
                    <h2
                        class="mt-0.5 text-xl font-semibold md:mt-1 md:text-2xl 2xl:text-4xl"
                    >
                        Na tablama sada
                    </h2>
                </div>

                <p class="hidden max-w-xl text-sm text-zinc-400 sm:block">
                    Prvi meč bez unetog rezultata na svakoj tabli.
                </p>
            </div>

            <div class="mt-3 grid gap-3 md:mt-4 md:gap-4 lg:grid-cols-2">
                <article
                    v-for="match in current_matches"
                    :key="match.id"
                    class="overflow-hidden rounded-xl border border-orange-400/20 bg-zinc-950/80 md:rounded-2xl"
                >
                    <div
                        class="flex items-center justify-between gap-2 border-b border-white/10 bg-orange-400/10 px-3 py-2 md:px-4 md:py-2.5"
                    >
                        <p
                            class="inline-flex min-w-0 items-center gap-1.5 text-xs font-bold text-orange-100 md:text-base 2xl:text-xl"
                        >
                            <CircleDot class="size-3.5 shrink-0" />
                            <span class="truncate">
                                {{
                                    match.resource_name ??
                                    'Tabla nije dodeljena'
                                }}
                            </span>
                        </p>

                        <p
                            class="shrink-0 text-[10px] text-zinc-400 md:text-sm 2xl:text-lg"
                        >
                            Meč #{{ match.scheduled_order }} ·
                            {{ matchContextLabel(match) }}
                        </p>
                    </div>

                    <div
                        class="grid grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)] items-center gap-2 px-3 py-3 md:gap-3 md:px-4 md:py-4"
                    >
                        <p
                            class="line-clamp-2 text-[13px] leading-tight font-semibold md:text-xl md:font-bold 2xl:text-3xl"
                            :class="
                                match.participant_a?.is_withdrawn
                                    ? 'text-zinc-500 line-through'
                                    : ''
                            "
                        >
                            {{ participantLabel(match.participant_a) }}
                        </p>

                        <span
                            class="rounded-full border border-white/10 bg-white/[0.06] px-2 py-1 text-[10px] font-bold text-zinc-500 md:px-3 md:text-sm 2xl:px-4 2xl:py-2 2xl:text-lg"
                        >
                            VS
                        </span>

                        <p
                            class="line-clamp-2 text-right text-[13px] leading-tight font-semibold md:text-xl md:font-bold 2xl:text-3xl"
                            :class="
                                match.participant_b?.is_withdrawn
                                    ? 'text-zinc-500 line-through'
                                    : ''
                            "
                        >
                            {{ participantLabel(match.participant_b) }}
                        </p>
                    </div>
                </article>
            </div>
        </section>

        <section
            v-if="postponed_matches.length"
            class="rounded-2xl border border-amber-400/25 bg-amber-500/[0.055] p-3.5 md:rounded-3xl md:p-5"
        >
            <div class="flex items-center justify-between gap-3">
                <div>
                    <p
                        class="text-xs font-semibold tracking-[0.18em] text-amber-300 uppercase 2xl:text-base"
                    >
                        Vraćaju se u raspored
                    </p>
                    <h2
                        class="mt-0.5 text-xl font-semibold md:text-2xl 2xl:text-4xl"
                    >
                        Privremeno preskočeni
                    </h2>
                </div>
                <span
                    class="rounded-full bg-amber-400/15 px-3 py-1 text-sm font-bold text-amber-200 2xl:text-lg"
                >
                    {{ postponed_matches.length }}
                </span>
            </div>

            <div class="mt-3 grid gap-2 md:grid-cols-2 md:gap-3">
                <article
                    v-for="match in postponed_matches"
                    :key="match.id"
                    class="rounded-xl border border-amber-300/15 bg-black/25 px-3 py-3 md:px-4"
                >
                    <div
                        class="flex items-center justify-between gap-3 text-[11px] text-amber-100/70 md:text-sm 2xl:text-lg"
                    >
                        <span>{{ matchContextLabel(match) }}</span>
                        <span>{{
                            match.resource_name ?? 'Tabla nije dodeljena'
                        }}</span>
                    </div>
                    <div
                        class="mt-2 grid grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)] items-center gap-2"
                    >
                        <p
                            class="line-clamp-2 text-[13px] leading-tight font-semibold md:text-lg 2xl:text-2xl"
                        >
                            {{ participantLabel(match.participant_a) }}
                        </p>
                        <span
                            class="text-xs font-bold text-amber-200/50 2xl:text-base"
                            >VS</span
                        >
                        <p
                            class="line-clamp-2 text-right text-[13px] leading-tight font-semibold md:text-lg 2xl:text-2xl"
                        >
                            {{ participantLabel(match.participant_b) }}
                        </p>
                    </div>
                </article>
            </div>
        </section>

        <div class="grid gap-4 md:gap-6 lg:grid-cols-2">
            <section
                class="rounded-2xl border border-white/10 bg-white/[0.03] p-3.5 md:rounded-3xl md:p-5"
            >
                <h2 class="text-xl font-semibold md:text-2xl 2xl:text-4xl">
                    Sledeći mečevi
                </h2>

                <div
                    v-if="next_matches.length"
                    class="mt-3 space-y-2 md:mt-4 md:space-y-3"
                >
                    <article
                        v-for="match in next_matches"
                        :key="match.id"
                        class="rounded-xl border border-white/10 bg-black/30 px-3 py-2.5 md:rounded-2xl md:px-4 md:py-3"
                    >
                        <div class="flex items-center justify-between gap-3">
                            <p
                                class="text-[11px] text-zinc-400 md:text-sm 2xl:text-lg"
                            >
                                {{ matchContextLabel(match) }}
                            </p>

                            <p
                                class="inline-flex items-center gap-1 text-[11px] font-semibold text-orange-200 md:text-sm 2xl:text-lg"
                            >
                                <CircleDot class="size-3" />
                                {{ match.resource_name ?? '-' }}
                            </p>
                        </div>

                        <div
                            class="mt-2 grid grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)] items-center gap-2 md:gap-3"
                        >
                            <p
                                class="line-clamp-2 text-[13px] leading-tight font-semibold md:text-lg 2xl:text-2xl"
                            >
                                {{ participantLabel(match.participant_a) }}
                            </p>

                            <span class="text-sm font-medium text-zinc-600"
                                >VS</span
                            >

                            <p
                                class="line-clamp-2 text-right text-[13px] leading-tight font-semibold md:text-lg 2xl:text-2xl"
                            >
                                {{ participantLabel(match.participant_b) }}
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

            <section
                class="rounded-2xl border border-white/10 bg-white/[0.03] p-3.5 md:rounded-3xl md:p-5"
            >
                <h2 class="text-xl font-semibold md:text-2xl 2xl:text-4xl">
                    Poslednji završeni
                </h2>

                <div
                    v-if="recent_matches.length"
                    class="mt-3 space-y-2 md:mt-4 md:space-y-3"
                >
                    <article
                        v-for="match in recent_matches"
                        :key="match.id"
                        class="rounded-xl border border-emerald-400/15 bg-emerald-500/[0.04] px-3 py-2.5 md:rounded-2xl md:px-4 md:py-3"
                    >
                        <div>
                            <p
                                class="text-[11px] text-zinc-400 md:text-sm 2xl:text-lg"
                            >
                                {{ matchContextLabel(match) }}
                            </p>
                        </div>

                        <div
                            class="mt-2 grid grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)] items-center gap-2 md:gap-3"
                        >
                            <p
                                class="line-clamp-2 text-[13px] leading-tight font-semibold md:text-xl 2xl:text-2xl"
                                :class="
                                    match.winner?.id === match.participant_a?.id
                                        ? 'font-bold text-emerald-300'
                                        : 'text-zinc-400'
                                "
                            >
                                {{ participantLabel(match.participant_a) }}
                            </p>

                            <div
                                class="rounded-lg border border-white/10 bg-white/10 px-2 py-1 text-base font-black tracking-tight md:rounded-xl md:px-3 md:py-1.5 md:text-2xl 2xl:px-4 2xl:py-2 2xl:text-4xl"
                            >
                                {{ match.score_a ?? '-' }} :
                                {{ match.score_b ?? '-' }}
                            </div>

                            <p
                                class="line-clamp-2 text-right text-[13px] leading-tight font-semibold md:text-xl 2xl:text-2xl"
                                :class="
                                    match.winner?.id === match.participant_b?.id
                                        ? 'font-bold text-emerald-300'
                                        : 'text-zinc-400'
                                "
                            >
                                {{ participantLabel(match.participant_b) }}
                            </p>
                        </div>
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
    </PublicTournamentLayout>
</template>
