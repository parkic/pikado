import { router } from '@inertiajs/vue3';

import type {
    TournamentKnockoutSeries,
    TournamentKnockoutSeriesParticipant,
    TournamentStatus,
} from '@/types/tournament';

type UseTournamentKnockoutActionsOptions = {
    tournamentStatus: () => TournamentStatus;
    generateBracketUrl: string;
    matchWalkoverUrl: (
        matchId: number,
        participantId: number,
    ) => string;
};

export const useTournamentKnockoutActions = ({
    tournamentStatus,
    generateBracketUrl,
    matchWalkoverUrl,
}: UseTournamentKnockoutActionsOptions) => {
    const generateKnockoutBracket = () => {
        const confirmed = window.confirm(
            'Da li želiš da generišeš nokaut kostur?',
        );

        if (!confirmed) {
            return;
        }

        router.post(generateBracketUrl);
    };

    const canApplyWalkover = (
        series: TournamentKnockoutSeries,
    ): boolean => {
        return tournamentStatus() === 'knockout_stage'
            && !series.winner
            && !!series.participant_a
            && !!series.participant_b
            && series.legs.length > 0;
    };

    const applyKnockoutWalkover = (
        series: TournamentKnockoutSeries,
        participant: TournamentKnockoutSeriesParticipant,
    ) => {
        const firstLeg = series.legs[0];

        if (!firstLeg) {
            window.alert('Serija nema partije.');

            return;
        }

        const confirmed = window.confirm(
            `Da li želiš da označiš da je "${participant.display_name}" odustao? Protivnik automatski dobija seriju.`,
        );

        if (!confirmed) {
            return;
        }

        router.patch(
            matchWalkoverUrl(
                firstLeg.id,
                participant.id,
            ),
            {},
            {
                preserveScroll: true,
                preserveState: false,
            },
        );
    };

    return {
        generateKnockoutBracket,
        canApplyWalkover,
        applyKnockoutWalkover,
    };
};
