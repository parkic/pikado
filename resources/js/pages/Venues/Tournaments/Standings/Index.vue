<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';

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
    can_manage_withdrawals: boolean;
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
    participant_status: string;
    is_withdrawn: boolean;
    withdrawn_at: string | null;
    qualification_status: string;
    qualification_label: string;
    qualification_override_status: string | null;
    qualification_is_manual: boolean;
};

type StandingGroup = {
    id: number;
    name: string;
    matches_count: number;
    finished_matches_count: number;
    rows: StandingRow[];
};

const props = defineProps<{
    venue: Venue;
    tournament: Tournament;
    groups: StandingGroup[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Tabela grupa',
                href: '#',
            },
        ],
    },
});

const differenceLabel = (difference: number): string => {
    if (difference > 0) {
        return `+${difference}`;
    }

    return String(difference);
};

const qualificationBadgeClasses = (status: string): string => {
    if (status === 'direct') {
        return 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300';
    }

    if (status === 'repechage') {
        return 'bg-yellow-500/10 text-yellow-700 dark:text-yellow-300';
    }

    if (status === 'withdrawn') {
        return 'bg-red-500/10 text-red-700 dark:text-red-300';
    }

    return 'bg-muted text-muted-foreground';
};

const updateQualificationOverride = (row: StandingRow, event: Event) => {
    const target = event.target as HTMLSelectElement;

    router.patch(
        `/venues/${props.venue.slug}/tournaments/${props.tournament.slug}/standings/participants/${row.participant_id}/qualification-override`,
        {
            qualification_override_status: target.value || null,
        },
        {
            preserveScroll: true,
            preserveState: false,
        },
    );
};

const withdrawParticipant = (row: StandingRow) => {
    const confirmed = window.confirm(
        `Da li želiš da označiš učesnika "${row.display_name}" kao odustao? Njegovi grupni mečevi biće anulirani za tabelu.`,
    );

    if (!confirmed) {
        return;
    }

    router.patch(
        `/venues/${props.venue.slug}/tournaments/${props.tournament.slug}/standings/participants/${row.participant_id}/withdraw`,
        {},
        {
            preserveScroll: true,
            preserveState: false,
        },
    );
};

const restoreParticipant = (row: StandingRow) => {
    const confirmed = window.confirm(
        `Da li želiš da vratiš učesnika "${row.display_name}" u aktivne? Njegovi grupni mečevi biće vraćeni na prethodni status.`,
    );

    if (!confirmed) {
        return;
    }

    router.patch(
        `/venues/${props.venue.slug}/tournaments/${props.tournament.slug}/standings/participants/${row.participant_id}/restore`,
        {},
        {
            preserveScroll: true,
            preserveState: false,
        },
    );
};
</script>

<template>
    <Head :title="`Tabela grupa - ${tournament.name}`" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4">
        <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
            <div>
                <p class="text-sm text-muted-foreground">
                    {{ venue.name }}
                </p>

                <h1 class="mt-1 text-2xl font-semibold tracking-tight">
                    Tabela grupa
                </h1>

                <p class="mt-2 max-w-2xl text-sm text-muted-foreground">
                    Automatski obračun plasmana po grupama za turnir: {{ tournament.name }}.
                </p>
            </div>

            <div class="flex flex-col gap-2 sm:flex-row">
                <Link
                    :href="`/venues/${venue.slug}/tournaments/${tournament.slug}/qualification/setup`"
                    class="inline-flex items-center justify-center rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground transition hover:opacity-90"
                >
                    Podešavanje prolaza
                </Link>

                <Link
                    :href="`/venues/${venue.slug}/tournaments/${tournament.slug}/repechage`"
                    class="inline-flex items-center justify-center rounded-lg border border-sidebar-border/70 px-4 py-2 text-sm font-medium transition hover:bg-muted dark:border-sidebar-border"
                >
                    Repasaž
                </Link>

                <Link
                    :href="`/venues/${venue.slug}/tournaments/${tournament.slug}/schedule`"
                    class="inline-flex items-center justify-center rounded-lg border border-sidebar-border/70 px-4 py-2 text-sm font-medium transition hover:bg-muted dark:border-sidebar-border"
                >
                    Raspored
                </Link>

                <Link
                    :href="`/venues/${venue.slug}/tournaments/${tournament.slug}`"
                    class="inline-flex items-center justify-center rounded-lg border border-sidebar-border/70 px-4 py-2 text-sm font-medium transition hover:bg-muted dark:border-sidebar-border"
                >
                    Nazad na turnir
                </Link>
            </div>
        </div>

        <div
            v-if="groups.length"
            class="grid gap-6 xl:grid-cols-2"
        >
            <div
                v-for="group in groups"
                :key="group.id"
                class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
            >
                <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                    <div>
                        <h2 class="text-lg font-medium">
                            Grupa {{ group.name }}
                        </h2>

                        <p class="mt-1 text-sm text-muted-foreground">
                            Odigrano {{ group.finished_matches_count }} / {{ group.matches_count }} mečeva.
                        </p>

                        <div class="mt-3 flex flex-wrap gap-2 text-xs">
                            <span class="inline-flex rounded-full bg-emerald-500/10 px-2.5 py-1 font-medium text-emerald-700 dark:text-emerald-300">
                                Direktan prolaz
                            </span>

                            <span class="inline-flex rounded-full bg-yellow-500/10 px-2.5 py-1 font-medium text-yellow-700 dark:text-yellow-300">
                                Repasaž
                            </span>

                            <span class="inline-flex rounded-full bg-muted px-2.5 py-1 font-medium text-muted-foreground">
                                Ispao
                            </span>
                            <span class="inline-flex rounded-full bg-red-500/10 px-2.5 py-1 font-medium text-red-700 dark:text-red-300">
                                Odustao
                            </span>
                        </div>
                    </div>
                </div>

                <div class="mt-4 overflow-hidden rounded-lg border border-sidebar-border/70 dark:border-sidebar-border">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="border-b border-sidebar-border/70 bg-muted/40 dark:border-sidebar-border">
                                <tr>
                                    <th class="px-3 py-3 font-medium">#</th>
                                    <th class="px-3 py-3 font-medium">Učesnik</th>
                                    <th class="px-3 py-3 text-center font-medium">O</th>
                                    <th class="px-3 py-3 text-center font-medium">P</th>
                                    <th class="px-3 py-3 text-center font-medium">I</th>
                                    <th class="px-3 py-3 text-center font-medium">Za</th>
                                    <th class="px-3 py-3 text-center font-medium">Protiv</th>
                                    <th class="px-3 py-3 text-center font-medium">+/-</th>
                                    <th class="px-3 py-3 text-center font-medium">Bod</th>
                                    <th class="px-3 py-3 text-center font-medium">Status</th>
                                    <th class="px-3 py-3 text-center font-medium">Akcije</th>
                                </tr>
                            </thead>

                            <tbody>
                                <tr
                                    v-for="row in group.rows"
                                    :key="row.participant_id"
                                    class="border-b border-sidebar-border/70 last:border-b-0 dark:border-sidebar-border"
                                >
                                    <td class="px-3 py-3 text-muted-foreground">
                                        {{ row.position ?? '-' }}
                                    </td>

                                    <td class="px-3 py-3">
                                        <div
                                            class="font-medium"
                                            :class="row.is_withdrawn ? 'text-muted-foreground line-through' : ''"
                                        >
                                            {{ row.display_name }}
                                        </div>

                                        <div class="mt-1 text-xs text-muted-foreground">
                                            {{ row.group_position ?? '-' }}
                                        </div>
                                        <div
                                            v-if="row.is_withdrawn && row.withdrawn_at"
                                            class="mt-1 text-xs text-red-600 dark:text-red-300"
                                        >
                                            Odustao: {{ row.withdrawn_at }}
                                        </div>
                                    </td>

                                    <td class="px-3 py-3 text-center text-muted-foreground">
                                        {{ row.played }}
                                    </td>

                                    <td class="px-3 py-3 text-center text-muted-foreground">
                                        {{ row.wins }}
                                    </td>

                                    <td class="px-3 py-3 text-center text-muted-foreground">
                                        {{ row.losses }}
                                    </td>

                                    <td class="px-3 py-3 text-center text-muted-foreground">
                                        {{ row.points_for }}
                                    </td>

                                    <td class="px-3 py-3 text-center text-muted-foreground">
                                        {{ row.points_against }}
                                    </td>

                                    <td class="px-3 py-3 text-center font-medium">
                                        {{ differenceLabel(row.points_difference) }}
                                    </td>

                                    <td class="px-3 py-3 text-center font-semibold">
                                        {{ row.standing_points }}
                                    </td>
                                    <td class="px-3 py-3">
                                        <div class="flex min-w-44 flex-col gap-2">
                                            <span
                                                class="inline-flex w-fit rounded-full px-2.5 py-1 text-xs font-medium"
                                                :class="qualificationBadgeClasses(row.qualification_status)"
                                            >
                                                {{ row.qualification_label }}
                                            </span>

                                            <select
                                                :value="row.qualification_override_status ?? ''"
                                                :disabled="row.is_withdrawn"
                                                class="rounded-lg border border-sidebar-border/70 bg-background px-2 py-2 text-xs outline-none transition focus:border-primary disabled:opacity-50 dark:border-sidebar-border"
                                                @change="updateQualificationOverride(row, $event)"
                                            >
                                                <option value="">
                                                    Automatski
                                                </option>

                                                <option value="direct">
                                                    Direktan prolaz
                                                </option>

                                                <option value="repechage">
                                                    Repasaž
                                                </option>

                                                <option value="eliminated">
                                                    Ispao
                                                </option>
                                            </select>

                                            <span
                                                v-if="row.qualification_is_manual"
                                                class="text-xs text-primary"
                                            >
                                                Ručno podešeno
                                            </span>
                                        </div>
                                    </td>

                                    <td class="px-3 py-3">
                                        <div
                                            v-if="tournament.can_manage_withdrawals && tournament.status === 'group_stage'"
                                            class="flex min-w-32 flex-col gap-2"
                                        >
                                            <button
                                                v-if="!row.is_withdrawn"
                                                type="button"
                                                class="inline-flex items-center justify-center rounded-lg border border-red-500/30 px-3 py-2 text-xs font-medium text-red-700 transition hover:bg-red-500/10 dark:text-red-300"
                                                @click="withdrawParticipant(row)"
                                            >
                                                Označi odustao
                                            </button>

                                            <button
                                                v-else
                                                type="button"
                                                class="inline-flex items-center justify-center rounded-lg border border-sidebar-border/70 px-3 py-2 text-xs font-medium transition hover:bg-muted dark:border-sidebar-border"
                                                @click="restoreParticipant(row)"
                                            >
                                                Vrati
                                            </button>
                                        </div>

                                        <span
                                            v-else
                                            class="text-xs text-muted-foreground"
                                        >
                                            -
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <p class="mt-3 text-xs text-muted-foreground">
                    Sortiranje: pobeda nosi 1 bod, zatim razlika, poeni za, ime.
                </p>
            </div>
        </div>

        <div
            v-else
            class="rounded-xl border border-dashed border-sidebar-border/70 p-4 text-sm text-muted-foreground dark:border-sidebar-border"
        >
            Još nema grupa za prikaz.
        </div>
    </div>
</template>
