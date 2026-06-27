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
    public_code: string;
    game_type: string;
    game_type_label: string;
    match_mode: string;
    match_mode_label: string;
    status: string;
    status_label: string;
    knockout_size: number | null;
    public_enabled: boolean;
    resources_count: number;
    created_at: string | null;
};

defineProps<{
    venue: Venue;
    tournaments: Tournament[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Turniri',
                href: '#',
            },
        ],
    },
});

const statusBadgeClasses = (status: string): string => {
    if (status === 'draft') {
        return 'bg-yellow-500/10 text-yellow-700 dark:text-yellow-300';
    }

    if (status === 'finished') {
        return 'bg-muted text-muted-foreground';
    }

    return 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300';
};
</script>

<template>
    <Head :title="`Turniri - ${venue.name}`" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4">
        <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
            <div>
                <p class="text-sm text-muted-foreground">
                    {{ venue.name }}
                </p>

                <h1 class="mt-1 text-2xl font-semibold tracking-tight">
                    Turniri
                </h1>

                <p class="mt-2 max-w-2xl text-sm text-muted-foreground">
                    Lista turnira za ovaj lokal.
                </p>
            </div>

            <div class="flex flex-col gap-2 sm:flex-row">
                <Link
                    :href="`/venues/${venue.slug}/dashboard`"
                    class="inline-flex items-center justify-center rounded-lg border border-sidebar-border/70 px-4 py-2 text-sm font-medium transition hover:bg-muted dark:border-sidebar-border"
                >
                    Nazad na dashboard
                </Link>

                <Link
                    :href="`/venues/${venue.slug}/tournaments/create`"
                    class="inline-flex items-center justify-center rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground transition hover:opacity-90"
                >
                    Novi turnir
                </Link>
            </div>
        </div>

        <div class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h2 class="text-lg font-medium">
                        Turniri lokala
                    </h2>

                    <p class="mt-1 text-sm text-muted-foreground">
                        Pregled draft, aktivnih i završenih turnira.
                    </p>
                </div>
            </div>

            <div
                v-if="tournaments.length"
                class="mt-4 overflow-hidden rounded-lg border border-sidebar-border/70 dark:border-sidebar-border"
            >
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="border-b border-sidebar-border/70 bg-muted/40 dark:border-sidebar-border">
                            <tr>
                                <th class="px-4 py-3 font-medium">
                                    Naziv
                                </th>
                                <th class="px-4 py-3 font-medium">
                                    Igra
                                </th>
                                <th class="px-4 py-3 font-medium">
                                    Format
                                </th>
                                <th class="px-4 py-3 font-medium">
                                    Status
                                </th>
                                <th class="px-4 py-3 font-medium">
                                    Resources
                                </th>
                                <th class="px-4 py-3 font-medium">
                                    Nokaut
                                </th>
                                <th class="px-4 py-3 font-medium">
                                    Public
                                </th>
                                <th class="px-4 py-3 font-medium">
                                    Kreiran
                                </th>
                            </tr>
                        </thead>

                        <tbody>
                            <tr
                                v-for="tournament in tournaments"
                                :key="tournament.id"
                                class="border-b border-sidebar-border/70 last:border-b-0 dark:border-sidebar-border"
                            >
                                <td class="px-4 py-3">
                                    <div class="font-medium">
                                        {{ tournament.name }}
                                    </div>

                                    <div class="mt-1 text-xs text-muted-foreground">
                                        public code: {{ tournament.public_code }}
                                    </div>
                                </td>

                                <td class="px-4 py-3 text-muted-foreground">
                                    {{ tournament.game_type_label }}
                                </td>

                                <td class="px-4 py-3 text-muted-foreground">
                                    {{ tournament.match_mode_label }}
                                </td>

                                <td class="px-4 py-3">
                                    <span
                                        class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium"
                                        :class="statusBadgeClasses(tournament.status)"
                                    >
                                        {{ tournament.status_label }}
                                    </span>
                                </td>

                                <td class="px-4 py-3 text-muted-foreground">
                                    {{ tournament.resources_count }}
                                </td>

                                <td class="px-4 py-3 text-muted-foreground">
                                    {{ tournament.knockout_size ?? '-' }}
                                </td>

                                <td class="px-4 py-3 text-muted-foreground">
                                    {{ tournament.public_enabled ? 'Da' : 'Ne' }}
                                </td>

                                <td class="px-4 py-3 text-muted-foreground">
                                    {{ tournament.created_at ?? '-' }}
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
                Ovaj lokal još nema turnire.
            </div>
        </div>
    </div>
</template>
