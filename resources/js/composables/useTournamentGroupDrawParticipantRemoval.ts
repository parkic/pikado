import { router } from '@inertiajs/vue3';

import { confirmAction } from '@/composables/useConfirmDialog';
import type { TournamentGroupParticipant } from '@/types/tournament';

type UseTournamentGroupDrawParticipantRemovalOptions = {
    participantDeleteUrl: (participantId: number) => string;
};

export const useTournamentGroupDrawParticipantRemoval = ({
    participantDeleteUrl,
}: UseTournamentGroupDrawParticipantRemovalOptions) => {
    const removeParticipant = async (
        participant: TournamentGroupParticipant | undefined,
    ) => {
        if (!participant) {
            return;
        }

        const confirmed = await confirmAction({
            title: 'Izbriši učesnika iz grupe?',
            description: `${participant.display_name} će biti izbrisan sa pozicije ${participant.group_position}. Njegovi neodigrani mečevi ostaju skriveni na istim mestima u rasporedu, spremni za novog učesnika.`,
            confirmLabel: 'Izbriši učesnika',
            variant: 'destructive',
        });

        if (!confirmed) {
            return;
        }

        router.delete(participantDeleteUrl(participant.id));
    };

    return {
        removeParticipant,
    };
};
