<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Trash2 } from '@lucide/vue';

import type { TournamentRoutes } from '@/lib/tournamentRoutes';
import type { TournamentStatus } from '@/types/tournament';

defineProps<{
    status: TournamentStatus;
    canGenerateGroupMatches: boolean;
    canDelete: boolean;
    publicEnabled: boolean;
    tournamentsUrl: string;
    publicUrl: string;
    routes: TournamentRoutes;
}>();

const emit = defineEmits<{
    'generate-group-matches': [];
    'delete-tournament': [];
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
        v-if="status === 'draft'"
        :href="routes.groupDraw"
        class="inline-flex items-center justify-center rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground transition hover:opacity-90"
    >
        Unesi učesnike
    </Link>

    <Link
        v-else-if="status === 'group_draw'"
        :href="routes.groupDraw"
        class="inline-flex items-center justify-center rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground transition hover:opacity-90"
    >
        Nastavi unos učesnika
    </Link>

    <button
        v-else-if="status === 'ready' && canGenerateGroupMatches"
        type="button"
        class="inline-flex items-center justify-center rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground transition hover:opacity-90"
        @click="emit('generate-group-matches')"
    >
        Generiši grupne mečeve
    </button>

    <Link
        v-else-if="status === 'ready'"
        :href="routes.groupDraw"
        class="inline-flex items-center justify-center rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground transition hover:opacity-90"
    >
        Pregled učesnika
    </Link>

    <div
        v-else-if="status === 'group_stage'"
        class="flex flex-col gap-2 sm:flex-row"
    >
        <Link
            :href="routes.schedule"
            class="inline-flex items-center justify-center rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground transition hover:opacity-90"
        >
            Otvori raspored
        </Link>

        <Link
            :href="routes.groupDraw"
            class="inline-flex items-center justify-center rounded-lg border border-sidebar-border/70 px-4 py-2 text-sm font-medium transition hover:bg-muted dark:border-sidebar-border"
        >
            Učesnici
        </Link>
    </div>

    <Link
        v-else-if="status === 'repechage'"
        :href="routes.repechage"
        class="inline-flex items-center justify-center rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground transition hover:opacity-90"
    >
        Otvori repasaž
    </Link>

    <Link
        v-else-if="status === 'knockout_draw'"
        :href="routes.knockout"
        class="inline-flex items-center justify-center rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground transition hover:opacity-90"
    >
        Otvori nokaut
    </Link>

    <Link
        v-else-if="status === 'knockout_stage'"
        :href="routes.schedule"
        class="inline-flex items-center justify-center rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground transition hover:opacity-90"
    >
        Otvori raspored
    </Link>

    <Link
        v-else-if="status === 'finished'"
        :href="routes.knockout"
        class="inline-flex items-center justify-center rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground transition hover:opacity-90"
    >
        Pogledaj završnicu
    </Link>

    <Link
        v-if="publicEnabled"
        :href="publicUrl"
        target="_blank"
        class="inline-flex items-center justify-center rounded-lg border border-emerald-600/40 px-4 py-2 text-sm font-medium text-emerald-700 transition hover:bg-emerald-500/10 dark:text-emerald-300"
    >
        Javni prikaz
    </Link>

    <button
        v-if="canDelete"
        type="button"
        class="inline-flex items-center justify-center gap-2 rounded-lg border border-red-500/35 px-4 py-2 text-sm font-medium text-red-600 transition hover:bg-red-500/10 dark:text-red-400"
        @click="emit('delete-tournament')"
    >
        <Trash2 class="size-4" />
        Obriši turnir
    </button>
</template>
