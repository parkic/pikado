<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

import PageHeader from '@/components/shared/PageHeader.vue';
import TournamentAdminNav from '@/components/tournaments/TournamentAdminNav.vue';
import TournamentScheduleMatchesTable from '@/components/tournaments/TournamentScheduleMatchesTable.vue';
import TournamentScheduleStageAction from '@/components/tournaments/TournamentScheduleStageAction.vue';
import TournamentScheduleStats from '@/components/tournaments/TournamentScheduleStats.vue';
import TournamentScheduleTieBreakerModal from '@/components/tournaments/TournamentScheduleTieBreakerModal.vue';

import { useTournamentActions } from '@/composables/useTournamentActions';
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

const routes = tournamentRoutes(props.venue.slug, props.tournament.slug);

const { completeGroupStage } = useTournamentActions({
    routes,
    nextStageAfterGroups: () => props.tournament.next_stage_after_groups,
});

const {
    resultForms,
    tieBreakerMatch,
    updateResultField,
    updateMatchResource,
    openTieBreakerModal,
    updateMatchResult,
    toggleMatchPostponement,
    closeTieBreakerModal,
    chooseTieBreakerWinner,
    saveTieBreakerWinner,
} = useTournamentScheduleMatches({
    matches: props.matches,
    matchResourceUrl: routes.scheduleMatchResource,
    matchResultUrl: routes.scheduleMatchResult,
    matchPostponementUrl: routes.scheduleMatchPostponement,
});
</script>

<template>
    <Head :title="`Raspored - ${tournament.name}`" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4">
        <PageHeader
            :eyebrow="venue.name"
            title="Raspored mečeva"
            :description="`Pregled generisanih mečeva za turnir: ${tournament.name}.`"
        />

        <TournamentAdminNav
            active="schedule"
            :routes="routes"
            :status="tournament.status"
            :repechage-enabled="tournament.repechage_enabled"
        />

        <TournamentScheduleStats
            :matches-count="tournament.matches_count"
            :finished-matches-count="tournament.finished_matches_count"
            :tournament-status="tournament.status_label"
        />

        <TournamentScheduleStageAction
            :status="tournament.status"
            :group-matches-count="tournament.group_matches_count"
            :completed-group-matches-count="
                tournament.completed_group_matches_count
            "
            :can-complete-group-stage="tournament.can_complete_group_stage"
            :next-stage-after-groups="tournament.next_stage_after_groups"
            @complete-group-stage="completeGroupStage"
        />

        <TournamentScheduleMatchesTable
            :matches="matches"
            :resources="resources"
            :result-forms="resultForms"
            @update-result-field="updateResultField"
            @update-resource="updateMatchResource"
            @update-result="updateMatchResult"
            @open-tie-breaker="openTieBreakerModal"
            @toggle-postponement="toggleMatchPostponement"
        />

        <TournamentScheduleTieBreakerModal
            v-if="tieBreakerMatch"
            :match="tieBreakerMatch"
            :result-form="resultForms[tieBreakerMatch.id]"
            @close="closeTieBreakerModal"
            @choose-winner="chooseTieBreakerWinner(tieBreakerMatch, $event)"
            @save="saveTieBreakerWinner"
        />
    </div>
</template>
