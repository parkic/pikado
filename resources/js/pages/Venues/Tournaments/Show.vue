<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';

type Venue = {
    id: number;
    name: string;
    slug: string;
};

type TournamentResource = {
    id: number;
    name: string;
    type: string;
    type_label: string;
    sort_order: number;
    is_active: boolean;
};

type TournamentSettings = {
    group_count?: number | null;
    group_size?: number | null;
    direct_qualifiers_per_group?: number | null;
    repechage_enabled?: boolean;
    repechage_qualifiers_count?: number | null;
    avoid_same_group_rematch?: boolean;
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

type PodiumParticipant = {
    id: number;
    display_name: string;
    group_position: string | null;
} | null;

type TournamentPodium = {
    champion: PodiumParticipant;
    second_place: PodiumParticipant;
    third_place: PodiumParticipant;
    fourth_place: PodiumParticipant;
    final_score: string | null;
    third_place_score: string | null;
    is_complete: boolean;
};

type Tournament = {
    id: number;
    name: string;
    slug: string;
    public_code: string;
    game_type: string;
    game_type_label: string;
    match_mode: string;
    match_mode_label: string;
    status: string;
    status_label: string;
    group_rounds: string;
    knockout_size: number | null;
    public_enabled: boolean;
    scoring_mode: string;
    settings: TournamentSettings;
    created_by: string | null;
    created_at: string | null;
    groups_count: number;
    finished_group_matches_count: number;
    can_complete_group_stage: boolean;
    next_stage_after_groups: string;
    groups: TournamentGroup[];
    participants_count: number;
    total_slots: number;
    group_matches_count: number;
    can_start_group_draw: boolean;
    can_mark_ready: boolean;
    can_generate_group_matches: boolean;
    podium: TournamentPodium;
    resources: TournamentResource[];
};

const props = defineProps<{
    venue: Venue;
    tournament: Tournament;
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

const statusBadgeClasses = (status: string): string => {
    if (status === 'draft') {
        return 'bg-yellow-500/10 text-yellow-700 dark:text-yellow-300';
    }

    if (status === 'finished') {
        return 'bg-muted text-muted-foreground';
    }

    return 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300';
};

const startGroupDraw = () => {
    const confirmed = window.confirm('Da li želiš da pokreneš Group Draw za ovaj turnir?');

    if (!confirmed) {
        return;
    }

    router.post(`/venues/${props.venue.slug}/tournaments/${props.tournament.slug}/start-group-draw`);
};

const markReady = () => {
    const confirmed = window.confirm('Da li želiš da označiš turnir kao spreman?');

    if (!confirmed) {
        return;
    }

    router.post(`/venues/${props.venue.slug}/tournaments/${props.tournament.slug}/mark-ready`);
};

const generateGroupMatches = () => {
    const confirmed = window.confirm('Da li želiš da generišeš grupne mečeve?');

    if (!confirmed) {
        return;
    }

    router.post(`/venues/${props.venue.slug}/tournaments/${props.tournament.slug}/generate-group-matches`);
};

const completeGroupStage = () => {
    const nextStageLabel = props.tournament.next_stage_after_groups === 'repechage'
        ? 'repasaž'
        : 'žreb za nokaut';

    const confirmed = window.confirm(`Da li želiš da završiš grupnu fazu? Sledeći korak je: ${nextStageLabel}.`);

    if (!confirmed) {
        return;
    }

    router.post(`/venues/${props.venue.slug}/tournaments/${props.tournament.slug}/complete-group-stage`);
};

const participantForSlot = (
    group: TournamentGroup,
    slotNumber: number,
): TournamentGroupParticipant | undefined => {
    return group.participants.find((participant) => {
        return participant.group_position === `${group.name}${slotNumber}`;
    });
};
</script>

<template>
    <Head :title="`${tournament.name} - ${venue.name}`" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4">
        <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
            <div>
                <p class="text-sm text-muted-foreground">
                    {{ venue.name }}
                </p>

                <div class="mt-1 flex flex-wrap items-center gap-3">
                    <h1 class="text-2xl font-semibold tracking-tight">
                        {{ tournament.name }}
                    </h1>

                    <span
                        class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium"
                        :class="statusBadgeClasses(tournament.status)"
                    >
                        {{ tournament.status_label }}
                    </span>
                </div>

                <p class="mt-2 max-w-2xl text-sm text-muted-foreground">
                    Admin pregled draft turnira. U sledećim koracima ovde dodajemo učesnike, grupe i mečeve.
                </p>
            </div>

            <div class="flex flex-col gap-2 sm:flex-row">
                <Link
                    :href="`/venues/${venue.slug}/tournaments`"
                    class="inline-flex items-center justify-center rounded-lg border border-sidebar-border/70 px-4 py-2 text-sm font-medium transition hover:bg-muted dark:border-sidebar-border"
                >
                    Nazad na turnire
                </Link>

                <Link
                    :href="`/venues/${venue.slug}/tournaments/${tournament.slug}/groups/setup`"
                    class="inline-flex items-center justify-center rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground transition hover:opacity-90"
                >
                    Setup grupa
                </Link>

                <Link
                    :href="`/venues/${venue.slug}/tournaments/${tournament.slug}/group-draw`"
                    class="inline-flex items-center justify-center rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground transition hover:opacity-90"
                >
                    Group Draw
                </Link>

                <button
                    v-if="tournament.can_start_group_draw && tournament.status === 'draft'"
                    type="button"
                    class="inline-flex items-center justify-center rounded-lg border border-sidebar-border/70 px-4 py-2 text-sm font-medium transition hover:bg-muted dark:border-sidebar-border"
                    @click="startGroupDraw"
                >
                    Pokreni Group Draw
                </button>

                <button
                    v-if="tournament.can_mark_ready && tournament.status !== 'ready'"
                    type="button"
                    class="inline-flex items-center justify-center rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground transition hover:opacity-90"
                    @click="markReady"
                >
                    Označi kao spreman
                </button>

                <button
                    v-if="tournament.can_generate_group_matches"
                    type="button"
                    class="inline-flex items-center justify-center rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground transition hover:opacity-90"
                    @click="generateGroupMatches"
                >
                    Generiši grupne mečeve
                </button>

                <button
                    v-if="tournament.can_complete_group_stage"
                    type="button"
                    class="inline-flex items-center justify-center rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground transition hover:opacity-90"
                    @click="completeGroupStage"
                >
                    Završi grupnu fazu
                </button>

                <Link
                    :href="`/venues/${venue.slug}/tournaments/${tournament.slug}/schedule`"
                    class="inline-flex items-center justify-center rounded-lg border border-sidebar-border/70 px-4 py-2 text-sm font-medium transition hover:bg-muted dark:border-sidebar-border"
                >
                    Raspored
                </Link>

                <Link
                    v-if="tournament.public_enabled"
                    :href="`/t/${tournament.public_code}/live`"
                    target="_blank"
                    class="inline-flex items-center justify-center rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white transition hover:opacity-90"
                >
                    Otvori public
                </Link>

                <Link
                    :href="`/venues/${venue.slug}/tournaments/${tournament.slug}/qualification/setup`"
                    class="inline-flex items-center justify-center rounded-lg border border-sidebar-border/70 px-4 py-2 text-sm font-medium transition hover:bg-muted dark:border-sidebar-border"
                >
                    Prolaz
                </Link>

                <Link
                    :href="`/venues/${venue.slug}/tournaments/${tournament.slug}/repechage`"
                    class="inline-flex items-center justify-center rounded-lg border border-sidebar-border/70 px-4 py-2 text-sm font-medium transition hover:bg-muted dark:border-sidebar-border"
                >
                    Repasaž
                </Link>

                <Link
                    :href="`/venues/${venue.slug}/tournaments/${tournament.slug}/knockout`"
                    class="inline-flex items-center justify-center rounded-lg border border-sidebar-border/70 px-4 py-2 text-sm font-medium transition hover:bg-muted dark:border-sidebar-border"
                >
                    Nokaut
                </Link>

                <Link
                    :href="`/venues/${venue.slug}/tournaments/${tournament.slug}/standings`"
                    class="inline-flex items-center justify-center rounded-lg border border-sidebar-border/70 px-4 py-2 text-sm font-medium transition hover:bg-muted dark:border-sidebar-border"
                >
                    Tabela
                </Link>
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-8">
            <div class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                <p class="text-sm text-muted-foreground">
                    Igra
                </p>

                <p class="mt-2 text-lg font-medium">
                    {{ tournament.game_type_label }}
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
                    Grupe
                </p>

                <p class="mt-2 text-lg font-medium">
                    {{ tournament.groups_count }}
                </p>
            </div>

            <div class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                <p class="text-sm text-muted-foreground">
                    Učesnici
                </p>

                <p class="mt-2 text-lg font-medium">
                    {{ tournament.participants_count }} / {{ tournament.total_slots }}
                </p>
            </div>

            <div class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                <p class="text-sm text-muted-foreground">
                    Grupni mečevi
                </p>

                <p class="mt-2 text-lg font-medium">
                    {{ tournament.group_matches_count }}
                </p>
            </div>

            <div class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                <p class="text-sm text-muted-foreground">
                    Završeni grupni
                </p>

                <p class="mt-2 text-lg font-medium">
                    {{ tournament.finished_group_matches_count }} / {{ tournament.group_matches_count }}
                </p>
            </div>

            <div class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                <p class="text-sm text-muted-foreground">
                    Resources
                </p>

                <p class="mt-2 text-lg font-medium">
                    {{ tournament.resources.length }}
                </p>
            </div>

            <div class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                <p class="text-sm text-muted-foreground">
                    Public link
                </p>

                <p class="mt-2 break-all text-sm font-medium">
                    /t/{{ tournament.public_code }}/live
                </p>

                <Link
                    v-if="tournament.public_enabled"
                    :href="`/t/${tournament.public_code}/live`"
                    target="_blank"
                    class="mt-3 inline-flex items-center justify-center rounded-lg border border-sidebar-border/70 px-3 py-2 text-xs font-medium transition hover:bg-muted dark:border-sidebar-border"
                >
                    Otvori
                </Link>

                <p
                    v-else
                    class="mt-3 text-xs text-muted-foreground"
                >
                    Public prikaz je isključen.
                </p>
            </div>
        </div>

        <div
            v-if="tournament.status === 'finished'"
            class="rounded-xl border border-emerald-500/30 bg-emerald-500/10 p-5 text-emerald-900 dark:text-emerald-100"
        >
            <div class="flex flex-col gap-2 md:flex-row md:items-start md:justify-between">
                <div>
                    <p class="text-sm font-medium uppercase tracking-wide text-emerald-700 dark:text-emerald-300">
                        Finalni plasman
                    </p>

                    <h2 class="mt-1 text-2xl font-semibold">
                        Turnir je završen
                    </h2>

                    <p
                        v-if="tournament.podium.is_complete"
                        class="mt-1 text-sm text-emerald-800/80 dark:text-emerald-100/80"
                    >
                        Finale i meč za treće mesto su završeni. Ovo je konačan poredak turnira.
                    </p>

                    <p
                        v-else
                        class="mt-1 text-sm text-emerald-800/80 dark:text-emerald-100/80"
                    >
                        Turnir je označen kao završen, ali finalni plasman još nije kompletno izračunat.
                    </p>
                </div>

                <div class="text-sm md:text-right">
                    <p v-if="tournament.podium.final_score">
                        Finale: <span class="font-semibold">{{ tournament.podium.final_score }}</span>
                    </p>

                    <p v-if="tournament.podium.third_place_score">
                        Treće mesto: <span class="font-semibold">{{ tournament.podium.third_place_score }}</span>
                    </p>
                </div>
            </div>

            <div class="mt-5 grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                <div class="rounded-xl border border-emerald-500/30 bg-background/70 p-4">
                    <p class="text-sm text-muted-foreground">
                        1. mesto
                    </p>

                    <p class="mt-2 text-xl font-semibold">
                        🏆 {{ tournament.podium.champion?.display_name ?? 'Još nije poznato' }}
                    </p>

                    <p
                        v-if="tournament.podium.champion?.group_position"
                        class="mt-1 text-sm text-muted-foreground"
                    >
                        {{ tournament.podium.champion.group_position }}
                    </p>
                </div>

                <div class="rounded-xl border border-sidebar-border/70 bg-background/70 p-4 dark:border-sidebar-border">
                    <p class="text-sm text-muted-foreground">
                        2. mesto
                    </p>

                    <p class="mt-2 text-lg font-semibold">
                        {{ tournament.podium.second_place?.display_name ?? 'Još nije poznato' }}
                    </p>

                    <p
                        v-if="tournament.podium.second_place?.group_position"
                        class="mt-1 text-sm text-muted-foreground"
                    >
                        {{ tournament.podium.second_place.group_position }}
                    </p>
                </div>

                <div class="rounded-xl border border-sidebar-border/70 bg-background/70 p-4 dark:border-sidebar-border">
                    <p class="text-sm text-muted-foreground">
                        3. mesto
                    </p>

                    <p class="mt-2 text-lg font-semibold">
                        🥉 {{ tournament.podium.third_place?.display_name ?? 'Još nije poznato' }}
                    </p>

                    <p
                        v-if="tournament.podium.third_place?.group_position"
                        class="mt-1 text-sm text-muted-foreground"
                    >
                        {{ tournament.podium.third_place.group_position }}
                    </p>
                </div>

                <div class="rounded-xl border border-sidebar-border/70 bg-background/70 p-4 dark:border-sidebar-border">
                    <p class="text-sm text-muted-foreground">
                        4. mesto
                    </p>

                    <p class="mt-2 text-lg font-semibold">
                        {{ tournament.podium.fourth_place?.display_name ?? 'Još nije poznato' }}
                    </p>

                    <p
                        v-if="tournament.podium.fourth_place?.group_position"
                        class="mt-1 text-sm text-muted-foreground"
                    >
                        {{ tournament.podium.fourth_place.group_position }}
                    </p>
                </div>
            </div>
        </div>

        <div
            v-if="tournament.status === 'draft'"
            class="rounded-xl border border-yellow-500/30 bg-yellow-500/10 p-4 text-yellow-700 dark:text-yellow-300"
        >
            <h2 class="text-lg font-medium">
                Turnir je u draft statusu
            </h2>

            <p class="mt-1 text-sm">
                Podesi grupe i pokreni Group Draw kada budeš spreman za izvlačenje učesnika.
            </p>
        </div>

        <div
            v-else-if="tournament.status === 'group_draw'"
            class="rounded-xl border border-primary/30 bg-primary/5 p-4"
        >
            <h2 class="text-lg font-medium">
                Group Draw je aktivan
            </h2>

            <p class="mt-1 text-sm text-muted-foreground">
                Popuni sva mesta u grupama. Kada sva mesta budu popunjena, možeš označiti turnir kao spreman.
            </p>
        </div>

        <div
            v-else-if="tournament.status === 'ready'"
            class="rounded-xl border border-emerald-500/30 bg-emerald-500/10 p-4 text-emerald-700 dark:text-emerald-300"
        >
            <h2 class="text-lg font-medium">
                Turnir je spreman
            </h2>

            <p class="mt-1 text-sm">
                Grupe su popunjene. Sledeći korak je generisanje grupnih mečeva.
            </p>

            <button
                v-if="tournament.can_generate_group_matches"
                type="button"
                class="mt-4 inline-flex items-center justify-center rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground transition hover:opacity-90"
                @click="generateGroupMatches"
            >
                Generiši grupne mečeve
            </button>
        </div>

        <div
            v-else-if="tournament.status === 'group_stage'"
            class="rounded-xl border border-primary/30 bg-primary/5 p-4"
        >
            <h2 class="text-lg font-medium">
                Grupna faza je u toku
            </h2>

            <p class="mt-1 text-sm text-muted-foreground">
                Unesi rezultate svih grupnih mečeva. Kada svi mečevi budu završeni, možeš završiti grupnu fazu.
            </p>

            <div class="mt-4 flex flex-col gap-2 sm:flex-row">
                <Link
                    :href="`/venues/${venue.slug}/tournaments/${tournament.slug}/schedule`"
                    class="inline-flex items-center justify-center rounded-lg border border-sidebar-border/70 px-4 py-2 text-sm font-medium transition hover:bg-muted dark:border-sidebar-border"
                >
                    Raspored
                </Link>

                <Link
                    :href="`/venues/${venue.slug}/tournaments/${tournament.slug}/standings`"
                    class="inline-flex items-center justify-center rounded-lg border border-sidebar-border/70 px-4 py-2 text-sm font-medium transition hover:bg-muted dark:border-sidebar-border"
                >
                    Tabela
                </Link>

                <button
                    v-if="tournament.can_complete_group_stage"
                    type="button"
                    class="inline-flex items-center justify-center rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground transition hover:opacity-90"
                    @click="completeGroupStage"
                >
                    Završi grupnu fazu
                </button>
            </div>
        </div>

        <div
            v-else-if="tournament.status === 'repechage'"
            class="rounded-xl border border-yellow-500/30 bg-yellow-500/10 p-4 text-yellow-700 dark:text-yellow-300"
        >
            <h2 class="text-lg font-medium">
                Turnir je u fazi repasaža
            </h2>

            <p class="mt-1 text-sm">
                Grupna faza je završena. Otvori repasaž i označi učesnike koji prolaze dalje.
            </p>

            <Link
                :href="`/venues/${venue.slug}/tournaments/${tournament.slug}/repechage`"
                class="mt-4 inline-flex items-center justify-center rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground transition hover:opacity-90"
            >
                Otvori repasaž
            </Link>
        </div>

        <div
            v-else-if="tournament.status === 'knockout_draw'"
            class="rounded-xl border border-emerald-500/30 bg-emerald-500/10 p-4 text-emerald-700 dark:text-emerald-300"
        >
            <h2 class="text-lg font-medium">
                Turnir je spreman za nokaut žreb
            </h2>

            <p class="mt-1 text-sm">
                Grupna faza je završena. Sledeći korak je generisanje nokaut kostura.
            </p>

            <Link
                :href="`/venues/${venue.slug}/tournaments/${tournament.slug}/knockout`"
                class="mt-4 inline-flex items-center justify-center rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground transition hover:opacity-90"
            >
                Otvori nokaut
            </Link>
        </div>

        <div
            v-else-if="tournament.status === 'knockout_stage'"
            class="rounded-xl border border-primary/30 bg-primary/5 p-4"
        >
            <h2 class="text-lg font-medium">
                Nokaut faza je u toku
            </h2>

            <p class="mt-1 text-sm text-muted-foreground">
                Nokaut kostur je generisan. Otvori raspored i unesi rezultate nokaut mečeva.
            </p>

            <Link
                :href="`/venues/${venue.slug}/tournaments/${tournament.slug}/schedule`"
                class="mt-4 inline-flex items-center justify-center rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground transition hover:opacity-90"
            >
                Otvori raspored
            </Link>
        </div>

        <div class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
            <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                <div>
                    <h2 class="text-lg font-medium">
                        Grupe
                    </h2>

                    <p class="mt-1 text-sm text-muted-foreground">
                        Pregled grupa i slotova za učesnike. U sledećem koraku povezujemo unos učesnika sa ovim slotovima.
                    </p>
                </div>

                <Link
                    :href="`/venues/${venue.slug}/tournaments/${tournament.slug}/groups/setup`"
                    class="inline-flex items-center justify-center rounded-lg border border-sidebar-border/70 px-4 py-2 text-sm font-medium transition hover:bg-muted dark:border-sidebar-border"
                >
                    Izmeni grupe
                </Link>
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
                            {{ tournament.settings.group_size ?? 0 }} mesta
                        </span>
                    </div>

                    <div
                        v-if="tournament.settings.group_size"
                        class="mt-4 space-y-2"
                    >
                        <div
                            v-for="slotNumber in tournament.settings.group_size"
                            :key="`${group.id}-${slotNumber}`"
                            class="flex items-center justify-between gap-3 rounded-lg border border-sidebar-border/70 px-3 py-2 text-sm dark:border-sidebar-border"
                        >
                            <span class="font-medium">
                                {{ group.name }}{{ slotNumber }}
                            </span>

                            <span
                                v-if="participantForSlot(group, slotNumber)"
                                class="text-right"
                            >
                                {{ participantForSlot(group, slotNumber)?.display_name }}
                            </span>

                            <span
                                v-else
                                class="text-muted-foreground"
                            >
                                Prazno
                            </span>
                        </div>
                    </div>

                    <div
                        v-else
                        class="mt-4 rounded-lg border border-dashed border-sidebar-border/70 p-3 text-sm text-muted-foreground dark:border-sidebar-border"
                    >
                        Broj mesta po grupi još nije podešen.
                    </div>
                </div>
            </div>

            <div
                v-else
                class="mt-4 rounded-lg border border-dashed border-sidebar-border/70 p-4 text-sm text-muted-foreground dark:border-sidebar-border"
            >
                Grupe još nisu napravljene.
            </div>
        </div>

        <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
            <div class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                <div>
                    <h2 class="text-lg font-medium">
                        Resources turnira
                    </h2>

                    <p class="mt-1 text-sm text-muted-foreground">
                        Ovo su kopije resources-a za ovaj konkretan turnir.
                    </p>
                </div>

                <div
                    v-if="tournament.resources.length"
                    class="mt-4 overflow-hidden rounded-lg border border-sidebar-border/70 dark:border-sidebar-border"
                >
                    <table class="w-full text-left text-sm">
                        <thead class="border-b border-sidebar-border/70 bg-muted/40 dark:border-sidebar-border">
                            <tr>
                                <th class="px-4 py-3 font-medium">Naziv</th>
                                <th class="px-4 py-3 font-medium">Tip</th>
                                <th class="px-4 py-3 font-medium">Redosled</th>
                                <th class="px-4 py-3 font-medium">Status</th>
                            </tr>
                        </thead>

                        <tbody>
                            <tr
                                v-for="resource in tournament.resources"
                                :key="resource.id"
                                class="border-b border-sidebar-border/70 last:border-b-0 dark:border-sidebar-border"
                            >
                                <td class="px-4 py-3">
                                    {{ resource.name }}
                                </td>

                                <td class="px-4 py-3 text-muted-foreground">
                                    {{ resource.type_label }}
                                </td>

                                <td class="px-4 py-3 text-muted-foreground">
                                    {{ resource.sort_order }}
                                </td>

                                <td class="px-4 py-3 text-muted-foreground">
                                    {{ resource.is_active ? 'Aktivan' : 'Neaktivan' }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div
                    v-else
                    class="mt-4 rounded-lg border border-dashed border-sidebar-border/70 p-4 text-sm text-muted-foreground dark:border-sidebar-border"
                >
                    Ovaj turnir nema resources.
                </div>
            </div>

            <div class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                <h2 class="text-lg font-medium">
                    Podešavanja
                </h2>

                <div class="mt-4 space-y-3 text-sm">
                    <div class="flex justify-between gap-4">
                        <span class="text-muted-foreground">Status</span>
                        <span class="font-medium">{{ tournament.status_label }}</span>
                    </div>

                    <div class="flex justify-between gap-4">
                        <span class="text-muted-foreground">Grupna faza</span>
                        <span class="font-medium">
                            {{ tournament.group_rounds === 'single' ? 'Jednokružno' : 'Dvokružno' }}
                        </span>
                    </div>

                    <div class="flex justify-between gap-4">
                        <span class="text-muted-foreground">Broj grupa</span>
                        <span class="font-medium">{{ tournament.settings.group_count ?? '-' }}</span>
                    </div>

                    <div class="flex justify-between gap-4">
                        <span class="text-muted-foreground">Mesta po grupi</span>
                        <span class="font-medium">{{ tournament.settings.group_size ?? '-' }}</span>
                    </div>

                    <div class="flex justify-between gap-4">
                        <span class="text-muted-foreground">Scoring</span>
                        <span class="font-medium">{{ tournament.scoring_mode }}</span>
                    </div>

                    <div class="flex justify-between gap-4">
                        <span class="text-muted-foreground">Nokaut</span>
                        <span class="font-medium">{{ tournament.knockout_size ?? '-' }}</span>
                    </div>

                    <div class="flex justify-between gap-4">
                        <span class="text-muted-foreground">Public</span>
                        <span class="font-medium">{{ tournament.public_enabled ? 'Uključen' : 'Isključen' }}</span>
                    </div>

                    <div class="flex justify-between gap-4">
                        <span class="text-muted-foreground">Repasaž</span>
                        <span class="font-medium">{{ tournament.settings.repechage_enabled ? 'Da' : 'Ne' }}</span>
                    </div>

                    <div class="flex justify-between gap-4">
                        <span class="text-muted-foreground">Avoid same group</span>
                        <span class="font-medium">{{ tournament.settings.avoid_same_group_rematch ? 'Da' : 'Ne' }}</span>
                    </div>

                    <div class="flex justify-between gap-4">
                        <span class="text-muted-foreground">Kreirao</span>
                        <span class="font-medium">{{ tournament.created_by ?? '-' }}</span>
                    </div>

                    <div class="flex justify-between gap-4">
                        <span class="text-muted-foreground">Kreiran</span>
                        <span class="font-medium">{{ tournament.created_at ?? '-' }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
