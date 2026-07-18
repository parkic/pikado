<script setup lang="ts">
import type {
    TournamentRepechageData,
} from '@/types/tournament';

defineProps<{
    tournament: TournamentRepechageData;
    directQualifiersCount: number;
    repechageParticipantsCount: number;
}>();

const emit = defineEmits<{
    complete: [];
}>();
</script>

<template>
    <div class="space-y-6">
        <div class="grid gap-4 md:grid-cols-4">
            <div
                class="rounded-xl border border-emerald-500/30 bg-emerald-500/10 p-4 text-emerald-700 dark:text-emerald-300"
            >
                <p class="text-sm">
                    Direktan prolaz
                </p>

                <p class="mt-2 text-2xl font-semibold">
                    {{ directQualifiersCount }}
                </p>
            </div>

            <div
                class="rounded-xl border border-yellow-500/30 bg-yellow-500/10 p-4 text-yellow-700 dark:text-yellow-300"
            >
                <p class="text-sm">
                    Repasaž
                </p>

                <p class="mt-2 text-2xl font-semibold">
                    {{ repechageParticipantsCount }}
                </p>
            </div>

            <div
                class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
            >
                <p class="text-sm text-muted-foreground">
                    Iz repasaža ide dalje
                </p>

                <p class="mt-2 text-2xl font-semibold">
                    {{
                        tournament.repechage_qualifiers_count
                            || '-'
                    }}
                </p>
            </div>

            <div
                class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
            >
                <p class="text-sm text-muted-foreground">
                    Označeno prošlo
                </p>

                <p class="mt-2 text-2xl font-semibold">
                    {{ tournament.repechage_advanced_count }}
                    /
                    {{
                        tournament.repechage_qualifiers_count
                            || '-'
                    }}
                </p>
            </div>
        </div>

        <div
            v-if="tournament.status === 'repechage'"
            class="rounded-xl border border-primary/30 bg-primary/5 p-4"
        >
            <h2 class="text-lg font-medium">
                Repasaž je u toku
            </h2>

            <p class="mt-1 text-sm text-muted-foreground">
                Označi tačno
                {{ tournament.repechage_qualifiers_count }}
                učesnika koji prolaze dalje. Trenutno označeno:
                {{ tournament.repechage_advanced_count }}.
            </p>

            <button
                v-if="tournament.can_complete_repechage"
                type="button"
                class="mt-4 inline-flex items-center justify-center rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground transition hover:opacity-90"
                @click="emit('complete')"
            >
                Završi repasaž
            </button>

            <p
                v-else
                class="mt-3 text-sm text-muted-foreground"
            >
                Dugme za završetak će se pojaviti kada
                označiš tačan broj učesnika koji prolaze
                dalje.
            </p>
        </div>

        <div
            v-else-if="tournament.status === 'knockout_draw'"
            class="rounded-xl border border-emerald-500/30 bg-emerald-500/10 p-4 text-emerald-700 dark:text-emerald-300"
        >
            <h2 class="text-lg font-medium">
                Repasaž je završen
            </h2>

            <p class="mt-1 text-sm">
                Turnir je spreman za nokaut žreb.
            </p>
        </div>

        <div
            v-if="!tournament.settings.repechage_enabled"
            class="rounded-xl border border-yellow-500/30 bg-yellow-500/10 p-4 text-yellow-700 dark:text-yellow-300"
        >
            <h2 class="text-lg font-medium">
                Repasaž nije uključen
            </h2>

            <p class="mt-1 text-sm">
                Ako želiš repasaž, vrati se na podešavanje prolaza i uključi ga.
            </p>
        </div>
    </div>
</template>
