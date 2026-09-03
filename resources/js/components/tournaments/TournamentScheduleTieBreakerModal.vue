<script setup lang="ts">
import type {
    TournamentScheduleMatch,
    TournamentScheduleResultForm,
} from '@/types/tournament';

const props = defineProps<{
    match: TournamentScheduleMatch;
    resultForm: TournamentScheduleResultForm;
}>();

const emit = defineEmits<{
    close: [];
    'choose-winner': [participantId: number];
    save: [];
}>();

const winnerButtonClasses = (participantId: number): string => {
    const isSelected =
        props.resultForm.winner_participant_id === String(participantId);

    if (isSelected) {
        return 'border-primary bg-primary/10 text-primary';
    }

    return 'border-sidebar-border/70 hover:bg-muted dark:border-sidebar-border';
};
</script>

<template>
    <div
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4"
    >
        <button
            type="button"
            class="absolute inset-0"
            aria-label="Zatvori modal"
            @click="emit('close')"
        />

        <div
            class="relative w-full max-w-lg rounded-xl border border-sidebar-border/70 bg-background p-5 shadow-xl dark:border-sidebar-border"
        >
            <div>
                <p class="text-sm text-muted-foreground">Nerešen rezultat</p>

                <h2 class="mt-1 text-xl font-semibold">Izaberi pobednika</h2>

                <p class="mt-2 text-sm text-muted-foreground">
                    Rezultat je

                    <span class="font-medium text-foreground">
                        {{ resultForm.score_a }}
                        :
                        {{ resultForm.score_b }}
                    </span>

                    i razlika će ostati 0. <br />
                    Izabrani učesnik dobija pobedu i 1 bod.
                </p>
            </div>

            <div class="mt-5 grid gap-3">
                <button
                    v-if="match.participant_a"
                    type="button"
                    class="rounded-xl border p-4 text-left transition"
                    :class="winnerButtonClasses(match.participant_a.id)"
                    @click="emit('choose-winner', match.participant_a.id)"
                >
                    <p class="font-medium">
                        {{ match.participant_a.display_name }}
                    </p>
                </button>

                <button
                    v-if="match.participant_b"
                    type="button"
                    class="rounded-xl border p-4 text-left transition"
                    :class="winnerButtonClasses(match.participant_b.id)"
                    @click="emit('choose-winner', match.participant_b.id)"
                >
                    <p class="font-medium">
                        {{ match.participant_b.display_name }}
                    </p>
                </button>
            </div>

            <div
                class="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end"
            >
                <button
                    type="button"
                    class="inline-flex items-center justify-center rounded-lg border border-sidebar-border/70 px-4 py-2 text-sm font-medium transition hover:bg-muted dark:border-sidebar-border"
                    @click="emit('close')"
                >
                    Otkaži
                </button>

                <button
                    type="button"
                    class="inline-flex items-center justify-center rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground transition hover:opacity-90"
                    @click="emit('save')"
                >
                    Sačuvaj rezultat
                </button>
            </div>
        </div>
    </div>
</template>
