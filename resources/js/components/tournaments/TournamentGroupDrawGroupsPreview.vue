<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

import type {
    TournamentGroup,
    TournamentGroupParticipant,
} from '@/types/tournament';

const props = defineProps<{
    groups: TournamentGroup[];
    groupSize: number;
    participantEditUrl: (participantId: number) => string;
    participantReplaceUrl: (participantId: number) => string;
}>();

const emit = defineEmits<{
    'select-slot': [groupName: string, slotNumber: number];
    'remove-participant': [participant: TournamentGroupParticipant | undefined];
}>();

const participantForSlot = (
    group: TournamentGroup,
    slotNumber: number,
): TournamentGroupParticipant | undefined => {
    return group.participants.find((participant) => {
        return participant.group_position === `${group.name}${slotNumber}`;
    });
};

const editUrlForParticipant = (
    participant: TournamentGroupParticipant | undefined,
): string => {
    if (!participant) {
        return '#';
    }

    return props.participantEditUrl(participant.id);
};

const replaceUrlForParticipant = (
    participant: TournamentGroupParticipant | undefined,
): string => {
    if (!participant) {
        return '#';
    }

    return props.participantReplaceUrl(participant.id);
};
</script>

<template>
    <div
        id="groups-preview"
        class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
    >
        <div>
            <h2 class="text-lg font-medium">Grupe</h2>

            <p class="mt-1 text-sm text-muted-foreground">
                TV/public prikaz ćemo kasnije povezati na isti raspored.
            </p>
        </div>

        <div
            v-if="groups.length"
            class="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-4"
        >
            <div
                v-for="group in groups"
                :key="group.id"
                class="rounded-lg border border-sidebar-border/70 p-4 dark:border-sidebar-border"
            >
                <div class="flex items-center justify-between gap-4">
                    <h3 class="text-lg font-medium">Grupa {{ group.name }}</h3>

                    <span class="text-sm text-muted-foreground">
                        {{ groupSize }} mesta
                    </span>
                </div>

                <div class="mt-4 space-y-2">
                    <div
                        v-for="slotNumber in groupSize"
                        :key="`${group.id}-${slotNumber}`"
                        class="flex items-center justify-between gap-3 rounded-lg border border-sidebar-border/70 px-3 py-2 text-sm dark:border-sidebar-border"
                    >
                        <span class="font-medium">
                            {{ group.name }}{{ slotNumber }}
                        </span>

                        <div
                            v-if="participantForSlot(group, slotNumber)"
                            class="flex items-center gap-2 text-right"
                        >
                            <span>
                                {{
                                    participantForSlot(group, slotNumber)
                                        ?.display_name
                                }}
                            </span>

                            <Link
                                :href="
                                    editUrlForParticipant(
                                        participantForSlot(group, slotNumber),
                                    )
                                "
                                class="text-xs font-medium text-primary hover:underline"
                            >
                                Izmeni
                            </Link>

                            <Link
                                v-if="
                                    participantForSlot(group, slotNumber)
                                        ?.can_replace
                                "
                                :href="
                                    replaceUrlForParticipant(
                                        participantForSlot(group, slotNumber),
                                    )
                                "
                                class="text-xs font-medium text-yellow-700 hover:underline dark:text-yellow-300"
                            >
                                Zameni
                            </Link>

                            <button
                                type="button"
                                class="text-xs font-medium text-red-600 hover:underline"
                                @click="
                                    emit(
                                        'remove-participant',
                                        participantForSlot(group, slotNumber),
                                    )
                                "
                            >
                                Ukloni
                            </button>
                        </div>

                        <button
                            v-else
                            type="button"
                            class="text-xs font-medium text-primary hover:underline"
                            @click="emit('select-slot', group.name, slotNumber)"
                        >
                            Dodaj ovde
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div
            v-else
            class="mt-4 rounded-lg border border-dashed border-sidebar-border/70 p-4 text-sm text-muted-foreground dark:border-sidebar-border"
        >
            Grupe još nisu napravljene. Prvo uradi setup grupa.
        </div>
    </div>
</template>
