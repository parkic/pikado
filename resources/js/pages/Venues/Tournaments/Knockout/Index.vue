<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

import PageHeader from '@/components/shared/PageHeader.vue';
import TournamentAdminNav from '@/components/tournaments/TournamentAdminNav.vue';
import TournamentKnockoutDraw from '@/components/tournaments/TournamentKnockoutDraw.vue';
import TournamentKnockoutOverview from '@/components/tournaments/TournamentKnockoutOverview.vue';
import TournamentKnockoutParticipants from '@/components/tournaments/TournamentKnockoutParticipants.vue';
import TournamentKnockoutSeriesList from '@/components/tournaments/TournamentKnockoutSeriesList.vue';

import { useTournamentKnockoutActions } from '@/composables/useTournamentKnockoutActions';
import { tournamentRoutes } from '@/lib/tournamentRoutes';

import type {
    TournamentKnockoutData,
    TournamentKnockoutDraw as TournamentKnockoutDrawData,
    TournamentKnockoutParticipant,
    TournamentKnockoutRound,
} from '@/types/tournament';

import type { VenueSummary } from '@/types/venue';

const props = defineProps<{
    venue: VenueSummary;
    tournament: TournamentKnockoutData;
    direct_qualifiers: TournamentKnockoutParticipant[];
    repechage_qualifiers: TournamentKnockoutParticipant[];
    knockout_participants: TournamentKnockoutParticipant[];
    knockout_draw: TournamentKnockoutDrawData;
    knockout_series: TournamentKnockoutRound[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Nokaut',
                href: '#',
            },
        ],
    },
});

const routes = tournamentRoutes(props.venue.slug, props.tournament.slug);

const { generateKnockoutBracket, canApplyWalkover, applyKnockoutWalkover } =
    useTournamentKnockoutActions({
        tournamentStatus: () => props.tournament.status,
        generateBracketUrl: routes.generateKnockoutBracket,
        matchWalkoverUrl: routes.knockoutMatchWalkover,
    });
</script>

<template>
    <Head :title="`Nokaut - ${tournament.name}`" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4">
        <PageHeader
            :eyebrow="venue.name"
            title="Nokaut žreb"
            :description="`Pregled učesnika koji ulaze u nokaut za turnir: ${tournament.name}.`"
        >
            <template #actions>
                <button
                    v-if="tournament.can_generate_knockout_bracket"
                    type="button"
                    class="inline-flex items-center justify-center rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground transition hover:opacity-90"
                    @click="generateKnockoutBracket"
                >
                    Generiši kostur
                </button>
            </template>
        </PageHeader>

        <TournamentAdminNav
            active="knockout"
            :routes="routes"
            :status="tournament.status"
            :repechage-enabled="tournament.repechage_enabled"
        />

        <TournamentKnockoutOverview
            :tournament="tournament"
            :schedule-url="routes.schedule"
            @generate="generateKnockoutBracket"
        />

        <TournamentKnockoutDraw
            :draw="knockout_draw"
            :update-seeding-url="routes.updateKnockoutSeeding"
            :draw-next-url="routes.drawNextKnockoutParticipant"
            :reset-url="routes.resetKnockoutDraw"
        />

        <TournamentKnockoutSeriesList
            v-if="knockout_series.length"
            :rounds="knockout_series"
            :schedule-url="routes.schedule"
            :can-apply-walkover="canApplyWalkover"
            @apply-walkover="applyKnockoutWalkover"
        />

        <TournamentKnockoutParticipants
            :participants="knockout_participants"
            :matches-count="tournament.knockout_matches_count"
        />
    </div>
</template>
