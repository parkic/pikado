import { router } from '@inertiajs/vue3';

import type {
    TournamentRepechageParticipant,
} from '@/types/tournament';

type UseTournamentRepechageActionsOptions = {
    participantOutcomeUrl: (
        participantId: number,
    ) => string;
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
            participantOutcomeUrl(
                participant.participant_id,
            ),
            {
                repechage_outcome_status:
                    target.value || null,
            },
            {
                preserveScroll: true,
                preserveState: false,
            },
        );
    };

    const completeRepechage = () => {
        const confirmed = window.confirm(
            'Da li želiš da završiš repasaž? Svi neodlučeni učesnici iz repasaža biće označeni kao ispali.',
        );

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
