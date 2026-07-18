export const venueTournamentRoutes = (
    venueSlug: string,
) => {
    const venueBase = `/venues/${venueSlug}`;
    const base = `${venueBase}/tournaments`;

    return {
        dashboard: `${venueBase}/dashboard`,
        index: base,
        create: `${base}/create`,
        store: base,
        show: (tournamentSlug: string) =>
            `${base}/${tournamentSlug}`,
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
        groupDrawParticipantReplace: (participantId: number) =>
            `${base}/group-draw/participants/${participantId}/replace`,
        startGroupDraw: `${base}/start-group-draw`,
        markReady: `${base}/mark-ready`,
        generateGroupMatches: `${base}/generate-group-matches`,
        completeGroupStage: `${base}/complete-group-stage`,

        schedule: `${base}/schedule`,
        scheduleMatchResource: (matchId: number) =>
            `${base}/schedule/matches/${matchId}/resource`,
        scheduleMatchResult: (matchId: number) =>
            `${base}/schedule/matches/${matchId}/result`,

        standings: `${base}/standings`,
        standingQualificationOverride: (participantId: number) =>
            `${base}/standings/participants/${participantId}/qualification-override`,
        standingParticipantWithdraw: (participantId: number) =>
            `${base}/standings/participants/${participantId}/withdraw`,
        standingParticipantRestore: (participantId: number) =>
            `${base}/standings/participants/${participantId}/restore`,

        qualificationSetup: `${base}/qualification/setup`,

        repechage: `${base}/repechage`,
        repechageParticipantOutcome: (participantId: number) =>
            `${base}/repechage/participants/${participantId}/outcome`,
        completeRepechage: `${base}/repechage/complete`,

        knockout: `${base}/knockout`,
        generateKnockoutBracket: `${base}/knockout/generate`,
        knockoutMatchWalkover: (
            matchId: number,
            participantId: number,
        ) =>
            `${base}/knockout/matches/${matchId}/participants/${participantId}/walkover`,
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
