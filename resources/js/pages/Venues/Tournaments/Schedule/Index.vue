<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';

import PageHeader from '@/components/shared/PageHeader.vue';
import TournamentScheduleMatchesTable from '@/components/tournaments/TournamentScheduleMatchesTable.vue';
import TournamentScheduleStats from '@/components/tournaments/TournamentScheduleStats.vue';
import TournamentScheduleTieBreakerModal from '@/components/tournaments/TournamentScheduleTieBreakerModal.vue';

import { useTournamentScheduleMatches } from '@/composables/useTournamentScheduleMatches';
import { tournamentRoutes } from '@/lib/tournamentRoutes';

import type {
    TournamentScheduleAvailableResource,
    TournamentScheduleData,
    TournamentScheduleMatch,
} from '@/types/tournament';
import type { VenueSummary } from '@/types/venue';


const props = defineProps<{
    venue: VenueSummary;
    tournament: TournamentScheduleData;
    matches: TournamentScheduleMatch[];
    resources: TournamentScheduleAvailableResource[];
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

const routes = tournamentRoutes(
    props.venue.slug,
    props.tournament.slug,
);

const {
    resultForms,
    tieBreakerMatch,
    updateResultField,
    updateMatchResource,
    openTieBreakerModal,
    updateMatchResult,
    closeTieBreakerModal,
    chooseTieBreakerWinner,
    saveTieBreakerWinner,
} = useTournamentScheduleMatches({
    matches: props.matches,
    matchResourceUrl: routes.scheduleMatchResource,
    matchResultUrl: routes.scheduleMatchResult,
});

</script>

<template>
    <Head :title="`Raspored - ${tournament.name}`" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4">
        <PageHeader
            :eyebrow="venue.name"
            title="Raspored mečeva"
            :description="`Pregled generisanih mečeva za turnir: ${tournament.name}.`"
        >
            <template #actions>
                <Link
                    :href="routes.standings"
                    class="inline-flex items-center justify-center rounded-lg border border-sidebar-border/70 px-4 py-2 text-sm font-medium transition hover:bg-muted dark:border-sidebar-border"
                >
                    Tabela
                </Link>

                <Link
                    :href="routes.show"
                    class="inline-flex items-center justify-center rounded-lg border border-sidebar-border/70 px-4 py-2 text-sm font-medium transition hover:bg-muted dark:border-sidebar-border"
                >
                    Nazad na turnir
                </Link>
            </template>
        </PageHeader>

        <TournamentScheduleStats
            :matches-count="tournament.matches_count"
            :group-matches-count="tournament.group_matches_count"
            :tournament-status="tournament.status"
        />

        <TournamentScheduleMatchesTable
            :matches="matches"
            :resources="resources"
            :result-forms="resultForms"
            @update-result-field="updateResultField"
            @update-resource="updateMatchResource"
            @update-result="updateMatchResult"
            @open-tie-breaker="openTieBreakerModal"
        />


        <TournamentScheduleTieBreakerModal
            v-if="tieBreakerMatch"
            :match="tieBreakerMatch"
            :result-form="resultForms[tieBreakerMatch.id]"
            @close="closeTieBreakerModal"
            @choose-winner="
                chooseTieBreakerWinner(
                    tieBreakerMatch,
                    $event,
                )
            "
            @save="saveTieBreakerWinner"
        />

    </div>
</template>
