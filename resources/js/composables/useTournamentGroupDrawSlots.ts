import { computed } from 'vue';

import type {
    TournamentGroupDrawData,
} from '@/types/tournament';

type UseTournamentGroupDrawSlotsOptions = {
    tournament: () => TournamentGroupDrawData;
    selectedGroupPosition: () => string;
    setSelectedGroupPosition: (value: string) => void;
    clearSlotError: () => void;
};

export const useTournamentGroupDrawSlots = ({
    tournament,
    selectedGroupPosition,
    setSelectedGroupPosition,
    clearSlotError,
}: UseTournamentGroupDrawSlotsOptions) => {
    const groupSize = computed<number>(() => {
        return tournament().settings.group_size ?? 0;
    });

    const activeGroupPosition = computed<string | null>(() => {
        return selectedGroupPosition()
            || tournament().next_slot?.group_position
            || null;
    });

    const isGroupDrawComplete = computed<boolean>(() => {
        return tournament().total_slots > 0
            && tournament().participants_count
                >= tournament().total_slots
            && !tournament().next_slot;
    });

    const hasGroupSetup = computed<boolean>(() => {
        return tournament().total_slots > 0;
    });

    const selectSlot = (
        groupName: string,
        slotNumber: number,
    ) => {
        setSelectedGroupPosition(
            `${groupName}${slotNumber}`,
        );

        clearSlotError();
    };

    const clearSelectedSlot = () => {
        setSelectedGroupPosition('');
        clearSlotError();
    };

    return {
        groupSize,
        activeGroupPosition,
        isGroupDrawComplete,
        hasGroupSetup,
        selectSlot,
        clearSelectedSlot,
    };
};
