<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';

import PageHeader from '@/components/shared/PageHeader.vue';
import TournamentGroupDrawGroupsPreview from '@/components/tournaments/TournamentGroupDrawGroupsPreview.vue';
import TournamentGroupDrawPlayerFields from '@/components/tournaments/TournamentGroupDrawPlayerFields.vue';
import TournamentGroupDrawSlotPanel from '@/components/tournaments/TournamentGroupDrawSlotPanel.vue';
import TournamentGroupDrawStats from '@/components/tournaments/TournamentGroupDrawStats.vue';
import TournamentGroupDrawStatus from '@/components/tournaments/TournamentGroupDrawStatus.vue';
import TournamentGroupDrawSubmitButton from '@/components/tournaments/TournamentGroupDrawSubmitButton.vue';
import TournamentGroupDrawTeamFields from '@/components/tournaments/TournamentGroupDrawTeamFields.vue';
import TournamentGroupDrawUnavailablePanel from '@/components/tournaments/TournamentGroupDrawUnavailablePanel.vue';

import { useTournamentGroupDrawParticipantRemoval } from '@/composables/useTournamentGroupDrawParticipantRemoval';
import { useTournamentGroupDrawSearch } from '@/composables/useTournamentGroupDrawSearch';
import { useTournamentGroupDrawSlots } from '@/composables/useTournamentGroupDrawSlots';
import { tournamentRoutes } from '@/lib/tournamentRoutes';

import type {
    TournamentGroupDrawData,
    TournamentGroupDrawFormData,
    TournamentGroupDrawPlayer,
    TournamentGroupDrawTeam,
} from '@/types/tournament';
import type { VenueSummary } from '@/types/venue';

const props = defineProps<{
    venue: VenueSummary;
    tournament: TournamentGroupDrawData;
    available_players: TournamentGroupDrawPlayer[];
    available_teams: TournamentGroupDrawTeam[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Group Draw',
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
    removeParticipant,
} = useTournamentGroupDrawParticipantRemoval({
    participantDeleteUrl: routes.groupDrawParticipant,
});

const form = useForm<TournamentGroupDrawFormData>({
    existing_player_id: null,
    existing_team_id: null,
    first_name: '',
    last_name: '',
    nickname: '',
    team_name: '',
    group_position: '',
});

const {
    groupSize,
    activeGroupPosition,
    isGroupDrawComplete,
    hasGroupSetup,
    selectSlot,
    clearSelectedSlot,
} = useTournamentGroupDrawSlots({
    tournament: () => props.tournament,
    selectedGroupPosition: () => form.group_position,
    setSelectedGroupPosition: (value) => {
        form.group_position = value;
    },
    clearSlotError: () => {
        form.clearErrors('slot');
    },
});

const {
    playerSearch,
    teamSearch,
    filteredAvailablePlayers,
    filteredAvailableTeams,
    selectedExistingPlayer,
    selectedExistingTeam,
    chooseExistingPlayer,
    clearExistingPlayer,
    chooseExistingTeam,
    clearExistingTeam,
    resetSearch,
} = useTournamentGroupDrawSearch({
    availablePlayers: () => props.available_players,
    availableTeams: () => props.available_teams,
    form,
});

const submit = () => {
    form.post(routes.groupDraw, {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            form.clearErrors();
            resetSearch();
        },
    });
};
</script>

<template>
    <Head :title="`Group Draw - ${tournament.name}`" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4">
        <PageHeader
            :eyebrow="venue.name"
            title="Group Draw"
            description="Unos učesnika redom po slotovima: A1, B1, C1... pa A2, B2, C2..."
        >
            <template #actions>
                <Link
                    :href="routes.show"
                    class="inline-flex items-center justify-center rounded-lg border border-sidebar-border/70 px-4 py-2 text-sm font-medium transition hover:bg-muted dark:border-sidebar-border"
                >
                    Nazad na turnir
                </Link>

                <Link
                    :href="routes.groupsSetup"
                    class="inline-flex items-center justify-center rounded-lg border border-sidebar-border/70 px-4 py-2 text-sm font-medium transition hover:bg-muted dark:border-sidebar-border"
                >
                    Setup grupa
                </Link>
            </template>
        </PageHeader>

        <TournamentGroupDrawStats
            :tournament-name="tournament.name"
            :match-mode-label="tournament.match_mode_label"
            :participants-count="tournament.participants_count"
            :total-slots="tournament.total_slots"
        />

        <TournamentGroupDrawStatus
            :is-complete="isGroupDrawComplete"
            :has-group-setup="hasGroupSetup"
            :tournament-url="routes.show"
            :groups-setup-url="routes.groupsSetup"
        />

        <div class="grid gap-6 xl:grid-cols-[360px_minmax(0,1fr)]">
            <form
                v-if="!isGroupDrawComplete && hasGroupSetup"
                class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border xl:sticky xl:top-4 xl:self-start"
                @submit.prevent="submit"
            >
                <h2 class="text-lg font-medium">
                    Sledeći učesnik
                </h2>

                <TournamentGroupDrawSlotPanel
                    :active-group-position="activeGroupPosition"
                    :manually-selected="Boolean(form.group_position)"
                    :slot-error="form.errors.slot"
                    @clear-selected-slot="clearSelectedSlot"
                />

                <TournamentGroupDrawPlayerFields
                    v-if="
                        activeGroupPosition
                            && tournament.match_mode === 'singles'
                    "
                    :player-search="playerSearch"
                    :filtered-players="filteredAvailablePlayers"
                    :selected-player="selectedExistingPlayer"
                    :first-name="form.first_name"
                    :last-name="form.last_name"
                    :nickname="form.nickname"
                    :existing-player-id-error="form.errors.existing_player_id"
                    :first-name-error="form.errors.first_name"
                    :last-name-error="form.errors.last_name"
                    :nickname-error="form.errors.nickname"
                    @update:player-search="playerSearch = $event"
                    @update:first-name="form.first_name = $event"
                    @update:last-name="form.last_name = $event"
                    @update:nickname="form.nickname = $event"
                    @choose-player="chooseExistingPlayer"
                    @clear-player="clearExistingPlayer"
                />

                <TournamentGroupDrawTeamFields
                    v-if="
                        activeGroupPosition
                            && tournament.match_mode === 'doubles'
                    "
                    :team-search="teamSearch"
                    :filtered-teams="filteredAvailableTeams"
                    :selected-team="selectedExistingTeam"
                    :team-name="form.team_name"
                    :existing-team-id-error="form.errors.existing_team_id"
                    :team-name-error="form.errors.team_name"
                    @update:team-search="teamSearch = $event"
                    @update:team-name="form.team_name = $event"
                    @choose-team="chooseExistingTeam"
                    @clear-team="clearExistingTeam"
                />

                <TournamentGroupDrawSubmitButton
                    :processing="form.processing"
                    :active-group-position="activeGroupPosition"
                />
            </form>

            <TournamentGroupDrawUnavailablePanel
                v-else
                :is-complete="isGroupDrawComplete"
                :has-group-setup="hasGroupSetup"
                :groups-setup-url="routes.groupsSetup"
            />

            <TournamentGroupDrawGroupsPreview
                :groups="tournament.groups"
                :group-size="groupSize"
                :participant-edit-url="routes.groupDrawParticipantEdit"
                @select-slot="selectSlot"
                @remove-participant="removeParticipant"
            />
        </div>
    </div>
</template>
