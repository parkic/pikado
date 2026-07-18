import { router } from '@inertiajs/vue3';

import type {
    TournamentGroupParticipant,
} from '@/types/tournament';

type UseTournamentGroupDrawParticipantRemovalOptions = {
    participantDeleteUrl: (
        participantId: number,
    ) => string;
};

export const useTournamentGroupDrawParticipantRemoval = ({
    participantDeleteUrl,
}: UseTournamentGroupDrawParticipantRemovalOptions) => {
    const removeParticipant = (
        participant: TournamentGroupParticipant | undefined,
    ) => {
        if (!participant) {
            return;
        }

        const confirmed = window.confirm(
            `Da li želiš da ukloniš ${participant.display_name} iz ${participant.group_position}?`,
        );

        if (!confirmed) {
            return;
        }

        router.delete(
            participantDeleteUrl(participant.id),
        );
    };

    return {
        removeParticipant,
    };
};
