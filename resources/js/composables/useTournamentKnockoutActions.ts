import { router } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';

import { confirmAction } from '@/composables/useConfirmDialog';
import type {
    TournamentKnockoutSeries,
    TournamentKnockoutSeriesParticipant,
    TournamentStatus,
} from '@/types/tournament';

type UseTournamentKnockoutActionsOptions = {
    tournamentStatus: () => TournamentStatus;
    generateBracketUrl: string;
    matchWalkoverUrl: (matchId: number, participantId: number) => string;
};

export const useTournamentKnockoutActions = ({
    tournamentStatus,
    generateBracketUrl,
    matchWalkoverUrl,
}: UseTournamentKnockoutActionsOptions) => {
    const generateKnockoutBracket = async () => {
        const confirmed = await confirmAction({
            title: 'Generiši nokaut kostur?',
            description:
                'Učesnici će biti raspoređeni u nokaut prema konačnom plasmanu iz grupa i repasaža.',
            confirmLabel: 'Generiši kostur',
        });

        if (!confirmed) {
            return;
        }

        router.post(generateBracketUrl);
    };

    const canApplyWalkover = (series: TournamentKnockoutSeries): boolean => {
        return (
            tournamentStatus() === 'knockout_stage' &&
            !series.winner &&
            !!series.participant_a &&
            !!series.participant_b &&
            series.legs.length > 0
        );
    };

    const applyKnockoutWalkover = async (
        series: TournamentKnockoutSeries,
        participant: TournamentKnockoutSeriesParticipant,
    ) => {
        const firstLeg = series.legs[0];

        if (!firstLeg) {
            toast.error(
                'Serija nema partije. Osveži stranicu i pokušaj ponovo.',
            );

            return;
        }

        const confirmed = await confirmAction({
            title: 'Potvrdi odustajanje?',
            description: `${participant.display_name} će biti označen kao da je odustao, a protivnik automatski dobija seriju.`,
            confirmLabel: 'Potvrdi odustajanje',
            variant: 'destructive',
        });

        if (!confirmed) {
            return;
        }

        router.patch(
            matchWalkoverUrl(firstLeg.id, participant.id),
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
