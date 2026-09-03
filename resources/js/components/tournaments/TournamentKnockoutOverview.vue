<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

import type { TournamentKnockoutData } from '@/types/tournament';

defineProps<{
    tournament: TournamentKnockoutData;
    scheduleUrl: string;
}>();

const emit = defineEmits<{
    generate: [];
}>();
</script>

<template>
    <div class="space-y-6">
        <div class="grid gap-4 md:grid-cols-5">
            <div
                class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
            >
                <p class="text-sm text-muted-foreground">Veličina nokauta</p>

                <p class="mt-2 text-2xl font-semibold">
                    {{ tournament.knockout_size ?? '-' }}
                </p>
            </div>

            <div
                class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
            >
                <p class="text-sm text-muted-foreground">Učesnika za nokaut</p>

                <p class="mt-2 text-2xl font-semibold">
                    {{ tournament.knockout_participants_count }}
                </p>
            </div>

            <div
                class="rounded-xl border border-emerald-500/30 bg-emerald-500/10 p-4 text-emerald-700 dark:text-emerald-300"
            >
                <p class="text-sm">Direktno</p>

                <p class="mt-2 text-2xl font-semibold">
                    {{ tournament.direct_qualifiers_count }}
                </p>
            </div>

            <div
                class="rounded-xl border border-yellow-500/30 bg-yellow-500/10 p-4 text-yellow-700 dark:text-yellow-300"
            >
                <p class="text-sm">Iz repasaža</p>

                <p class="mt-2 text-2xl font-semibold">
                    {{ tournament.repechage_qualifiers_count }}
                </p>
            </div>
        </div>

        <div
            v-if="tournament.is_knockout_ready"
            class="rounded-xl border border-emerald-500/30 bg-emerald-500/10 p-4 text-emerald-700 dark:text-emerald-300"
        >
            <h2 class="text-lg font-medium">Nokaut je spreman</h2>

            <p class="mt-1 text-sm">
                Broj učesnika se poklapa sa veličinom nokauta. Sledeći korak je
                generisanje nokaut kostura.
            </p>

            <button
                v-if="tournament.can_generate_knockout_bracket"
                type="button"
                class="mt-4 inline-flex items-center justify-center rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground transition hover:opacity-90"
                @click="emit('generate')"
            >
                Generiši kostur
            </button>

            <Link
                v-else-if="tournament.knockout_matches_count > 0"
                :href="scheduleUrl"
                class="mt-4 inline-flex items-center justify-center rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground transition hover:opacity-90"
            >
                Otvori raspored
            </Link>
        </div>

        <div
            v-else
            class="rounded-xl border border-yellow-500/30 bg-yellow-500/10 p-4 text-yellow-700 dark:text-yellow-300"
        >
            <h2 class="text-lg font-medium">Nokaut još nije spreman</h2>

            <p class="mt-1 text-sm">
                Potrebno je da broj učesnika za nokaut bude tačno
                {{ tournament.knockout_size ?? '-' }}. <br />
                Trenutno ih ima {{ tournament.knockout_participants_count }}.
            </p>
        </div>
    </div>
</template>
