<script setup lang="ts">
import { ArrowRight, CircleCheckBig, TimerReset } from '@lucide/vue';

import type {
    TournamentNextStageAfterGroups,
    TournamentStatus,
} from '@/types/tournament';

const props = defineProps<{
    status: TournamentStatus;
    groupMatchesCount: number;
    completedGroupMatchesCount: number;
    canCompleteGroupStage: boolean;
    nextStageAfterGroups: TournamentNextStageAfterGroups;
}>();

const emit = defineEmits<{
    completeGroupStage: [];
}>();

const nextStageLabel =
    props.nextStageAfterGroups === 'repechage' ? 'repasaž' : 'nokaut žreb';
</script>

<template>
    <section
        v-if="status === 'group_stage'"
        class="rounded-2xl border p-4 sm:p-5"
        :class="
            canCompleteGroupStage
                ? 'border-emerald-500/35 bg-emerald-500/[0.08]'
                : 'border-sidebar-border/70 bg-muted/20 dark:border-sidebar-border'
        "
    >
        <div
            class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between"
        >
            <div class="flex min-w-0 items-start gap-3">
                <span
                    class="mt-0.5 flex size-10 shrink-0 items-center justify-center rounded-xl"
                    :class="
                        canCompleteGroupStage
                            ? 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-300'
                            : 'bg-muted text-muted-foreground'
                    "
                >
                    <CircleCheckBig
                        v-if="canCompleteGroupStage"
                        class="size-5"
                    />
                    <TimerReset v-else class="size-5" />
                </span>

                <div class="min-w-0">
                    <h2 class="text-base font-semibold sm:text-lg">
                        {{
                            canCompleteGroupStage
                                ? 'Svi grupni mečevi su završeni'
                                : 'Grupna faza je u toku'
                        }}
                    </h2>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{ completedGroupMatchesCount }} /
                        {{ groupMatchesCount }} mečeva je obrađeno.
                        <template v-if="canCompleteGroupStage">
                            Turnir može odmah da pređe na {{ nextStageLabel }}.
                        </template>
                        <template v-else>
                            Kada obradiš sve rezultate, ovde će se pojaviti
                            završna akcija.
                        </template>
                    </p>
                </div>
            </div>

            <button
                v-if="canCompleteGroupStage"
                type="button"
                class="inline-flex min-h-11 shrink-0 items-center justify-center gap-2 rounded-xl bg-primary px-5 py-2.5 text-sm font-semibold text-primary-foreground shadow-sm transition hover:opacity-90"
                @click="emit('completeGroupStage')"
            >
                Završi grupnu fazu
                <ArrowRight class="size-4" />
            </button>
        </div>
    </section>
</template>
