<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowRight,
    CircleDot,
    ExternalLink,
    Plus,
    Trophy,
    Users,
    UsersRound,
} from '@lucide/vue';

type VenueResource = {
    id: number;
    name: string;
    type: string;
    sort_order: number;
};

type Venue = {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    instagram_url: string | null;
    global_players_count: number;
    teams_count: number;
    resources: VenueResource[];
};

type TournamentSummary = {
    id: number;
    name: string;
    slug: string;
    public_code: string;
    public_enabled: boolean;
    game_type_label: string;
    match_mode_label: string;
    status: string;
    status_label: string;
    participants_count: number;
    matches_count: number;
    finished_matches_count: number;
    created_at: string | null;
    finished_at: string | null;
    action_url: string;
    action_label: string;
    public_url: string;
};

const props = defineProps<{
    venue: Venue;
    activeTournaments: TournamentSummary[];
    recentTournaments: TournamentSummary[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Početna',
                href: '#',
            },
        ],
    },
});

const tournamentProgress = (tournament: TournamentSummary): number => {
    if (tournament.matches_count < 1) {
        return 0;
    }

    return Math.round(
        (tournament.finished_matches_count / tournament.matches_count) * 100,
    );
};

const statusClasses = (status: string): string => {
    if (status === 'draft' || status === 'group_draw') {
        return 'bg-amber-500/10 text-amber-700 dark:text-amber-300';
    }

    if (status === 'finished') {
        return 'bg-muted text-muted-foreground';
    }

    return 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300';
};

const venueBase = `/venues/${props.venue.slug}`;
</script>

<template>
    <Head :title="venue.name" />

    <div class="flex h-full flex-1 flex-col gap-7 p-4 md:p-6">
        <section
            class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between"
        >
            <div>
                <p class="text-sm font-medium text-primary">Kontrolni centar</p>
                <h1 class="mt-1 text-3xl font-semibold tracking-tight">
                    {{ venue.name }}
                </h1>
                <p
                    v-if="venue.description"
                    class="mt-2 max-w-2xl text-sm text-muted-foreground"
                >
                    {{ venue.description }}
                </p>
            </div>

            <Link
                :href="`${venueBase}/tournaments/create`"
                class="inline-flex items-center justify-center gap-2 rounded-xl bg-primary px-5 py-3 text-sm font-semibold text-primary-foreground shadow-sm transition hover:opacity-90"
            >
                <Plus class="size-4" />
                Novi turnir
            </Link>
        </section>

        <section>
            <div class="mb-3 flex items-center justify-between gap-4">
                <div>
                    <h2 class="text-lg font-semibold">Aktivni turniri</h2>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Nastavi tamo gde si stao.
                    </p>
                </div>

                <Link
                    :href="`${venueBase}/tournaments`"
                    class="hidden items-center gap-1 text-sm font-medium text-primary hover:underline sm:inline-flex"
                >
                    Svi turniri
                    <ArrowRight class="size-4" />
                </Link>
            </div>

            <div
                v-if="activeTournaments.length"
                class="grid gap-4 xl:grid-cols-2"
            >
                <article
                    v-for="tournament in activeTournaments"
                    :key="tournament.id"
                    class="rounded-2xl border border-sidebar-border/70 bg-card p-5 shadow-sm dark:border-sidebar-border"
                >
                    <div
                        class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
                    >
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <span
                                    class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium"
                                    :class="statusClasses(tournament.status)"
                                >
                                    {{ tournament.status_label }}
                                </span>
                                <span class="text-xs text-muted-foreground">
                                    {{ tournament.game_type_label }} ·
                                    {{ tournament.match_mode_label }}
                                </span>
                            </div>

                            <h3 class="mt-3 truncate text-xl font-semibold">
                                {{ tournament.name }}
                            </h3>
                        </div>

                        <span class="text-sm text-muted-foreground">
                            {{ tournament.participants_count }} učesnika
                        </span>
                    </div>

                    <div v-if="tournament.matches_count" class="mt-5">
                        <div class="flex items-center justify-between text-xs">
                            <span class="text-muted-foreground">
                                {{ tournament.finished_matches_count }} /
                                {{ tournament.matches_count }} mečeva
                            </span>
                            <span class="font-medium">
                                {{ tournamentProgress(tournament) }}%
                            </span>
                        </div>
                        <div
                            class="mt-2 h-2 overflow-hidden rounded-full bg-muted"
                        >
                            <div
                                class="h-full rounded-full bg-primary transition-all"
                                :style="{
                                    width: `${tournamentProgress(tournament)}%`,
                                }"
                            />
                        </div>
                    </div>

                    <div class="mt-5 flex flex-col gap-2 sm:flex-row">
                        <Link
                            :href="tournament.action_url"
                            class="inline-flex flex-1 items-center justify-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-sm font-semibold text-primary-foreground transition hover:opacity-90"
                        >
                            {{ tournament.action_label }}
                            <ArrowRight class="size-4" />
                        </Link>

                        <a
                            v-if="tournament.public_enabled"
                            :href="tournament.public_url"
                            target="_blank"
                            class="inline-flex items-center justify-center gap-2 rounded-xl border border-sidebar-border/70 px-4 py-2.5 text-sm font-medium transition hover:bg-muted dark:border-sidebar-border"
                        >
                            Javno
                            <ExternalLink class="size-4" />
                        </a>
                    </div>
                </article>
            </div>

            <div
                v-else
                class="rounded-2xl border border-dashed border-sidebar-border/70 p-7 text-center dark:border-sidebar-border"
            >
                <Trophy class="mx-auto size-8 text-muted-foreground" />
                <h3 class="mt-3 font-semibold">Nema aktivnog turnira</h3>
                <p class="mt-1 text-sm text-muted-foreground">
                    Napravi turnir i odmah počni sa unosom učesnika.
                </p>
                <Link
                    :href="`${venueBase}/tournaments/create`"
                    class="mt-4 inline-flex items-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-sm font-semibold text-primary-foreground"
                >
                    <Plus class="size-4" />
                    Novi turnir
                </Link>
            </div>
        </section>

        <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <Link
                href="/admin/players"
                class="group rounded-2xl border border-sidebar-border/70 p-4 transition hover:border-primary/40 hover:bg-muted/40 dark:border-sidebar-border"
            >
                <Users class="size-5 text-primary" />
                <div class="mt-4 flex items-end justify-between gap-4">
                    <div>
                        <p class="font-semibold">Imenik igrača</p>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Globalno u aplikaciji
                        </p>
                    </div>
                    <span class="text-2xl font-semibold">{{
                        venue.global_players_count
                    }}</span>
                </div>
            </Link>

            <Link
                :href="`${venueBase}/teams`"
                class="group rounded-2xl border border-sidebar-border/70 p-4 transition hover:border-primary/40 hover:bg-muted/40 dark:border-sidebar-border"
            >
                <UsersRound class="size-5 text-primary" />
                <div class="mt-4 flex items-end justify-between gap-4">
                    <div>
                        <p class="font-semibold">Timovi</p>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Ekipni formati
                        </p>
                    </div>
                    <span class="text-2xl font-semibold">{{
                        venue.teams_count
                    }}</span>
                </div>
            </Link>

            <Link
                :href="`${venueBase}/resources`"
                class="group rounded-2xl border border-sidebar-border/70 p-4 transition hover:border-primary/40 hover:bg-muted/40 dark:border-sidebar-border"
            >
                <CircleDot class="size-5 text-primary" />
                <div class="mt-4 flex items-end justify-between gap-4">
                    <div>
                        <p class="font-semibold">Oprema</p>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Table i stolovi za igru
                        </p>
                    </div>
                    <span class="text-2xl font-semibold">{{
                        venue.resources.length
                    }}</span>
                </div>
            </Link>

            <Link
                :href="`${venueBase}/tournaments`"
                class="group rounded-2xl border border-sidebar-border/70 p-4 transition hover:border-primary/40 hover:bg-muted/40 dark:border-sidebar-border"
            >
                <Trophy class="size-5 text-primary" />
                <div class="mt-4">
                    <p class="font-semibold">Svi turniri</p>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Aktivni, priprema i arhiva
                    </p>
                </div>
            </Link>
        </section>

        <section v-if="recentTournaments.length">
            <div class="mb-3">
                <h2 class="text-lg font-semibold">Nedavno završeni</h2>
                <p class="mt-1 text-sm text-muted-foreground">
                    Brz pristup rezultatima i arhivi.
                </p>
            </div>

            <div
                class="overflow-hidden rounded-2xl border border-sidebar-border/70 dark:border-sidebar-border"
            >
                <Link
                    v-for="tournament in recentTournaments"
                    :key="tournament.id"
                    :href="tournament.action_url"
                    class="flex items-center justify-between gap-4 border-b border-sidebar-border/70 px-4 py-4 transition last:border-b-0 hover:bg-muted/40 dark:border-sidebar-border"
                >
                    <div class="min-w-0">
                        <p class="truncate font-medium">
                            {{ tournament.name }}
                        </p>
                        <p class="mt-1 text-xs text-muted-foreground">
                            {{ tournament.game_type_label }} ·
                            {{ tournament.match_mode_label }} ·
                            {{
                                tournament.finished_at ?? tournament.created_at
                            }}
                        </p>
                    </div>
                    <ArrowRight class="size-4 shrink-0 text-muted-foreground" />
                </Link>
            </div>
        </section>
    </div>
</template>
