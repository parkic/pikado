<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

type Venue = {
    id: number;
    name: string;
    slug: string;
};

type AvailablePlayer = {
    id: number;
    first_name: string;
    last_name: string;
    nickname: string | null;
    display_name: string;
};

type AvailableTeam = {
    id: number;
    name: string;
};

type TournamentGroupParticipant = {
    id: number;
    participant_type: string;
    group_position: string | null;
    status: string;
    display_name: string;
};

type TournamentGroup = {
    id: number;
    name: string;
    sort_order: number;
    participants: TournamentGroupParticipant[];
};

type NextSlot = {
    group_id: number;
    group_name: string;
    slot_number: number;
    group_position: string;
};

type TournamentSettings = {
    group_count?: number | null;
    group_size?: number | null;
};

type Tournament = {
    id: number;
    name: string;
    slug: string;
    match_mode: string;
    match_mode_label: string;
    settings: TournamentSettings;
    groups_count: number;
    participants_count: number;
    total_slots: number;
    next_slot: NextSlot | null;
    groups: TournamentGroup[];
};

const props = defineProps<{
    venue: Venue;
    tournament: Tournament;
    available_players: AvailablePlayer[];
    available_teams: AvailableTeam[];
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

const form = useForm({
    existing_player_id: null as number | null,
    existing_team_id: null as number | null,
    first_name: '',
    last_name: '',
    nickname: '',
    team_name: '',
    group_position: '',
});

const playerSearch = ref('');
const teamSearch = ref('');

const groupSize = computed(() => props.tournament.settings.group_size ?? 0);
const activeGroupPosition = computed(() => {
    return form.group_position || props.tournament.next_slot?.group_position || null;
});

const filteredAvailablePlayers = computed(() => {
    const search = playerSearch.value.trim().toLowerCase();

    if (!search) {
        return props.available_players.slice(0, 8);
    }

    return props.available_players
        .filter((player) => {
            return player.display_name.toLowerCase().includes(search);
        })
        .slice(0, 8);
});

const filteredAvailableTeams = computed(() => {
    const search = teamSearch.value.trim().toLowerCase();

    if (!search) {
        return props.available_teams.slice(0, 8);
    }

    return props.available_teams
        .filter((team) => {
            return team.name.toLowerCase().includes(search);
        })
        .slice(0, 8);
});

const selectedExistingPlayer = computed(() => {
    return props.available_players.find((player) => player.id === form.existing_player_id);
});

const selectedExistingTeam = computed(() => {
    return props.available_teams.find((team) => team.id === form.existing_team_id);
});

const participantForSlot = (
    group: TournamentGroup,
    slotNumber: number,
): TournamentGroupParticipant | undefined => {
    return group.participants.find((participant) => {
        return participant.group_position === `${group.name}${slotNumber}`;
    });
};

const selectSlot = (groupName: string, slotNumber: number) => {
    form.group_position = `${groupName}${slotNumber}`;
    form.clearErrors('slot');
};

const clearSelectedSlot = () => {
    form.group_position = '';
    form.clearErrors('slot');
};

const chooseExistingPlayer = (player: AvailablePlayer) => {
    form.existing_player_id = player.id;
    form.first_name = player.first_name;
    form.last_name = player.last_name;
    form.nickname = player.nickname ?? '';
    playerSearch.value = player.display_name;
    form.clearErrors('existing_player_id', 'first_name', 'last_name', 'nickname');
};

const clearExistingPlayer = () => {
    form.existing_player_id = null;
    form.first_name = '';
    form.last_name = '';
    form.nickname = '';
    playerSearch.value = '';
    form.clearErrors('existing_player_id', 'first_name', 'last_name', 'nickname');
};

const chooseExistingTeam = (team: AvailableTeam) => {
    form.existing_team_id = team.id;
    form.team_name = team.name;
    teamSearch.value = team.name;
    form.clearErrors('existing_team_id', 'team_name');
};

const clearExistingTeam = () => {
    form.existing_team_id = null;
    form.team_name = '';
    teamSearch.value = '';
    form.clearErrors('existing_team_id', 'team_name');
};

const submit = () => {
    form.post(`/venues/${props.venue.slug}/tournaments/${props.tournament.slug}/group-draw`, {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            form.clearErrors();
            playerSearch.value = '';
            teamSearch.value = '';
        },
    });
};

const removeParticipant = (participant: TournamentGroupParticipant | undefined) => {
    if (!participant) {
        return;
    }

    const confirmed = window.confirm(
        `Da li želiš da ukloniš ${participant.display_name} iz ${participant.group_position}?`,
    );

    if (!confirmed) {
        return;
    }

    router.delete(
        `/venues/${props.venue.slug}/tournaments/${props.tournament.slug}/group-draw/participants/${participant.id}`,
    );
};
</script>

<template>
    <Head :title="`Group Draw - ${tournament.name}`" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4">
        <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
            <div>
                <p class="text-sm text-muted-foreground">
                    {{ venue.name }}
                </p>

                <h1 class="mt-1 text-2xl font-semibold tracking-tight">
                    Group Draw
                </h1>

                <p class="mt-2 max-w-2xl text-sm text-muted-foreground">
                    Unos učesnika redom po slotovima: A1, B1, C1... pa A2, B2, C2...
                </p>
            </div>

            <div class="flex flex-col gap-2 sm:flex-row">
                <Link
                    :href="`/venues/${venue.slug}/tournaments/${tournament.slug}`"
                    class="inline-flex items-center justify-center rounded-lg border border-sidebar-border/70 px-4 py-2 text-sm font-medium transition hover:bg-muted dark:border-sidebar-border"
                >
                    Nazad na turnir
                </Link>

                <Link
                    :href="`/venues/${venue.slug}/tournaments/${tournament.slug}/groups/setup`"
                    class="inline-flex items-center justify-center rounded-lg border border-sidebar-border/70 px-4 py-2 text-sm font-medium transition hover:bg-muted dark:border-sidebar-border"
                >
                    Setup grupa
                </Link>
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-3">
            <div class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                <p class="text-sm text-muted-foreground">
                    Turnir
                </p>

                <p class="mt-2 text-lg font-medium">
                    {{ tournament.name }}
                </p>
            </div>

            <div class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                <p class="text-sm text-muted-foreground">
                    Format
                </p>

                <p class="mt-2 text-lg font-medium">
                    {{ tournament.match_mode_label }}
                </p>
            </div>

            <div class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                <p class="text-sm text-muted-foreground">
                    Popunjeno
                </p>

                <p class="mt-2 text-lg font-medium">
                    {{ tournament.participants_count }} / {{ tournament.total_slots }}
                </p>
            </div>
        </div>

        <div class="grid gap-6 xl:grid-cols-[360px_minmax(0,1fr)]">
            <form
                class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border xl:sticky xl:top-4 xl:self-start"
                @submit.prevent="submit"
            >
                <h2 class="text-lg font-medium">
                    Sledeći učesnik
                </h2>

                <div
                    v-if="activeGroupPosition"
                    class="mt-4 rounded-lg border border-primary/30 bg-primary/5 p-4"
                >
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-sm text-muted-foreground">
                                {{ form.group_position ? 'Izabrano mesto' : 'Sledeće mesto' }}
                            </p>

                            <p class="mt-1 text-3xl font-semibold tracking-tight">
                                {{ activeGroupPosition }}
                            </p>
                        </div>

                        <button
                            v-if="form.group_position"
                            type="button"
                            class="text-xs font-medium text-primary hover:underline"
                            @click="clearSelectedSlot"
                        >
                            Vrati automatski
                        </button>
                    </div>
                </div>

                <div
                    v-else
                    class="mt-4 rounded-lg border border-emerald-500/30 bg-emerald-500/10 p-4 text-sm text-emerald-700 dark:text-emerald-300"
                >
                    Sve grupe su popunjene.
                </div>

                <div
                    v-if="form.errors.slot"
                    class="mt-4 rounded-lg border border-red-500/30 bg-red-500/10 p-3 text-sm text-red-600"
                >
                    {{ form.errors.slot }}
                </div>

                <div
                    v-if="activeGroupPosition && tournament.match_mode === 'singles'"
                    class="mt-5 space-y-4"
                >

                    <div>
                        <label class="text-sm font-medium">
                            Pretraga postojećeg igrača
                        </label>

                        <input
                            v-model="playerSearch"
                            type="text"
                            class="mt-2 w-full rounded-lg border border-sidebar-border/70 bg-background px-3 py-2 text-sm outline-none transition focus:border-primary dark:border-sidebar-border"
                            placeholder="Kucaj ime, prezime ili nadimak..."
                        >

                        <div
                            v-if="playerSearch && filteredAvailablePlayers.length"
                            class="mt-2 overflow-hidden rounded-lg border border-sidebar-border/70 dark:border-sidebar-border"
                        >
                            <button
                                v-for="player in filteredAvailablePlayers"
                                :key="player.id"
                                type="button"
                                class="block w-full px-3 py-2 text-left text-sm transition hover:bg-muted"
                                @click="chooseExistingPlayer(player)"
                            >
                                {{ player.display_name }}
                            </button>
                        </div>

                        <div
                            v-if="playerSearch && !filteredAvailablePlayers.length && !selectedExistingPlayer"
                            class="mt-2 rounded-lg border border-dashed border-sidebar-border/70 p-3 text-sm text-muted-foreground dark:border-sidebar-border"
                        >
                            Nema pronađenih igrača. Nastavi ručni unos ispod i napravićemo novog igrača.
                        </div>

                        <div
                            v-if="selectedExistingPlayer"
                            class="mt-2 flex items-center justify-between gap-3 rounded-lg border border-primary/30 bg-primary/5 p-3 text-sm"
                        >
                            <span>
                                Izabran: <strong>{{ selectedExistingPlayer.display_name }}</strong>
                            </span>

                            <button
                                type="button"
                                class="text-xs font-medium text-primary hover:underline"
                                @click="clearExistingPlayer"
                            >
                                Unesi novog
                            </button>
                        </div>

                        <p
                            v-if="form.errors.existing_player_id"
                            class="mt-1 text-sm text-red-600"
                        >
                            {{ form.errors.existing_player_id }}
                        </p>
                    </div>
                    <div>
                        <label class="text-sm font-medium">
                            Ime
                        </label>

                        <input
                            v-model="form.first_name"
                            type="text"
                            class="mt-2 w-full rounded-lg border border-sidebar-border/70 bg-background px-3 py-2 text-sm outline-none transition focus:border-primary dark:border-sidebar-border"
                            placeholder="Jelena"
                        >

                        <p
                            v-if="form.errors.first_name"
                            class="mt-1 text-sm text-red-600"
                        >
                            {{ form.errors.first_name }}
                        </p>
                    </div>

                    <div>
                        <label class="text-sm font-medium">
                            Prezime
                        </label>

                        <input
                            v-model="form.last_name"
                            type="text"
                            class="mt-2 w-full rounded-lg border border-sidebar-border/70 bg-background px-3 py-2 text-sm outline-none transition focus:border-primary dark:border-sidebar-border"
                            placeholder="Mladenović"
                        >

                        <p
                            v-if="form.errors.last_name"
                            class="mt-1 text-sm text-red-600"
                        >
                            {{ form.errors.last_name }}
                        </p>
                    </div>

                    <div>
                        <label class="text-sm font-medium">
                            Nadimak
                        </label>

                        <input
                            v-model="form.nickname"
                            type="text"
                            class="mt-2 w-full rounded-lg border border-sidebar-border/70 bg-background px-3 py-2 text-sm outline-none transition focus:border-primary dark:border-sidebar-border"
                            placeholder="Jeca"
                        >

                        <p
                            v-if="form.errors.nickname"
                            class="mt-1 text-sm text-red-600"
                        >
                            {{ form.errors.nickname }}
                        </p>
                    </div>
                </div>

                <div
                    v-if="activeGroupPosition && tournament.match_mode === 'doubles'"
                    class="mt-5 space-y-4"
                >
                    <div>
                        <label class="text-sm font-medium">
                            Pretraga postojeće ekipe
                        </label>

                        <input
                            v-model="teamSearch"
                            type="text"
                            class="mt-2 w-full rounded-lg border border-sidebar-border/70 bg-background px-3 py-2 text-sm outline-none transition focus:border-primary dark:border-sidebar-border"
                            placeholder="Kucaj naziv ekipe..."
                        >

                        <div
                            v-if="teamSearch && filteredAvailableTeams.length"
                            class="mt-2 overflow-hidden rounded-lg border border-sidebar-border/70 dark:border-sidebar-border"
                        >
                            <button
                                v-for="team in filteredAvailableTeams"
                                :key="team.id"
                                type="button"
                                class="block w-full px-3 py-2 text-left text-sm transition hover:bg-muted"
                                @click="chooseExistingTeam(team)"
                            >
                                {{ team.name }}
                            </button>
                        </div>

                        <div
                            v-if="teamSearch && !filteredAvailableTeams.length && !selectedExistingTeam"
                            class="mt-2 rounded-lg border border-dashed border-sidebar-border/70 p-3 text-sm text-muted-foreground dark:border-sidebar-border"
                        >
                            Nema pronađenih ekipa. Nastavi ručni unos ispod i napravićemo novu ekipu.
                        </div>

                        <div
                            v-if="selectedExistingTeam"
                            class="mt-2 flex items-center justify-between gap-3 rounded-lg border border-primary/30 bg-primary/5 p-3 text-sm"
                        >
                            <span>
                                Izabrana ekipa: <strong>{{ selectedExistingTeam.name }}</strong>
                            </span>

                            <button
                                type="button"
                                class="text-xs font-medium text-primary hover:underline"
                                @click="clearExistingTeam"
                            >
                                Unesi novu
                            </button>
                        </div>

                        <p
                            v-if="form.errors.existing_team_id"
                            class="mt-1 text-sm text-red-600"
                        >
                            {{ form.errors.existing_team_id }}
                        </p>
                    </div>

                    <div>
                        <label class="text-sm font-medium">
                            Naziv ekipe
                        </label>

                        <input
                            v-model="form.team_name"
                            type="text"
                            class="mt-2 w-full rounded-lg border border-sidebar-border/70 bg-background px-3 py-2 text-sm outline-none transition focus:border-primary dark:border-sidebar-border"
                            placeholder="SB22"
                        >

                        <p
                            v-if="form.errors.team_name"
                            class="mt-1 text-sm text-red-600"
                        >
                            {{ form.errors.team_name }}
                        </p>
                    </div>
                </div>

                <button
                    type="submit"
                    :disabled="form.processing || !activeGroupPosition"
                    class="mt-6 inline-flex w-full items-center justify-center rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground transition hover:opacity-90 disabled:opacity-50"
                >
                    <span v-if="activeGroupPosition">
                        {{ form.processing ? 'Dodajem...' : `Dodaj u ${activeGroupPosition}` }}
                    </span>

                    <span v-else>
                        Grupe su popunjene
                    </span>
                </button>
            </form>

            <div class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                <div>
                    <h2 class="text-lg font-medium">
                        Grupe
                    </h2>

                    <p class="mt-1 text-sm text-muted-foreground">
                        TV/public prikaz ćemo kasnije povezati na isti raspored.
                    </p>
                </div>

                <div
                    v-if="tournament.groups.length"
                    class="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-4"
                >
                    <div
                        v-for="group in tournament.groups"
                        :key="group.id"
                        class="rounded-lg border border-sidebar-border/70 p-4 dark:border-sidebar-border"
                    >
                        <div class="flex items-center justify-between gap-4">
                            <h3 class="text-lg font-medium">
                                Grupa {{ group.name }}
                            </h3>

                            <span class="text-sm text-muted-foreground">
                                {{ groupSize }} mesta
                            </span>
                        </div>

                        <div class="mt-4 space-y-2">
                            <div
                                v-for="slotNumber in groupSize"
                                :key="`${group.id}-${slotNumber}`"
                                class="flex items-center justify-between gap-3 rounded-lg border border-sidebar-border/70 px-3 py-2 text-sm dark:border-sidebar-border"
                            >
                                <span class="font-medium">
                                    {{ group.name }}{{ slotNumber }}
                                </span>

                                <div
                                    v-if="participantForSlot(group, slotNumber)"
                                    class="flex items-center gap-2 text-right"
                                >
                                    <span>
                                        {{ participantForSlot(group, slotNumber)?.display_name }}
                                    </span>

                                    <Link
                                        :href="`/venues/${venue.slug}/tournaments/${tournament.slug}/group-draw/participants/${participantForSlot(group, slotNumber)?.id}/edit`"
                                        class="text-xs font-medium text-primary hover:underline"
                                    >
                                        Izmeni
                                    </Link>

                                    <button
                                        type="button"
                                        class="text-xs font-medium text-red-600 hover:underline"
                                        @click="removeParticipant(participantForSlot(group, slotNumber))"
                                    >
                                        Ukloni
                                    </button>
                                </div>

                                <button
                                    v-else
                                    type="button"
                                    class="text-xs font-medium text-primary hover:underline"
                                    @click="selectSlot(group.name, slotNumber)"
                                >
                                    Dodaj ovde
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div
                    v-else
                    class="mt-4 rounded-lg border border-dashed border-sidebar-border/70 p-4 text-sm text-muted-foreground dark:border-sidebar-border"
                >
                    Grupe još nisu napravljene. Prvo uradi setup grupa.
                </div>
            </div>
        </div>
    </div>
</template>
