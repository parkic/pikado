<script setup lang="ts">
import { computed } from 'vue';

import type {
    TournamentCreateOptions,
    TournamentGroupRounds,
} from '@/types/tournament';

const props = withDefaults(
    defineProps<{
        name: string;
        tournamentDate: string;
        gameType: string;
        matchMode: string;
        groupRounds: TournamentGroupRounds;
        knockoutSize: number | null;
        options: TournamentCreateOptions;
        showAdvanced?: boolean;
        nameError?: string;
        tournamentDateError?: string;
        gameTypeError?: string;
        matchModeError?: string;
        groupRoundsError?: string;
        knockoutSizeError?: string;
    }>(),
    {
        showAdvanced: true,
    },
);

const emit = defineEmits<{
    'update:name': [value: string];
    'update:tournamentDate': [value: string];
    'update:gameType': [value: string];
    'update:matchMode': [value: string];
    'update:groupRounds': [value: TournamentGroupRounds];
    'update:knockoutSize': [value: number | null];
}>();

const nameModel = computed({
    get: () => props.name,
    set: (value: string) => emit('update:name', value),
});

const tournamentDateModel = computed({
    get: () => props.tournamentDate,
    set: (value: string) => emit('update:tournamentDate', value),
});

const gameTypeModel = computed({
    get: () => props.gameType,
    set: (value: string) => emit('update:gameType', value),
});

const matchModeModel = computed({
    get: () => props.matchMode,
    set: (value: string) => emit('update:matchMode', value),
});

const groupRoundsModel = computed<TournamentGroupRounds>({
    get: () => props.groupRounds,
    set: (value) => emit('update:groupRounds', value),
});

const knockoutSizeModel = computed<number | null>({
    get: () => props.knockoutSize,
    set: (value) => emit('update:knockoutSize', value),
});
</script>

<template>
    <div
        class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
    >
        <h2 class="text-lg font-medium">Osnovne informacije</h2>

        <p class="mt-1 text-sm text-muted-foreground">
            Naziv, igra i osnovni format turnira.
        </p>

        <div class="mt-4 grid gap-4 md:grid-cols-2">
            <div>
                <label class="text-sm font-medium"> Naziv turnira </label>

                <input
                    v-model="nameModel"
                    type="text"
                    class="mt-2 w-full rounded-lg border border-sidebar-border/70 bg-background px-3 py-2 text-sm transition outline-none focus:border-primary dark:border-sidebar-border"
                    placeholder="301 Single Out - 24. jun 2026"
                />

                <p v-if="nameError" class="mt-1 text-sm text-red-600">
                    {{ nameError }}
                </p>
            </div>

            <div>
                <label class="text-sm font-medium"> Datum turnira </label>

                <input
                    v-model="tournamentDateModel"
                    type="date"
                    class="mt-2 w-full rounded-lg border border-sidebar-border/70 bg-background px-3 py-2 text-sm transition outline-none focus:border-primary dark:border-sidebar-border"
                />

                <p v-if="tournamentDateError" class="mt-1 text-sm text-red-600">
                    {{ tournamentDateError }}
                </p>
            </div>

            <div>
                <label class="text-sm font-medium"> Igra </label>

                <select
                    v-model="gameTypeModel"
                    class="mt-2 w-full rounded-lg border border-sidebar-border/70 bg-background px-3 py-2 text-sm transition outline-none focus:border-primary dark:border-sidebar-border"
                >
                    <option
                        v-for="option in options.game_types"
                        :key="option.value"
                        :value="option.value"
                    >
                        {{ option.label }}
                    </option>
                </select>

                <p v-if="gameTypeError" class="mt-1 text-sm text-red-600">
                    {{ gameTypeError }}
                </p>
            </div>

            <div>
                <label class="text-sm font-medium"> Format </label>

                <select
                    v-model="matchModeModel"
                    class="mt-2 w-full rounded-lg border border-sidebar-border/70 bg-background px-3 py-2 text-sm transition outline-none focus:border-primary dark:border-sidebar-border"
                >
                    <option
                        v-for="option in options.match_modes"
                        :key="option.value"
                        :value="option.value"
                    >
                        {{ option.label }}
                    </option>
                </select>

                <p v-if="matchModeError" class="mt-1 text-sm text-red-600">
                    {{ matchModeError }}
                </p>
            </div>

            <div v-if="showAdvanced">
                <label class="text-sm font-medium"> Grupna faza </label>

                <select
                    v-model="groupRoundsModel"
                    class="mt-2 w-full rounded-lg border border-sidebar-border/70 bg-background px-3 py-2 text-sm transition outline-none focus:border-primary dark:border-sidebar-border"
                >
                    <option
                        v-for="option in options.group_rounds"
                        :key="option.value"
                        :value="option.value"
                    >
                        {{ option.label }}
                    </option>
                </select>

                <p v-if="groupRoundsError" class="mt-1 text-sm text-red-600">
                    {{ groupRoundsError }}
                </p>
            </div>

            <div v-if="showAdvanced">
                <label class="text-sm font-medium"> Veličina nokauta </label>

                <select
                    v-model.number="knockoutSizeModel"
                    class="mt-2 w-full rounded-lg border border-sidebar-border/70 bg-background px-3 py-2 text-sm transition outline-none focus:border-primary dark:border-sidebar-border"
                >
                    <option
                        v-for="option in options.knockout_sizes"
                        :key="option.value"
                        :value="option.value"
                    >
                        {{ option.label }}
                    </option>
                </select>

                <p v-if="knockoutSizeError" class="mt-1 text-sm text-red-600">
                    {{ knockoutSizeError }}
                </p>
            </div>
        </div>
    </div>
</template>
