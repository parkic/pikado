import { router } from '@inertiajs/vue3';

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
    const startGroupDraw = () => {
        const confirmed = window.confirm(
            'Da li želiš da pokreneš Group Draw za ovaj turnir?',
        );

        if (!confirmed) {
            return;
        }

        router.post(routes.startGroupDraw);
    };

    const markReady = () => {
        const confirmed = window.confirm(
            'Da li želiš da označiš turnir kao spreman?',
        );

        if (!confirmed) {
            return;
        }

        router.post(routes.markReady);
    };

    const generateGroupMatches = () => {
        const confirmed = window.confirm(
            'Da li želiš da generišeš grupne mečeve?',
        );

        if (!confirmed) {
            return;
        }

        router.post(routes.generateGroupMatches);
    };

    const completeGroupStage = () => {
        const nextStageLabel =
            nextStageAfterGroups() === 'repechage'
                ? 'repasaž'
                : 'žreb za nokaut';

        const confirmed = window.confirm(
            `Da li želiš da završiš grupnu fazu? Sledeći korak je: ${nextStageLabel}.`,
        );

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
