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
    knockout_size: number | null;
    knockout_participants_count: number;
    direct_qualifiers_count: number;
    repechage_qualifiers_count: number;
    is_knockout_ready: boolean;
    knockout_matches_count: number;
    can_generate_knockout_bracket: boolean;
};

type KnockoutParticipant = {
    seed: number;
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
    source: string;
    source_label: string;
};

const props = defineProps<{
    venue: Venue;
    tournament: Tournament;
    direct_qualifiers: KnockoutParticipant[];
    repechage_qualifiers: KnockoutParticipant[];
    knockout_participants: KnockoutParticipant[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Nokaut',
                href: '#',
            },
        ],
    },
});

const sourceBadgeClasses = (source: string): string => {
    if (source === 'direct') {
        return 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300';
    }

    return 'bg-yellow-500/10 text-yellow-700 dark:text-yellow-300';
};

const differenceLabel = (difference: number): string => {
    if (difference > 0) {
        return `+${difference}`;
    }

    return String(difference);
};

const generateKnockoutBracket = () => {
    const confirmed = window.confirm('Da li želiš da generišeš nokaut kostur?');

    if (!confirmed) {
        return;
    }

    router.post(`/venues/${props.venue.slug}/tournaments/${props.tournament.slug}/knockout/generate`);
};
</script>

<template>
    <Head :title="`Nokaut - ${tournament.name}`" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4">
        <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
            <div>
                <p class="text-sm text-muted-foreground">
                    {{ venue.name }}
                </p>

                <h1 class="mt-1 text-2xl font-semibold tracking-tight">
                    Nokaut žreb
                </h1>

                <p class="mt-2 max-w-2xl text-sm text-muted-foreground">
                    Pregled učesnika koji ulaze u nokaut za turnir: {{ tournament.name }}.
                </p>
            </div>

            <div class="flex flex-col gap-2 sm:flex-row">
                <button
                    v-if="tournament.can_generate_knockout_bracket"
                    type="button"
                    class="inline-flex items-center justify-center rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground transition hover:opacity-90"
                    @click="generateKnockoutBracket"
                >
                    Generiši kostur
                </button>

                <Link
                    :href="`/venues/${venue.slug}/tournaments/${tournament.slug}/repechage`"
                    class="inline-flex items-center justify-center rounded-lg border border-sidebar-border/70 px-4 py-2 text-sm font-medium transition hover:bg-muted dark:border-sidebar-border"
                >
                    Repasaž
                </Link>

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

        <div class="grid gap-4 md:grid-cols-5">
            <div class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                <p class="text-sm text-muted-foreground">
                    Veličina nokauta
                </p>

                <p class="mt-2 text-2xl font-semibold">
                    {{ tournament.knockout_size ?? '-' }}
                </p>
            </div>

            <div class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                <p class="text-sm text-muted-foreground">
                    Učesnika za nokaut
                </p>

                <p class="mt-2 text-2xl font-semibold">
                    {{ tournament.knockout_participants_count }}
                </p>
            </div>

            <div class="rounded-xl border border-emerald-500/30 bg-emerald-500/10 p-4 text-emerald-700 dark:text-emerald-300">
                <p class="text-sm">
                    Direktno
                </p>

                <p class="mt-2 text-2xl font-semibold">
                    {{ tournament.direct_qualifiers_count }}
                </p>
            </div>

            <div class="rounded-xl border border-yellow-500/30 bg-yellow-500/10 p-4 text-yellow-700 dark:text-yellow-300">
                <p class="text-sm">
                    Iz repasaža
                </p>

                <p class="mt-2 text-2xl font-semibold">
                    {{ tournament.repechage_qualifiers_count }}
                </p>
            </div>
        </div>

        <div
            v-if="tournament.is_knockout_ready"
            class="rounded-xl border border-emerald-500/30 bg-emerald-500/10 p-4 text-emerald-700 dark:text-emerald-300"
        >
            <h2 class="text-lg font-medium">
                Nokaut je spreman
            </h2>

            <p class="mt-1 text-sm">
                Broj učesnika se poklapa sa veličinom nokauta. Sledeći korak je generisanje nokaut kostura.
            </p>

            <button
                v-if="tournament.can_generate_knockout_bracket"
                type="button"
                class="mt-4 inline-flex items-center justify-center rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground transition hover:opacity-90"
                @click="generateKnockoutBracket"
            >
                Generiši kostur
            </button>

            <Link
                v-else-if="tournament.knockout_matches_count > 0"
                :href="`/venues/${venue.slug}/tournaments/${tournament.slug}/schedule`"
                class="mt-4 inline-flex items-center justify-center rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground transition hover:opacity-90"
            >
                Otvori raspored
            </Link>
        </div>

        <div
            v-else
            class="rounded-xl border border-yellow-500/30 bg-yellow-500/10 p-4 text-yellow-700 dark:text-yellow-300"
        >
            <h2 class="text-lg font-medium">
                Nokaut još nije spreman
            </h2>

            <p class="mt-1 text-sm">
                Potrebno je da broj učesnika za nokaut bude tačno {{ tournament.knockout_size ?? '-' }}.
                Trenutno ih ima {{ tournament.knockout_participants_count }}.
            </p>
        </div>

        <div class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
            <div>
                <h2 class="text-lg font-medium">
                    Učesnici za nokaut
                </h2>

                <p class="mt-1 text-sm text-muted-foreground">
                    Lista učesnika koji ulaze u nokaut. Kostur još nije generisan.
                </p>
            </div>

            <div class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                <p class="text-sm text-muted-foreground">
                    Nokaut mečevi
                </p>

                <p class="mt-2 text-2xl font-semibold">
                    {{ tournament.knockout_matches_count }}
                </p>
            </div>

            <div
                v-if="knockout_participants.length"
                class="mt-4 overflow-hidden rounded-lg border border-sidebar-border/70 dark:border-sidebar-border"
            >
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="border-b border-sidebar-border/70 bg-muted/40 dark:border-sidebar-border">
                            <tr>
                                <th class="px-3 py-3 font-medium">Seed</th>
                                <th class="px-3 py-3 font-medium">Učesnik</th>
                                <th class="px-3 py-3 text-center font-medium">Grupa</th>
                                <th class="px-3 py-3 text-center font-medium">Izvor</th>
                                <th class="px-3 py-3 text-center font-medium">P</th>
                                <th class="px-3 py-3 text-center font-medium">+/-</th>
                                <th class="px-3 py-3 text-center font-medium">Bod</th>
                            </tr>
                        </thead>

                        <tbody>
                            <tr
                                v-for="participant in knockout_participants"
                                :key="participant.participant_id"
                                class="border-b border-sidebar-border/70 last:border-b-0 dark:border-sidebar-border"
                            >
                                <td class="px-3 py-3 text-muted-foreground">
                                    {{ participant.seed }}
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

                                <td class="px-3 py-3 text-center">
                                    <span
                                        class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium"
                                        :class="sourceBadgeClasses(participant.source)"
                                    >
                                        {{ participant.source_label }}
                                    </span>
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
                Još nema učesnika za nokaut.
            </div>
        </div>
    </div>
</template>
