<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { GripVertical, RotateCcw } from '@lucide/vue';
import { computed, ref, watch } from 'vue';

import TournamentNameWheel from '@/components/tournaments/TournamentNameWheel.vue';
import { confirmAction } from '@/composables/useConfirmDialog';
import type {
    TournamentKnockoutDraw,
    TournamentKnockoutDrawParticipant,
} from '@/types/tournament';

const props = defineProps<{
    draw: TournamentKnockoutDraw;
    updateSeedingUrl: string;
    drawNextUrl: string;
    resetUrl: string;
}>();

const draggedParticipant = ref<{
    participant: TournamentKnockoutDrawParticipant;
    list: 'seeded' | 'unseeded';
} | null>(null);
const isDrawing = ref(false);
const displayedLatestParticipant = ref(props.draw.latest_participant);
const displayedSlots = ref([...props.draw.slots]);
const displayedPhase = ref(props.draw.phase);
const displayedComplete = ref(props.draw.complete);
const displayedRemainingCount = ref(props.draw.remaining_count);
const displayedDrawnIds = ref([
    ...props.draw.drawn_seeded_ids,
    ...props.draw.drawn_unseeded_ids,
]);

const phaseLabel = computed(() => {
    if (displayedPhase.value === 'seeded') {
        return 'Izvlačenje nosilaca';
    }

    if (displayedPhase.value === 'unseeded') {
        return 'Izvlačenje nenosilaca';
    }

    return 'Žreb je kompletan';
});

const drawnIdSet = computed(() => new Set(displayedDrawnIds.value));

const activePool = computed(() =>
    (displayedPhase.value === 'seeded'
        ? props.draw.seeded
        : displayedPhase.value === 'unseeded'
          ? props.draw.unseeded
          : []
    ).filter(
        (participant) => !drawnIdSet.value.has(participant.participant_id),
    ),
);

const startDrag = (
    participant: TournamentKnockoutDrawParticipant,
    list: 'seeded' | 'unseeded',
) => {
    if (!props.draw.can_manage || !props.draw.can_customize_seeding) {
        return;
    }

    draggedParticipant.value = { participant, list };
};

const swapWith = (
    target: TournamentKnockoutDrawParticipant,
    targetList: 'seeded' | 'unseeded',
) => {
    const dragged = draggedParticipant.value;
    draggedParticipant.value = null;

    if (!dragged || dragged.list === targetList) {
        return;
    }

    const seededIds = props.draw.seeded.map(
        (participant) => participant.participant_id,
    );

    if (dragged.list === 'seeded') {
        seededIds.splice(
            seededIds.indexOf(dragged.participant.participant_id),
            1,
            target.participant_id,
        );
    } else {
        seededIds.splice(
            seededIds.indexOf(target.participant_id),
            1,
            dragged.participant.participant_id,
        );
    }

    router.patch(
        props.updateSeedingUrl,
        { seeded_participant_ids: seededIds },
        { preserveScroll: true },
    );
};

const drawNext = () => {
    if (isDrawing.value || props.draw.complete) {
        return;
    }

    isDrawing.value = true;

    router.post(
        props.drawNextUrl,
        {},
        {
            preserveScroll: true,
            onError: () => {
                isDrawing.value = false;
            },
        },
    );
};

const finishDraw = (): void => {
    displayedLatestParticipant.value = props.draw.latest_participant;
    displayedSlots.value = [...props.draw.slots];
    displayedPhase.value = props.draw.phase;
    displayedComplete.value = props.draw.complete;
    displayedRemainingCount.value = props.draw.remaining_count;
    displayedDrawnIds.value = [
        ...props.draw.drawn_seeded_ids,
        ...props.draw.drawn_unseeded_ids,
    ];
    isDrawing.value = false;
};

watch(
    () => props.draw.latest_participant?.participant_id ?? null,
    (participantId) => {
        if (participantId === null) {
            displayedLatestParticipant.value = null;
            displayedSlots.value = [...props.draw.slots];
            displayedPhase.value = props.draw.phase;
            displayedComplete.value = props.draw.complete;
            displayedRemainingCount.value = props.draw.remaining_count;
            displayedDrawnIds.value = [
                ...props.draw.drawn_seeded_ids,
                ...props.draw.drawn_unseeded_ids,
            ];
            isDrawing.value = false;
        }
    },
);

const resetDraw = async () => {
    const confirmed = await confirmAction({
        title: 'Ponovi izvlačenje?',
        description:
            'Sva do sada izvučena imena biće vraćena u bubanj. Liste nosilaca i nenosilaca ostaju iste.',
        confirmLabel: 'Vrati na početak',
        variant: 'destructive',
    });

    if (!confirmed) {
        return;
    }

    router.delete(props.resetUrl, { preserveScroll: true });
};
</script>

<template>
    <section
        v-if="draw.enabled"
        class="overflow-hidden rounded-2xl border border-orange-500/25 bg-gradient-to-br from-orange-500/[0.08] via-background to-background"
    >
        <header
            class="flex flex-col gap-4 border-b border-border/70 p-5 lg:flex-row lg:items-center lg:justify-between"
        >
            <div>
                <p
                    class="text-xs font-bold tracking-[0.18em] text-orange-500 uppercase"
                >
                    Javni žreb
                </p>
                <h2 class="mt-1 text-2xl font-bold">Nosioci i nenosioci</h2>
                <p class="mt-1 max-w-3xl text-sm text-muted-foreground">
                    Najboljeplasirani su automatski nosioci. Pre početka
                    izvlačenja prevuci igrača na karticu u drugoj listi da ih
                    zameniš.
                </p>
            </div>

            <button
                v-if="draw.can_manage && draw.started"
                type="button"
                class="inline-flex items-center justify-center gap-2 rounded-xl border border-border px-4 py-2 text-sm font-semibold transition hover:bg-muted"
                @click="resetDraw"
            >
                <RotateCcw class="size-4" />
                Ponovi izvlačenje
            </button>
        </header>

        <div
            class="grid gap-5 p-5 xl:grid-cols-[minmax(0,0.9fr)_minmax(0,0.9fr)_minmax(20rem,1.25fr)]"
        >
            <div
                class="rounded-xl border border-emerald-500/20 bg-emerald-500/[0.04] p-3"
            >
                <div class="mb-3 flex items-center justify-between">
                    <h3
                        class="font-bold text-emerald-700 dark:text-emerald-300"
                    >
                        Nosioci
                    </h3>
                    <span class="text-xs text-muted-foreground">
                        {{ draw.seeded.length }} igrača
                    </span>
                </div>
                <div class="space-y-2">
                    <article
                        v-for="participant in draw.seeded"
                        :key="participant.participant_id"
                        draggable="true"
                        class="flex items-center gap-2 rounded-xl border border-border/70 bg-background/80 p-3 transition"
                        :class="{
                            'opacity-45': drawnIdSet.has(
                                participant.participant_id,
                            ),
                            'cursor-grab hover:border-emerald-500/50':
                                draw.can_manage && draw.can_customize_seeding,
                        }"
                        @dragstart="startDrag(participant, 'seeded')"
                        @dragover.prevent
                        @drop="swapWith(participant, 'seeded')"
                    >
                        <GripVertical
                            v-if="draw.can_manage && draw.can_customize_seeding"
                            class="size-4 shrink-0 text-muted-foreground"
                        />
                        <span
                            class="inline-flex size-7 shrink-0 items-center justify-center rounded-lg bg-emerald-500/15 text-xs font-bold text-emerald-700 dark:text-emerald-300"
                        >
                            {{ participant.group_name
                            }}{{ participant.group_rank }}
                        </span>
                        <span
                            class="min-w-0 flex-1 leading-tight font-semibold break-words"
                            :title="participant.display_name"
                        >
                            {{ participant.display_name }}
                        </span>
                    </article>
                </div>
            </div>

            <div
                class="rounded-xl border border-sky-500/20 bg-sky-500/[0.04] p-3"
            >
                <div class="mb-3 flex items-center justify-between">
                    <h3 class="font-bold text-sky-700 dark:text-sky-300">
                        Nenosioci
                    </h3>
                    <span class="text-xs text-muted-foreground">
                        {{ draw.unseeded.length }} igrača
                    </span>
                </div>
                <div class="space-y-2">
                    <article
                        v-for="participant in draw.unseeded"
                        :key="participant.participant_id"
                        draggable="true"
                        class="flex items-center gap-2 rounded-xl border border-border/70 bg-background/80 p-3 transition"
                        :class="{
                            'opacity-45': drawnIdSet.has(
                                participant.participant_id,
                            ),
                            'cursor-grab hover:border-sky-500/50':
                                draw.can_manage && draw.can_customize_seeding,
                        }"
                        @dragstart="startDrag(participant, 'unseeded')"
                        @dragover.prevent
                        @drop="swapWith(participant, 'unseeded')"
                    >
                        <GripVertical
                            v-if="draw.can_manage && draw.can_customize_seeding"
                            class="size-4 shrink-0 text-muted-foreground"
                        />
                        <span
                            class="inline-flex size-7 shrink-0 items-center justify-center rounded-lg bg-sky-500/15 text-xs font-bold text-sky-700 dark:text-sky-300"
                        >
                            {{ participant.group_name
                            }}{{ participant.group_rank }}
                        </span>
                        <span
                            class="min-w-0 flex-1 leading-tight font-semibold break-words"
                            :title="participant.display_name"
                        >
                            {{ participant.display_name }}
                        </span>
                    </article>
                </div>
            </div>

            <div
                class="flex flex-col rounded-xl border border-orange-500/25 bg-black/[0.03] p-4 dark:bg-black/20"
            >
                <div class="text-center">
                    <p class="text-sm font-bold text-orange-500">
                        {{ phaseLabel }}
                    </p>
                    <TournamentNameWheel
                        class="mt-5"
                        :participants="activePool"
                        :selected-participant-id="
                            draw.latest_participant?.participant_id
                        "
                        :waiting="isDrawing"
                        size-class="w-64 md:w-72 2xl:w-80"
                        @landed="finishDraw"
                    />

                    <div
                        v-if="displayedLatestParticipant"
                        class="mx-auto mt-4 rounded-xl border border-orange-500/25 bg-orange-500/10 px-3 py-2"
                    >
                        <p
                            class="text-xs font-bold text-muted-foreground uppercase"
                        >
                            Poslednji izvučen
                        </p>
                        <p
                            class="mt-0.5 font-black text-orange-600 dark:text-orange-300"
                        >
                            {{ displayedLatestParticipant.display_name }}
                        </p>
                    </div>
                    <button
                        v-if="draw.can_manage && !displayedComplete"
                        type="button"
                        :disabled="isDrawing"
                        class="mt-4 w-full rounded-xl bg-orange-500 px-4 py-3 font-bold text-white transition hover:bg-orange-400 disabled:opacity-50"
                        @click="drawNext"
                    >
                        {{
                            isDrawing
                                ? 'Točak se vrti…'
                                : `Izvuci sledećeg (${displayedRemainingCount})`
                        }}
                    </button>
                    <p v-else class="mt-4 font-bold text-emerald-600">
                        Svi učesnici su izvučeni — kostur je spreman.
                    </p>
                </div>

                <div class="mt-5 space-y-2">
                    <article
                        v-for="slot in displayedSlots"
                        :key="slot.position"
                        class="grid grid-cols-[2rem_minmax(0,1fr)_auto_minmax(0,1fr)] items-center gap-2 rounded-lg border border-border/60 bg-background/70 p-2 text-sm"
                    >
                        <span
                            class="text-center font-bold text-muted-foreground"
                        >
                            {{ slot.position }}
                        </span>
                        <span
                            class="line-clamp-2 min-w-0 leading-tight font-semibold break-words"
                            :title="slot.seeded?.display_name"
                        >
                            {{ slot.seeded?.display_name ?? '—' }}
                        </span>
                        <span class="text-muted-foreground">vs</span>
                        <span
                            class="line-clamp-2 min-w-0 text-right leading-tight font-semibold break-words"
                            :title="slot.unseeded?.display_name"
                        >
                            {{ slot.unseeded?.display_name ?? '—' }}
                        </span>
                    </article>
                </div>
            </div>
        </div>
    </section>
</template>
