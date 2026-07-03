<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { reactive, ref } from 'vue';

type Venue = {
    id: number;
    name: string;
    slug: string;
};

type Tournament = {
    id: number;
    name: string;
    slug: string;
    status: string;
    status_label: string;
    matches_count: number;
    group_matches_count: number;
};

type Participant = {
    id: number;
    group_position: string | null;
    display_name: string;
    status: string;
    is_withdrawn: boolean;
};

type MatchResource = {
    id: number;
    name: string;
    type: string;
};

type AvailableResource = {
    id: number;
    name: string;
    type: string;
    type_label: string;
};

type Match = {
    id: number;
    stage: string;
    stage_label: string;
    group_name: string | null;
    scheduled_order: number | null;
    round_robin_leg: number | null;
    wins_required: number | null;
    bracket_round: string | null;
    bracket_round_label: string | null;
    bracket_position: number | null;
    participant_a: Participant | null;
    participant_b: Participant | null;
    score_a: number | null;
    score_b: number | null;
    winner: {
        id: number;
        display_name: string;
    } | null;
    status: string;
    status_label: string;
    resource: MatchResource | null;
};

const props = defineProps<{
    venue: Venue;
    tournament: Tournament;
    matches: Match[];
    resources: AvailableResource[];
}>();

const resultForms = reactive<Record<number, {
    score_a: string;
    score_b: string;
    winner_participant_id: string;
}>>(
    Object.fromEntries(
        props.matches.map((match) => [
            match.id,
            {
                score_a: match.score_a !== null ? String(match.score_a) : '',
                score_b: match.score_b !== null ? String(match.score_b) : '',
                winner_participant_id: match.winner ? String(match.winner.id) : '',
            },
        ]),
    ),
);

const tieBreakerMatch = ref<Match | null>(null);

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Raspored',
                href: '#',
            },
        ],
    },
});

const statusBadgeClasses = (status: string): string => {
    if (status === 'scheduled') {
        return 'bg-muted text-muted-foreground';
    }

    if (status === 'in_progress') {
        return 'bg-primary/10 text-primary';
    }

    if (status === 'finished') {
        return 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300';
    }

    return 'bg-yellow-500/10 text-yellow-700 dark:text-yellow-300';
};

const updateMatchResource = (match: Match, event: Event) => {
    const target = event.target as HTMLSelectElement;
    const selectedValue = target.value;

    router.patch(
        `/venues/${props.venue.slug}/tournaments/${props.tournament.slug}/schedule/matches/${match.id}/resource`,
        {
            tournament_resource_id: selectedValue ? Number(selectedValue) : null,
        },
        {
            preserveScroll: true,
        },
    );
};

const isDrawResult = (match: Match): boolean => {
    const resultForm = resultForms[match.id];

    if (resultForm.score_a === '' || resultForm.score_b === '') {
        return false;
    }

    return Number(resultForm.score_a) === Number(resultForm.score_b);
};

const submitMatchResult = (match: Match, onSuccess?: () => void) => {
    const resultForm = resultForms[match.id];

    router.patch(
        `/venues/${props.venue.slug}/tournaments/${props.tournament.slug}/schedule/matches/${match.id}/result`,
        {
            score_a: resultForm.score_a,
            score_b: resultForm.score_b,
            winner_participant_id: resultForm.winner_participant_id || null,
        },
        {
            preserveScroll: true,
            preserveState: false,
            onSuccess,
        },
    );
};

const openTieBreakerModal = (match: Match) => {
    const resultForm = resultForms[match.id];

    if (!resultForm.winner_participant_id && match.winner) {
        resultForm.winner_participant_id = String(match.winner.id);
    }

    tieBreakerMatch.value = match;
};

const updateMatchResult = (match: Match) => {
    const resultForm = resultForms[match.id];

    if (isDrawResult(match) && !resultForm.winner_participant_id) {
        openTieBreakerModal(match);

        return;
    }

    submitMatchResult(match);
};

const closeTieBreakerModal = () => {
    tieBreakerMatch.value = null;
};

const chooseTieBreakerWinner = (match: Match, participantId: number) => {
    resultForms[match.id].winner_participant_id = String(participantId);
};

const saveTieBreakerWinner = () => {
    if (!tieBreakerMatch.value) {
        return;
    }

    const match = tieBreakerMatch.value;
    const resultForm = resultForms[match.id];

    if (!resultForm.winner_participant_id) {
        window.alert('Moraš da izabereš pobednika.');

        return;
    }

    submitMatchResult(match, () => {
        closeTieBreakerModal();
    });
};

const tieBreakerWinnerButtonClasses = (match: Match, participantId: number): string => {
    const isSelected = resultForms[match.id].winner_participant_id === String(participantId);

    if (isSelected) {
        return 'border-primary bg-primary/10 text-primary';
    }

    return 'border-sidebar-border/70 hover:bg-muted dark:border-sidebar-border';
};
</script>

<template>
    <Head :title="`Raspored - ${tournament.name}`" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4">
        <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
            <div>
                <p class="text-sm text-muted-foreground">
                    {{ venue.name }}
                </p>

                <h1 class="mt-1 text-2xl font-semibold tracking-tight">
                    Raspored mečeva
                </h1>

                <p class="mt-2 max-w-2xl text-sm text-muted-foreground">
                    Pregled generisanih mečeva za turnir: {{ tournament.name }}.
                </p>
            </div>

            <div class="flex flex-col gap-2 sm:flex-row">
                <Link
                    :href="`/venues/${venue.slug}/tournaments/${tournament.slug}/standings`"
                    class="inline-flex items-center justify-center rounded-lg border border-sidebar-border/70 px-4 py-2 text-sm font-medium transition hover:bg-muted dark:border-sidebar-border"
                >
                    Tabela
                </Link>

                <Link
                    :href="`/venues/${venue.slug}/tournaments/${tournament.slug}`"
                    class="inline-flex items-center justify-center rounded-lg border border-sidebar-border/70 px-4 py-2 text-sm font-medium transition hover:bg-muted dark:border-sidebar-border"
                >
                    Nazad na turnir
                </Link>
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-3">
            <div class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                <p class="text-sm text-muted-foreground">
                    Ukupno mečeva
                </p>

                <p class="mt-2 text-lg font-medium">
                    {{ tournament.matches_count }}
                </p>
            </div>

            <div class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                <p class="text-sm text-muted-foreground">
                    Grupni mečevi
                </p>

                <p class="mt-2 text-lg font-medium">
                    {{ tournament.group_matches_count }}
                </p>
            </div>

            <div class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                <p class="text-sm text-muted-foreground">
                    Status turnira
                </p>

                <p class="mt-2 text-lg font-medium">
                    {{ tournament.status }}
                </p>
            </div>
        </div>

        <div class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
            <div>
                <h2 class="text-lg font-medium">
                    Mečevi
                </h2>

                <p class="mt-1 text-sm text-muted-foreground">
                    Za sada je ovo samo pregled. U sledećem koraku dodajemo unos rezultata.
                </p>
            </div>

            <div
                v-if="matches.length"
                class="mt-4 overflow-hidden rounded-lg border border-sidebar-border/70 dark:border-sidebar-border"
            >
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="border-b border-sidebar-border/70 bg-muted/40 dark:border-sidebar-border">
                            <tr>
                                <th class="px-4 py-3 font-medium">#</th>
                                <th class="px-4 py-3 font-medium">Faza</th>
                                <th class="px-4 py-3 font-medium">Grupa</th>
                                <th class="px-4 py-3 font-medium">Meč</th>
                                <th class="px-4 py-3 font-medium">Resource</th>
                                <th class="px-4 py-3 font-medium">Krug</th>
                                <th class="px-4 py-3 font-medium">Rezultat</th>
                                <th class="px-4 py-3 font-medium">Status</th>
                            </tr>
                        </thead>

                        <tbody>
                            <tr
                                v-for="match in matches"
                                :key="match.id"
                                class="border-b border-sidebar-border/70 last:border-b-0 dark:border-sidebar-border"
                            >
                                <td class="px-4 py-3 text-muted-foreground">
                                    {{ match.scheduled_order ?? '-' }}
                                </td>

                                <td class="px-4 py-3 text-muted-foreground">
                                    {{ match.stage_label }}
                                </td>

                                <td class="px-4 py-3">
                                    <template v-if="match.group_name">
                                        Grupa {{ match.group_name }}
                                    </template>

                                    <template v-else-if="match.bracket_round_label">
                                        {{ match.bracket_round_label }}
                                        <span
                                            v-if="match.bracket_position"
                                            class="text-muted-foreground"
                                        >
                                            #{{ match.bracket_position }}
                                        </span>
                                    </template>

                                    <template v-else>
                                        -
                                    </template>
                                </td>

                                <td class="px-4 py-3">
                                    <div class="font-medium">
                                        <span :class="match.participant_a?.is_withdrawn ? 'text-muted-foreground line-through' : ''">
                                            {{ match.participant_a?.display_name ?? 'TBD' }}
                                        </span>

                                        <span
                                            v-if="match.participant_a?.is_withdrawn"
                                            class="ml-2 inline-flex rounded-full bg-red-500/10 px-2 py-0.5 text-[11px] font-medium text-red-700 dark:text-red-300"
                                        >
                                            Odustao
                                        </span>

                                        <span class="mx-2 text-muted-foreground">
                                            vs
                                        </span>

                                        <span :class="match.participant_b?.is_withdrawn ? 'text-muted-foreground line-through' : ''">
                                            {{ match.participant_b?.display_name ?? 'TBD' }}
                                        </span>

                                        <span
                                            v-if="match.participant_b?.is_withdrawn"
                                            class="ml-2 inline-flex rounded-full bg-red-500/10 px-2 py-0.5 text-[11px] font-medium text-red-700 dark:text-red-300"
                                        >
                                            Odustao
                                        </span>
                                    </div>

                                    <div class="mt-1 text-xs text-muted-foreground">
                                        {{ match.participant_a?.group_position ?? '-' }}
                                        vs
                                        {{ match.participant_b?.group_position ?? '-' }}
                                    </div>
                                </td>

                                <td class="px-4 py-3">
                                    <select
                                        :value="match.resource?.id ?? ''"
                                        class="w-full min-w-36 rounded-lg border border-sidebar-border/70 bg-background px-3 py-2 text-sm outline-none transition focus:border-primary dark:border-sidebar-border"
                                        @change="updateMatchResource(match, $event)"
                                    >
                                        <option value="">
                                            Bez resource-a
                                        </option>

                                        <option
                                            v-for="resource in resources"
                                            :key="resource.id"
                                            :value="resource.id"
                                        >
                                            {{ resource.name }}
                                        </option>
                                    </select>
                                </td>

                                <td class="px-4 py-3 text-muted-foreground">
                                    <template v-if="match.round_robin_leg">
                                        {{ match.round_robin_leg }}

                                        <span
                                            v-if="match.wins_required && match.stage !== 'group'"
                                            class="block text-xs"
                                        >
                                            na {{ match.wins_required }} dobijene
                                        </span>
                                    </template>

                                    <template v-else>
                                        -
                                    </template>
                                </td>

                                <td class="px-4 py-3">
                                    <div class="flex min-w-44 items-center gap-2">
                                        <input
                                            v-model="resultForms[match.id].score_a"
                                            :disabled="match.status === 'voided'"
                                            type="number"
                                            min="0"
                                            max="999"
                                            class="w-16 rounded-lg border border-sidebar-border/70 bg-background px-2 py-2 text-center text-sm outline-none transition focus:border-primary disabled:opacity-50 dark:border-sidebar-border"
                                        >

                                        <span class="text-muted-foreground">
                                            :
                                        </span>

                                        <input
                                            v-model="resultForms[match.id].score_b"
                                            :disabled="match.status === 'voided'"
                                            type="number"
                                            min="0"
                                            max="999"
                                            class="w-16 rounded-lg border border-sidebar-border/70 bg-background px-2 py-2 text-center text-sm outline-none transition focus:border-primary disabled:opacity-50 dark:border-sidebar-border"
                                        >

                                        <button
                                            type="button"
                                            :disabled="match.status === 'voided'"
                                            class="inline-flex items-center justify-center rounded-lg bg-primary px-3 py-2 text-xs font-medium text-primary-foreground transition hover:opacity-90 disabled:opacity-50"
                                            @click="updateMatchResult(match)"
                                        >
                                            {{ isDrawResult(match) && !resultForms[match.id].winner_participant_id ? 'Izaberi' : 'Sačuvaj' }}
                                        </button>
                                    </div>

                                    <p
                                        v-if="isDrawResult(match) && !resultForms[match.id].winner_participant_id"
                                        class="mt-1 text-xs text-yellow-600 dark:text-yellow-300"
                                    >
                                        Nerešeno — treba izabrati pobednika.
                                    </p>

                                    <div
                                        v-if="match.winner"
                                        class="mt-1 flex flex-wrap items-center gap-2 text-xs"
                                    >
                                        <span class="text-emerald-600 dark:text-emerald-300">
                                            Pobednik: {{ match.winner.display_name }}
                                        </span>

                                        <button
                                            v-if="isDrawResult(match)"
                                            type="button"
                                            class="font-medium text-primary hover:underline"
                                            @click="openTieBreakerModal(match)"
                                        >
                                            Izmeni
                                        </button>
                                    </div>
                                </td>

                                <td class="px-4 py-3">
                                    <span
                                        class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium"
                                        :class="statusBadgeClasses(match.status)"
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
                v-else
                class="mt-4 rounded-lg border border-dashed border-sidebar-border/70 p-4 text-sm text-muted-foreground dark:border-sidebar-border"
            >
                Još nema generisanih mečeva.
            </div>
        </div>


        <div
            v-if="tieBreakerMatch"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4"
        >
            <button
                type="button"
                class="absolute inset-0"
                aria-label="Zatvori modal"
                @click="closeTieBreakerModal"
            />

            <div class="relative w-full max-w-lg rounded-xl border border-sidebar-border/70 bg-background p-5 shadow-xl dark:border-sidebar-border">
                <div>
                    <p class="text-sm text-muted-foreground">
                        Nerešen rezultat
                    </p>

                    <h2 class="mt-1 text-xl font-semibold">
                        Izaberi pobednika
                    </h2>

                    <p class="mt-2 text-sm text-muted-foreground">
                        Rezultat je
                        <span class="font-medium text-foreground">
                            {{ resultForms[tieBreakerMatch.id].score_a }} : {{ resultForms[tieBreakerMatch.id].score_b }}
                        </span>
                        i razlika će ostati 0. Izabrani učesnik dobija pobedu i 1 bod.
                    </p>
                </div>

                <div class="mt-5 grid gap-3">
                    <button
                        v-if="tieBreakerMatch.participant_a"
                        type="button"
                        class="rounded-xl border p-4 text-left transition"
                        :class="tieBreakerWinnerButtonClasses(tieBreakerMatch, tieBreakerMatch.participant_a.id)"
                        @click="chooseTieBreakerWinner(tieBreakerMatch, tieBreakerMatch.participant_a.id)"
                    >
                        <p class="text-xs text-muted-foreground">
                            {{ tieBreakerMatch.participant_a.group_position ?? '-' }}
                        </p>

                        <p class="mt-1 font-medium">
                            {{ tieBreakerMatch.participant_a.display_name }}
                        </p>
                    </button>

                    <button
                        v-if="tieBreakerMatch.participant_b"
                        type="button"
                        class="rounded-xl border p-4 text-left transition"
                        :class="tieBreakerWinnerButtonClasses(tieBreakerMatch, tieBreakerMatch.participant_b.id)"
                        @click="chooseTieBreakerWinner(tieBreakerMatch, tieBreakerMatch.participant_b.id)"
                    >
                        <p class="text-xs text-muted-foreground">
                            {{ tieBreakerMatch.participant_b.group_position ?? '-' }}
                        </p>

                        <p class="mt-1 font-medium">
                            {{ tieBreakerMatch.participant_b.display_name }}
                        </p>
                    </button>
                </div>

                <div class="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <button
                        type="button"
                        class="inline-flex items-center justify-center rounded-lg border border-sidebar-border/70 px-4 py-2 text-sm font-medium transition hover:bg-muted dark:border-sidebar-border"
                        @click="closeTieBreakerModal"
                    >
                        Otkaži
                    </button>

                    <button
                        type="button"
                        class="inline-flex items-center justify-center rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground transition hover:opacity-90"
                        @click="saveTieBreakerWinner"
                    >
                        Sačuvaj rezultat
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
