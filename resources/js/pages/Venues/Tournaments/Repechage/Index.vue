<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';

type Venue = {
    id: number;
    name: string;
    slug: string;
};

type TournamentSettings = {
    direct_qualifiers_per_group?: number | null;
    repechage_enabled?: boolean;
    repechage_qualifiers_count?: number | null;
};

type Tournament = {
    id: number;
    name: string;
    slug: string;
    status: string;
    status_label: string;
    settings: TournamentSettings;
    repechage_qualifiers_count: number;
    repechage_advanced_count: number;
    repechage_eliminated_count: number;
    can_complete_repechage: boolean;
};

type ParticipantRow = {
    participant_id: number;
    group_name: string;
    group_position: string | null;
    group_rank: number;
    display_name: string;
    played: number;
    wins: number;
    losses: number;
    points_for: number;
    points_against: number;
    points_difference: number;
    standing_points: number;
    repechage_outcome_status: string | null;
    repechage_outcome_label: string;
};

const props = defineProps<{
    venue: Venue;
    tournament: Tournament;
    direct_qualifiers: ParticipantRow[];
    repechage_participants: ParticipantRow[];
    eliminated_participants: ParticipantRow[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Repasaž',
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

const repechageOutcomeBadgeClasses = (status: string | null): string => {
    if (status === 'advanced') {
        return 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300';
    }

    if (status === 'eliminated') {
        return 'bg-muted text-muted-foreground';
    }

    return 'bg-yellow-500/10 text-yellow-700 dark:text-yellow-300';
};

const updateRepechageOutcome = (participant: ParticipantRow, event: Event) => {
    const target = event.target as HTMLSelectElement;

    router.patch(
        `/venues/${props.venue.slug}/tournaments/${props.tournament.slug}/repechage/participants/${participant.participant_id}/outcome`,
        {
            repechage_outcome_status: target.value || null,
        },
        {
            preserveScroll: true,
            preserveState: false,
        },
    );
};

const completeRepechage = () => {
    const confirmed = window.confirm('Da li želiš da završiš repasaž? Svi neodlučeni učesnici iz repasaža biće označeni kao ispali.');

    if (!confirmed) {
        return;
    }

    router.post(
        `/venues/${props.venue.slug}/tournaments/${props.tournament.slug}/repechage/complete`,
        {},
        {
            preserveScroll: true,
        },
    );
};
</script>

<template>
    <Head :title="`Repasaž - ${tournament.name}`" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4">
        <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
            <div>
                <p class="text-sm text-muted-foreground">
                    {{ venue.name }}
                </p>

                <h1 class="mt-1 text-2xl font-semibold tracking-tight">
                    Repasaž
                </h1>

                <p class="mt-2 max-w-2xl text-sm text-muted-foreground">
                    Pregled učesnika posle grupne faze za turnir: {{ tournament.name }}.
                </p>
            </div>

            <button
                v-if="tournament.can_complete_repechage"
                type="button"
                class="inline-flex items-center justify-center rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground transition hover:opacity-90"
                @click="completeRepechage"
            >
                Završi repasaž
            </button>

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

        <div class="grid gap-4 md:grid-cols-4">
            <div class="rounded-xl border border-emerald-500/30 bg-emerald-500/10 p-4 text-emerald-700 dark:text-emerald-300">
                <p class="text-sm">
                    Direktan prolaz
                </p>

                <p class="mt-2 text-2xl font-semibold">
                    {{ direct_qualifiers.length }}
                </p>
            </div>

            <div class="rounded-xl border border-yellow-500/30 bg-yellow-500/10 p-4 text-yellow-700 dark:text-yellow-300">
                <p class="text-sm">
                    Repasaž
                </p>

                <p class="mt-2 text-2xl font-semibold">
                    {{ repechage_participants.length }}
                </p>
            </div>

            <div class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                <p class="text-sm text-muted-foreground">
                    Iz repasaža ide dalje
                </p>

                <p class="mt-2 text-2xl font-semibold">
                    {{ tournament.repechage_qualifiers_count || '-' }}
                </p>
            </div>

            <div class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                <p class="text-sm text-muted-foreground">
                    Označeno prošlo
                </p>

                <p class="mt-2 text-2xl font-semibold">
                    {{ tournament.repechage_advanced_count }} / {{ tournament.repechage_qualifiers_count || '-' }}
                </p>
            </div>
        </div>

        <div
            v-if="tournament.status === 'repechage'"
            class="rounded-xl border border-primary/30 bg-primary/5 p-4"
        >
            <h2 class="text-lg font-medium">
                Repasaž je u toku
            </h2>

            <p class="mt-1 text-sm text-muted-foreground">
                Označi tačno {{ tournament.repechage_qualifiers_count }} učesnika koji prolaze dalje.
                Trenutno označeno: {{ tournament.repechage_advanced_count }}.
            </p>

            <button
                v-if="tournament.can_complete_repechage"
                type="button"
                class="mt-4 inline-flex items-center justify-center rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground transition hover:opacity-90"
                @click="completeRepechage"
            >
                Završi repasaž
            </button>

            <p
                v-else
                class="mt-3 text-sm text-muted-foreground"
            >
                Dugme za završetak će se pojaviti kada označiš tačan broj učesnika koji prolaze dalje.
            </p>
        </div>

        <div
            v-else-if="tournament.status === 'knockout_draw'"
            class="rounded-xl border border-emerald-500/30 bg-emerald-500/10 p-4 text-emerald-700 dark:text-emerald-300"
        >
            <h2 class="text-lg font-medium">
                Repasaž je završen
            </h2>

            <p class="mt-1 text-sm">
                Turnir je spreman za nokaut žreb.
            </p>
        </div>

        <div
            v-if="!tournament.settings.repechage_enabled"
            class="rounded-xl border border-yellow-500/30 bg-yellow-500/10 p-4 text-yellow-700 dark:text-yellow-300"
        >
            <h2 class="text-lg font-medium">
                Repasaž nije uključen
            </h2>

            <p class="mt-1 text-sm">
                Ako želiš repasaž, vrati se na podešavanje prolaza i uključi ga.
            </p>
        </div>

        <div class="grid gap-6 xl:grid-cols-2">
            <div class="rounded-xl border border-yellow-500/30 p-4 dark:border-yellow-500/30">
                <div>
                    <h2 class="text-lg font-medium">
                        Učesnici za repasaž
                    </h2>

                    <p class="mt-1 text-sm text-muted-foreground">
                        Sortirani po učinku iz grupne faze.
                    </p>
                </div>

                <div
                    v-if="repechage_participants.length"
                    class="mt-4 overflow-hidden rounded-lg border border-sidebar-border/70 dark:border-sidebar-border"
                >
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="border-b border-sidebar-border/70 bg-muted/40 dark:border-sidebar-border">
                                <tr>
                                    <th class="px-3 py-3 font-medium">#</th>
                                    <th class="px-3 py-3 font-medium">Učesnik</th>
                                    <th class="px-3 py-3 text-center font-medium">Grupa</th>
                                    <th class="px-3 py-3 text-center font-medium">P</th>
                                    <th class="px-3 py-3 text-center font-medium">+/-</th>
                                    <th class="px-3 py-3 text-center font-medium">Bod</th>
                                    <th class="px-3 py-3 font-medium">Ishod</th>
                                </tr>
                            </thead>

                            <tbody>
                                <tr
                                    v-for="(participant, index) in repechage_participants"
                                    :key="participant.participant_id"
                                    class="border-b border-sidebar-border/70 last:border-b-0 dark:border-sidebar-border"
                                >
                                    <td class="px-3 py-3 text-muted-foreground">
                                        {{ index + 1 }}
                                    </td>

                                    <td class="px-3 py-3">
                                        <div class="font-medium">
                                            {{ participant.display_name }}
                                        </div>

                                        <div class="mt-1 text-xs text-muted-foreground">
                                            {{ participant.group_position ?? '-' }}
                                        </div>
                                    </td>

                                    <td class="px-3 py-3 text-center text-muted-foreground">
                                        {{ participant.group_name }} / {{ participant.group_rank }}.
                                    </td>

                                    <td class="px-3 py-3 text-center text-muted-foreground">
                                        {{ participant.wins }}
                                    </td>

                                    <td class="px-3 py-3 text-center font-medium">
                                        {{ differenceLabel(participant.points_difference) }}
                                    </td>

                                    <td class="px-3 py-3 text-center font-semibold">
                                        {{ participant.standing_points }}
                                    </td>
                                    <td class="px-3 py-3">
                                        <div class="flex min-w-48 flex-col gap-2">
                                            <span
                                                class="inline-flex w-fit rounded-full px-2.5 py-1 text-xs font-medium"
                                                :class="repechageOutcomeBadgeClasses(participant.repechage_outcome_status)"
                                            >
                                                {{ participant.repechage_outcome_label }}
                                            </span>

                                            <select
                                                :value="participant.repechage_outcome_status ?? ''"
                                                class="rounded-lg border border-sidebar-border/70 bg-background px-2 py-2 text-xs outline-none transition focus:border-primary dark:border-sidebar-border"
                                                @change="updateRepechageOutcome(participant, $event)"
                                            >
                                                <option value="">
                                                    Neodlučeno
                                                </option>

                                                <option value="advanced">
                                                    Prošao iz repasaža
                                                </option>

                                                <option value="eliminated">
                                                    Ispao posle repasaža
                                                </option>
                                            </select>
                                        </div>
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
                    Nema učesnika za repasaž.
                </div>
            </div>

            <div class="rounded-xl border border-emerald-500/30 p-4 dark:border-emerald-500/30">
                <div>
                    <h2 class="text-lg font-medium">
                        Direktan prolaz
                    </h2>

                    <p class="mt-1 text-sm text-muted-foreground">
                        Ovi učesnici čekaju sledeću fazu.
                    </p>
                </div>

                <div
                    v-if="direct_qualifiers.length"
                    class="mt-4 overflow-hidden rounded-lg border border-sidebar-border/70 dark:border-sidebar-border"
                >
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="border-b border-sidebar-border/70 bg-muted/40 dark:border-sidebar-border">
                                <tr>
                                    <th class="px-3 py-3 font-medium">#</th>
                                    <th class="px-3 py-3 font-medium">Učesnik</th>
                                    <th class="px-3 py-3 text-center font-medium">Grupa</th>
                                    <th class="px-3 py-3 text-center font-medium">P</th>
                                    <th class="px-3 py-3 text-center font-medium">+/-</th>
                                    <th class="px-3 py-3 text-center font-medium">Bod</th>
                                </tr>
                            </thead>

                            <tbody>
                                <tr
                                    v-for="(participant, index) in direct_qualifiers"
                                    :key="participant.participant_id"
                                    class="border-b border-sidebar-border/70 last:border-b-0 dark:border-sidebar-border"
                                >
                                    <td class="px-3 py-3 text-muted-foreground">
                                        {{ index + 1 }}
                                    </td>

                                    <td class="px-3 py-3">
                                        <div class="font-medium">
                                            {{ participant.display_name }}
                                        </div>

                                        <div class="mt-1 text-xs text-muted-foreground">
                                            {{ participant.group_position ?? '-' }}
                                        </div>
                                    </td>

                                    <td class="px-3 py-3 text-center text-muted-foreground">
                                        {{ participant.group_name }} / {{ participant.group_rank }}.
                                    </td>

                                    <td class="px-3 py-3 text-center text-muted-foreground">
                                        {{ participant.wins }}
                                    </td>

                                    <td class="px-3 py-3 text-center font-medium">
                                        {{ differenceLabel(participant.points_difference) }}
                                    </td>

                                    <td class="px-3 py-3 text-center font-semibold">
                                        {{ participant.standing_points }}
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
                    Nema direktnih prolaza.
                </div>
            </div>
        </div>

        <div class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
            <div>
                <h2 class="text-lg font-medium">
                    Ispali
                </h2>

                <p class="mt-1 text-sm text-muted-foreground">
                    Učesnici koji ne nastavljaju takmičenje.
                </p>
            </div>

            <div
                v-if="eliminated_participants.length"
                class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-4"
            >
                <div
                    v-for="participant in eliminated_participants"
                    :key="participant.participant_id"
                    class="rounded-lg border border-sidebar-border/70 p-3 text-sm dark:border-sidebar-border"
                >
                    <div class="font-medium">
                        {{ participant.display_name }}
                    </div>

                    <div class="mt-1 text-xs text-muted-foreground">
                        Grupa {{ participant.group_name }} · {{ participant.group_position ?? '-' }}
                    </div>
                </div>
            </div>

            <div
                v-else
                class="mt-4 rounded-lg border border-dashed border-sidebar-border/70 p-4 text-sm text-muted-foreground dark:border-sidebar-border"
            >
                Nema eliminisanih učesnika.
            </div>
        </div>
    </div>
</template>
