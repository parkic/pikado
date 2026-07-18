export type TournamentStatus =
    | 'draft'
    | 'group_draw'
    | 'ready'
    | 'group_stage'
    | 'repechage'
    | 'knockout_draw'
    | 'knockout_stage'
    | 'finished';

export type TournamentGroupRounds =
    | 'single'
    | 'double';

export type TournamentMatchMode =
    | 'singles'
    | 'doubles';

export type TournamentNextStageAfterGroups =
    | 'repechage'
    | 'knockout_draw';

export type TournamentIdentity = {
    id: number;
    name: string;
    slug: string;
};

export type TournamentSettings = {
    group_count?: number | null;
    group_size?: number | null;
    direct_qualifiers_per_group?: number | null;
    repechage_enabled?: boolean;
    repechage_participants_count?: number | null;
    repechage_qualifiers_count?: number | null;
    avoid_same_group_rematch?: boolean;
};

export type TournamentGroupSummary = {
    id: number;
    name: string;
    sort_order: number;
};

export type TournamentResource = {
    id: number;
    name: string;
    type: string;
    type_label: string;
    sort_order: number;
    is_active: boolean;
};

export type TournamentGroupParticipant = {
    id: number;
    participant_type: string;
    group_position: string | null;
    status: string;
    display_name: string;
};

export type TournamentGroup = {
    id: number;
    name: string;
    sort_order: number;
    participants: TournamentGroupParticipant[];
};

export type TournamentPodiumParticipant = {
    id: number;
    display_name: string;
    group_position: string | null;
};

export type TournamentPodiumData = {
    champion: TournamentPodiumParticipant | null;
    second_place: TournamentPodiumParticipant | null;
    third_place: TournamentPodiumParticipant | null;
    fourth_place: TournamentPodiumParticipant | null;
    final_score: string | null;
    third_place_score: string | null;
    is_complete: boolean;
};

export type TournamentShowData = TournamentIdentity & {
    public_code: string;
    game_type: string;
    game_type_label: string;
    match_mode: string;
    match_mode_label: string;
    status: TournamentStatus;
    status_label: string;
    group_rounds: TournamentGroupRounds;
    knockout_size: number | null;
    public_enabled: boolean;
    scoring_mode: string;
    settings: TournamentSettings;
    created_by: string | null;
    created_at: string | null;
    groups_count: number;
    finished_group_matches_count: number;
    can_complete_group_stage: boolean;
    next_stage_after_groups: TournamentNextStageAfterGroups;
    groups: TournamentGroup[];
    participants_count: number;
    total_slots: number;
    group_matches_count: number;
    can_start_group_draw: boolean;
    can_mark_ready: boolean;
    can_generate_group_matches: boolean;
    podium: TournamentPodiumData;
    resources: TournamentResource[];
};

export type TournamentPublicLink = {
    key: string;
    label: string;
    description: string;
    url: string;
};

export type TournamentFormOption = {
    value: string | number;
    label: string;
};

export type TournamentCreateOptions = {
    game_types: TournamentFormOption[];
    match_modes: TournamentFormOption[];
    group_rounds: TournamentFormOption[];
    knockout_sizes: TournamentFormOption[];
};

export type TournamentSelectableResource = {
    id: number;
    name: string;
    type: string;
    type_label: string;
    sort_order: number;
};

export type TournamentCreateFormData = {
    name: string;
    game_type: string;
    match_mode: TournamentMatchMode;
    group_rounds: TournamentGroupRounds;
    knockout_size: number | null;
    public_enabled: boolean;
    resource_ids: number[];
};

export type TournamentGroupsSetupData = TournamentIdentity & {
    status: TournamentStatus;
    status_label: string;
    settings: TournamentSettings;
    participants_count: number;
    groups: TournamentGroupSummary[];
};

export type TournamentGroupsSetupFormData = {
    group_count: number;
    group_size: number;
};

export type TournamentQualificationSetupData = TournamentIdentity & {
    settings: TournamentSettings;
    groups_count: number;
    participants_count: number;
};

export type TournamentQualificationFormData = {
    direct_qualifiers_per_group: number;
    repechage_enabled: boolean;
    repechage_participants_count: number | null;
    repechage_qualifiers_count: number | null;
};

export type TournamentGroupDrawPlayer = {
    id: number;
    first_name: string;
    last_name: string;
    nickname: string | null;
    display_name: string;
};

export type TournamentGroupDrawTeam = {
    id: number;
    name: string;
};

export type TournamentGroupDrawNextSlot = {
    group_id: number;
    group_name: string;
    slot_number: number;
    group_position: string;
};

export type TournamentGroupDrawData = TournamentIdentity & {
    match_mode: TournamentMatchMode;
    match_mode_label: string;
    settings: TournamentSettings;
    groups_count: number;
    participants_count: number;
    total_slots: number;
    next_slot: TournamentGroupDrawNextSlot | null;
    groups: TournamentGroup[];
};

export type TournamentGroupDrawFormData = {
    existing_player_id: number | null;
    existing_team_id: number | null;
    first_name: string;
    last_name: string;
    nickname: string;
    team_name: string;
    group_position: string;

    /**
     * Backend validaciona greška za izabrani slot.
     * Nije stvarno polje koje unosimo u formu.
    */
    slot?: string;
};

export type TournamentScheduleData = TournamentIdentity & {
    status: TournamentStatus;
    status_label: string;
    matches_count: number;
    group_matches_count: number;
};

export type TournamentScheduleParticipant = {
    id: number;
    group_position: string | null;
    display_name: string;
    status: string;
    is_withdrawn: boolean;
};

export type TournamentScheduleMatchResource = {
    id: number;
    name: string;
    type: string;
};

export type TournamentScheduleAvailableResource = {
    id: number;
    name: string;
    type: string;
    type_label: string;
};

export type TournamentScheduleWinner = {
    id: number;
    display_name: string;
};

export type TournamentScheduleMatch = {
    id: number;
    stage: string;
    stage_label: string;
    group_name: string | null;
    scheduled_order: number | null;
    round_robin_leg: number | null;
    wins_required: number | null;
    bracket_round: string | null;
    bracket_round_label: string | null;
    bracket_position: number | null;
    participant_a: TournamentScheduleParticipant | null;
    participant_b: TournamentScheduleParticipant | null;
    score_a: number | null;
    score_b: number | null;
    winner: TournamentScheduleWinner | null;
    status: string;
    status_label: string;
    resource: TournamentScheduleMatchResource | null;
};

export type TournamentScheduleResultForm = {
    score_a: string;
    score_b: string;
    winner_participant_id: string;
};


export type TournamentStandingsData = TournamentIdentity & {
    status: TournamentStatus;
    status_label: string;
    can_manage_withdrawals: boolean;
};

export type TournamentStandingRow = {
    participant_id: number;
    group_position: string | null;
    display_name: string;
    played: number;
    wins: number;
    losses: number;
    points_for: number;
    points_against: number;
    points_difference: number;
    standing_points: number;
    position: number | null;
    participant_status: string;
    is_withdrawn: boolean;
    withdrawn_at: string | null;
    qualification_status: string;
    qualification_label: string;
    qualification_override_status: string | null;
    qualification_is_manual: boolean;
};

export type TournamentStandingGroup = {
    id: number;
    name: string;
    matches_count: number;
    finished_matches_count: number;
    rows: TournamentStandingRow[];
};
