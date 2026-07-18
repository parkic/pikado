import { router } from '@inertiajs/vue3';

import type {
    TournamentStandingRow,
} from '@/types/tournament';

type UseTournamentStandingsActionsOptions = {
    qualificationOverrideUrl: (
        participantId: number,
    ) => string;
    participantWithdrawUrl: (
        participantId: number,
    ) => string;
    participantRestoreUrl: (
        participantId: number,
    ) => string;
};

export const useTournamentStandingsActions = ({
    qualificationOverrideUrl,
    participantWithdrawUrl,
    participantRestoreUrl,
}: UseTournamentStandingsActionsOptions) => {
    const updateQualificationOverride = (
        row: TournamentStandingRow,
        event: Event,
    ) => {
        const target = event.target as HTMLSelectElement;

        router.patch(
            qualificationOverrideUrl(
                row.participant_id,
            ),
            {
                qualification_override_status:
                    target.value || null,
            },
            {
                preserveScroll: true,
                preserveState: false,
            },
        );
    };

    const withdrawParticipant = (
        row: TournamentStandingRow,
    ) => {
        const confirmed = window.confirm(
            `Da li želiš da označiš učesnika "${row.display_name}" kao odustao? Njegovi grupni mečevi biće anulirani za tabelu.`,
        );

        if (!confirmed) {
            return;
        }

        router.patch(
            participantWithdrawUrl(
                row.participant_id,
            ),
            {},
            {
                preserveScroll: true,
                preserveState: false,
            },
        );
    };

    const restoreParticipant = (
        row: TournamentStandingRow,
    ) => {
        const confirmed = window.confirm(
            `Da li želiš da vratiš učesnika "${row.display_name}" u aktivne? Njegovi grupni mečevi biće vraćeni na prethodni status.`,
        );

        if (!confirmed) {
            return;
        }

        router.patch(
            participantRestoreUrl(
                row.participant_id,
            ),
            {},
            {
                preserveScroll: true,
                preserveState: false,
            },
        );
    };

    return {
        updateQualificationOverride,
        withdrawParticipant,
        restoreParticipant,
    };
};
