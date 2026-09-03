<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

defineProps<{
    isComplete: boolean;
    hasGroupSetup: boolean;
    tournamentUrl: string;
    groupsSetupUrl: string;
}>();
</script>

<template>
    <div
        v-if="isComplete"
        class="rounded-xl border border-emerald-500/30 bg-emerald-500/10 p-4 text-emerald-700 dark:text-emerald-300"
    >
        <h2 class="text-lg font-medium">Unos učesnika je završen</h2>

        <p class="mt-1 text-sm">
            Sva mesta u grupama su popunjena. Možeš da se vratiš na pregled
            turnira ili da pregledaš grupe ispod.
        </p>

        <div class="mt-4 flex flex-col gap-2 sm:flex-row">
            <Link
                :href="tournamentUrl"
                class="inline-flex items-center justify-center rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground transition hover:opacity-90"
            >
                Nazad na turnir
            </Link>

            <a
                href="#groups-preview"
                class="inline-flex items-center justify-center rounded-lg border border-emerald-500/30 px-4 py-2 text-sm font-medium transition hover:bg-emerald-500/10"
            >
                Pregled grupa
            </a>
        </div>
    </div>

    <div
        v-else-if="!hasGroupSetup"
        class="rounded-xl border border-yellow-500/30 bg-yellow-500/10 p-4 text-yellow-700 dark:text-yellow-300"
    >
        <h2 class="text-lg font-medium">Grupe nisu podešene</h2>

        <p class="mt-1 text-sm">
            Pre unosa učesnika moraš da podesiš broj grupa i broj mesta po
            grupi.
        </p>

        <Link
            :href="groupsSetupUrl"
            class="mt-4 inline-flex items-center justify-center rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground transition hover:opacity-90"
        >
            Podesi grupe
        </Link>
    </div>
</template>
