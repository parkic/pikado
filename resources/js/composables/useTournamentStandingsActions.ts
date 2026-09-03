import { router } from '@inertiajs/vue3';

import { confirmAction } from '@/composables/useConfirmDialog';
import type { TournamentStandingRow } from '@/types/tournament';

type UseTournamentStandingsActionsOptions = {
    qualificationOverrideUrl: (participantId: number) => string;
    participantWithdrawUrl: (participantId: number) => string;
    participantRestoreUrl: (participantId: number) => string;
};

export const useTournamentStandingsActions = ({
    qualificationOverrideUrl,
    participantWithdrawUrl,
    participantRestoreUrl,
}: UseTournamentStandingsActionsOptions) => {
    const updateQualificationOverride = (
        row: TournamentStandingRow,
        value: string,
    ) => {
        router.patch(
            qualificationOverrideUrl(row.participant_id),
            {
                qualification_override_status: value || null,
            },
            {
                preserveScroll: true,
                preserveState: false,
            },
        );
    };

    const withdrawParticipant = async (
        row: TournamentStandingRow,
        withdrawalPolicy: string,
    ) => {
        const policyDescriptions: Record<string, string> = {
            void_all:
                'Svi njegovi rezultati biće anulirani i neće uticati na tabelu.',
            keep_played_average_rest:
                'Odigrani rezultati ostaju, a preostali se automatski obračunavaju prema proseku igrača.',
            keep_played_manual_rest:
                'Odigrani rezultati ostaju, a preostali mečevi čekaju da administrator ručno unese rezultat.',
        };
        const confirmed = await confirmAction({
            title: 'Označi učesnika kao odustalog?',
            description: `${row.display_name} više neće biti aktivan. ${policyDescriptions[withdrawalPolicy] ?? ''}`,
            confirmLabel: 'Označi kao odustao',
            variant: 'destructive',
        });

        if (!confirmed) {
            return;
        }

        router.patch(
            participantWithdrawUrl(row.participant_id),
            { withdrawal_policy: withdrawalPolicy },
            {
                preserveScroll: true,
                preserveState: false,
            },
        );
    };

    const restoreParticipant = async (row: TournamentStandingRow) => {
        const confirmed = await confirmAction({
            title: 'Vrati učesnika u turnir?',
            description: `${row.display_name} će ponovo biti aktivan, a njegovi grupni mečevi vraćeni na prethodni status.`,
            confirmLabel: 'Vrati učesnika',
        });

        if (!confirmed) {
            return;
        }

        router.patch(
            participantRestoreUrl(row.participant_id),
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
