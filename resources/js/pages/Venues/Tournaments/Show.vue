<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';

import PageHeader from '@/components/shared/PageHeader.vue';
import TournamentAdminNav from '@/components/tournaments/TournamentAdminNav.vue';
import TournamentGroupsPreview from '@/components/tournaments/TournamentGroupsPreview.vue';
import TournamentHeaderActions from '@/components/tournaments/TournamentHeaderActions.vue';
import TournamentPodium from '@/components/tournaments/TournamentPodium.vue';
import TournamentPublicPanel from '@/components/tournaments/TournamentPublicPanel.vue';
import TournamentResourcesTable from '@/components/tournaments/TournamentResourcesTable.vue';
import TournamentSettingsSummary from '@/components/tournaments/TournamentSettingsSummary.vue';
import TournamentStatsGrid from '@/components/tournaments/TournamentStatsGrid.vue';
import TournamentStatusBadge from '@/components/tournaments/TournamentStatusBadge.vue';
import TournamentStatusPanel from '@/components/tournaments/TournamentStatusPanel.vue';
import { confirmAction } from '@/composables/useConfirmDialog';
import { useTournamentActions } from '@/composables/useTournamentActions';
import { useTournamentPublicLinks } from '@/composables/useTournamentPublicLinks';

import {
    publicTournamentRoutes,
    tournamentRoutes,
    venueTournamentRoutes,
} from '@/lib/tournamentRoutes';
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

const routes = tournamentRoutes(props.venue.slug, props.tournament.slug);

const venueRoutes = venueTournamentRoutes(props.venue.slug);

const publicRoutes = publicTournamentRoutes(props.tournament.public_code);

const { generateGroupMatches, completeGroupStage } = useTournamentActions({
    routes,
    nextStageAfterGroups: () => props.tournament.next_stage_after_groups,
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

const deleteTournament = async (): Promise<void> => {
    const confirmed = await confirmAction({
        title: 'Obriši turnir?',
        description: `Turnir „${props.tournament.name}” nestaće iz administracije i javni link više neće raditi. Oprema lokala neće biti obrisana.`,
        confirmLabel: 'Obriši turnir',
        variant: 'destructive',
    });

    if (!confirmed) {
        return;
    }

    router.delete(routes.destroy);
};
</script>

<template>
    <Head :title="`${tournament.name} - ${venue.name}`" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4">
        <PageHeader
            :eyebrow="venue.name"
            :title="tournament.name"
            description="Sve što ti je potrebno za sledeći korak turnira."
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
                    :can-generate-group-matches="
                        tournament.can_generate_group_matches
                    "
                    :can-delete="tournament.can_delete"
                    :public-enabled="tournament.public_enabled"
                    :tournaments-url="venueRoutes.index"
                    :public-url="publicRoutes.live"
                    :routes="routes"
                    @generate-group-matches="generateGroupMatches"
                    @delete-tournament="deleteTournament"
                />
            </template>
        </PageHeader>

        <TournamentAdminNav
            active="overview"
            :routes="routes"
            :status="tournament.status"
            :repechage-enabled="tournament.settings.repechage_enabled"
        />

        <TournamentStatsGrid
            :game-type-label="tournament.game_type_label"
            :match-mode-label="tournament.match_mode_label"
            :groups-count="tournament.groups_count"
            :participants-count="tournament.participants_count"
            :total-slots="tournament.total_slots"
            :group-matches-count="tournament.group_matches_count"
            :finished-group-matches-count="
                tournament.finished_group_matches_count
            "
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
            v-if="['draft', 'group_draw', 'ready'].includes(tournament.status)"
            :groups="tournament.groups"
            :group-size="tournament.settings.group_size"
            :edit-url="routes.groupsSetup"
        />

        <details
            v-else
            class="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
        >
            <summary class="cursor-pointer list-none p-4">
                <span class="font-medium">Grupe i učesnici</span>
                <span class="mt-1 block text-sm text-muted-foreground">
                    {{ tournament.groups_count }} grupa ·
                    {{ tournament.participants_count }} učesnika
                </span>
            </summary>
            <div
                class="border-t border-sidebar-border/70 p-4 dark:border-sidebar-border"
            >
                <TournamentGroupsPreview
                    :groups="tournament.groups"
                    :group-size="tournament.settings.group_size"
                    :edit-url="routes.groupsSetup"
                    :can-edit="false"
                />
            </div>
        </details>

        <details
            class="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
        >
            <summary class="cursor-pointer list-none p-4">
                <span class="font-medium">Podešavanja i oprema</span>
                <span class="mt-1 block text-sm text-muted-foreground">
                    Pravila turnira, prolaz dalje i dostupne table.
                </span>
            </summary>

            <div
                class="grid gap-6 border-t border-sidebar-border/70 p-4 xl:grid-cols-[minmax(0,1fr)_360px] dark:border-sidebar-border"
            >
                <TournamentResourcesTable :resources="tournament.resources" />

                <TournamentSettingsSummary
                    :status-label="tournament.status_label"
                    :group-rounds="tournament.group_rounds"
                    :settings="tournament.settings"
                    :scoring-mode="tournament.scoring_mode"
                    :knockout-size="tournament.knockout_size"
                    :public-enabled="tournament.public_enabled"
                    :tournament-date="tournament.tournament_date"
                    :created-by="tournament.created_by"
                    :created-at="tournament.created_at"
                />
            </div>
        </details>
    </div>
</template>
