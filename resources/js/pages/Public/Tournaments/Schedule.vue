<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { onBeforeUnmount, onMounted, ref } from 'vue';

type Venue = {
    name: string;
    slug: string;
    logo_path: string | null;
};

type Tournament = {
    id: number;
    name: string;
    public_code: string;
    game_type: string;
    match_mode: string;
    status: string;
    status_label: string;
    matches_count: number;
    scheduled_matches_count: number;
    in_progress_matches_count: number;
    finished_matches_count: number;
    voided_matches_count: number;
};

type MatchParticipant = {
    id: number;
    display_name: string;
    group_position: string | null;
    is_withdrawn: boolean;
} | null;

type PublicMatch = {
    id: number;
    scheduled_order: number | null;
    stage: string;
    stage_label: string;
    group_name: string | null;
    bracket_round: string | null;
    bracket_round_label: string | null;
    bracket_position: number | null;
    round_robin_leg: number | null;
    wins_required: number | null;
    participant_a: MatchParticipant;
    participant_b: MatchParticipant;
    score_a: number | null;
    score_b: number | null;
    winner: MatchParticipant;
    status: string;
    status_label: string;
    resource_name: string | null;
    finished_at: string | null;
};

const props = defineProps<{
    venue: Venue;
    tournament: Tournament;
    matches: PublicMatch[];
}>();

const realtimeStatus = ref<'connecting' | 'connected' | 'updated' | 'error'>('connecting');
const lastRealtimeUpdateAt = ref<string | null>(null);

const formatRealtimeTime = (): string => {
    return new Date().toLocaleTimeString('sr-RS', {
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
    });
};

const reloadScheduleData = () => {
    realtimeStatus.value = 'updated';
    lastRealtimeUpdateAt.value = formatRealtimeTime();

    router.reload({
        preserveScroll: true,
        preserveState: true,
    });
};

onMounted(() => {
    if (!window.Echo) {
        realtimeStatus.value = 'error';

        return;
    }

    realtimeStatus.value = 'connected';

    window.Echo
        .channel(`public-tournament.${props.tournament.public_code}`)
        .listen('.TournamentLiveUpdated', () => {
            reloadScheduleData();
        });
});

onBeforeUnmount(() => {
    if (!window.Echo) {
        return;
    }

    window.Echo.leave(`public-tournament.${props.tournament.public_code}`);
});

const matchContextLabel = (match: PublicMatch): string => {
    if (match.group_name) {
        return `Grupa ${match.group_name}`;
    }

    if (match.bracket_round_label) {
        return `${match.bracket_round_label}${match.bracket_position ? ` #${match.bracket_position}` : ''}`;
    }

    return match.stage_label;
};

const statusClasses = (status: string): string => {
    if (status === 'finished') {
        return 'bg-emerald-500/15 text-emerald-200';
    }

    if (status === 'in_progress') {
        return 'bg-sky-500/15 text-sky-200';
    }

    if (status === 'voided') {
        return 'bg-zinc-700 text-zinc-300';
    }

    if (status === 'cancelled') {
        return 'bg-red-500/15 text-red-200';
    }

    return 'bg-yellow-500/15 text-yellow-200';
};

const stageClasses = (stage: string): string => {
    if (stage === 'group') {
        return 'bg-violet-500/15 text-violet-200';
    }

    if (stage === 'final') {
        return 'bg-amber-500/15 text-amber-200';
    }

    if (stage === 'third_place') {
        return 'bg-orange-500/15 text-orange-200';
    }

    return 'bg-cyan-500/15 text-cyan-200';
};
</script>

<template>
    <Head :title="`${tournament.name} - Raspored`" />

    <div class="min-h-screen bg-zinc-950 text-zinc-50">
        <main class="mx-auto flex w-full max-w-full flex-col gap-6 px-4 py-6 md:px-8">
            <header class="rounded-3xl border border-white/10 bg-white/[0.03] p-5 md:p-8">
                <div class="flex flex-col gap-5 md:flex-row md:items-start md:justify-between">
                    <div>
                        <p class="text-sm uppercase tracking-[0.25em] text-zinc-400">
                            {{ venue.name }}
                        </p>

                        <h1 class="mt-3 text-3xl font-bold tracking-tight md:text-5xl">
                            Raspored
                        </h1>

                        <p class="mt-3 max-w-3xl text-zinc-400">
                            {{ tournament.name }} · {{ tournament.status_label }}
                        </p>
                    </div>

                    <nav class="flex flex-wrap gap-2">
                        <Link
                            :href="`/t/${tournament.public_code}/live`"
                            class="rounded-full border border-white/10 px-4 py-2 text-sm text-zinc-200 transition hover:bg-white/10"
                        >
                            Live
                        </Link>

                        <Link
                            :href="`/t/${tournament.public_code}/groups`"
                            class="rounded-full border border-white/10 px-4 py-2 text-sm text-zinc-200 transition hover:bg-white/10"
                        >
                            Grupe
                        </Link>

                        <Link
                            :href="`/t/${tournament.public_code}/schedule`"
                            class="rounded-full bg-white px-4 py-2 text-sm font-medium text-zinc-950"
                        >
                            Raspored
                        </Link>

                        <Link
                            :href="`/t/${tournament.public_code}/knockout`"
                            class="rounded-full border border-white/10 px-4 py-2 text-sm text-zinc-200 transition hover:bg-white/10"
                        >
                            Nokaut
                        </Link>
                    </nav>

                    <div class="mt-3 flex flex-wrap items-center gap-2 text-xs text-zinc-500">
                        <span
                            class="inline-flex rounded-full px-2.5 py-1"
                            :class="{
                                'bg-sky-500/15 text-sky-200': realtimeStatus === 'connected',
                                'bg-emerald-500/15 text-emerald-200': realtimeStatus === 'updated',
                                'bg-yellow-500/15 text-yellow-200': realtimeStatus === 'connecting',
                                'bg-red-500/15 text-red-200': realtimeStatus === 'error',
                            }"
                        >
                            Realtime:
                            <template v-if="realtimeStatus === 'connecting'">
                                povezivanje
                            </template>

                            <template v-else-if="realtimeStatus === 'connected'">
                                aktivan
                            </template>

                            <template v-else-if="realtimeStatus === 'updated'">
                                osveženo
                            </template>

                            <template v-else>
                                greška
                            </template>
                        </span>

                        <span v-if="lastRealtimeUpdateAt">
                            Poslednja promena: {{ lastRealtimeUpdateAt }}
                        </span>
                    </div>
                </div>
            </header>

            <section class="grid gap-4 md:grid-cols-5">
                <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-4">
                    <p class="text-sm text-zinc-400">
                        Ukupno
                    </p>

                    <p class="mt-2 text-2xl font-semibold">
                        {{ tournament.matches_count }}
                    </p>
                </div>

                <div class="rounded-2xl border border-yellow-500/20 bg-yellow-500/5 p-4">
                    <p class="text-sm text-yellow-200">
                        Zakazano
                    </p>

                    <p class="mt-2 text-2xl font-semibold text-yellow-200">
                        {{ tournament.scheduled_matches_count }}
                    </p>
                </div>

                <div class="rounded-2xl border border-sky-500/20 bg-sky-500/5 p-4">
                    <p class="text-sm text-sky-200">
                        U toku
                    </p>

                    <p class="mt-2 text-2xl font-semibold text-sky-200">
                        {{ tournament.in_progress_matches_count }}
                    </p>
                </div>

                <div class="rounded-2xl border border-emerald-500/20 bg-emerald-500/5 p-4">
                    <p class="text-sm text-emerald-200">
                        Završeno
                    </p>

                    <p class="mt-2 text-2xl font-semibold text-emerald-200">
                        {{ tournament.finished_matches_count }}
                    </p>
                </div>

                <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-4">
                    <p class="text-sm text-zinc-400">
                        Anulirano
                    </p>

                    <p class="mt-2 text-2xl font-semibold">
                        {{ tournament.voided_matches_count }}
                    </p>
                </div>
            </section>

            <section class="rounded-3xl border border-white/10 bg-white/[0.03] p-5">
                <div class="flex flex-col gap-2 md:flex-row md:items-end md:justify-between">
                    <div>
                        <h2 class="text-2xl font-semibold">
                            Svi mečevi
                        </h2>

                        <p class="mt-1 text-sm text-zinc-400">
                            Redosled je isti kao u admin rasporedu.
                        </p>
                    </div>
                </div>

                <div
                    v-if="matches.length"
                    class="mt-5 overflow-hidden rounded-2xl border border-white/10"
                >
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[980px] text-left text-sm">
                            <thead class="bg-white/5 text-zinc-400">
                                <tr>
                                    <th class="px-3 py-3 font-medium">#</th>
                                    <th class="px-3 py-3 font-medium">Faza</th>
                                    <th class="px-3 py-3 font-medium">Kontekst</th>
                                    <th class="px-3 py-3 font-medium">Meč</th>
                                    <th class="px-3 py-3 font-medium">Resource</th>
                                    <th class="px-3 py-3 text-center font-medium">Leg</th>
                                    <th class="px-3 py-3 text-center font-medium">Rezultat</th>
                                    <th class="px-3 py-3 text-center font-medium">Pobednik</th>
                                    <th class="px-3 py-3 text-center font-medium">Status</th>
                                </tr>
                            </thead>

                            <tbody>
                                <tr
                                    v-for="match in matches"
                                    :key="match.id"
                                    class="border-t border-white/10"
                                >
                                    <td class="px-3 py-3 text-zinc-400">
                                        {{ match.scheduled_order ?? '-' }}
                                    </td>

                                    <td class="px-3 py-3">
                                        <span
                                            class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium"
                                            :class="stageClasses(match.stage)"
                                        >
                                            {{ match.stage_label }}
                                        </span>
                                    </td>

                                    <td class="px-3 py-3 text-zinc-300">
                                        {{ matchContextLabel(match) }}
                                    </td>

                                    <td class="px-3 py-3">
                                        <div class="grid grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)] items-center gap-2">
                                            <div>
                                                <p
                                                    class="font-semibold"
                                                    :class="[
                                                        match.participant_a?.is_withdrawn ? 'text-zinc-500 line-through' : '',
                                                        match.winner?.id === match.participant_a?.id ? 'text-emerald-200' : '',
                                                    ]"
                                                >
                                                    {{ match.participant_a?.display_name ?? 'TBD' }}
                                                </p>

                                                <p class="mt-1 text-xs text-zinc-500">
                                                    {{ match.participant_a?.group_position ?? '-' }}
                                                </p>
                                            </div>

                                            <span class="text-zinc-500">
                                                vs
                                            </span>

                                            <div class="text-right">
                                                <p
                                                    class="font-semibold"
                                                    :class="[
                                                        match.participant_b?.is_withdrawn ? 'text-zinc-500 line-through' : '',
                                                        match.winner?.id === match.participant_b?.id ? 'text-emerald-200' : '',
                                                    ]"
                                                >
                                                    {{ match.participant_b?.display_name ?? 'TBD' }}
                                                </p>

                                                <p class="mt-1 text-xs text-zinc-500">
                                                    {{ match.participant_b?.group_position ?? '-' }}
                                                </p>
                                            </div>
                                        </div>
                                    </td>

                                    <td class="px-3 py-3 text-zinc-400">
                                        {{ match.resource_name ?? '-' }}
                                    </td>

                                    <td class="px-3 py-3 text-center text-zinc-400">
                                        <template v-if="match.round_robin_leg">
                                            {{ match.round_robin_leg }}

                                            <span
                                                v-if="match.wins_required && match.stage !== 'group'"
                                                class="block text-xs text-zinc-500"
                                            >
                                                na {{ match.wins_required }}
                                            </span>
                                        </template>

                                        <template v-else>
                                            -
                                        </template>
                                    </td>

                                    <td class="px-3 py-3 text-center">
                                        <span class="rounded-xl bg-white/10 px-3 py-1 font-semibold">
                                            {{ match.score_a ?? '-' }} : {{ match.score_b ?? '-' }}
                                        </span>
                                    </td>

                                    <td class="px-3 py-3 text-center text-zinc-300">
                                        {{ match.winner?.display_name ?? '-' }}
                                    </td>

                                    <td class="px-3 py-3 text-center">
                                        <span
                                            class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium"
                                            :class="statusClasses(match.status)"
                                        >
                                            {{ match.status_label }}
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <p
                    v-else
                    class="mt-5 rounded-2xl border border-dashed border-white/10 p-4 text-sm text-zinc-400"
                >
                    Još nema generisanih mečeva.
                </p>
            </section>
        </main>
    </div>
</template>
