<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

import PageHeader from '@/components/shared/PageHeader.vue';
import TournamentGroupsPreview from '@/components/tournaments/TournamentGroupsPreview.vue';
import TournamentHeaderActions from '@/components/tournaments/TournamentHeaderActions.vue';
import TournamentPodium from '@/components/tournaments/TournamentPodium.vue';
import TournamentPublicPanel from '@/components/tournaments/TournamentPublicPanel.vue';
import TournamentResourcesTable from '@/components/tournaments/TournamentResourcesTable.vue';
import TournamentSettingsSummary from '@/components/tournaments/TournamentSettingsSummary.vue';
import TournamentStatsGrid from '@/components/tournaments/TournamentStatsGrid.vue';
import TournamentStatusBadge from '@/components/tournaments/TournamentStatusBadge.vue';
import TournamentStatusPanel from '@/components/tournaments/TournamentStatusPanel.vue';
import { useTournamentActions } from '@/composables/useTournamentActions';
import { useTournamentPublicLinks } from '@/composables/useTournamentPublicLinks';

import { publicTournamentRoutes, tournamentRoutes, venueTournamentRoutes, } from '@/lib/tournamentRoutes';
import type { TournamentShowData } from '@/types/tournament';
import type { VenueSummary } from '@/types/venue';

const props = defineProps<{
    venue: VenueSummary;
    tournament: TournamentShowData;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Turnir',
                href: '#',
            },
        ],
    },
});

const routes = tournamentRoutes(
    props.venue.slug,
    props.tournament.slug,
);

const venueRoutes = venueTournamentRoutes(props.venue.slug);

const publicRoutes = publicTournamentRoutes(
    props.tournament.public_code,
);

const {
    generateGroupMatches,
    completeGroupStage,
} = useTournamentActions({
    routes,
    nextStageAfterGroups: () =>
        props.tournament.next_stage_after_groups,
});

const {
    copiedPublicLink,
    publicLiveUrl,
    publicQrCodeDataUrl,
    publicLinks,
    copyPublicLink,
} = useTournamentPublicLinks({
    publicCode: () => props.tournament.public_code,
    publicEnabled: () => props.tournament.public_enabled,
});
</script>

<template>
    <Head :title="`${tournament.name} - ${venue.name}`" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4">
        <PageHeader
            :eyebrow="venue.name"
            :title="tournament.name"
            description="Admin pregled turnira, trenutne faze, grupa, mečeva i rezultata."
        >
            <template #after-title>
                <TournamentStatusBadge
                    :status="tournament.status"
                    :label="tournament.status_label"
                />
            </template>

            <template #actions>
                <TournamentHeaderActions
                    :status="tournament.status"
                    :can-generate-group-matches="tournament.can_generate_group_matches"
                    :public-enabled="tournament.public_enabled"
                    :tournaments-url="venueRoutes.index"
                    :public-url="publicRoutes.live"
                    :routes="routes"
                    @generate-group-matches="generateGroupMatches"
                />
            </template>
        </PageHeader>

        <TournamentStatsGrid
            :game-type-label="tournament.game_type_label"
            :match-mode-label="tournament.match_mode_label"
            :groups-count="tournament.groups_count"
            :participants-count="tournament.participants_count"
            :total-slots="tournament.total_slots"
            :group-matches-count="tournament.group_matches_count"
            :finished-group-matches-count="tournament.finished_group_matches_count"
            :resources-count="tournament.resources.length"
        />

        <TournamentPublicPanel
            :public-enabled="tournament.public_enabled"
            :public-qr-code-data-url="publicQrCodeDataUrl"
            :public-live-url="publicLiveUrl"
            :public-links="publicLinks"
            :copied-public-link="copiedPublicLink"
            :tournament-slug="tournament.slug"
            @copy-link="copyPublicLink"
        />

        <TournamentPodium
            v-if="tournament.status === 'finished'"
            :podium="tournament.podium"
        />

        <TournamentStatusPanel
            :status="tournament.status"
            :can-generate-group-matches="tournament.can_generate_group_matches"
            :can-complete-group-stage="tournament.can_complete_group_stage"
            :group-draw-url="routes.groupDraw"
            :schedule-url="routes.schedule"
            :standings-url="routes.standings"
            :repechage-url="routes.repechage"
            :knockout-url="routes.knockout"
            @generate-group-matches="generateGroupMatches"
            @complete-group-stage="completeGroupStage"
        />

        <TournamentGroupsPreview
            :groups="tournament.groups"
            :group-size="tournament.settings.group_size"
            :edit-url="routes.groupsSetup"
        />

        <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
            <TournamentResourcesTable
                :resources="tournament.resources"
            />

            <TournamentSettingsSummary
                :status-label="tournament.status_label"
                :group-rounds="tournament.group_rounds"
                :settings="tournament.settings"
                :scoring-mode="tournament.scoring_mode"
                :knockout-size="tournament.knockout_size"
                :public-enabled="tournament.public_enabled"
                :created-by="tournament.created_by"
                :created-at="tournament.created_at"
            />
        </div>
    </div>
</template>
