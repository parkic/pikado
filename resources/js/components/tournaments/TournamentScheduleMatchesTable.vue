<script setup lang="ts">
import type {
    TournamentScheduleAvailableResource,
    TournamentScheduleMatch,
    TournamentScheduleResultForm,
} from '@/types/tournament';

const props = defineProps<{
    matches: TournamentScheduleMatch[];
    resources: TournamentScheduleAvailableResource[];
    resultForms: Record<number, TournamentScheduleResultForm>;
}>();

const emit = defineEmits<{
    'update-resource': [
        match: TournamentScheduleMatch,
        event: Event,
    ];
    'update-result-field': [
        matchId: number,
        field: 'score_a' | 'score_b',
        value: string,
    ];
    'update-result': [match: TournamentScheduleMatch];
    'open-tie-breaker': [match: TournamentScheduleMatch];
}>();

const statusBadgeClasses = (status: string): string => {
    if (status === 'scheduled') {
        return 'bg-muted text-muted-foreground';
    }

    if (status === 'in_progress') {
        return 'bg-primary/10 text-primary';
    }

    if (status === 'finished') {
        return 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300';
    }

    return 'bg-yellow-500/10 text-yellow-700 dark:text-yellow-300';
};

const isDrawResult = (
    match: TournamentScheduleMatch,
): boolean => {
    const resultForm = props.resultForms[match.id];

    if (
        resultForm.score_a === ''
        || resultForm.score_b === ''
    ) {
        return false;
    }

    return Number(resultForm.score_a)
        === Number(resultForm.score_b);
};

const updateResultField = (
    matchId: number,
    field: 'score_a' | 'score_b',
    event: Event,
) => {
    const input = event.target as HTMLInputElement;

    emit(
        'update-result-field',
        matchId,
        field,
        input.value,
    );
};
</script>

<template>
    <div
        class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
    >
        <div>
            <h2 class="text-lg font-medium">
                Mečevi
            </h2>

            <p class="mt-1 text-sm text-muted-foreground">
                Pregled, dodela resursa i unos rezultata
                generisanih mečeva.
            </p>
        </div>

        <div
            v-if="matches.length"
            class="mt-4 overflow-hidden rounded-lg border border-sidebar-border/70 dark:border-sidebar-border"
        >
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead
                        class="border-b border-sidebar-border/70 bg-muted/40 dark:border-sidebar-border"
                    >
                        <tr>
                            <th class="px-4 py-3 font-medium">
                                #
                            </th>

                            <th class="px-4 py-3 font-medium">
                                Faza
                            </th>

                            <th class="px-4 py-3 font-medium">
                                Grupa
                            </th>

                            <th class="px-4 py-3 font-medium">
                                Meč
                            </th>

                            <th class="px-4 py-3 font-medium">
                                Resource
                            </th>

                            <th class="px-4 py-3 font-medium">
                                Krug
                            </th>

                            <th class="px-4 py-3 font-medium">
                                Rezultat
                            </th>

                            <th class="px-4 py-3 font-medium">
                                Status
                            </th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr
                            v-for="match in matches"
                            :key="match.id"
                            class="border-b border-sidebar-border/70 last:border-b-0 dark:border-sidebar-border"
                        >
                            <td
                                class="px-4 py-3 text-muted-foreground"
                            >
                                {{ match.scheduled_order ?? '-' }}
                            </td>

                            <td
                                class="px-4 py-3 text-muted-foreground"
                            >
                                {{ match.stage_label }}
                            </td>

                            <td class="px-4 py-3">
                                <template v-if="match.group_name">
                                    Grupa {{ match.group_name }}
                                </template>

                                <template
                                    v-else-if="
                                        match.bracket_round_label
                                    "
                                >
                                    {{ match.bracket_round_label }}

                                    <span
                                        v-if="match.bracket_position"
                                        class="text-muted-foreground"
                                    >
                                        #{{ match.bracket_position }}
                                    </span>
                                </template>

                                <template v-else>
                                    -
                                </template>
                            </td>

                            <td class="px-4 py-3">
                                <div class="font-medium">
                                    <span
                                        :class="
                                            match.participant_a
                                                ?.is_withdrawn
                                                ? 'text-muted-foreground line-through'
                                                : ''
                                        "
                                    >
                                        {{
                                            match.participant_a
                                                ?.display_name
                                                ?? 'TBD'
                                        }}
                                    </span>

                                    <span
                                        v-if="
                                            match.participant_a
                                                ?.is_withdrawn
                                        "
                                        class="ml-2 inline-flex rounded-full bg-red-500/10 px-2 py-0.5 text-[11px] font-medium text-red-700 dark:text-red-300"
                                    >
                                        Odustao
                                    </span>

                                    <span
                                        class="mx-2 text-muted-foreground"
                                    >
                                        vs
                                    </span>

                                    <span
                                        :class="
                                            match.participant_b
                                                ?.is_withdrawn
                                                ? 'text-muted-foreground line-through'
                                                : ''
                                        "
                                    >
                                        {{
                                            match.participant_b
                                                ?.display_name
                                                ?? 'TBD'
                                        }}
                                    </span>

                                    <span
                                        v-if="
                                            match.participant_b
                                                ?.is_withdrawn
                                        "
                                        class="ml-2 inline-flex rounded-full bg-red-500/10 px-2 py-0.5 text-[11px] font-medium text-red-700 dark:text-red-300"
                                    >
                                        Odustao
                                    </span>
                                </div>

                                <div
                                    class="mt-1 text-xs text-muted-foreground"
                                >
                                    {{
                                        match.participant_a
                                            ?.group_position
                                            ?? '-'
                                    }}
                                    vs
                                    {{
                                        match.participant_b
                                            ?.group_position
                                            ?? '-'
                                    }}
                                </div>
                            </td>

                            <td class="px-4 py-3">
                                <select
                                    :value="match.resource?.id ?? ''"
                                    class="w-full min-w-36 rounded-lg border border-sidebar-border/70 bg-background px-3 py-2 text-sm outline-none transition focus:border-primary dark:border-sidebar-border"
                                    @change="
                                        emit(
                                            'update-resource',
                                            match,
                                            $event,
                                        )
                                    "
                                >
                                    <option value="">
                                        Bez resource-a
                                    </option>

                                    <option
                                        v-for="resource in resources"
                                        :key="resource.id"
                                        :value="resource.id"
                                    >
                                        {{ resource.name }}
                                    </option>
                                </select>
                            </td>

                            <td
                                class="px-4 py-3 text-muted-foreground"
                            >
                                <template
                                    v-if="match.round_robin_leg"
                                >
                                    {{ match.round_robin_leg }}

                                    <span
                                        v-if="
                                            match.wins_required
                                                && match.stage
                                                    !== 'group'
                                        "
                                        class="block text-xs"
                                    >
                                        na
                                        {{ match.wins_required }}
                                        dobijene
                                    </span>
                                </template>

                                <template v-else>
                                    -
                                </template>
                            </td>

                            <td class="px-4 py-3">
                                <div
                                    class="flex min-w-44 items-center gap-2"
                                >
                                    <input
                                        :value="
                                            resultForms[match.id]
                                                .score_a
                                        "
                                        :disabled="
                                            match.status === 'voided'
                                        "
                                        type="number"
                                        min="0"
                                        max="999"
                                        class="w-16 rounded-lg border border-sidebar-border/70 bg-background px-2 py-2 text-center text-sm outline-none transition focus:border-primary disabled:opacity-50 dark:border-sidebar-border"
                                        @input="
                                            updateResultField(
                                                match.id,
                                                'score_a',
                                                $event,
                                            )
                                        "
                                    >

                                    <span
                                        class="text-muted-foreground"
                                    >
                                        :
                                    </span>

                                    <input
                                        :value="
                                            resultForms[match.id]
                                                .score_b
                                        "
                                        :disabled="
                                            match.status === 'voided'
                                        "
                                        type="number"
                                        min="0"
                                        max="999"
                                        class="w-16 rounded-lg border border-sidebar-border/70 bg-background px-2 py-2 text-center text-sm outline-none transition focus:border-primary disabled:opacity-50 dark:border-sidebar-border"
                                        @input="
                                            updateResultField(
                                                match.id,
                                                'score_b',
                                                $event,
                                            )
                                        "
                                    >

                                    <button
                                        type="button"
                                        :disabled="
                                            match.status === 'voided'
                                        "
                                        class="inline-flex items-center justify-center rounded-lg bg-primary px-3 py-2 text-xs font-medium text-primary-foreground transition hover:opacity-90 disabled:opacity-50"
                                        @click="
                                            emit(
                                                'update-result',
                                                match,
                                            )
                                        "
                                    >
                                        {{
                                            isDrawResult(match)
                                                && !resultForms[
                                                    match.id
                                                ]
                                                    .winner_participant_id
                                                ? 'Izaberi'
                                                : 'Sačuvaj'
                                        }}
                                    </button>
                                </div>

                                <p
                                    v-if="
                                        isDrawResult(match)
                                            && !resultForms[
                                                match.id
                                            ]
                                                .winner_participant_id
                                    "
                                    class="mt-1 text-xs text-yellow-600 dark:text-yellow-300"
                                >
                                    Nerešeno — treba izabrati
                                    pobednika.
                                </p>

                                <div
                                    v-if="match.winner"
                                    class="mt-1 flex flex-wrap items-center gap-2 text-xs"
                                >
                                    <span
                                        class="text-emerald-600 dark:text-emerald-300"
                                    >
                                        Pobednik:
                                        {{ match.winner.display_name }}
                                    </span>

                                    <button
                                        v-if="isDrawResult(match)"
                                        type="button"
                                        class="font-medium text-primary hover:underline"
                                        @click="
                                            emit(
                                                'open-tie-breaker',
                                                match,
                                            )
                                        "
                                    >
                                        Izmeni
                                    </button>
                                </div>
                            </td>

                            <td class="px-4 py-3">
                                <span
                                    class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium"
                                    :class="
                                        statusBadgeClasses(
                                            match.status,
                                        )
                                    "
                                >
                                    {{ match.status_label }}
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div
            v-else
            class="mt-4 rounded-lg border border-dashed border-sidebar-border/70 p-4 text-sm text-muted-foreground dark:border-sidebar-border"
        >
            Još nema generisanih mečeva.
        </div>
    </div>
</template>
