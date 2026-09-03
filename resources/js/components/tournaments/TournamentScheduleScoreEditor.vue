<script setup lang="ts">
import { computed } from 'vue';
import type {
    TournamentScheduleMatch,
    TournamentScheduleResultForm,
} from '@/types/tournament';

const props = withDefaults(
    defineProps<{
        match: TournamentScheduleMatch;
        resultForm: TournamentScheduleResultForm;
        compact?: boolean;
    }>(),
    {
        compact: false,
    },
);

const emit = defineEmits<{
    'update-field': [field: 'score_a' | 'score_b', value: string];
    save: [];
    'open-tie-breaker': [];
}>();

const isDrawResult = computed(() => {
    if (props.resultForm.score_a === '' || props.resultForm.score_b === '') {
        return false;
    }

    return (
        Number(props.resultForm.score_a) === Number(props.resultForm.score_b)
    );
});

const updateField = (field: 'score_a' | 'score_b', event: Event) => {
    emit('update-field', field, (event.target as HTMLInputElement).value);
};
</script>

<template>
    <form @submit.prevent="emit('save')">
        <div
            :class="
                compact
                    ? 'grid grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)] items-center gap-2'
                    : 'flex min-w-44 items-center gap-2'
            "
        >
            <input
                :value="resultForm.score_a"
                :disabled="match.status === 'voided'"
                type="number"
                min="0"
                max="999"
                inputmode="numeric"
                aria-label="Rezultat prvog učesnika"
                :class="
                    compact
                        ? 'w-full rounded-xl border border-sidebar-border/70 bg-background px-3 py-3 text-center text-lg font-semibold transition outline-none focus:border-primary disabled:opacity-50 dark:border-sidebar-border'
                        : 'w-16 rounded-lg border border-sidebar-border/70 bg-background px-2 py-2 text-center text-sm transition outline-none focus:border-primary disabled:opacity-50 dark:border-sidebar-border'
                "
                @input="updateField('score_a', $event)"
            />

            <span class="text-muted-foreground">:</span>

            <input
                :value="resultForm.score_b"
                :disabled="match.status === 'voided'"
                type="number"
                min="0"
                max="999"
                inputmode="numeric"
                aria-label="Rezultat drugog učesnika"
                :class="
                    compact
                        ? 'w-full rounded-xl border border-sidebar-border/70 bg-background px-3 py-3 text-center text-lg font-semibold transition outline-none focus:border-primary disabled:opacity-50 dark:border-sidebar-border'
                        : 'w-16 rounded-lg border border-sidebar-border/70 bg-background px-2 py-2 text-center text-sm transition outline-none focus:border-primary disabled:opacity-50 dark:border-sidebar-border'
                "
                @input="updateField('score_b', $event)"
            />

            <button
                type="submit"
                :disabled="match.status === 'voided'"
                class="inline-flex items-center justify-center rounded-lg bg-primary px-3 py-2 text-xs font-medium text-primary-foreground transition hover:opacity-90 disabled:opacity-50"
                :class="
                    compact
                        ? 'col-span-3 mt-1 w-full py-3 text-sm font-semibold'
                        : ''
                "
            >
                {{
                    isDrawResult && !resultForm.winner_participant_id
                        ? 'Izaberi pobednika'
                        : match.status === 'finished'
                          ? 'Sačuvaj izmenu'
                          : 'Sačuvaj rezultat'
                }}
            </button>
        </div>

        <p
            v-if="isDrawResult && !resultForm.winner_participant_id"
            class="mt-2 text-xs text-yellow-600 dark:text-yellow-300"
        >
            Rezultat je nerešen — izaberi pobednika.
        </p>

        <div
            v-if="match.winner"
            class="mt-2 flex flex-wrap items-center gap-2 text-xs"
        >
            <span
                class="inline-flex rounded-full bg-emerald-500/10 px-2.5 py-1 font-semibold text-emerald-700 dark:text-emerald-300"
            >
                ✓ {{ match.winner.display_name }}
            </span>

            <button
                v-if="isDrawResult"
                type="button"
                class="font-medium text-primary hover:underline"
                @click="emit('open-tie-breaker')"
            >
                Izmeni
            </button>
        </div>
    </form>
</template>
