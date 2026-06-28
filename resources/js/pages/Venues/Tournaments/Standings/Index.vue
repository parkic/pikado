<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';

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
    position: number;
};

type StandingGroup = {
    id: number;
    name: string;
    matches_count: number;
    finished_matches_count: number;
    rows: StandingRow[];
};

defineProps<{
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
                                </tr>
                            </thead>

                            <tbody>
                                <tr
                                    v-for="row in group.rows"
                                    :key="row.participant_id"
                                    class="border-b border-sidebar-border/70 last:border-b-0 dark:border-sidebar-border"
                                >
                                    <td class="px-3 py-3 text-muted-foreground">
                                        {{ row.position }}
                                    </td>

                                    <td class="px-3 py-3">
                                        <div class="font-medium">
                                            {{ row.display_name }}
                                        </div>

                                        <div class="mt-1 text-xs text-muted-foreground">
                                            {{ row.group_position ?? '-' }}
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
