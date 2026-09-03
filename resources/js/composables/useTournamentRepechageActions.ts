import { router } from '@inertiajs/vue3';

import { confirmAction } from '@/composables/useConfirmDialog';
import type { TournamentRepechageParticipant } from '@/types/tournament';

type UseTournamentRepechageActionsOptions = {
    participantOutcomeUrl: (participantId: number) => string;
    completeRepechageUrl: string;
};

export const useTournamentRepechageActions = ({
    participantOutcomeUrl,
    completeRepechageUrl,
}: UseTournamentRepechageActionsOptions) => {
    const updateRepechageOutcome = (
        participant: TournamentRepechageParticipant,
        event: Event,
    ) => {
        const target = event.target as HTMLSelectElement;

        router.patch(
            participantOutcomeUrl(participant.participant_id),
            {
                repechage_outcome_status: target.value || null,
            },
            {
                preserveScroll: true,
                preserveState: false,
            },
        );
    };

    const completeRepechage = async () => {
        const confirmed = await confirmAction({
            title: 'Završi repasaž?',
            description:
                'Svi neodlučeni učesnici biće označeni kao ispali, a prolaznici ulaze u nokaut.',
            confirmLabel: 'Završi repasaž',
            variant: 'warning',
        });

        if (!confirmed) {
            return;
        }

        router.post(
            completeRepechageUrl,
            {},
            {
                preserveScroll: true,
            },
        );
    };

    return {
        updateRepechageOutcome,
        completeRepechage,
    };
};
