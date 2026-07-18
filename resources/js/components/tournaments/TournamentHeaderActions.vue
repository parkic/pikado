<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

import type { TournamentRoutes } from '@/lib/tournamentRoutes';
import type { TournamentStatus } from '@/types/tournament';

defineProps<{
    status: TournamentStatus;
    canStartGroupDraw: boolean;
    canMarkReady: boolean;
    canGenerateGroupMatches: boolean;
    canCompleteGroupStage: boolean;
    publicEnabled: boolean;
    tournamentsUrl: string;
    publicUrl: string;
    routes: TournamentRoutes;
}>();

const emit = defineEmits<{
    'start-group-draw': [];
    'mark-ready': [];
    'generate-group-matches': [];
    'complete-group-stage': [];
}>();
</script>

<template>
    <Link
        :href="tournamentsUrl"
        class="inline-flex items-center justify-center rounded-lg border border-sidebar-border/70 px-4 py-2 text-sm font-medium transition hover:bg-muted dark:border-sidebar-border"
    >
        Nazad na turnire
    </Link>

    <Link
        :href="routes.groupsSetup"
        class="inline-flex items-center justify-center rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground transition hover:opacity-90"
    >
        Setup grupa
    </Link>

    <Link
        :href="routes.groupDraw"
        class="inline-flex items-center justify-center rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground transition hover:opacity-90"
    >
        Group Draw
    </Link>

    <button
        v-if="canStartGroupDraw && status === 'draft'"
        type="button"
        class="inline-flex items-center justify-center rounded-lg border border-sidebar-border/70 px-4 py-2 text-sm font-medium transition hover:bg-muted dark:border-sidebar-border"
        @click="emit('start-group-draw')"
    >
        Pokreni Group Draw
    </button>

    <button
        v-if="canMarkReady && status !== 'ready'"
        type="button"
        class="inline-flex items-center justify-center rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground transition hover:opacity-90"
        @click="emit('mark-ready')"
    >
        Označi kao spreman
    </button>

    <button
        v-if="canGenerateGroupMatches"
        type="button"
        class="inline-flex items-center justify-center rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground transition hover:opacity-90"
        @click="emit('generate-group-matches')"
    >
        Generiši grupne mečeve
    </button>

    <button
        v-if="canCompleteGroupStage"
        type="button"
        class="inline-flex items-center justify-center rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground transition hover:opacity-90"
        @click="emit('complete-group-stage')"
    >
        Završi grupnu fazu
    </button>

    <Link
        :href="routes.schedule"
        class="inline-flex items-center justify-center rounded-lg border border-sidebar-border/70 px-4 py-2 text-sm font-medium transition hover:bg-muted dark:border-sidebar-border"
    >
        Raspored
    </Link>

    <Link
        v-if="publicEnabled"
        :href="publicUrl"
        target="_blank"
        class="inline-flex items-center justify-center rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white transition hover:opacity-90"
    >
        Otvori public
    </Link>

    <Link
        :href="routes.qualificationSetup"
        class="inline-flex items-center justify-center rounded-lg border border-sidebar-border/70 px-4 py-2 text-sm font-medium transition hover:bg-muted dark:border-sidebar-border"
    >
        Prolaz
    </Link>

    <Link
        :href="routes.repechage"
        class="inline-flex items-center justify-center rounded-lg border border-sidebar-border/70 px-4 py-2 text-sm font-medium transition hover:bg-muted dark:border-sidebar-border"
    >
        Repasaž
    </Link>

    <Link
        :href="routes.knockout"
        class="inline-flex items-center justify-center rounded-lg border border-sidebar-border/70 px-4 py-2 text-sm font-medium transition hover:bg-muted dark:border-sidebar-border"
    >
        Nokaut
    </Link>

    <Link
        :href="routes.standings"
        class="inline-flex items-center justify-center rounded-lg border border-sidebar-border/70 px-4 py-2 text-sm font-medium transition hover:bg-muted dark:border-sidebar-border"
    >
        Tabela
    </Link>
</template>
