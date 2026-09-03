<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';

import PageHeader from '@/components/shared/PageHeader.vue';
import TournamentAdminNav from '@/components/tournaments/TournamentAdminNav.vue';
import TournamentStandingsGroupCard from '@/components/tournaments/TournamentStandingsGroupCard.vue';

import { useTournamentStandingsActions } from '@/composables/useTournamentStandingsActions';
import { tournamentRoutes } from '@/lib/tournamentRoutes';

import type {
    TournamentStandingGroup,
    TournamentStandingsData,
} from '@/types/tournament';

import type { VenueSummary } from '@/types/venue';

const props = defineProps<{
    venue: VenueSummary;
    tournament: TournamentStandingsData;
    groups: TournamentStandingGroup[];
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

const routes = tournamentRoutes(props.venue.slug, props.tournament.slug);

const { updateQualificationOverride, withdrawParticipant, restoreParticipant } =
    useTournamentStandingsActions({
        qualificationOverrideUrl: routes.standingQualificationOverride,
        participantWithdrawUrl: routes.standingParticipantWithdraw,
        participantRestoreUrl: routes.standingParticipantRestore,
    });
</script>

<template>
    <Head :title="`Tabela grupa - ${tournament.name}`" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4">
        <PageHeader
            :eyebrow="venue.name"
            title="Tabela grupa"
            :description="`Automatski obračun plasmana po grupama za turnir: ${tournament.name}.`"
        >
            <template #actions>
                <Link
                    :href="routes.qualificationSetup"
                    class="inline-flex items-center justify-center rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground transition hover:opacity-90"
                >
                    Podešavanje prolaza
                </Link>
            </template>
        </PageHeader>

        <TournamentAdminNav
            active="standings"
            :routes="routes"
            :status="tournament.status"
            :repechage-enabled="tournament.repechage_enabled"
        />

        <div v-if="groups.length" class="grid gap-6 xl:grid-cols-2">
            <TournamentStandingsGroupCard
                v-for="group in groups"
                :key="group.id"
                :group="group"
                :can-manage-withdrawals="tournament.can_manage_withdrawals"
                :tournament-status="tournament.status"
                @update-qualification-override="updateQualificationOverride"
                @withdraw-participant="withdrawParticipant"
                @restore-participant="restoreParticipant"
            />
        </div>

        <div
            v-else
            class="rounded-xl border border-dashed border-sidebar-border/70 p-4 text-sm text-muted-foreground dark:border-sidebar-border"
        >
            Još nema grupa za prikaz.
        </div>
    </div>
</template>
