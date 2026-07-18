<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';

import PageHeader from '@/components/shared/PageHeader.vue';

import { venueTournamentRoutes } from '@/lib/tournamentRoutes';

import type { TournamentListItem } from '@/types/tournament';
import type { VenueSummary } from '@/types/venue';


const props =defineProps<{
    venue: VenueSummary;
    tournaments: TournamentListItem[];
}>();

const routes = venueTournamentRoutes(
    props.venue.slug,
);

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
        <PageHeader
            :eyebrow="venue.name"
            title="Turniri"
            description="Lista turnira za ovaj lokal."
        >
            <template #actions>
                <Link
                    :href="routes.dashboard"
                    class="inline-flex items-center justify-center rounded-lg border border-sidebar-border/70 px-4 py-2 text-sm font-medium transition hover:bg-muted dark:border-sidebar-border"
                >
                    Nazad na dashboard
                </Link>

                <Link
                    :href="routes.create"
                    class="inline-flex items-center justify-center rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground transition hover:opacity-90"
                >
                    Novi turnir
                </Link>
            </template>
        </PageHeader>

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
                                <th class="px-4 py-3 font-medium">
                                    Akcije
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
                                <td class="px-4 py-3">
                                    <Link
                                        :href="routes.show(tournament.slug)"
                                        class="text-sm font-medium text-primary hover:underline"
                                    >
                                        Otvori
                                    </Link>
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
