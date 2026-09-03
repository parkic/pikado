<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowRight, Plus, Search, Trash2, Trophy } from '@lucide/vue';
import { computed, ref } from 'vue';

import PageHeader from '@/components/shared/PageHeader.vue';
import { confirmAction } from '@/composables/useConfirmDialog';
import { normalizeSearchText } from '@/lib/normalizeSearchText';
import {
    tournamentRoutes,
    venueTournamentRoutes,
} from '@/lib/tournamentRoutes';
import type { TournamentListItem } from '@/types/tournament';
import type { VenueSummary } from '@/types/venue';

const props = defineProps<{
    venue: VenueSummary;
    tournaments: TournamentListItem[];
}>();

const routes = venueTournamentRoutes(props.venue.slug);
const search = ref('');
const statusFilter = ref<'all' | 'active' | 'preparation' | 'finished'>('all');

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Turniri',
                href: '#',
            },
        ],
    },
});

const activeStatuses = [
    'group_draw',
    'ready',
    'group_stage',
    'repechage',
    'knockout_draw',
    'knockout_stage',
];

const filteredTournaments = computed(() => {
    const query = normalizeSearchText(search.value);

    return props.tournaments.filter((tournament) => {
        const matchesSearch =
            query === '' ||
            normalizeSearchText(tournament.name).includes(query) ||
            normalizeSearchText(tournament.game_type_label).includes(query);

        if (!matchesSearch) {
            return false;
        }

        if (statusFilter.value === 'active') {
            return activeStatuses.includes(tournament.status);
        }

        if (statusFilter.value === 'preparation') {
            return tournament.status === 'draft';
        }

        if (statusFilter.value === 'finished') {
            return tournament.status === 'finished';
        }

        return true;
    });
});

const filterOptions = computed(() => [
    { value: 'all' as const, label: 'Svi', count: props.tournaments.length },
    {
        value: 'active' as const,
        label: 'Aktivni',
        count: props.tournaments.filter((item) =>
            activeStatuses.includes(item.status),
        ).length,
    },
    {
        value: 'preparation' as const,
        label: 'Priprema',
        count: props.tournaments.filter((item) => item.status === 'draft')
            .length,
    },
    {
        value: 'finished' as const,
        label: 'Završeni',
        count: props.tournaments.filter((item) => item.status === 'finished')
            .length,
    },
]);

const statusBadgeClasses = (status: string): string => {
    if (status === 'draft' || status === 'group_draw') {
        return 'bg-amber-500/10 text-amber-700 dark:text-amber-300';
    }

    if (status === 'finished') {
        return 'bg-muted text-muted-foreground';
    }

    return 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300';
};

const actionLabel = (status: string): string => {
    if (status === 'draft') {
        return 'Dodaj učesnike';
    }

    if (status === 'group_draw') {
        return 'Nastavi unos';
    }

    if (status === 'ready') {
        return 'Pokreni turnir';
    }

    if (status === 'group_stage') {
        return 'Unesi rezultate';
    }

    if (status === 'repechage') {
        return 'Otvori repasaž';
    }

    if (status === 'knockout_draw') {
        return 'Napravi nokaut';
    }

    if (status === 'knockout_stage') {
        return 'Nastavi nokaut';
    }

    return 'Pogledaj rezultate';
};

const actionUrl = (tournament: TournamentListItem): string => {
    const itemRoutes = tournamentRoutes(props.venue.slug, tournament.slug);

    if (['draft', 'group_draw'].includes(tournament.status)) {
        return itemRoutes.groupDraw;
    }

    if (['group_stage', 'knockout_stage'].includes(tournament.status)) {
        return itemRoutes.schedule;
    }

    if (tournament.status === 'repechage') {
        return itemRoutes.repechage;
    }

    if (tournament.status === 'knockout_draw') {
        return itemRoutes.knockout;
    }

    return itemRoutes.show;
};

const matchProgress = (tournament: TournamentListItem): number => {
    if (tournament.matches_count < 1) {
        return 0;
    }

    return Math.round(
        (tournament.finished_matches_count / tournament.matches_count) * 100,
    );
};

const deleteTournament = async (
    tournament: TournamentListItem,
): Promise<void> => {
    const confirmed = await confirmAction({
        title: 'Obriši turnir?',
        description: `Turnir „${tournament.name}” nestaće iz administracije i njegov javni link više neće raditi. Oprema lokala ostaje netaknuta.`,
        confirmLabel: 'Obriši turnir',
        variant: 'destructive',
    });

    if (!confirmed) {
        return;
    }

    router.delete(tournamentRoutes(props.venue.slug, tournament.slug).destroy, {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head :title="`Turniri - ${venue.name}`" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
        <PageHeader
            :eyebrow="venue.name"
            title="Turniri"
            description="Nastavi aktivan turnir, pripremi novi ili otvori arhivu."
        >
            <template #actions>
                <Link
                    :href="routes.create"
                    class="inline-flex items-center justify-center gap-2 rounded-xl bg-primary px-5 py-2.5 text-sm font-semibold text-primary-foreground transition hover:opacity-90"
                >
                    <Plus class="size-4" />
                    Novi turnir
                </Link>
            </template>
        </PageHeader>

        <section class="space-y-4">
            <div
                class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between"
            >
                <div class="flex gap-2 overflow-x-auto pb-1">
                    <button
                        v-for="option in filterOptions"
                        :key="option.value"
                        type="button"
                        class="inline-flex shrink-0 items-center gap-2 rounded-full border px-3 py-2 text-sm font-medium transition"
                        :class="
                            statusFilter === option.value
                                ? 'border-primary bg-primary text-primary-foreground'
                                : 'border-sidebar-border/70 hover:bg-muted dark:border-sidebar-border'
                        "
                        @click="statusFilter = option.value"
                    >
                        {{ option.label }}
                        <span
                            class="rounded-full px-1.5 py-0.5 text-[11px]"
                            :class="
                                statusFilter === option.value
                                    ? 'bg-white/15'
                                    : 'bg-muted text-muted-foreground'
                            "
                        >
                            {{ option.count }}
                        </span>
                    </button>
                </div>

                <label class="relative block lg:w-72">
                    <Search
                        class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                    />
                    <input
                        v-model="search"
                        type="search"
                        class="w-full rounded-xl border border-sidebar-border/70 bg-background py-2.5 pr-3 pl-9 text-sm transition outline-none focus:border-primary dark:border-sidebar-border"
                        placeholder="Pretraži turnire..."
                    />
                </label>
            </div>

            <div
                v-if="filteredTournaments.length"
                class="grid gap-4 xl:grid-cols-2"
            >
                <article
                    v-for="tournament in filteredTournaments"
                    :key="tournament.id"
                    class="flex flex-col rounded-2xl border border-sidebar-border/70 bg-card p-5 shadow-sm dark:border-sidebar-border"
                >
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <span
                                    class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium"
                                    :class="
                                        statusBadgeClasses(tournament.status)
                                    "
                                >
                                    {{ tournament.status_label }}
                                </span>
                                <span class="text-xs text-muted-foreground">
                                    {{ tournament.game_type_label }} ·
                                    {{ tournament.match_mode_label }}
                                </span>
                            </div>

                            <h2 class="mt-3 text-lg font-semibold">
                                {{ tournament.name }}
                            </h2>
                        </div>

                        <span class="shrink-0 text-xs text-muted-foreground">
                            {{ tournament.tournament_date ?? '-' }}
                        </span>
                    </div>

                    <div class="mt-5 grid grid-cols-3 gap-3">
                        <div class="rounded-xl bg-muted/50 p-3">
                            <p class="text-xs text-muted-foreground">
                                Učesnici
                            </p>
                            <p class="mt-1 font-semibold">
                                {{ tournament.participants_count }}
                            </p>
                        </div>
                        <div class="rounded-xl bg-muted/50 p-3">
                            <p class="text-xs text-muted-foreground">Oprema</p>
                            <p class="mt-1 font-semibold">
                                {{ tournament.resources_count }}
                            </p>
                        </div>
                        <div class="rounded-xl bg-muted/50 p-3">
                            <p class="text-xs text-muted-foreground">Nokaut</p>
                            <p class="mt-1 font-semibold">
                                {{
                                    tournament.knockout_size
                                        ? `Top ${tournament.knockout_size}`
                                        : '-'
                                }}
                            </p>
                        </div>
                    </div>

                    <div v-if="tournament.matches_count" class="mt-4">
                        <div class="flex items-center justify-between text-xs">
                            <span class="text-muted-foreground">
                                {{ tournament.finished_matches_count }} /
                                {{ tournament.matches_count }} mečeva
                            </span>
                            <span class="font-medium"
                                >{{ matchProgress(tournament) }}%</span
                            >
                        </div>
                        <div
                            class="mt-2 h-1.5 overflow-hidden rounded-full bg-muted"
                        >
                            <div
                                class="h-full rounded-full bg-primary"
                                :style="{
                                    width: `${matchProgress(tournament)}%`,
                                }"
                            />
                        </div>
                    </div>

                    <div class="mt-5 flex gap-2">
                        <Link
                            :href="actionUrl(tournament)"
                            class="inline-flex min-w-0 flex-1 items-center justify-center gap-2 rounded-xl border border-primary/30 bg-primary/5 px-4 py-2.5 text-sm font-semibold text-primary transition hover:bg-primary hover:text-primary-foreground"
                        >
                            {{ actionLabel(tournament.status) }}
                            <ArrowRight class="size-4" />
                        </Link>

                        <button
                            v-if="tournament.can_delete"
                            type="button"
                            class="inline-flex size-11 shrink-0 items-center justify-center rounded-xl border border-red-500/30 text-red-600 transition hover:bg-red-500/10 dark:text-red-400"
                            :aria-label="`Obriši turnir ${tournament.name}`"
                            :title="`Obriši turnir ${tournament.name}`"
                            @click="deleteTournament(tournament)"
                        >
                            <Trash2 class="size-4" />
                        </button>
                    </div>
                </article>
            </div>

            <div
                v-else
                class="rounded-2xl border border-dashed border-sidebar-border/70 p-8 text-center dark:border-sidebar-border"
            >
                <Trophy class="mx-auto size-8 text-muted-foreground" />
                <h2 class="mt-3 font-semibold">
                    {{
                        tournaments.length
                            ? 'Nema rezultata'
                            : 'Još nema turnira'
                    }}
                </h2>
                <p class="mt-1 text-sm text-muted-foreground">
                    {{
                        tournaments.length
                            ? 'Promeni filter ili pojam za pretragu.'
                            : 'Napravi prvi turnir za ovaj lokal.'
                    }}
                </p>
            </div>
        </section>
    </div>
</template>
