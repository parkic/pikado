<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import TournamentNameWheel from '@/components/tournaments/TournamentNameWheel.vue';
import type { TournamentKnockoutDraw } from '@/types/tournament';

const props = defineProps<{ draw: TournamentKnockoutDraw }>();

const celebrationKey = ref(0);
const displayedLatestParticipant = ref(props.draw.latest_participant);
const displayedSlots = ref([...props.draw.slots]);
const displayedPhase = ref(props.draw.phase);
const displayedRemainingCount = ref(props.draw.remaining_count);
const displayedDrawnIds = ref([
    ...props.draw.drawn_seeded_ids,
    ...props.draw.drawn_unseeded_ids,
]);
const drawnIds = computed(() => new Set(displayedDrawnIds.value));
const activePool = computed(() =>
    (displayedPhase.value === 'seeded'
        ? props.draw.seeded
        : displayedPhase.value === 'unseeded'
          ? props.draw.unseeded
          : []
    ).filter((participant) => !drawnIds.value.has(participant.participant_id)),
);
const phaseLabel = computed(() => {
    if (displayedPhase.value === 'seeded') {
        return 'Izvlačimo nosioce';
    }

    if (displayedPhase.value === 'unseeded') {
        return 'Izvlačimo nenosioce';
    }

    return 'Žreb je završen';
});

watch(
    () => props.draw.latest_participant?.participant_id,
    (next) => {
        if (!next) {
            displayedLatestParticipant.value = null;
            displayedSlots.value = [...props.draw.slots];
            displayedPhase.value = props.draw.phase;
            displayedRemainingCount.value = props.draw.remaining_count;
            displayedDrawnIds.value = [
                ...props.draw.drawn_seeded_ids,
                ...props.draw.drawn_unseeded_ids,
            ];
        }
    },
);

const revealSelection = (): void => {
    displayedLatestParticipant.value = props.draw.latest_participant;
    displayedSlots.value = [...props.draw.slots];
    displayedPhase.value = props.draw.phase;
    displayedRemainingCount.value = props.draw.remaining_count;
    displayedDrawnIds.value = [
        ...props.draw.drawn_seeded_ids,
        ...props.draw.drawn_unseeded_ids,
    ];
    celebrationKey.value++;
};
</script>

<template>
    <section
        v-if="draw.enabled && (draw.started || !draw.complete)"
        class="relative overflow-hidden rounded-2xl border border-orange-400/30 bg-gradient-to-br from-orange-500/[0.13] via-zinc-950 to-amber-500/[0.08] p-4 md:rounded-3xl md:p-8"
    >
        <div
            v-if="displayedLatestParticipant"
            :key="celebrationKey"
            class="pointer-events-none absolute inset-0 overflow-hidden"
            aria-hidden="true"
        >
            <i
                v-for="index in 96"
                :key="index"
                class="confetti absolute top-[-8%] h-3 w-2 rounded-sm"
                :style="{
                    left: `${(index * 37) % 100}%`,
                    width: `${5 + (index % 4) * 2}px`,
                    height: `${8 + (index % 5) * 2}px`,
                    animationDelay: `${(index % 12) * 0.045}s`,
                    animationDuration: `${1.9 + (index % 6) * 0.16}s`,
                    transform: `rotate(${(index * 47) % 180}deg)`,
                    backgroundColor: [
                        '#fb923c',
                        '#facc15',
                        '#34d399',
                        '#38bdf8',
                        '#fb7185',
                        '#a78bfa',
                    ][index % 6],
                }"
            />
        </div>

        <div
            class="relative grid items-center gap-6 xl:grid-cols-[minmax(20rem,0.85fr)_minmax(0,1.15fr)]"
        >
            <div class="text-center xl:text-left">
                <p
                    class="text-xs font-black tracking-[0.24em] text-orange-300 uppercase md:text-sm"
                >
                    Lucky wheel · nokaut žreb
                </p>
                <h2 class="mt-2 text-2xl font-black md:text-4xl 2xl:text-5xl">
                    {{ phaseLabel }}
                </h2>
                <p class="mt-2 text-sm text-zinc-400 md:text-base 2xl:text-xl">
                    Još {{ displayedRemainingCount }} učesnika čeka izvlačenje.
                    Novi izbor se pojavljuje uživo.
                </p>

                <TournamentNameWheel
                    class="mt-6 xl:mx-0"
                    :participants="activePool"
                    :selected-participant-id="
                        draw.latest_participant?.participant_id
                    "
                    size-class="w-64 md:w-80 2xl:w-96"
                    @landed="revealSelection"
                />

                <div
                    v-if="displayedLatestParticipant"
                    class="mx-auto mt-5 max-w-sm rounded-2xl border border-orange-300/25 bg-orange-400/10 px-4 py-3 text-center xl:mx-0"
                >
                    <span class="text-xs font-bold text-zinc-500 uppercase">
                        Poslednji izvučen
                    </span>
                    <strong
                        class="mt-1 block text-xl leading-tight text-orange-200 md:text-2xl 2xl:text-3xl"
                    >
                        {{ displayedLatestParticipant.display_name }}
                    </strong>
                    <span class="mt-1 block text-sm font-bold text-zinc-400">
                        {{ displayedLatestParticipant.group_name
                        }}{{ displayedLatestParticipant.group_rank }}
                    </span>
                </div>
            </div>

            <div>
                <div class="mb-3 flex items-end justify-between gap-3">
                    <div>
                        <p class="text-xs font-bold text-zinc-500 uppercase">
                            Kostur u nastajanju
                        </p>
                        <h3 class="text-lg font-bold md:text-2xl">
                            Izvučene pozicije
                        </h3>
                    </div>
                    <span class="text-sm font-semibold text-zinc-400">
                        {{
                            displayedSlots.filter((slot) => slot.seeded).length
                        }}
                        / {{ displayedSlots.length }} nosilaca
                    </span>
                </div>

                <div class="grid gap-2 sm:grid-cols-2">
                    <article
                        v-for="slot in displayedSlots"
                        :key="slot.position"
                        class="rounded-xl border border-white/10 bg-black/35 p-3 md:p-4"
                    >
                        <p
                            class="mb-2 text-[10px] font-black tracking-wider text-orange-300 uppercase"
                        >
                            Par {{ slot.position }}
                        </p>
                        <div class="space-y-1.5">
                            <div
                                class="flex min-h-8 items-center justify-between gap-2 rounded-lg bg-emerald-400/[0.09] px-2.5 py-1.5"
                            >
                                <span
                                    class="line-clamp-2 min-w-0 leading-tight font-bold break-words 2xl:text-lg"
                                    :title="slot.seeded?.display_name"
                                >
                                    {{ slot.seeded?.display_name ?? '—' }}
                                </span>
                                <span
                                    v-if="slot.seeded"
                                    class="shrink-0 text-xs text-zinc-500"
                                >
                                    {{ slot.seeded.group_name
                                    }}{{ slot.seeded.group_rank }}
                                </span>
                            </div>
                            <div
                                class="flex min-h-8 items-center justify-between gap-2 rounded-lg bg-sky-400/[0.08] px-2.5 py-1.5"
                            >
                                <span
                                    class="line-clamp-2 min-w-0 leading-tight font-bold break-words 2xl:text-lg"
                                    :title="slot.unseeded?.display_name"
                                >
                                    {{ slot.unseeded?.display_name ?? '—' }}
                                </span>
                                <span
                                    v-if="slot.unseeded"
                                    class="shrink-0 text-xs text-zinc-500"
                                >
                                    {{ slot.unseeded.group_name
                                    }}{{ slot.unseeded.group_rank }}
                                </span>
                            </div>
                        </div>
                    </article>
                </div>
            </div>
        </div>
    </section>
</template>

<style scoped>
@keyframes confetti-fall {
    0% {
        transform: translateY(-10vh) rotate(0deg);
        opacity: 1;
    }
    100% {
        transform: translateY(105vh) rotate(760deg);
        opacity: 0;
    }
}

.confetti {
    animation: confetti-fall 2.2s cubic-bezier(0.18, 0.8, 0.32, 1) both;
}
</style>
