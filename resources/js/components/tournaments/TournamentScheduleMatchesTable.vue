<script setup lang="ts">
import { Clock3, Play, Search, SlidersHorizontal } from '@lucide/vue';
import { useMediaQuery } from '@vueuse/core';
import { computed, ref, watch } from 'vue';
import TournamentScheduleScoreEditor from '@/components/tournaments/TournamentScheduleScoreEditor.vue';
import { normalizeSearchText } from '@/lib/normalizeSearchText';
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
    'update-resource': [match: TournamentScheduleMatch, event: Event];
    'update-result-field': [
        matchId: number,
        field: 'score_a' | 'score_b',
        value: string,
    ];
    'update-result': [match: TournamentScheduleMatch];
    'open-tie-breaker': [match: TournamentScheduleMatch];
    'toggle-postponement': [match: TournamentScheduleMatch];
}>();

const isMobile = useMediaQuery('(max-width: 767px)');
const search = ref('');
const statusFilter = ref('open');
const stageFilter = ref('all');
const groupFilter = ref('all');
const resourceFilter = ref('all');
const visibleLimit = ref(10);

const groups = computed(() => {
    return Array.from(
        new Set(
            props.matches
                .map((match) => match.group_name)
                .filter((group): group is string => Boolean(group)),
        ),
    ).sort();
});

const stages = computed(() => {
    const uniqueStages = new Map<string, string>();

    props.matches.forEach((match) => {
        uniqueStages.set(match.stage, match.stage_label);
    });

    return Array.from(uniqueStages, ([value, label]) => ({ value, label }));
});

const filteredMatches = computed(() => {
    const query = normalizeSearchText(search.value);

    return props.matches.filter((match) => {
        if (
            statusFilter.value === 'open' &&
            !['scheduled', 'in_progress', 'postponed'].includes(match.status)
        ) {
            return false;
        }

        if (
            statusFilter.value !== 'all' &&
            statusFilter.value !== 'open' &&
            match.status !== statusFilter.value
        ) {
            return false;
        }

        if (stageFilter.value !== 'all' && match.stage !== stageFilter.value) {
            return false;
        }

        if (
            groupFilter.value !== 'all' &&
            match.group_name !== groupFilter.value
        ) {
            return false;
        }

        if (
            resourceFilter.value !== 'all' &&
            String(match.resource?.id ?? '') !== resourceFilter.value
        ) {
            return false;
        }

        if (query === '') {
            return true;
        }

        return [
            match.participant_a?.display_name,
            match.participant_b?.display_name,
            match.group_name ? `grupa ${match.group_name}` : null,
            match.resource?.name,
        ]
            .filter(Boolean)
            .some((value) => normalizeSearchText(value!).includes(query));
    });
});

const displayedMatches = computed(() => {
    if (!isMobile.value) {
        return filteredMatches.value;
    }

    return filteredMatches.value.slice(0, visibleLimit.value);
});

watch([search, statusFilter, stageFilter, groupFilter, resourceFilter], () => {
    visibleLimit.value = 10;
});

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

    if (status === 'postponed') {
        return 'bg-amber-500/10 text-amber-700 dark:text-amber-300';
    }

    return 'bg-yellow-500/10 text-yellow-700 dark:text-yellow-300';
};

const matchContext = (match: TournamentScheduleMatch): string => {
    if (match.group_name) {
        return `Grupa ${match.group_name}`;
    }

    if (match.bracket_round_label) {
        return `${match.bracket_round_label}${
            match.bracket_position ? ` #${match.bracket_position}` : ''
        }`;
    }

    return match.stage_label;
};

const participantLabel = (
    participant: TournamentScheduleMatch['participant_a'],
): string => {
    if (!participant) {
        return '—';
    }

    return participant.display_name;
};

const matchContainerClasses = (match: TournamentScheduleMatch): string => {
    if (match.live_queue === 'current') {
        return 'bg-sky-500/[0.09] ring-1 ring-inset ring-sky-400/25 dark:bg-sky-400/[0.08]';
    }

    if (match.live_queue === 'next') {
        return 'bg-orange-500/[0.075] ring-1 ring-inset ring-orange-400/20 dark:bg-orange-400/[0.07]';
    }

    if (match.status === 'finished') {
        return 'bg-emerald-500/[0.045] dark:bg-emerald-400/[0.055]';
    }

    if (match.status === 'postponed') {
        return 'bg-amber-500/[0.06] dark:bg-amber-400/[0.07]';
    }

    return '';
};

const liveQueueLabel = (match: TournamentScheduleMatch): string | null => {
    if (match.live_queue === 'current') {
        return 'Na TV-u sada';
    }

    if (match.live_queue === 'next') {
        return `Sledeći na TV-u #${match.live_queue_order}`;
    }

    return null;
};

const liveQueueBadgeClasses = (match: TournamentScheduleMatch): string =>
    match.live_queue === 'current'
        ? 'bg-sky-500/15 text-sky-700 dark:text-sky-200'
        : 'bg-orange-500/15 text-orange-700 dark:text-orange-200';

const participantClasses = (
    match: TournamentScheduleMatch,
    participant: TournamentScheduleMatch['participant_a'],
): string => {
    if (participant?.is_withdrawn) {
        return 'text-muted-foreground line-through';
    }

    if (match.winner?.id === participant?.id) {
        return 'font-semibold text-emerald-700 dark:text-emerald-300';
    }

    return match.status === 'finished' ? 'text-muted-foreground' : '';
};
</script>

<template>
    <section
        class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
    >
        <div>
            <h2 class="text-lg font-medium">Mečevi</h2>
            <p class="mt-1 text-sm text-muted-foreground">
                Pronađi meč i sačuvaj rezultat bez traženja kroz ceo raspored.
            </p>
            <div class="mt-2 flex flex-wrap items-center gap-2 text-xs">
                <span
                    class="rounded-full bg-sky-500/15 px-2.5 py-1 font-medium text-sky-700 dark:text-sky-200"
                >
                    Na TV-u sada
                </span>
                <span
                    class="rounded-full bg-orange-500/15 px-2.5 py-1 font-medium text-orange-700 dark:text-orange-200"
                >
                    Sledeći na TV-u
                </span>
                <span class="text-muted-foreground">
                    Iste oznake prate javni Uživo ekran.
                </span>
            </div>
        </div>

        <details
            :open="!isMobile"
            class="group mt-5 rounded-xl bg-muted/40 p-3"
        >
            <summary
                class="flex cursor-pointer list-none items-center justify-between gap-3 text-sm font-medium"
            >
                <span class="flex items-center gap-2">
                    <SlidersHorizontal class="size-4 text-primary" />
                    Filteri
                    <span class="font-normal text-muted-foreground">
                        · {{ filteredMatches.length }} mečeva
                    </span>
                </span>
                <span class="text-xs text-muted-foreground group-open:hidden">
                    Otvori
                </span>
                <span
                    class="hidden text-xs text-muted-foreground group-open:inline"
                >
                    Sklopi
                </span>
            </summary>

            <div class="mt-3 grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
                <label class="relative block sm:col-span-2 xl:col-span-1">
                    <Search
                        class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                    />
                    <input
                        v-model="search"
                        type="search"
                        class="w-full rounded-xl border border-sidebar-border/70 bg-background py-2.5 pr-3 pl-9 text-sm transition outline-none focus:border-primary dark:border-sidebar-border"
                        placeholder="Ime učesnika..."
                    />
                </label>

                <select
                    v-model="statusFilter"
                    aria-label="Status meča"
                    class="w-full rounded-xl border border-sidebar-border/70 bg-background px-3 py-2.5 text-sm transition outline-none focus:border-primary dark:border-sidebar-border"
                >
                    <option value="open">Za unos rezultata</option>
                    <option value="all">Svi statusi</option>
                    <option value="in_progress">U toku</option>
                    <option value="scheduled">Zakazani</option>
                    <option value="postponed">Privremeno preskočeni</option>
                    <option value="finished">Završeni</option>
                    <option value="voided">Anulirani</option>
                    <option value="cancelled">Otkazani</option>
                </select>

                <select
                    v-model="stageFilter"
                    aria-label="Faza turnira"
                    class="w-full rounded-xl border border-sidebar-border/70 bg-background px-3 py-2.5 text-sm transition outline-none focus:border-primary dark:border-sidebar-border"
                >
                    <option value="all">Sve faze</option>
                    <option
                        v-for="stage in stages"
                        :key="stage.value"
                        :value="stage.value"
                    >
                        {{ stage.label }}
                    </option>
                </select>

                <select
                    v-model="groupFilter"
                    aria-label="Grupa"
                    class="w-full rounded-xl border border-sidebar-border/70 bg-background px-3 py-2.5 text-sm transition outline-none focus:border-primary dark:border-sidebar-border"
                >
                    <option value="all">Sve grupe</option>
                    <option v-for="group in groups" :key="group" :value="group">
                        Grupa {{ group }}
                    </option>
                </select>

                <select
                    v-model="resourceFilter"
                    aria-label="Oprema"
                    class="w-full rounded-xl border border-sidebar-border/70 bg-background px-3 py-2.5 text-sm transition outline-none focus:border-primary dark:border-sidebar-border"
                >
                    <option value="all">Sve table / stolovi</option>
                    <option
                        v-for="resource in resources"
                        :key="resource.id"
                        :value="String(resource.id)"
                    >
                        {{ resource.name }}
                    </option>
                </select>
            </div>

            <p class="mt-3 text-xs text-muted-foreground">
                Prikazano {{ filteredMatches.length }} od
                {{ matches.length }} mečeva
            </p>
        </details>

        <div
            v-if="displayedMatches.length && !isMobile"
            class="mt-4 overflow-hidden rounded-lg border border-sidebar-border/70 dark:border-sidebar-border"
        >
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead
                        class="border-b border-sidebar-border/70 bg-muted/40 dark:border-sidebar-border"
                    >
                        <tr>
                            <th class="px-4 py-3 font-medium">#</th>
                            <th class="px-4 py-3 font-medium">Faza</th>
                            <th class="px-4 py-3 font-medium">Meč</th>
                            <th class="px-4 py-3 font-medium">Tabla / sto</th>
                            <th class="px-4 py-3 font-medium">Rezultat</th>
                            <th class="px-4 py-3 font-medium">Status</th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr
                            v-for="match in displayedMatches"
                            :key="match.id"
                            class="border-b border-sidebar-border/70 transition last:border-b-0 dark:border-sidebar-border"
                            :class="matchContainerClasses(match)"
                        >
                            <td class="px-4 py-3 text-muted-foreground">
                                {{ match.scheduled_order ?? '-' }}
                                <span
                                    v-if="liveQueueLabel(match)"
                                    class="mt-1.5 block w-fit rounded-full px-2 py-0.5 text-[10px] font-bold whitespace-nowrap uppercase"
                                    :class="liveQueueBadgeClasses(match)"
                                >
                                    {{ liveQueueLabel(match) }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <span class="font-medium">{{
                                    matchContext(match)
                                }}</span>
                                <span
                                    v-if="match.round_robin_leg"
                                    class="mt-1 block text-xs text-muted-foreground"
                                >
                                    {{ match.round_robin_leg }}. krug
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="font-medium">
                                    <span
                                        :class="
                                            participantClasses(
                                                match,
                                                match.participant_a,
                                            )
                                        "
                                    >
                                        {{
                                            participantLabel(
                                                match.participant_a,
                                            )
                                        }}
                                    </span>
                                    <span
                                        v-if="
                                            match.participant_a?.is_withdrawn &&
                                            match.participant_a.score_suggestion
                                        "
                                        class="mt-1 block text-[11px] font-normal text-amber-700 dark:text-amber-300"
                                    >
                                        Predlog: prosek
                                        {{
                                            match.participant_a.score_suggestion
                                                .average
                                        }}
                                        · medijana
                                        {{
                                            match.participant_a.score_suggestion
                                                .median
                                        }}
                                    </span>
                                    <span class="mx-2 text-muted-foreground"
                                        >vs</span
                                    >
                                    <span
                                        :class="
                                            participantClasses(
                                                match,
                                                match.participant_b,
                                            )
                                        "
                                    >
                                        {{
                                            participantLabel(
                                                match.participant_b,
                                            )
                                        }}
                                    </span>
                                    <span
                                        v-if="
                                            match.participant_b?.is_withdrawn &&
                                            match.participant_b.score_suggestion
                                        "
                                        class="mt-1 block text-[11px] font-normal text-amber-700 dark:text-amber-300"
                                    >
                                        Predlog: prosek
                                        {{
                                            match.participant_b.score_suggestion
                                                .average
                                        }}
                                        · medijana
                                        {{
                                            match.participant_b.score_suggestion
                                                .median
                                        }}
                                    </span>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <select
                                    :value="match.resource?.id ?? ''"
                                    class="w-full min-w-36 rounded-lg border border-sidebar-border/70 bg-background px-3 py-2 text-sm transition outline-none focus:border-primary dark:border-sidebar-border"
                                    @change="
                                        emit('update-resource', match, $event)
                                    "
                                >
                                    <option value="">Nije dodeljeno</option>
                                    <option
                                        v-for="resource in resources"
                                        :key="resource.id"
                                        :value="resource.id"
                                    >
                                        {{ resource.name }}
                                    </option>
                                </select>
                            </td>
                            <td class="px-4 py-3">
                                <TournamentScheduleScoreEditor
                                    :match="match"
                                    :result-form="resultForms[match.id]"
                                    @update-field="
                                        (field, value) =>
                                            emit(
                                                'update-result-field',
                                                match.id,
                                                field,
                                                value,
                                            )
                                    "
                                    @save="emit('update-result', match)"
                                    @open-tie-breaker="
                                        emit('open-tie-breaker', match)
                                    "
                                />
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex flex-col items-start gap-2">
                                    <span
                                        class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium"
                                        :class="
                                            statusBadgeClasses(match.status)
                                        "
                                    >
                                        {{ match.status_label }}
                                    </span>
                                    <button
                                        v-if="
                                            ![
                                                'finished',
                                                'voided',
                                                'cancelled',
                                            ].includes(match.status)
                                        "
                                        type="button"
                                        class="inline-flex items-center gap-1.5 rounded-lg border border-sidebar-border/70 px-2.5 py-1.5 text-xs font-medium transition hover:bg-muted dark:border-sidebar-border"
                                        :class="
                                            match.status === 'postponed'
                                                ? 'text-emerald-700 dark:text-emerald-300'
                                                : 'text-amber-700 dark:text-amber-300'
                                        "
                                        @click="
                                            emit('toggle-postponement', match)
                                        "
                                    >
                                        <Play
                                            v-if="match.status === 'postponed'"
                                            class="size-3.5"
                                        />
                                        <Clock3 v-else class="size-3.5" />
                                        {{
                                            match.status === 'postponed'
                                                ? 'Vrati u raspored'
                                                : 'Preskoči za sada'
                                        }}
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div v-else-if="displayedMatches.length" class="mt-4 space-y-3">
            <article
                v-for="match in displayedMatches"
                :key="match.id"
                class="rounded-2xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
                :class="matchContainerClasses(match)"
            >
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-xs font-medium text-primary">
                            #{{ match.scheduled_order ?? '-' }} ·
                            {{ matchContext(match) }}
                        </p>
                        <span
                            v-if="liveQueueLabel(match)"
                            class="mt-1.5 inline-flex rounded-full px-2 py-0.5 text-[10px] font-bold uppercase"
                            :class="liveQueueBadgeClasses(match)"
                        >
                            {{ liveQueueLabel(match) }}
                        </span>
                        <p
                            v-if="match.round_robin_leg"
                            class="mt-1 text-xs text-muted-foreground"
                        >
                            {{ match.round_robin_leg }}. krug
                        </p>
                    </div>
                    <span
                        class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium"
                        :class="statusBadgeClasses(match.status)"
                    >
                        {{ match.status_label }}
                    </span>
                </div>

                <button
                    v-if="
                        !['finished', 'voided', 'cancelled'].includes(
                            match.status,
                        )
                    "
                    type="button"
                    class="mt-3 inline-flex w-full items-center justify-center gap-2 rounded-xl border border-sidebar-border/70 px-3 py-2.5 text-sm font-medium transition hover:bg-muted dark:border-sidebar-border"
                    :class="
                        match.status === 'postponed'
                            ? 'text-emerald-700 dark:text-emerald-300'
                            : 'text-amber-700 dark:text-amber-300'
                    "
                    @click="emit('toggle-postponement', match)"
                >
                    <Play v-if="match.status === 'postponed'" class="size-4" />
                    <Clock3 v-else class="size-4" />
                    {{
                        match.status === 'postponed'
                            ? 'Vrati meč u raspored'
                            : 'Preskoči meč za sada'
                    }}
                </button>

                <div class="mt-4 space-y-2">
                    <div
                        class="rounded-xl border px-3 py-2.5 font-medium"
                        :class="[
                            participantClasses(match, match.participant_a),
                            match.winner?.id === match.participant_a?.id
                                ? 'border-emerald-500/30 bg-emerald-500/10'
                                : 'border-transparent bg-muted/50',
                        ]"
                    >
                        {{ participantLabel(match.participant_a) }}
                        <span
                            v-if="
                                match.participant_a?.is_withdrawn &&
                                match.participant_a.score_suggestion
                            "
                            class="mt-1 block text-xs font-normal text-amber-700 dark:text-amber-300"
                        >
                            Predlog: prosek
                            {{ match.participant_a.score_suggestion.average }} ·
                            medijana
                            {{ match.participant_a.score_suggestion.median }}
                        </span>
                    </div>
                    <div
                        class="rounded-xl border px-3 py-2.5 font-medium"
                        :class="[
                            participantClasses(match, match.participant_b),
                            match.winner?.id === match.participant_b?.id
                                ? 'border-emerald-500/30 bg-emerald-500/10'
                                : 'border-transparent bg-muted/50',
                        ]"
                    >
                        {{ participantLabel(match.participant_b) }}
                        <span
                            v-if="
                                match.participant_b?.is_withdrawn &&
                                match.participant_b.score_suggestion
                            "
                            class="mt-1 block text-xs font-normal text-amber-700 dark:text-amber-300"
                        >
                            Predlog: prosek
                            {{ match.participant_b.score_suggestion.average }} ·
                            medijana
                            {{ match.participant_b.score_suggestion.median }}
                        </span>
                    </div>
                </div>

                <label
                    class="mt-4 block text-xs font-medium text-muted-foreground"
                >
                    Tabla / sto
                    <select
                        :value="match.resource?.id ?? ''"
                        class="mt-2 w-full rounded-xl border border-sidebar-border/70 bg-background px-3 py-3 text-sm text-foreground transition outline-none focus:border-primary dark:border-sidebar-border"
                        @change="emit('update-resource', match, $event)"
                    >
                        <option value="">Nije dodeljeno</option>
                        <option
                            v-for="resource in resources"
                            :key="resource.id"
                            :value="resource.id"
                        >
                            {{ resource.name }}
                        </option>
                    </select>
                </label>

                <div class="mt-4">
                    <p class="mb-2 text-xs font-medium text-muted-foreground">
                        Rezultat
                    </p>
                    <TournamentScheduleScoreEditor
                        :match="match"
                        :result-form="resultForms[match.id]"
                        compact
                        @update-field="
                            (field, value) =>
                                emit(
                                    'update-result-field',
                                    match.id,
                                    field,
                                    value,
                                )
                        "
                        @save="emit('update-result', match)"
                        @open-tie-breaker="emit('open-tie-breaker', match)"
                    />
                </div>
            </article>

            <button
                v-if="visibleLimit < filteredMatches.length"
                type="button"
                class="inline-flex w-full items-center justify-center rounded-xl border border-sidebar-border/70 px-4 py-3 text-sm font-medium transition hover:bg-muted dark:border-sidebar-border"
                @click="visibleLimit += 10"
            >
                Prikaži još
            </button>
        </div>

        <div
            v-else
            class="mt-4 rounded-xl border border-dashed border-sidebar-border/70 p-6 text-center text-sm text-muted-foreground dark:border-sidebar-border"
        >
            Nema mečeva koji odgovaraju izabranim filterima.
        </div>
    </section>
</template>
