<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { useMediaQuery } from '@vueuse/core';
import { computed, ref } from 'vue';
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
    has_repechage: boolean;
};

type StandingRow = {
    participant_id: number;
    group_position: string | null;
    qualification_position: string | null;
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
    profile_url: string | null;
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

const props = defineProps<{
    venue: PublicVenueBranding;
    tournament: Tournament;
    groups: PublicGroup[];
}>();

usePublicTournamentRealtime({
    publicCode: props.tournament.public_code,
});
const isMobile = useMediaQuery('(max-width: 767px)');
const selectedGroupName = ref(props.groups[0]?.name ?? '');
const displayedGroups = computed(() =>
    isMobile.value
        ? props.groups.filter((group) => group.name === selectedGroupName.value)
        : props.groups,
);

const differenceLabel = (difference: number): string => {
    if (difference > 0) {
        return `+${difference}`;
    }

    return String(difference);
};

const qualificationRowClasses = (status: string): string => {
    if (status === 'direct') {
        return 'border-emerald-500/20 bg-emerald-500/[0.08]';
    }

    if (status === 'repechage') {
        return 'border-amber-300/30 bg-amber-400/[0.13]';
    }

    if (status === 'eliminated' || status === 'withdrawn') {
        return 'border-red-400/30 bg-red-500/[0.12]';
    }

    return 'border-white/10 bg-white/[0.03]';
};

const eliminationLegendLabel = computed(() =>
    props.tournament.status === 'group_stage' ? 'Ispada' : 'Ispao',
);
</script>

<template>
    <Head :title="`${tournament.name} - Grupe`" />

    <PublicTournamentLayout :theme="venue.public_theme">
        <PublicTournamentHeader
            :venue="venue"
            :tournament="tournament"
            active-page="groups"
        />

        <div
            v-if="groups.length > 1"
            class="sticky top-2 z-20 rounded-2xl border border-white/10 bg-zinc-950/95 p-2 shadow-xl shadow-black/20 backdrop-blur md:hidden"
        >
            <p
                class="px-1 pb-1.5 text-[10px] font-semibold tracking-wider text-zinc-500 uppercase"
            >
                Izaberi grupu
            </p>
            <div class="flex gap-1.5 overflow-x-auto">
                <button
                    v-for="group in groups"
                    :key="group.id"
                    type="button"
                    class="shrink-0 rounded-xl px-3 py-2 text-xs font-bold transition"
                    :class="
                        selectedGroupName === group.name
                            ? 'bg-orange-500 text-white shadow-sm'
                            : 'border border-white/10 bg-white/[0.03] text-zinc-300'
                    "
                    @click="selectedGroupName = group.name"
                >
                    Grupa {{ group.name }}
                </button>
            </div>
        </div>

        <section
            v-if="groups.length"
            class="grid gap-4 md:gap-6 xl:grid-cols-2"
        >
            <article
                v-for="group in displayedGroups"
                :key="group.id"
                class="rounded-2xl border border-white/10 bg-white/[0.03] p-3.5 md:rounded-3xl md:p-5"
            >
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2
                            class="text-xl font-semibold md:text-2xl 2xl:text-4xl"
                        >
                            Grupa {{ group.name }}
                        </h2>

                        <p
                            class="mt-0.5 text-[11px] text-zinc-400 md:mt-1 md:text-sm"
                        >
                            Odigrano {{ group.finished_matches_count }} /
                            {{ group.matches_count }} mečeva
                        </p>
                    </div>

                    <div
                        class="flex max-w-[55%] flex-wrap justify-end gap-x-2 gap-y-1 text-[9px] md:max-w-none md:gap-2 md:text-xs"
                    >
                        <span
                            class="inline-flex items-center gap-1 font-medium text-emerald-200 md:rounded-full md:bg-emerald-500/15 md:px-2.5 md:py-1"
                        >
                            <span
                                class="size-1.5 rounded-full bg-emerald-400 md:hidden"
                            />
                            Direktno
                        </span>

                        <span
                            v-if="tournament.has_repechage"
                            class="inline-flex items-center gap-1 font-medium text-yellow-200 md:rounded-full md:bg-yellow-500/15 md:px-2.5 md:py-1"
                        >
                            <span
                                class="size-1.5 rounded-full bg-yellow-400 md:hidden"
                            />
                            Repasaž
                        </span>

                        <span
                            class="inline-flex items-center gap-1 font-medium text-red-200 md:rounded-full md:bg-red-500/15 md:px-2.5 md:py-1"
                        >
                            <span
                                class="size-1.5 rounded-full bg-red-400 md:hidden"
                            />
                            {{ eliminationLegendLabel }}
                        </span>
                    </div>
                </div>

                <div class="mt-3 space-y-1.5 md:hidden">
                    <div
                        class="grid grid-cols-[1.25rem_minmax(0,1fr)_1.5rem_1.5rem_1.5rem_2.25rem_1.75rem] items-center gap-1 px-2 text-center text-[9px] font-semibold text-zinc-500 uppercase"
                    >
                        <span>#</span>
                        <span class="text-left">Učesnik</span>
                        <span>O</span>
                        <span>P</span>
                        <span>I</span>
                        <span>+/-</span>
                        <span>B</span>
                    </div>
                    <div
                        v-for="row in group.rows"
                        :key="row.participant_id"
                        class="grid min-h-13 grid-cols-[1.25rem_minmax(0,1fr)_1.5rem_1.5rem_1.5rem_2.25rem_1.75rem] items-center gap-1 rounded-xl border px-2 py-2 text-center"
                        :class="
                            qualificationRowClasses(row.qualification_status)
                        "
                    >
                        <span class="text-xs font-bold text-zinc-400">
                            {{ row.position ?? '-' }}
                        </span>

                        <div class="min-w-0 text-left">
                            <Link
                                v-if="row.profile_url"
                                :href="row.profile_url"
                                class="line-clamp-2 text-[13px] leading-tight font-semibold"
                                :class="
                                    row.is_withdrawn
                                        ? 'text-zinc-500 line-through'
                                        : ''
                                "
                            >
                                {{ row.display_name }}
                            </Link>
                            <p
                                v-else
                                class="line-clamp-2 text-[13px] leading-tight font-semibold"
                                :class="
                                    row.is_withdrawn
                                        ? 'text-zinc-500 line-through'
                                        : ''
                                "
                            >
                                {{ row.display_name }}
                            </p>
                        </div>

                        <span class="text-xs text-zinc-300">{{
                            row.played
                        }}</span>
                        <span class="text-xs text-zinc-300">{{
                            row.wins
                        }}</span>
                        <span class="text-xs text-zinc-300">{{
                            row.losses
                        }}</span>
                        <span class="text-xs font-medium">
                            {{ differenceLabel(row.points_difference) }}
                        </span>
                        <strong class="text-sm">{{
                            row.standing_points
                        }}</strong>
                    </div>
                </div>

                <div
                    class="mt-5 hidden overflow-hidden rounded-2xl border border-white/10 md:block"
                >
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm 2xl:text-lg">
                            <thead class="bg-white/5 text-zinc-400">
                                <tr>
                                    <th class="px-3 py-3 font-medium">#</th>
                                    <th class="px-3 py-3 font-medium">
                                        Učesnik
                                    </th>
                                    <th
                                        class="px-3 py-3 text-center font-medium"
                                    >
                                        O
                                    </th>
                                    <th
                                        class="px-3 py-3 text-center font-medium"
                                    >
                                        P
                                    </th>
                                    <th
                                        class="px-3 py-3 text-center font-medium"
                                    >
                                        I
                                    </th>
                                    <th
                                        class="px-3 py-3 text-center font-medium"
                                    >
                                        +/-
                                    </th>
                                    <th
                                        class="px-3 py-3 text-center font-medium"
                                    >
                                        Bod
                                    </th>
                                </tr>
                            </thead>

                            <tbody>
                                <tr
                                    v-for="row in group.rows"
                                    :key="row.participant_id"
                                    class="border-t border-white/10"
                                    :class="
                                        qualificationRowClasses(
                                            row.qualification_status,
                                        )
                                    "
                                >
                                    <td class="px-3 py-3 text-zinc-400">
                                        {{ row.position ?? '-' }}
                                    </td>

                                    <td class="px-3 py-3">
                                        <Link
                                            v-if="row.profile_url"
                                            :href="row.profile_url"
                                            class="font-medium"
                                            :class="
                                                row.is_withdrawn
                                                    ? 'text-zinc-500 line-through'
                                                    : ''
                                            "
                                        >
                                            {{ row.display_name }}
                                        </Link>
                                        <div
                                            v-else
                                            class="font-medium"
                                            :class="
                                                row.is_withdrawn
                                                    ? 'text-zinc-500 line-through'
                                                    : ''
                                            "
                                        >
                                            {{ row.display_name }}
                                        </div>
                                    </td>

                                    <td
                                        class="px-3 py-3 text-center text-zinc-300"
                                    >
                                        {{ row.played }}
                                    </td>

                                    <td
                                        class="px-3 py-3 text-center text-zinc-300"
                                    >
                                        {{ row.wins }}
                                    </td>

                                    <td
                                        class="px-3 py-3 text-center text-zinc-300"
                                    >
                                        {{ row.losses }}
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
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <details class="group mt-4 md:mt-6">
                    <summary
                        class="flex cursor-pointer list-none items-center justify-between gap-3 rounded-xl border border-white/10 px-3 py-2.5 md:px-4 md:py-3"
                    >
                        <div>
                            <h3 class="text-sm font-semibold md:text-lg">
                                Mečevi grupe {{ group.name }}
                            </h3>
                            <p class="mt-1 text-xs text-zinc-500">
                                {{ group.matches.length }} mečeva
                            </p>
                        </div>
                        <span class="text-sm text-zinc-400 group-open:hidden">
                            Prikaži
                        </span>
                        <span
                            class="hidden text-sm text-zinc-400 group-open:inline"
                        >
                            Sakrij
                        </span>
                    </summary>

                    <div v-if="group.matches.length" class="mt-3 space-y-3">
                        <div
                            v-for="match in group.matches"
                            :key="match.id"
                            class="rounded-xl border border-white/10 bg-black/30 p-3 md:rounded-2xl md:p-4"
                        >
                            <div
                                class="grid grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)] items-center gap-2 md:gap-3"
                            >
                                <div>
                                    <p
                                        class="line-clamp-2 text-sm leading-tight font-semibold md:text-base 2xl:text-2xl"
                                        :class="
                                            match.winner?.id ===
                                            match.participant_a?.id
                                                ? 'text-emerald-200'
                                                : ''
                                        "
                                    >
                                        {{
                                            match.participant_a?.display_name ??
                                            '—'
                                        }}
                                    </p>
                                </div>

                                <div
                                    class="rounded-lg bg-white/10 px-2 py-1 text-base font-bold md:rounded-xl md:px-3 md:text-lg 2xl:text-3xl"
                                >
                                    {{ match.score_a ?? '-' }} :
                                    {{ match.score_b ?? '-' }}
                                </div>

                                <div class="text-right">
                                    <p
                                        class="line-clamp-2 text-sm leading-tight font-semibold md:text-base 2xl:text-2xl"
                                        :class="
                                            match.winner?.id ===
                                            match.participant_b?.id
                                                ? 'text-emerald-200'
                                                : ''
                                        "
                                    >
                                        {{
                                            match.participant_b?.display_name ??
                                            '—'
                                        }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <p
                        v-else
                        class="mt-3 rounded-2xl border border-dashed border-white/10 p-4 text-sm text-zinc-400"
                    >
                        Još nema generisanih mečeva za ovu grupu.
                    </p>
                </details>
            </article>
        </section>

        <section
            v-else
            class="rounded-3xl border border-dashed border-white/10 p-6 text-zinc-400"
        >
            Još nema grupa za prikaz.
        </section>
    </PublicTournamentLayout>
</template>
