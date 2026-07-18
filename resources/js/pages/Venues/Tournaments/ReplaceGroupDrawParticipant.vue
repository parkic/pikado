<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';

import PageHeader from '@/components/shared/PageHeader.vue';
import TournamentGroupDrawPlayerFields from '@/components/tournaments/TournamentGroupDrawPlayerFields.vue';
import TournamentGroupDrawTeamFields from '@/components/tournaments/TournamentGroupDrawTeamFields.vue';

import { useTournamentGroupDrawSearch } from '@/composables/useTournamentGroupDrawSearch';
import { tournamentRoutes } from '@/lib/tournamentRoutes';

import type {
    TournamentEditGroupDrawData,
    TournamentGroupDrawFormData,
    TournamentGroupDrawPlayer,
    TournamentGroupDrawTeam,
    TournamentReplaceGroupDrawParticipant,
} from '@/types/tournament';
import type { VenueSummary } from '@/types/venue';

const props = defineProps<{
    venue: VenueSummary;
    tournament: TournamentEditGroupDrawData;
    participant: TournamentReplaceGroupDrawParticipant;
    available_players: TournamentGroupDrawPlayer[];
    available_teams: TournamentGroupDrawTeam[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Zamena učesnika',
                href: '#',
            },
        ],
    },
});

const routes = tournamentRoutes(props.venue.slug, props.tournament.slug);

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
} = useTournamentGroupDrawSearch({
    availablePlayers: () => props.available_players,
    availableTeams: () => props.available_teams,
    form,
});

const submit = () => {
    form.put(routes.groupDrawParticipantReplace(props.participant.id));
};
</script>

<template>
    <Head :title="`Zamena učesnika - ${tournament.name}`" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4">
        <PageHeader
            :eyebrow="venue.name"
            title="Zameni učesnika"
            :description="`Novi učesnik preuzima slot ${participant.group_position ?? '-'} i njegove postojeće mečeve u rasporedu.`"
        >
            <template #actions>
                <Link
                    :href="routes.groupDraw"
                    class="inline-flex items-center justify-center rounded-lg border border-sidebar-border/70 px-4 py-2 text-sm font-medium transition hover:bg-muted dark:border-sidebar-border"
                >
                    Nazad na unos učesnika
                </Link>
            </template>
        </PageHeader>

        <form
            class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_360px]"
            @submit.prevent="submit"
        >
            <div class="flex flex-col gap-6">
                <div
                    class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
                >
                    <h2 class="text-lg font-medium">Novi učesnik</h2>

                    <p class="mt-1 text-sm text-muted-foreground">
                        Izaberi postojećeg učesnika lokala ili unesi podatke za
                        novog. Stari profil neće biti obrisan niti preimenovan.
                    </p>

                    <div
                        v-if="form.errors.participant"
                        class="mt-4 rounded-lg border border-red-500/30 bg-red-500/10 p-3 text-sm text-red-700 dark:text-red-300"
                    >
                        {{ form.errors.participant }}
                    </div>

                    <div class="mt-5">
                        <TournamentGroupDrawPlayerFields
                            v-if="tournament.match_mode === 'singles'"
                            :player-search="playerSearch"
                            :filtered-players="filteredAvailablePlayers"
                            :selected-player="selectedExistingPlayer"
                            :first-name="form.first_name"
                            :last-name="form.last_name"
                            :nickname="form.nickname"
                            :existing-player-id-error="
                                form.errors.existing_player_id
                            "
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
                            v-else
                            :team-search="teamSearch"
                            :filtered-teams="filteredAvailableTeams"
                            :selected-team="selectedExistingTeam"
                            :team-name="form.team_name"
                            :existing-team-id-error="
                                form.errors.existing_team_id
                            "
                            :team-name-error="form.errors.team_name"
                            @update:team-search="teamSearch = $event"
                            @update:team-name="form.team_name = $event"
                            @choose-team="chooseExistingTeam"
                            @clear-team="clearExistingTeam"
                        />
                    </div>
                </div>

                <div
                    class="rounded-xl border border-yellow-500/30 bg-yellow-500/10 p-4 text-sm text-yellow-800 dark:text-yellow-200"
                >
                    <h2 class="font-medium">Šta se dešava sa rasporedom?</h2>

                    <p class="mt-1">
                        Novi učesnik preuzima isti turnirski slot i isti
                        Tournament Participant zapis. Postojeći redosled mečeva
                        ostaje nepromenjen.
                    </p>

                    <p class="mt-2">
                        Zamena nije dozvoljena kada je stari učesnik već započeo
                        ili završio grupni meč.
                    </p>
                </div>
            </div>

            <div
                class="rounded-xl border border-sidebar-border/70 p-4 xl:sticky xl:top-4 xl:self-start dark:border-sidebar-border"
            >
                <h2 class="text-lg font-medium">Pregled zamene</h2>

                <div class="mt-4 space-y-3 text-sm">
                    <div class="flex justify-between gap-4">
                        <span class="text-muted-foreground"> Turnir </span>

                        <span class="text-right font-medium">
                            {{ tournament.name }}
                        </span>
                    </div>

                    <div class="flex justify-between gap-4">
                        <span class="text-muted-foreground"> Slot </span>

                        <span class="font-medium">
                            {{ participant.group_position ?? '-' }}
                        </span>
                    </div>

                    <div class="flex justify-between gap-4">
                        <span class="text-muted-foreground">
                            Trenutni učesnik
                        </span>

                        <span class="text-right font-medium">
                            {{ participant.display_name }}
                        </span>
                    </div>

                    <div class="flex justify-between gap-4">
                        <span class="text-muted-foreground"> Tip </span>

                        <span class="font-medium">
                            {{
                                tournament.match_mode === 'singles'
                                    ? 'Igrač'
                                    : 'Ekipa'
                            }}
                        </span>
                    </div>

                    <div class="flex justify-between gap-4">
                        <span class="text-muted-foreground"> Status </span>

                        <span class="font-medium">
                            {{ participant.status }}
                        </span>
                    </div>
                </div>

                <button
                    type="submit"
                    :disabled="form.processing"
                    class="mt-6 inline-flex w-full items-center justify-center rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground transition hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-50"
                >
                    {{ form.processing ? 'Menjam...' : 'Potvrdi zamenu' }}
                </button>
            </div>
        </form>
    </div>
</template>
