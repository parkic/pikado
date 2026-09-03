<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    EllipsisVertical,
    Pencil,
    RefreshCw,
    RotateCcw,
    Trash2,
    UserPlus,
    UserX,
} from '@lucide/vue';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

import type {
    TournamentGroup,
    TournamentGroupParticipant,
} from '@/types/tournament';

const props = defineProps<{
    groups: TournamentGroup[];
    groupSize: number;
    canManageRoster: boolean;
    canManageWithdrawals: boolean;
    participantEditUrl: (participantId: number) => string;
    participantReplaceUrl: (participantId: number) => string;
}>();

const emit = defineEmits<{
    'select-slot': [groupName: string, slotNumber: number];
    'remove-participant': [participant: TournamentGroupParticipant];
    'withdraw-participant': [participant: TournamentGroupParticipant];
    'restore-participant': [participant: TournamentGroupParticipant];
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
                Upravljaj učesnicima i slobodnim mestima po grupama.
            </p>
        </div>

        <div
            v-if="groups.length"
            class="mt-4 grid gap-4 lg:grid-cols-2 2xl:grid-cols-4"
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
                        class="grid min-h-14 grid-cols-[auto_minmax(0,1fr)_auto] items-center gap-3 rounded-xl border border-sidebar-border/70 px-3 py-2.5 text-sm transition hover:border-primary/25 dark:border-sidebar-border"
                    >
                        <span
                            class="flex size-8 items-center justify-center rounded-lg bg-muted font-semibold text-muted-foreground"
                        >
                            {{ group.name }}{{ slotNumber }}
                        </span>

                        <div
                            v-if="participantForSlot(group, slotNumber)"
                            class="contents"
                        >
                            <div class="min-w-0 text-left">
                                <p
                                    class="truncate font-medium"
                                    :class="
                                        participantForSlot(group, slotNumber)
                                            ?.status === 'withdrawn'
                                            ? 'text-muted-foreground line-through'
                                            : ''
                                    "
                                >
                                    {{
                                        participantForSlot(group, slotNumber)
                                            ?.display_name
                                    }}
                                </p>

                                <span
                                    v-if="
                                        participantForSlot(group, slotNumber)
                                            ?.status === 'withdrawn'
                                    "
                                    class="mt-1 inline-flex rounded-full bg-red-500/10 px-2 py-0.5 text-[10px] font-medium text-red-700 dark:text-red-300"
                                >
                                    Odustao
                                </span>
                            </div>

                            <DropdownMenu
                                v-if="
                                    canManageRoster ||
                                    canManageWithdrawals ||
                                    participantForSlot(group, slotNumber)
                                        ?.can_replace
                                "
                            >
                                <DropdownMenuTrigger as-child>
                                    <button
                                        type="button"
                                        class="inline-flex size-8 shrink-0 items-center justify-center rounded-lg text-muted-foreground transition hover:bg-muted hover:text-foreground"
                                        aria-label="Otvori akcije učesnika"
                                        title="Akcije učesnika"
                                    >
                                        <EllipsisVertical class="size-4" />
                                    </button>
                                </DropdownMenuTrigger>

                                <DropdownMenuContent align="end" class="w-52">
                                    <DropdownMenuItem
                                        v-if="canManageRoster"
                                        :as-child="true"
                                    >
                                        <Link
                                            :href="
                                                editUrlForParticipant(
                                                    participantForSlot(
                                                        group,
                                                        slotNumber,
                                                    ),
                                                )
                                            "
                                            class="flex w-full cursor-pointer items-center"
                                        >
                                            <Pencil class="mr-2 size-3.5" />
                                            Izmeni
                                        </Link>
                                    </DropdownMenuItem>

                                    <DropdownMenuItem
                                        v-if="
                                            participantForSlot(
                                                group,
                                                slotNumber,
                                            )?.can_replace
                                        "
                                        :as-child="true"
                                    >
                                        <Link
                                            :href="
                                                replaceUrlForParticipant(
                                                    participantForSlot(
                                                        group,
                                                        slotNumber,
                                                    ),
                                                )
                                            "
                                            class="flex w-full cursor-pointer items-center"
                                        >
                                            <RefreshCw class="mr-2 size-3.5" />
                                            Zameni
                                        </Link>
                                    </DropdownMenuItem>

                                    <DropdownMenuItem
                                        v-if="
                                            canManageWithdrawals &&
                                            participantForSlot(
                                                group,
                                                slotNumber,
                                            )?.status !== 'withdrawn'
                                        "
                                        class="cursor-pointer"
                                        @select="
                                            emit(
                                                'withdraw-participant',
                                                participantForSlot(
                                                    group,
                                                    slotNumber,
                                                )!,
                                            )
                                        "
                                    >
                                        <UserX class="mr-2 size-3.5" />
                                        Označi kao odustao
                                    </DropdownMenuItem>

                                    <DropdownMenuItem
                                        v-if="
                                            canManageWithdrawals &&
                                            participantForSlot(
                                                group,
                                                slotNumber,
                                            )?.status === 'withdrawn'
                                        "
                                        class="cursor-pointer"
                                        @select="
                                            emit(
                                                'restore-participant',
                                                participantForSlot(
                                                    group,
                                                    slotNumber,
                                                )!,
                                            )
                                        "
                                    >
                                        <RotateCcw class="mr-2 size-3.5" />
                                        Vrati u turnir
                                    </DropdownMenuItem>

                                    <DropdownMenuSeparator
                                        v-if="
                                            canManageRoster &&
                                            participantForSlot(
                                                group,
                                                slotNumber,
                                            )?.can_remove
                                        "
                                    />

                                    <DropdownMenuItem
                                        v-if="
                                            canManageRoster &&
                                            participantForSlot(
                                                group,
                                                slotNumber,
                                            )?.can_remove
                                        "
                                        class="cursor-pointer text-red-600 focus:text-red-600 dark:text-red-400 dark:focus:text-red-400"
                                        @select="
                                            emit(
                                                'remove-participant',
                                                participantForSlot(
                                                    group,
                                                    slotNumber,
                                                )!,
                                            )
                                        "
                                    >
                                        <Trash2 class="mr-2 size-3.5" />
                                        Izbriši
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </div>

                        <button
                            v-else-if="canManageRoster"
                            type="button"
                            class="col-span-2 inline-flex items-center justify-end gap-1.5 text-xs font-medium text-primary hover:underline"
                            @click="emit('select-slot', group.name, slotNumber)"
                        >
                            <UserPlus class="size-3.5" />
                            Dodaj ovde
                        </button>

                        <span v-else class="text-xs text-muted-foreground">
                            Prazno
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <div
            v-else
            class="mt-4 rounded-lg border border-dashed border-sidebar-border/70 p-4 text-sm text-muted-foreground dark:border-sidebar-border"
        >
            Grupe još nisu napravljene. Prvo podesi grupe.
        </div>
    </div>
</template>
