<script setup lang="ts">
import type { TournamentPodiumData } from '@/types/tournament';

defineProps<{
    podium: TournamentPodiumData;
}>();
</script>

<template>
    <div
        class="rounded-xl border border-emerald-500/30 bg-emerald-500/10 p-5 text-emerald-900 dark:text-emerald-100"
    >
        <div
            class="flex flex-col gap-2 md:flex-row md:items-start md:justify-between"
        >
            <div>
                <p
                    class="text-sm font-medium tracking-wide text-emerald-700 uppercase dark:text-emerald-300"
                >
                    Finalni plasman
                </p>

                <h2 class="mt-1 text-2xl font-semibold">Turnir je završen</h2>

                <p
                    v-if="podium.is_complete"
                    class="mt-1 text-sm text-emerald-800/80 dark:text-emerald-100/80"
                >
                    Finale i meč za treće mesto su završeni. Ovo je konačan
                    poredak turnira.
                </p>

                <p
                    v-else
                    class="mt-1 text-sm text-emerald-800/80 dark:text-emerald-100/80"
                >
                    Turnir je označen kao završen, ali finalni plasman još nije
                    kompletno izračunat.
                </p>
            </div>

            <div class="text-sm md:text-right">
                <p v-if="podium.final_score">
                    Finale:
                    <span class="font-semibold">
                        {{ podium.final_score }}
                    </span>
                </p>

                <p v-if="podium.third_place_score">
                    Treće mesto:
                    <span class="font-semibold">
                        {{ podium.third_place_score }}
                    </span>
                </p>
            </div>
        </div>

        <div class="mt-5 grid gap-3 md:grid-cols-2 xl:grid-cols-4">
            <div
                class="rounded-xl border border-emerald-500/30 bg-background/70 p-4"
            >
                <p class="text-sm text-muted-foreground">1. mesto</p>

                <p class="mt-2 text-xl font-semibold">
                    🏆 {{ podium.champion?.display_name ?? 'Još nije poznato' }}
                </p>
            </div>

            <div
                class="rounded-xl border border-sidebar-border/70 bg-background/70 p-4 dark:border-sidebar-border"
            >
                <p class="text-sm text-muted-foreground">2. mesto</p>

                <p class="mt-2 text-lg font-semibold">
                    {{
                        podium.second_place?.display_name ?? 'Još nije poznato'
                    }}
                </p>
            </div>

            <div
                class="rounded-xl border border-sidebar-border/70 bg-background/70 p-4 dark:border-sidebar-border"
            >
                <p class="text-sm text-muted-foreground">3. mesto</p>

                <p class="mt-2 text-lg font-semibold">
                    🥉
                    {{ podium.third_place?.display_name ?? 'Još nije poznato' }}
                </p>
            </div>

            <div
                class="rounded-xl border border-sidebar-border/70 bg-background/70 p-4 dark:border-sidebar-border"
            >
                <p class="text-sm text-muted-foreground">4. mesto</p>

                <p class="mt-2 text-lg font-semibold">
                    {{
                        podium.fourth_place?.display_name ?? 'Još nije poznato'
                    }}
                </p>
            </div>
        </div>
    </div>
</template>
