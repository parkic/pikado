<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

import type {
    TournamentGroup,
    TournamentGroupParticipant,
} from '@/types/tournament';

withDefaults(
    defineProps<{
        groups: TournamentGroup[];
        groupSize: number | null | undefined;
        editUrl: string;
        canEdit?: boolean;
    }>(),
    {
        canEdit: true,
    },
);

const participantForSlot = (
    group: TournamentGroup,
    slotNumber: number,
): TournamentGroupParticipant | undefined => {
    return group.participants.find((participant) => {
        return participant.group_position === `${group.name}${slotNumber}`;
    });
};
</script>

<template>
    <div
        class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
    >
        <div
            class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between"
        >
            <div>
                <h2 class="text-lg font-medium">Grupe</h2>

                <p class="mt-1 text-sm text-muted-foreground">
                    Pregled grupa i mesta za učesnike.
                </p>
            </div>

            <Link
                v-if="canEdit"
                :href="editUrl"
                class="inline-flex items-center justify-center rounded-lg border border-sidebar-border/70 px-4 py-2 text-sm font-medium transition hover:bg-muted dark:border-sidebar-border"
            >
                Izmeni grupe
            </Link>
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
                        {{ groupSize ?? 0 }} mesta
                    </span>
                </div>

                <div v-if="groupSize" class="mt-4 space-y-2">
                    <div
                        v-for="slotNumber in groupSize"
                        :key="`${group.id}-${slotNumber}`"
                        class="flex items-center justify-between gap-3 rounded-lg border border-sidebar-border/70 px-3 py-2 text-sm dark:border-sidebar-border"
                    >
                        <span class="font-medium">
                            {{ group.name }}{{ slotNumber }}
                        </span>

                        <span
                            v-if="participantForSlot(group, slotNumber)"
                            class="text-right"
                        >
                            {{
                                participantForSlot(group, slotNumber)
                                    ?.display_name
                            }}
                        </span>

                        <span v-else class="text-muted-foreground">
                            Prazno
                        </span>
                    </div>
                </div>

                <div
                    v-else
                    class="mt-4 rounded-lg border border-dashed border-sidebar-border/70 p-3 text-sm text-muted-foreground dark:border-sidebar-border"
                >
                    Broj mesta po grupi još nije podešen.
                </div>
            </div>
        </div>

        <div
            v-else
            class="mt-4 rounded-lg border border-dashed border-sidebar-border/70 p-4 text-sm text-muted-foreground dark:border-sidebar-border"
        >
            Grupe još nisu napravljene.
        </div>
    </div>
</template>
