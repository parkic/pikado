import { router } from '@inertiajs/vue3';

import { confirmAction } from '@/composables/useConfirmDialog';
import type { TournamentRoutes } from '@/lib/tournamentRoutes';
import type { TournamentNextStageAfterGroups } from '@/types/tournament';

type UseTournamentActionsOptions = {
    routes: TournamentRoutes;
    nextStageAfterGroups: () => TournamentNextStageAfterGroups;
};

export const useTournamentActions = ({
    routes,
    nextStageAfterGroups,
}: UseTournamentActionsOptions) => {
    const startGroupDraw = async () => {
        const confirmed = await confirmAction({
            title: 'Pokreni unos učesnika?',
            description:
                'Turnir prelazi u fazu unosa učesnika i otvara raspored grupa.',
            confirmLabel: 'Pokreni unos',
        });

        if (!confirmed) {
            return;
        }

        router.post(routes.startGroupDraw);
    };

    const markReady = async () => {
        const confirmed = await confirmAction({
            title: 'Završi unos učesnika?',
            description:
                'Proverićemo raspored po grupama i označiti turnir kao spreman za generisanje mečeva.',
            confirmLabel: 'Označi kao spreman',
        });

        if (!confirmed) {
            return;
        }

        router.post(routes.markReady);
    };

    const generateGroupMatches = async () => {
        const confirmed = await confirmAction({
            title: 'Generiši grupne mečeve?',
            description:
                'Biće napravljen kompletan raspored grupne faze prema trenutnim grupama i opremi.',
            confirmLabel: 'Generiši mečeve',
        });

        if (!confirmed) {
            return;
        }

        router.post(routes.generateGroupMatches);
    };

    const completeGroupStage = async () => {
        const nextStageLabel =
            nextStageAfterGroups() === 'repechage'
                ? 'repasaž'
                : 'žreb za nokaut';

        const confirmed = await confirmAction({
            title: 'Završi grupnu fazu?',
            description: `Tabela će biti zaključena, a sledeći korak je ${nextStageLabel}.`,
            confirmLabel: 'Završi grupnu fazu',
            variant: 'warning',
        });

        if (!confirmed) {
            return;
        }

        router.post(routes.completeGroupStage);
    };

    return {
        startGroupDraw,
        markReady,
        generateGroupMatches,
        completeGroupStage,
    };
};
