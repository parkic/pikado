import { computed, ref } from 'vue';
import { normalizeSearchText } from '@/lib/normalizeSearchText';

import type {
    TournamentGroupDrawFormData,
    TournamentGroupDrawPlayer,
    TournamentGroupDrawTeam,
} from '@/types/tournament';

type UseTournamentGroupDrawSearchOptions = {
    availablePlayers: () => TournamentGroupDrawPlayer[];
    availableTeams: () => TournamentGroupDrawTeam[];
    form: TournamentGroupDrawFormData;
};

export const useTournamentGroupDrawSearch = ({
    availablePlayers,
    availableTeams,
    form,
}: UseTournamentGroupDrawSearchOptions) => {
    const playerSearch = ref('');
    const teamSearch = ref('');

    const filteredAvailablePlayers = computed<TournamentGroupDrawPlayer[]>(
        () => {
            const search = normalizeSearchText(playerSearch.value);

            if (!search) {
                return availablePlayers().slice(0, 8);
            }

            return availablePlayers()
                .filter((player) => {
                    return normalizeSearchText(player.display_name).includes(
                        search,
                    );
                })
                .slice(0, 8);
        },
    );

    const filteredAvailableTeams = computed<TournamentGroupDrawTeam[]>(() => {
        const search = normalizeSearchText(teamSearch.value);

        if (!search) {
            return availableTeams().slice(0, 8);
        }

        return availableTeams()
            .filter((team) => {
                return normalizeSearchText(team.name).includes(search);
            })
            .slice(0, 8);
    });

    const selectedExistingPlayer = computed<
        TournamentGroupDrawPlayer | undefined
    >(() => {
        return availablePlayers().find((player) => {
            return player.id === form.existing_player_id;
        });
    });

    const selectedExistingTeam = computed<TournamentGroupDrawTeam | undefined>(
        () => {
            return availableTeams().find((team) => {
                return team.id === form.existing_team_id;
            });
        },
    );

    const chooseExistingPlayer = (player: TournamentGroupDrawPlayer) => {
        form.existing_player_id = player.id;
        form.first_name = player.first_name;
        form.last_name = player.last_name;
        form.nickname = player.nickname ?? '';

        playerSearch.value = player.display_name;
    };

    const clearExistingPlayer = () => {
        form.existing_player_id = null;
        form.first_name = '';
        form.last_name = '';
        form.nickname = '';

        playerSearch.value = '';
    };

    const chooseExistingTeam = (team: TournamentGroupDrawTeam) => {
        form.existing_team_id = team.id;
        form.team_name = team.name;

        teamSearch.value = team.name;
    };

    const clearExistingTeam = () => {
        form.existing_team_id = null;
        form.team_name = '';

        teamSearch.value = '';
    };

    const resetSearch = () => {
        playerSearch.value = '';
        teamSearch.value = '';
    };

    return {
        playerSearch,
        teamSearch,
        filteredAvailablePlayers,
        filteredAvailableTeams,
        selectedExistingPlayer,
        selectedExistingTeam,
        chooseExistingPlayer,
        clearExistingPlayer,
        chooseExistingTeam,
        clearExistingTeam,
        resetSearch,
    };
};
