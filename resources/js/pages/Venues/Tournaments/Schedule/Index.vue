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
    matches_count: number;
    group_matches_count: number;
};

type Participant = {
    id: number;
    group_position: string | null;
    display_name: string;
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

            <Link
                :href="`/venues/${venue.slug}/tournaments/${tournament.slug}`"
                class="inline-flex items-center justify-center rounded-lg border border-sidebar-border/70 px-4 py-2 text-sm font-medium transition hover:bg-muted dark:border-sidebar-border"
            >
                Nazad na turnir
            </Link>
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
                                    {{ match.group_name ? `Grupa ${match.group_name}` : '-' }}
                                </td>

                                <td class="px-4 py-3">
                                    <div class="font-medium">
                                        <span>
                                            {{ match.participant_a?.display_name ?? 'TBD' }}
                                        </span>

                                        <span class="mx-2 text-muted-foreground">
                                            vs
                                        </span>

                                        <span>
                                            {{ match.participant_b?.display_name ?? 'TBD' }}
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
                                    {{ match.round_robin_leg ?? '-' }}
                                </td>

                                <td class="px-4 py-3 text-muted-foreground">
                                    <template v-if="match.score_a !== null && match.score_b !== null">
                                        {{ match.score_a }} : {{ match.score_b }}
                                    </template>

                                    <template v-else>
                                        -
                                    </template>
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
    </div>
</template>
