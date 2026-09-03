<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue';

import type { TournamentKnockoutDrawParticipant } from '@/types/tournament';

const props = withDefaults(
    defineProps<{
        participants: TournamentKnockoutDrawParticipant[];
        selectedParticipantId?: number | null;
        waiting?: boolean;
        sizeClass?: string;
    }>(),
    {
        selectedParticipantId: null,
        waiting: false,
        sizeClass: 'w-64',
    },
);

const emit = defineEmits<{
    landed: [participantId: number];
}>();

const colors = [
    '#f97316',
    '#0ea5e9',
    '#10b981',
    '#8b5cf6',
    '#e11d48',
    '#eab308',
    '#14b8a6',
    '#6366f1',
    '#fb7185',
    '#22c55e',
    '#f59e0b',
    '#3b82f6',
];

const displayedParticipants = ref([...props.participants]);
const rotation = ref(0);
const isLanding = ref(false);
const lastHandledSelectionId = ref(props.selectedParticipantId);
let landingTimer: number | null = null;

const polarPoint = (angle: number, radius = 47): [number, number] => {
    const radians = ((angle - 90) * Math.PI) / 180;

    return [50 + radius * Math.cos(radians), 50 + radius * Math.sin(radians)];
};

const wedgePath = (startAngle: number, endAngle: number): string => {
    const [startX, startY] = polarPoint(startAngle);
    const [endX, endY] = polarPoint(endAngle);
    const largeArc = endAngle - startAngle > 180 ? 1 : 0;

    return [
        'M 50 50',
        `L ${startX} ${startY}`,
        `A 47 47 0 ${largeArc} 1 ${endX} ${endY}`,
        'Z',
    ].join(' ');
};

const segments = computed(() => {
    const total = displayedParticipants.value.length;

    if (total === 0) {
        return [];
    }

    const angle = 360 / total;
    const maximumLabelLength =
        total <= 4
            ? 16
            : total <= 6
              ? 18
              : total <= 12
                ? 20
                : total <= 16
                  ? 18
                  : 16;

    return displayedParticipants.value.map((participant, index) => ({
        participant,
        color: colors[index % colors.length],
        path:
            total === 1 ? null : wedgePath(index * angle, (index + 1) * angle),
        middleAngle: index * angle + angle / 2,
        label:
            participant.display_name.length > maximumLabelLength
                ? `${participant.display_name.slice(0, maximumLabelLength - 1)}…`
                : participant.display_name,
    }));
});

const labelFontSize = computed(() => {
    const total = displayedParticipants.value.length;

    if (total <= 4) {
        return 4;
    }

    if (total <= 6) {
        return 3.4;
    }

    if (total <= 8) {
        return 3;
    }

    if (total > 16) {
        return 2.05;
    }

    if (total > 12) {
        return 2.25;
    }

    return 2.55;
});

watch(
    () => props.selectedParticipantId,
    (selectedId, previousId) => {
        if (selectedId === previousId) {
            return;
        }

        lastHandledSelectionId.value = selectedId;

        if (!selectedId) {
            displayedParticipants.value = [...props.participants];

            return;
        }

        const selectedIndex = displayedParticipants.value.findIndex(
            (participant) => participant.participant_id === selectedId,
        );

        if (selectedIndex < 0 || displayedParticipants.value.length === 0) {
            displayedParticipants.value = [...props.participants];

            return;
        }

        const sliceAngle = 360 / displayedParticipants.value.length;
        const selectedMiddleAngle = selectedIndex * sliceAngle + sliceAngle / 2;
        const normalizedRotation = ((rotation.value % 360) + 360) % 360;
        const targetRotation = ((-selectedMiddleAngle % 360) + 360) % 360;
        const landingDelta =
            ((targetRotation - normalizedRotation + 360) % 360) + 360 * 5;

        isLanding.value = true;
        rotation.value += landingDelta;

        if (landingTimer !== null) {
            window.clearTimeout(landingTimer);
        }

        landingTimer = window.setTimeout(() => {
            isLanding.value = false;
            displayedParticipants.value = [...props.participants];
            emit('landed', selectedId);
            landingTimer = null;
        }, 3600);
    },
);

watch(
    () =>
        props.participants
            .map((participant) => participant.participant_id)
            .join(','),
    () => {
        if (
            !isLanding.value &&
            props.selectedParticipantId === lastHandledSelectionId.value
        ) {
            displayedParticipants.value = [...props.participants];
        }
    },
);

onBeforeUnmount(() => {
    if (landingTimer !== null) {
        window.clearTimeout(landingTimer);
    }
});
</script>

<template>
    <div
        class="relative mx-auto aspect-square max-w-full"
        :class="sizeClass"
        role="img"
        :aria-label="
            displayedParticipants.length
                ? `Točak sa ${displayedParticipants.length} preostalih učesnika`
                : 'Svi učesnici su izvučeni'
        "
    >
        <div
            class="pointer-events-none absolute top-[-0.35rem] left-1/2 z-20 h-0 w-0 -translate-x-1/2 border-x-[13px] border-t-[25px] border-x-transparent border-t-white drop-shadow-[0_3px_3px_rgba(0,0,0,0.65)]"
        />

        <div
            class="size-full rounded-full border-[7px] border-white/85 bg-zinc-900 p-1 shadow-2xl ring-4 shadow-black/50 ring-orange-400/20"
            :class="{ 'wheel-waiting': waiting && !isLanding }"
        >
            <svg
                v-if="displayedParticipants.length"
                viewBox="0 0 100 100"
                class="size-full overflow-visible rounded-full"
            >
                <g
                    class="wheel-face"
                    :class="{ 'wheel-face--landing': isLanding }"
                    :style="{ transform: `rotate(${rotation}deg)` }"
                >
                    <template
                        v-for="segment in segments"
                        :key="segment.participant.participant_id"
                    >
                        <circle
                            v-if="segment.path === null"
                            cx="50"
                            cy="50"
                            r="47"
                            :fill="segment.color"
                            stroke="rgba(255,255,255,0.38)"
                            stroke-width="0.55"
                        />
                        <path
                            v-else
                            :d="segment.path"
                            :fill="segment.color"
                            stroke="rgba(255,255,255,0.38)"
                            stroke-width="0.55"
                        />
                        <text
                            x="60.5"
                            y="51"
                            text-anchor="start"
                            dominant-baseline="middle"
                            fill="white"
                            stroke="rgba(0,0,0,0.5)"
                            stroke-width="0.32"
                            paint-order="stroke"
                            :font-size="labelFontSize"
                            font-weight="800"
                            letter-spacing="-0.04em"
                            :transform="`rotate(${segment.middleAngle - 90} 50 50)`"
                            class="drop-shadow-[0_1px_1px_rgba(0,0,0,0.7)] select-none"
                        >
                            <title>
                                {{ segment.participant.display_name }}
                            </title>
                            {{ segment.label }}
                        </text>
                    </template>
                </g>
            </svg>

            <div
                v-else
                class="flex size-full items-center justify-center rounded-full bg-zinc-950 text-center"
            >
                <span
                    class="px-6 text-sm font-black text-emerald-300 uppercase"
                >
                    Žreb kompletan
                </span>
            </div>
        </div>

        <div
            class="pointer-events-none absolute top-1/2 left-1/2 z-10 flex size-[22%] -translate-x-1/2 -translate-y-1/2 items-center justify-center rounded-full border-4 border-white/85 bg-zinc-950 shadow-xl"
        >
            <span
                class="text-center text-[0.58rem] leading-tight font-black text-orange-300 uppercase sm:text-xs"
            >
                {{ displayedParticipants.length }}<br />imena
            </span>
        </div>
    </div>
</template>

<style scoped>
.wheel-face {
    transform-origin: 50px 50px;
}

.wheel-face--landing {
    transition: transform 3.5s cubic-bezier(0.12, 0.62, 0.08, 1);
}

.wheel-waiting {
    animation: wheel-ready-pulse 0.8s ease-in-out infinite alternate;
}

@keyframes wheel-ready-pulse {
    from {
        box-shadow:
            0 0 0 0 rgba(249, 115, 22, 0.2),
            0 24px 48px rgba(0, 0, 0, 0.45);
    }
    to {
        box-shadow:
            0 0 0 10px rgba(249, 115, 22, 0.06),
            0 24px 48px rgba(0, 0, 0, 0.55);
    }
}
</style>
