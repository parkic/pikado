<?php

namespace App\Http\Controllers;

use App\Enums\MatchStatus;
use App\Enums\MatchStage;
use App\Models\Tournament;
use App\Models\TournamentMatch;
use App\Models\TournamentParticipant;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;
use App\Services\GroupStandingsCalculator;


class PublicTournamentController extends Controller
{
    public function live(string $publicCode): Response
    {
        $tournament = Tournament::query()
            ->with([
                'venue',
            ])
            ->where('public_code', $publicCode)
            ->where('public_enabled', true)
            ->firstOrFail();

        $matchesQuery = $tournament->matches()
            ->with([
                'group',
                'participantA.player',
                'participantA.team',
                'participantB.player',
                'participantB.team',
                'winner.player',
                'winner.team',
                'resource',
            ]);

        $matchesCount = (clone $matchesQuery)->count();

        $finishedMatchesCount = (clone $matchesQuery)
            ->where('status', MatchStatus::FINISHED->value)
            ->count();

        $voidedMatchesCount = (clone $matchesQuery)
            ->where('status', MatchStatus::VOIDED->value)
            ->count();

        $countedAsDoneMatchesCount = $finishedMatchesCount + $voidedMatchesCount;

        $activeMatches = (clone $matchesQuery)
            ->where('status', MatchStatus::IN_PROGRESS->value)
            ->orderBy('scheduled_order')
            ->orderBy('id')
            ->limit(6)
            ->get()
            ->map(fn (TournamentMatch $match) => $this->matchSummary($match))
            ->values();

        $nextMatches = (clone $matchesQuery)
            ->where('status', MatchStatus::SCHEDULED->value)
            ->orderBy('scheduled_order')
            ->orderBy('id')
            ->limit(8)
            ->get()
            ->map(fn (TournamentMatch $match) => $this->matchSummary($match))
            ->values();

        $recentMatches = (clone $matchesQuery)
            ->where('status', MatchStatus::FINISHED->value)
            ->orderByDesc('finished_at')
            ->orderByDesc('updated_at')
            ->limit(8)
            ->get()
            ->map(fn (TournamentMatch $match) => $this->matchSummary($match))
            ->values();

        return Inertia::render('Public/Tournaments/Live', [
            'venue' => [
                'name' => $tournament->venue->name,
                'slug' => $tournament->venue->slug,
                'logo_path' => $tournament->venue->logo_path,
            ],
            'tournament' => [
                'id' => $tournament->id,
                'name' => $tournament->name,
                'public_code' => $tournament->public_code,
                'game_type' => $tournament->game_type->value,
                'match_mode' => $tournament->match_mode->value,
                'status' => $tournament->status->value,
                'status_label' => $this->tournamentStatusLabel($tournament->status->value),
                'matches_count' => $matchesCount,
                'finished_matches_count' => $finishedMatchesCount,
                'voided_matches_count' => $voidedMatchesCount,
                'done_matches_count' => $countedAsDoneMatchesCount,
                'progress_percent' => $matchesCount > 0
                    ? round(($countedAsDoneMatchesCount / $matchesCount) * 100)
                    : 0,
                'finished_at' => $tournament->finished_at?->format('d.m.Y. H:i'),
            ],
            'active_matches' => $activeMatches,
            'next_matches' => $nextMatches,
            'recent_matches' => $recentMatches,
            'podium' => $this->publicTournamentPodium($tournament),
        ]);
    }

    public function groups(string $publicCode, GroupStandingsCalculator $calculator): Response
    {
        $tournament = Tournament::query()
            ->with([
                'venue',
            ])
            ->where('public_code', $publicCode)
            ->where('public_enabled', true)
            ->firstOrFail();

        $groups = collect($calculator->calculate($tournament));

        $groupMatches = $tournament->matches()
            ->with([
                'group',
                'participantA.player',
                'participantA.team',
                'participantB.player',
                'participantB.team',
                'winner.player',
                'winner.team',
                'resource',
            ])
            ->where('stage', MatchStage::GROUP->value)
            ->orderBy('scheduled_order')
            ->orderBy('id')
            ->get()
            ->groupBy('tournament_group_id');

        $groups = $groups
            ->map(function (array $group) use ($groupMatches) {
                return [
                    'id' => $group['id'],
                    'name' => $group['name'],
                    'matches_count' => $group['matches_count'],
                    'finished_matches_count' => $group['finished_matches_count'],
                    'rows' => $group['rows'],
                    'matches' => collect($groupMatches->get($group['id'], collect()))
                        ->map(fn (TournamentMatch $match) => $this->matchSummary($match))
                        ->values(),
                ];
            })
            ->values();

        return Inertia::render('Public/Tournaments/Groups', [
            'venue' => [
                'name' => $tournament->venue->name,
                'slug' => $tournament->venue->slug,
                'logo_path' => $tournament->venue->logo_path,
            ],
            'tournament' => [
                'id' => $tournament->id,
                'name' => $tournament->name,
                'public_code' => $tournament->public_code,
                'game_type' => $tournament->game_type->value,
                'match_mode' => $tournament->match_mode->value,
                'status' => $tournament->status->value,
                'status_label' => $this->tournamentStatusLabel($tournament->status->value),
            ],
            'groups' => $groups,
        ]);
    }

    public function schedule(string $publicCode): Response
    {
        $tournament = Tournament::query()
            ->with([
                'venue',
            ])
            ->where('public_code', $publicCode)
            ->where('public_enabled', true)
            ->firstOrFail();

        $matchesQuery = $tournament->matches()
            ->with([
                'group',
                'participantA.player',
                'participantA.team',
                'participantB.player',
                'participantB.team',
                'winner.player',
                'winner.team',
                'resource',
            ]);

        $matchesCount = (clone $matchesQuery)->count();

        $scheduledMatchesCount = (clone $matchesQuery)
            ->where('status', MatchStatus::SCHEDULED->value)
            ->count();

        $inProgressMatchesCount = (clone $matchesQuery)
            ->where('status', MatchStatus::IN_PROGRESS->value)
            ->count();

        $finishedMatchesCount = (clone $matchesQuery)
            ->where('status', MatchStatus::FINISHED->value)
            ->count();

        $voidedMatchesCount = (clone $matchesQuery)
            ->where('status', MatchStatus::VOIDED->value)
            ->count();

        $matches = (clone $matchesQuery)
            ->orderBy('scheduled_order')
            ->orderBy('id')
            ->get()
            ->map(fn (TournamentMatch $match) => $this->matchSummary($match))
            ->values();

        return Inertia::render('Public/Tournaments/Schedule', [
            'venue' => [
                'name' => $tournament->venue->name,
                'slug' => $tournament->venue->slug,
                'logo_path' => $tournament->venue->logo_path,
            ],
            'tournament' => [
                'id' => $tournament->id,
                'name' => $tournament->name,
                'public_code' => $tournament->public_code,
                'game_type' => $tournament->game_type->value,
                'match_mode' => $tournament->match_mode->value,
                'status' => $tournament->status->value,
                'status_label' => $this->tournamentStatusLabel($tournament->status->value),
                'matches_count' => $matchesCount,
                'scheduled_matches_count' => $scheduledMatchesCount,
                'in_progress_matches_count' => $inProgressMatchesCount,
                'finished_matches_count' => $finishedMatchesCount,
                'voided_matches_count' => $voidedMatchesCount,
            ],
            'matches' => $matches,
        ]);
    }

    public function knockout(string $publicCode): Response
    {
        $tournament = Tournament::query()
            ->with([
                'venue',
            ])
            ->where('public_code', $publicCode)
            ->where('public_enabled', true)
            ->firstOrFail();

        $knockoutMatchesCount = $tournament->matches()
            ->whereIn('stage', [
                MatchStage::KNOCKOUT->value,
                MatchStage::THIRD_PLACE->value,
                MatchStage::FINAL->value,
            ])
            ->count();

        $finishedKnockoutMatchesCount = $tournament->matches()
            ->whereIn('stage', [
                MatchStage::KNOCKOUT->value,
                MatchStage::THIRD_PLACE->value,
                MatchStage::FINAL->value,
            ])
            ->where('status', MatchStatus::FINISHED->value)
            ->count();

        $voidedKnockoutMatchesCount = $tournament->matches()
            ->whereIn('stage', [
                MatchStage::KNOCKOUT->value,
                MatchStage::THIRD_PLACE->value,
                MatchStage::FINAL->value,
            ])
            ->where('status', MatchStatus::VOIDED->value)
            ->count();

        return Inertia::render('Public/Tournaments/Knockout', [
            'venue' => [
                'name' => $tournament->venue->name,
                'slug' => $tournament->venue->slug,
                'logo_path' => $tournament->venue->logo_path,
            ],
            'tournament' => [
                'id' => $tournament->id,
                'name' => $tournament->name,
                'public_code' => $tournament->public_code,
                'game_type' => $tournament->game_type->value,
                'match_mode' => $tournament->match_mode->value,
                'status' => $tournament->status->value,
                'status_label' => $this->tournamentStatusLabel($tournament->status->value),
                'knockout_size' => $tournament->knockout_size,
                'knockout_matches_count' => $knockoutMatchesCount,
                'finished_knockout_matches_count' => $finishedKnockoutMatchesCount,
                'voided_knockout_matches_count' => $voidedKnockoutMatchesCount,
            ],
            'rounds' => $this->publicKnockoutRounds($tournament),
        ]);
    }

    private function matchSummary(TournamentMatch $match): array
    {
        return [
            'id' => $match->id,
            'scheduled_order' => $match->scheduled_order,
            'stage' => $match->stage->value,
            'stage_label' => $this->matchStageLabel($match->stage->value),
            'group_name' => $match->group?->name,
            'bracket_round' => $match->bracket_round,
            'bracket_round_label' => $this->bracketRoundLabel($match->bracket_round),
            'bracket_position' => $match->bracket_position,
            'round_robin_leg' => $match->round_robin_leg,
            'wins_required' => $match->wins_required,
            'participant_a' => $this->participantSummary($match->participantA),
            'participant_b' => $this->participantSummary($match->participantB),
            'score_a' => $match->score_a,
            'score_b' => $match->score_b,
            'winner' => $this->participantSummary($match->winner),
            'status' => $match->status->value,
            'status_label' => $this->matchStatusLabel($match->status->value),
            'resource_name' => $match->resource?->name,
            'finished_at' => $match->finished_at?->format('d.m.Y. H:i'),
        ];
    }

    private function publicTournamentPodium(Tournament $tournament): array
    {
        $final = $this->publicKnockoutSeriesResult($tournament, MatchStage::FINAL);
        $thirdPlace = $this->publicKnockoutSeriesResult($tournament, MatchStage::THIRD_PLACE);

        $champion = $final['winner'];
        $secondPlace = $final['loser'];

        $third = $thirdPlace['winner'];
        $fourth = $thirdPlace['loser'];

        return [
            'champion' => $this->participantSummary($champion),
            'second_place' => $this->participantSummary($secondPlace),
            'third_place' => $this->participantSummary($third),
            'fourth_place' => $this->participantSummary($fourth),
            'final_score' => $final['score'],
            'third_place_score' => $thirdPlace['score'],
            'is_complete' => $champion !== null
                && $secondPlace !== null
                && $third !== null
                && $fourth !== null,
        ];
    }

    private function publicKnockoutSeriesResult(Tournament $tournament, MatchStage $stage): array
    {
        $seriesMatches = TournamentMatch::query()
            ->with([
                'participantA.player',
                'participantA.team',
                'participantB.player',
                'participantB.team',
                'winner.player',
                'winner.team',
            ])
            ->where('tournament_id', $tournament->id)
            ->where('stage', $stage->value)
            ->orderBy('round_robin_leg')
            ->orderBy('id')
            ->get();

        if ($seriesMatches->isEmpty()) {
            return [
                'winner' => null,
                'loser' => null,
                'score' => null,
            ];
        }

        /** @var TournamentMatch $firstMatch */
        $firstMatch = $seriesMatches->first();

        $winsRequired = (int) ($firstMatch->wins_required ?: 1);

        $winnerWins = $seriesMatches
            ->filter(fn (TournamentMatch $match) => $match->status === MatchStatus::FINISHED)
            ->groupBy('winner_participant_id')
            ->map(fn (Collection $matches) => $matches->count())
            ->sortDesc();

        $winnerParticipantId = null;

        foreach ($winnerWins as $participantId => $winsCount) {
            if ($participantId && $winsCount >= $winsRequired) {
                $winnerParticipantId = (int) $participantId;
                break;
            }
        }

        if (! $winnerParticipantId) {
            return [
                'winner' => null,
                'loser' => null,
                'score' => null,
            ];
        }

        $loserParticipantId = $this->publicSeriesOpponentId($seriesMatches, $winnerParticipantId);

        $winner = $this->participantFromSeries($seriesMatches, $winnerParticipantId);
        $loser = $this->participantFromSeries($seriesMatches, $loserParticipantId);

        $participantAWins = $seriesMatches
            ->filter(fn (TournamentMatch $match) => $match->status === MatchStatus::FINISHED)
            ->filter(fn (TournamentMatch $match) => (int) $match->winner_participant_id === (int) $firstMatch->participant_a_id)
            ->count();

        $participantBWins = $seriesMatches
            ->filter(fn (TournamentMatch $match) => $match->status === MatchStatus::FINISHED)
            ->filter(fn (TournamentMatch $match) => (int) $match->winner_participant_id === (int) $firstMatch->participant_b_id)
            ->count();

        return [
            'winner' => $winner,
            'loser' => $loser,
            'score' => $participantAWins . ' : ' . $participantBWins,
        ];
    }

    private function publicSeriesOpponentId(Collection $seriesMatches, int $winnerParticipantId): ?int
    {
        /** @var TournamentMatch|null $match */
        $match = $seriesMatches
            ->first(function (TournamentMatch $seriesMatch) use ($winnerParticipantId) {
                return (int) $seriesMatch->participant_a_id === $winnerParticipantId
                    || (int) $seriesMatch->participant_b_id === $winnerParticipantId;
            });

        if (! $match) {
            return null;
        }

        if ((int) $match->participant_a_id === $winnerParticipantId) {
            return $match->participant_b_id ? (int) $match->participant_b_id : null;
        }

        return $match->participant_a_id ? (int) $match->participant_a_id : null;
    }

    private function participantSummary(?TournamentParticipant $participant): ?array
    {
        if (! $participant) {
            return null;
        }

        return [
            'id' => $participant->id,
            'display_name' => $this->participantDisplayName($participant),
            'group_position' => $participant->group_position,
            'is_withdrawn' => $participant->status->value === 'withdrawn',
        ];
    }

    private function participantDisplayName(TournamentParticipant $participant): string
    {
        if ($participant->team) {
            return $participant->team->name;
        }

        if ($participant->player) {
            $name = trim($participant->player->first_name . ' ' . $participant->player->last_name);

            if ($participant->player->nickname) {
                $name .= ' (' . $participant->player->nickname . ')';
            }

            return $name;
        }

        return 'Učesnik #' . $participant->id;
    }

    private function publicKnockoutRounds(Tournament $tournament): Collection
    {
        $matches = TournamentMatch::query()
            ->with([
                'participantA.player',
                'participantA.team',
                'participantB.player',
                'participantB.team',
                'winner.player',
                'winner.team',
                'resource',
            ])
            ->where('tournament_id', $tournament->id)
            ->whereIn('stage', [
                MatchStage::KNOCKOUT->value,
                MatchStage::THIRD_PLACE->value,
                MatchStage::FINAL->value,
            ])
            ->orderBy('bracket_position')
            ->orderBy('round_robin_leg')
            ->orderBy('id')
            ->get();

        return $matches
            ->groupBy(function (TournamentMatch $match) {
                return ($match->bracket_round ?: $match->stage->value) . '-' . ($match->bracket_position ?: 0);
            })
            ->map(fn (Collection $seriesMatches) => $this->publicKnockoutSeriesSummary($seriesMatches))
            ->sortBy([
                ['round_sort', 'asc'],
                ['position', 'asc'],
            ])
            ->values()
            ->groupBy('round_key')
            ->map(function (Collection $series, string $roundKey) {
                $firstSeries = $series->first();

                return [
                    'round_key' => $roundKey,
                    'round_label' => $firstSeries['round_label'],
                    'round_sort' => $firstSeries['round_sort'],
                    'series' => $series->values(),
                ];
            })
            ->sortBy('round_sort')
            ->values();
    }

    private function publicKnockoutSeriesSummary(Collection $seriesMatches): array
    {
        $seriesMatches = $seriesMatches
            ->sortBy([
                ['round_robin_leg', 'asc'],
                ['id', 'asc'],
            ])
            ->values();

        /** @var TournamentMatch $firstMatch */
        $firstMatch = $seriesMatches->first();

        $roundKey = $firstMatch->bracket_round ?: $firstMatch->stage->value;
        $roundLabel = $this->bracketRoundLabel($roundKey) ?: $this->matchStageLabel($firstMatch->stage->value);
        $winsRequired = (int) ($firstMatch->wins_required ?: 1);

        $participantA = $this->participantFromSeries($seriesMatches, $firstMatch->participant_a_id);
        $participantB = $this->participantFromSeries($seriesMatches, $firstMatch->participant_b_id);

        $participantAWins = $seriesMatches
            ->filter(fn (TournamentMatch $match) => $match->status === MatchStatus::FINISHED)
            ->filter(fn (TournamentMatch $match) => (int) $match->winner_participant_id === (int) $firstMatch->participant_a_id)
            ->count();

        $participantBWins = $seriesMatches
            ->filter(fn (TournamentMatch $match) => $match->status === MatchStatus::FINISHED)
            ->filter(fn (TournamentMatch $match) => (int) $match->winner_participant_id === (int) $firstMatch->participant_b_id)
            ->count();

        $winnerParticipantId = null;

        if ($firstMatch->participant_a_id && $participantAWins >= $winsRequired) {
            $winnerParticipantId = $firstMatch->participant_a_id;
        }

        if ($firstMatch->participant_b_id && $participantBWins >= $winsRequired) {
            $winnerParticipantId = $firstMatch->participant_b_id;
        }

        $finishedLegsCount = $seriesMatches
            ->filter(fn (TournamentMatch $match) => $match->status === MatchStatus::FINISHED)
            ->count();

        $status = $this->publicKnockoutSeriesStatus(
            $participantA,
            $participantB,
            $winnerParticipantId,
            $finishedLegsCount
        );

        return [
            'round_key' => $roundKey,
            'round_label' => $roundLabel,
            'round_sort' => $this->bracketRoundSort($roundKey),
            'position' => (int) ($firstMatch->bracket_position ?: 0),
            'title' => $roundLabel . ($firstMatch->bracket_position ? ' #' . $firstMatch->bracket_position : ''),
            'wins_required' => $winsRequired,
            'max_legs' => $seriesMatches->count(),
            'participant_a' => $this->participantSummary($participantA),
            'participant_b' => $this->participantSummary($participantB),
            'participant_a_wins' => $participantAWins,
            'participant_b_wins' => $participantBWins,
            'series_score' => $participantAWins . ' : ' . $participantBWins,
            'winner' => $this->participantSummary(
                $this->participantFromSeries($seriesMatches, $winnerParticipantId)
            ),
            'status' => $status['status'],
            'status_label' => $status['label'],
            'legs' => $seriesMatches
                ->map(fn (TournamentMatch $match) => [
                    'id' => $match->id,
                    'leg' => $match->round_robin_leg,
                    'status' => $match->status->value,
                    'status_label' => $this->matchStatusLabel($match->status->value),
                    'score' => $match->score_a !== null && $match->score_b !== null
                        ? $match->score_a . ' : ' . $match->score_b
                        : null,
                    'winner' => $this->participantSummary($match->winner),
                    'resource_name' => $match->resource?->name,
                    'win_reason' => $match->win_reason?->value,
                ])
                ->values(),
        ];
    }

    private function participantFromSeries(Collection $seriesMatches, ?int $participantId): ?TournamentParticipant
    {
        if (! $participantId) {
            return null;
        }

        foreach ($seriesMatches as $match) {
            /** @var TournamentMatch $match */
            if ($match->participantA && (int) $match->participantA->id === (int) $participantId) {
                return $match->participantA;
            }

            if ($match->participantB && (int) $match->participantB->id === (int) $participantId) {
                return $match->participantB;
            }

            if ($match->winner && (int) $match->winner->id === (int) $participantId) {
                return $match->winner;
            }
        }

        return null;
    }

    private function publicKnockoutSeriesStatus(
        ?TournamentParticipant $participantA,
        ?TournamentParticipant $participantB,
        ?int $winnerParticipantId,
        int $finishedLegsCount
    ): array {
        if ($winnerParticipantId) {
            return [
                'status' => 'finished',
                'label' => 'Završeno',
            ];
        }

        if (! $participantA || ! $participantB) {
            return [
                'status' => 'waiting_participants',
                'label' => 'Čeka učesnike',
            ];
        }

        if ($finishedLegsCount > 0) {
            return [
                'status' => 'in_progress',
                'label' => 'U toku',
            ];
        }

        return [
            'status' => 'scheduled',
            'label' => 'Zakazano',
        ];
    }

    private function bracketRoundSort(?string $bracketRound): int
    {
        return match ($bracketRound) {
            'round_of_32' => 10,
            'round_of_16' => 20,
            'quarter_final' => 30,
            'semi_final' => 40,
            'third_place' => 50,
            'final' => 60,
            default => 999,
        };
    }

    private function tournamentStatusLabel(string $status): string
    {
        return match ($status) {
            'draft' => 'Draft',
            'group_draw' => 'Izvlačenje grupa',
            'ready' => 'Spreman',
            'group_stage' => 'Grupna faza',
            'repechage' => 'Repasaž',
            'knockout_draw' => 'Žreb za nokaut',
            'knockout_stage' => 'Nokaut faza',
            'finished' => 'Završen',
            default => $status,
        };
    }

    private function matchStageLabel(string $stage): string
    {
        return match ($stage) {
            'group' => 'Grupa',
            'knockout' => 'Nokaut',
            'third_place' => 'Treće mesto',
            'final' => 'Finale',
            default => $stage,
        };
    }

    private function matchStatusLabel(string $status): string
    {
        return match ($status) {
            'scheduled' => 'Zakazano',
            'in_progress' => 'U toku',
            'finished' => 'Završeno',
            'voided' => 'Anulirano',
            'cancelled' => 'Otkazano',
            default => $status,
        };
    }

    private function bracketRoundLabel(?string $bracketRound): ?string
    {
        return match ($bracketRound) {
            'round_of_32' => '1/16 finala',
            'round_of_16' => '1/8 finala',
            'quarter_final' => 'Četvrtfinale',
            'semi_final' => 'Polufinale',
            'third_place' => 'Treće mesto',
            'final' => 'Finale',
            default => $bracketRound,
        };
    }
}
