import { router } from '@inertiajs/vue3';
import { reactive, ref } from 'vue';
import { toast } from 'vue-sonner';

import type {
    TournamentScheduleMatch,
    TournamentScheduleResultForm,
} from '@/types/tournament';

type UseTournamentScheduleMatchesOptions = {
    matches: TournamentScheduleMatch[];
    matchResourceUrl: (matchId: number) => string;
    matchResultUrl: (matchId: number) => string;
    matchPostponementUrl: (matchId: number) => string;
};

export const useTournamentScheduleMatches = ({
    matches,
    matchResourceUrl,
    matchResultUrl,
    matchPostponementUrl,
}: UseTournamentScheduleMatchesOptions) => {
    const resultForms = reactive<Record<number, TournamentScheduleResultForm>>(
        Object.fromEntries(
            matches.map((match) => [
                match.id,
                {
                    score_a:
                        match.score_a !== null ? String(match.score_a) : '',
                    score_b:
                        match.score_b !== null ? String(match.score_b) : '',
                    winner_participant_id: match.winner
                        ? String(match.winner.id)
                        : '',
                },
            ]),
        ),
    );

    const tieBreakerMatch = ref<TournamentScheduleMatch | null>(null);

    const updateMatchResource = (
        match: TournamentScheduleMatch,
        event: Event,
    ) => {
        const target = event.target as HTMLSelectElement;
        const selectedValue = target.value;

        router.patch(
            matchResourceUrl(match.id),
            {
                tournament_resource_id: selectedValue
                    ? Number(selectedValue)
                    : null,
            },
            {
                preserveScroll: true,
            },
        );
    };

    const isDrawResult = (match: TournamentScheduleMatch): boolean => {
        const resultForm = resultForms[match.id];

        if (resultForm.score_a === '' || resultForm.score_b === '') {
            return false;
        }

        return Number(resultForm.score_a) === Number(resultForm.score_b);
    };

    const normalizeResult = (match: TournamentScheduleMatch) => {
        const resultForm = resultForms[match.id];

        resultForm.score_a = resultForm.score_a.trim() || '0';
        resultForm.score_b = resultForm.score_b.trim() || '0';
    };

    const submitMatchResult = (
        match: TournamentScheduleMatch,
        onSuccess?: () => void,
    ) => {
        const resultForm = resultForms[match.id];

        router.patch(
            matchResultUrl(match.id),
            {
                score_a: resultForm.score_a,
                score_b: resultForm.score_b,
                winner_participant_id: resultForm.winner_participant_id || null,
            },
            {
                preserveScroll: true,
                preserveState: false,
                onSuccess,
            },
        );
    };

    const openTieBreakerModal = (match: TournamentScheduleMatch) => {
        const resultForm = resultForms[match.id];

        if (!resultForm.winner_participant_id && match.winner) {
            resultForm.winner_participant_id = String(match.winner.id);
        }

        tieBreakerMatch.value = match;
    };

    const updateMatchResult = (match: TournamentScheduleMatch) => {
        normalizeResult(match);
        const resultForm = resultForms[match.id];

        if (isDrawResult(match) && !resultForm.winner_participant_id) {
            openTieBreakerModal(match);

            return;
        }

        submitMatchResult(match);
    };

    const toggleMatchPostponement = (match: TournamentScheduleMatch) => {
        router.patch(
            matchPostponementUrl(match.id),
            { postponed: match.status !== 'postponed' },
            { preserveScroll: true },
        );
    };

    const closeTieBreakerModal = () => {
        tieBreakerMatch.value = null;
    };

    const chooseTieBreakerWinner = (
        match: TournamentScheduleMatch,
        participantId: number,
    ) => {
        resultForms[match.id].winner_participant_id = String(participantId);
    };

    const saveTieBreakerWinner = () => {
        if (!tieBreakerMatch.value) {
            return;
        }

        const match = tieBreakerMatch.value;
        const resultForm = resultForms[match.id];

        if (!resultForm.winner_participant_id) {
            toast.error('Izaberi pobednika pre čuvanja rezultata.');

            return;
        }

        submitMatchResult(match, () => {
            closeTieBreakerModal();
        });
    };

    const updateResultField = (
        matchId: number,
        field: 'score_a' | 'score_b',
        value: string,
    ) => {
        resultForms[matchId][field] = value;
    };

    return {
        resultForms,
        tieBreakerMatch,
        updateResultField,
        updateMatchResource,
        openTieBreakerModal,
        updateMatchResult,
        toggleMatchPostponement,
        closeTieBreakerModal,
        chooseTieBreakerWinner,
        saveTieBreakerWinner,
    };
};
