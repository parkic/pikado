<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

import type { TournamentStatus } from '@/types/tournament';

defineProps<{
    status: TournamentStatus;
    canGenerateGroupMatches: boolean;
    canCompleteGroupStage: boolean;
    scheduleUrl: string;
    standingsUrl: string;
    repechageUrl: string;
    knockoutUrl: string;
}>();

const emit = defineEmits<{
    generateGroupMatches: [];
    completeGroupStage: [];
}>();
</script>

<template>
    <div
        v-if="status === 'draft'"
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
        v-else-if="status === 'group_draw'"
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
        v-else-if="status === 'ready'"
        class="rounded-xl border border-emerald-500/30 bg-emerald-500/10 p-4 text-emerald-700 dark:text-emerald-300"
    >
        <h2 class="text-lg font-medium">
            Turnir je spreman
        </h2>

        <p class="mt-1 text-sm">
            Grupe su popunjene. Sledeći korak je generisanje grupnih mečeva.
        </p>

        <button
            v-if="canGenerateGroupMatches"
            type="button"
            class="mt-4 inline-flex items-center justify-center rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground transition hover:opacity-90"
            @click="emit('generateGroupMatches')"
        >
            Generiši grupne mečeve
        </button>
    </div>

    <div
        v-else-if="status === 'group_stage'"
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
                :href="scheduleUrl"
                class="inline-flex items-center justify-center rounded-lg border border-sidebar-border/70 px-4 py-2 text-sm font-medium transition hover:bg-muted dark:border-sidebar-border"
            >
                Raspored
            </Link>

            <Link
                :href="standingsUrl"
                class="inline-flex items-center justify-center rounded-lg border border-sidebar-border/70 px-4 py-2 text-sm font-medium transition hover:bg-muted dark:border-sidebar-border"
            >
                Tabela
            </Link>

            <button
                v-if="canCompleteGroupStage"
                type="button"
                class="inline-flex items-center justify-center rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground transition hover:opacity-90"
                @click="emit('completeGroupStage')"
            >
                Završi grupnu fazu
            </button>
        </div>
    </div>

    <div
        v-else-if="status === 'repechage'"
        class="rounded-xl border border-yellow-500/30 bg-yellow-500/10 p-4 text-yellow-700 dark:text-yellow-300"
    >
        <h2 class="text-lg font-medium">
            Turnir je u fazi repasaža
        </h2>

        <p class="mt-1 text-sm">
            Grupna faza je završena. Otvori repasaž i označi učesnike koji prolaze dalje.
        </p>

        <Link
            :href="repechageUrl"
            class="mt-4 inline-flex items-center justify-center rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground transition hover:opacity-90"
        >
            Otvori repasaž
        </Link>
    </div>

    <div
        v-else-if="status === 'knockout_draw'"
        class="rounded-xl border border-emerald-500/30 bg-emerald-500/10 p-4 text-emerald-700 dark:text-emerald-300"
    >
        <h2 class="text-lg font-medium">
            Turnir je spreman za nokaut žreb
        </h2>

        <p class="mt-1 text-sm">
            Grupna faza je završena. Sledeći korak je generisanje nokaut kostura.
        </p>

        <Link
            :href="knockoutUrl"
            class="mt-4 inline-flex items-center justify-center rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground transition hover:opacity-90"
        >
            Otvori nokaut
        </Link>
    </div>

    <div
        v-else-if="status === 'knockout_stage'"
        class="rounded-xl border border-primary/30 bg-primary/5 p-4"
    >
        <h2 class="text-lg font-medium">
            Nokaut faza je u toku
        </h2>

        <p class="mt-1 text-sm text-muted-foreground">
            Nokaut kostur je generisan. Otvori raspored i unesi rezultate nokaut mečeva.
        </p>

        <Link
            :href="scheduleUrl"
            class="mt-4 inline-flex items-center justify-center rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground transition hover:opacity-90"
        >
            Otvori raspored
        </Link>
    </div>
</template>
