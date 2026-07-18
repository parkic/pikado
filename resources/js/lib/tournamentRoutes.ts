export const venueTournamentRoutes = (venueSlug: string) => {
    const base = `/venues/${venueSlug}/tournaments`;

    return {
        index: base,
        create: `${base}/create`,
        store: base,
    };
};

export const tournamentRoutes = (
    venueSlug: string,
    tournamentSlug: string,
) => {
    const base = `/venues/${venueSlug}/tournaments/${tournamentSlug}`;

    return {
        show: base,

        groupsSetup: `${base}/groups/setup`,
        groupDraw: `${base}/group-draw`,
        groupDrawParticipant: (participantId: number) =>
            `${base}/group-draw/participants/${participantId}`,
        groupDrawParticipantEdit: (participantId: number) =>
            `${base}/group-draw/participants/${participantId}/edit`,
        startGroupDraw: `${base}/start-group-draw`,
        markReady: `${base}/mark-ready`,
        generateGroupMatches: `${base}/generate-group-matches`,
        completeGroupStage: `${base}/complete-group-stage`,

        schedule: `${base}/schedule`,
        standings: `${base}/standings`,
        qualificationSetup: `${base}/qualification/setup`,
        repechage: `${base}/repechage`,
        knockout: `${base}/knockout`,
    };
};

export type TournamentRoutes = ReturnType<typeof tournamentRoutes>;

export const publicTournamentRoutes = (publicCode: string) => {
    const base = `/t/${publicCode}`;

    return {
        live: `${base}/live`,
        groups: `${base}/groups`,
        schedule: `${base}/schedule`,
        knockout: `${base}/knockout`,
    };
};
