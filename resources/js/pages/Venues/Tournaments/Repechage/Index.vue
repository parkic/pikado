<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';

import PageHeader from '@/components/shared/PageHeader.vue';
import TournamentRepechageOverview from '@/components/tournaments/TournamentRepechageOverview.vue';
import TournamentRepechageParticipants from '@/components/tournaments/TournamentRepechageParticipants.vue';

import { useTournamentRepechageActions } from '@/composables/useTournamentRepechageActions';
import { tournamentRoutes } from '@/lib/tournamentRoutes';

import type {
    TournamentRepechageData,
    TournamentRepechageParticipant,
} from '@/types/tournament';
import type { VenueSummary } from '@/types/venue';

const props = defineProps<{
    venue: VenueSummary;
    tournament: TournamentRepechageData;
    direct_qualifiers: TournamentRepechageParticipant[];
    repechage_participants: TournamentRepechageParticipant[];
    eliminated_participants: TournamentRepechageParticipant[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Repasaž',
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
    updateRepechageOutcome,
    completeRepechage,
} = useTournamentRepechageActions({
    participantOutcomeUrl:
        routes.repechageParticipantOutcome,
    completeRepechageUrl:
        routes.completeRepechage,
});

</script>

<template>
    <Head :title="`Repasaž - ${tournament.name}`" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4">
        <PageHeader
            :eyebrow="venue.name"
            title="Repasaž"
            :description="`Pregled učesnika posle grupne faze za turnir: ${tournament.name}.`"
        >
            <template #actions>
                <button
                    v-if="tournament.can_complete_repechage"
                    type="button"
                    class="inline-flex items-center justify-center rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground transition hover:opacity-90"
                    @click="completeRepechage"
                >
                    Završi repasaž
                </button>

                <Link
                    :href="routes.standings"
                    class="inline-flex items-center justify-center rounded-lg border border-sidebar-border/70 px-4 py-2 text-sm font-medium transition hover:bg-muted dark:border-sidebar-border"
                >
                    Tabela
                </Link>

                <Link
                    :href="routes.knockout"
                    class="inline-flex items-center justify-center rounded-lg border border-sidebar-border/70 px-4 py-2 text-sm font-medium transition hover:bg-muted dark:border-sidebar-border"
                >
                    Nokaut
                </Link>

                <Link
                    :href="routes.show"
                    class="inline-flex items-center justify-center rounded-lg border border-sidebar-border/70 px-4 py-2 text-sm font-medium transition hover:bg-muted dark:border-sidebar-border"
                >
                    Nazad na turnir
                </Link>
            </template>
        </PageHeader>

        <TournamentRepechageOverview
            :tournament="tournament"
            :direct-qualifiers-count="direct_qualifiers.length"
            :repechage-participants-count="
                repechage_participants.length
            "
            @complete="completeRepechage"
        />

        <TournamentRepechageParticipants
            :direct-qualifiers="direct_qualifiers"
            :repechage-participants="repechage_participants"
            :eliminated-participants="eliminated_participants"
            @update-outcome="updateRepechageOutcome"
        />

    </div>
</template>
