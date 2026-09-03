<?php

namespace App\Http\Controllers;

use App\Enums\GameType;
use App\Enums\MatchMode;
use App\Enums\MatchStage;
use App\Enums\MatchStatus;
use App\Models\Tournament;
use App\Models\TournamentMatch;
use App\Models\TournamentParticipant;
use App\Models\Venue;
use App\Services\GroupStandingsCalculator;
use App\Services\KnockoutDrawService;
use App\Services\TournamentLiveMatchSelector;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class PublicTournamentController extends Controller
{
    public function live(
        string $publicCode,
        GroupStandingsCalculator $calculator,
        TournamentLiveMatchSelector $liveMatchSelector,
    ): Response {
        $tournament = Tournament::query()
            ->with([
                'venue',
            ])
            ->where('public_code', $publicCode)
            ->where('public_enabled', true)
            ->firstOrFail();

        $qualificationPositions = $this->qualificationPositions(
            collect($calculator->calculate($tournament)),
        );

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

        $scheduledMatches = (clone $matchesQuery)
            ->where('status', MatchStatus::SCHEDULED->value)
            ->orderBy('scheduled_order')
            ->orderBy('id')
            ->get();

        $postponedMatches = (clone $matchesQuery)
            ->where('status', MatchStatus::POSTPONED->value)
            ->orderBy('scheduled_order')
            ->orderBy('id')
            ->get()
            ->unique(fn (TournamentMatch $match) => $liveMatchSelector->seriesKey($match))
            ->map(fn (TournamentMatch $match) => $this->matchSummary($match, $qualificationPositions))
            ->values();

        $liveMatches = $liveMatchSelector->select($scheduledMatches);

        $currentMatches = $liveMatches['current']
            ->map(fn (TournamentMatch $match) => $this->matchSummary($match, $qualificationPositions))
            ->values();

        $nextMatches = $liveMatches['next']
            ->map(fn (TournamentMatch $match) => $this->matchSummary($match, $qualificationPositions))
            ->values();

        $recentMatches = (clone $matchesQuery)
            ->where('status', MatchStatus::FINISHED->value)
            ->orderByDesc('finished_at')
            ->orderByDesc('updated_at')
            ->limit(8)
            ->get()
            ->map(fn (TournamentMatch $match) => $this->matchSummary($match, $qualificationPositions))
            ->values();

        return Inertia::render('Public/Tournaments/Live', [
            'venue' => $this->publicVenueData($tournament->venue),
            'tournament' => [
                'id' => $tournament->id,
                'name' => $tournament->name,
                'public_code' => $tournament->public_code,
                'game_type' => $this->gameTypeLabel($tournament->game_type),
                'match_mode' => $this->matchModeLabel($tournament->match_mode),
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
            'current_matches' => $currentMatches,
            'postponed_matches' => $postponedMatches,
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
        $profileUrls = $tournament->participants()
            ->with('player')
            ->get()
            ->mapWithKeys(fn (TournamentParticipant $participant): array => [
                $participant->id => $participant->player
                    ? route('public.players.show', $participant->player, false)
                    : null,
            ]);

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
            ->map(function (array $group) use ($groupMatches, $profileUrls) {
                return [
                    'id' => $group['id'],
                    'name' => $group['name'],
                    'matches_count' => $group['matches_count'],
                    'finished_matches_count' => $group['finished_matches_count'],
                    'rows' => collect($group['rows'])
                        ->map(fn (array $row): array => array_merge($row, [
                            'profile_url' => $profileUrls->get($row['participant_id']),
                        ]))
                        ->values(),
                    'matches' => collect($groupMatches->get($group['id'], collect()))
                        ->map(fn (TournamentMatch $match) => $this->matchSummary($match))
                        ->values(),
                ];
            })
            ->values();

        return Inertia::render('Public/Tournaments/Groups', [
            'venue' => $this->publicVenueData($tournament->venue),
            'tournament' => [
                'id' => $tournament->id,
                'name' => $tournament->name,
                'public_code' => $tournament->public_code,
                'game_type' => $this->gameTypeLabel($tournament->game_type),
                'match_mode' => $this->matchModeLabel($tournament->match_mode),
                'status' => $tournament->status->value,
                'status_label' => $this->tournamentStatusLabel($tournament->status->value),
                'has_repechage' => (bool) data_get(
                    $tournament->settings ?? [],
                    'repechage_enabled',
                    false
                ),
            ],
            'groups' => $groups,
        ]);
    }

    public function schedule(
        string $publicCode,
        GroupStandingsCalculator $calculator,
    ): Response {
        $tournament = Tournament::query()
            ->with([
                'venue',
            ])
            ->where('public_code', $publicCode)
            ->where('public_enabled', true)
            ->firstOrFail();

        $qualificationPositions = $this->qualificationPositions(
            collect($calculator->calculate($tournament)),
        );

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

        $postponedMatchesCount = (clone $matchesQuery)
            ->where('status', MatchStatus::POSTPONED->value)
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
            ->map(fn (TournamentMatch $match) => $this->matchSummary($match, $qualificationPositions))
            ->values();

        return Inertia::render('Public/Tournaments/Schedule', [
            'venue' => $this->publicVenueData($tournament->venue),
            'tournament' => [
                'id' => $tournament->id,
                'name' => $tournament->name,
                'public_code' => $tournament->public_code,
                'game_type' => $this->gameTypeLabel($tournament->game_type),
                'match_mode' => $this->matchModeLabel($tournament->match_mode),
                'status' => $tournament->status->value,
                'status_label' => $this->tournamentStatusLabel($tournament->status->value),
                'matches_count' => $matchesCount,
                'scheduled_matches_count' => $scheduledMatchesCount,
                'in_progress_matches_count' => $inProgressMatchesCount,
                'postponed_matches_count' => $postponedMatchesCount,
                'finished_matches_count' => $finishedMatchesCount,
                'voided_matches_count' => $voidedMatchesCount,
            ],
            'matches' => $matches,
        ]);
    }

    public function knockout(
        string $publicCode,
        GroupStandingsCalculator $calculator,
        KnockoutDrawService $drawService,
    ): Response {
        $tournament = Tournament::query()
            ->with([
                'venue',
            ])
            ->where('public_code', $publicCode)
            ->where('public_enabled', true)
            ->firstOrFail();

        $groups = collect($calculator->calculate($tournament));
        $qualificationPositions = $this->qualificationPositions($groups);

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
            'venue' => $this->publicVenueData($tournament->venue),
            'tournament' => [
                'id' => $tournament->id,
                'name' => $tournament->name,
                'public_code' => $tournament->public_code,
                'game_type' => $this->gameTypeLabel($tournament->game_type),
                'match_mode' => $this->matchModeLabel($tournament->match_mode),
                'status' => $tournament->status->value,
                'status_label' => $this->tournamentStatusLabel($tournament->status->value),
                'knockout_size' => $tournament->knockout_size,
                'knockout_matches_count' => $knockoutMatchesCount,
                'finished_knockout_matches_count' => $finishedKnockoutMatchesCount,
                'voided_knockout_matches_count' => $voidedKnockoutMatchesCount,
                'repechage_qualifiers_count' => (int) data_get(
                    $tournament->settings ?? [],
                    'repechage_qualifiers_count',
                    0,
                ),
            ],
            'repechage_participants' => $tournament->status->value === 'repechage'
                ? $this->publicRepechageParticipants($tournament, $groups)
                : [],
            'rounds' => $this->publicKnockoutRounds(
                $tournament,
                $qualificationPositions,
            ),
            'knockout_draw' => $drawService->state($tournament),
        ]);
    }

    private function matchSummary(
        TournamentMatch $match,
        ?Collection $qualificationPositions = null,
    ): array {
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
            'participant_a' => $this->participantSummary($match->participantA, $qualificationPositions),
            'participant_b' => $this->participantSummary($match->participantB, $qualificationPositions),
            'score_a' => $match->score_a,
            'score_b' => $match->score_b,
            'winner' => $this->participantSummary($match->winner, $qualificationPositions),
            'status' => $match->status->value,
            'status_label' => $this->matchStatusLabel($match->status->value),
            'resource_name' => $match->resource?->name,
            'finished_at' => $match->finished_at?->toIso8601String(),
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
            'score' => $participantAWins.' : '.$participantBWins,
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

    private function participantSummary(
        ?TournamentParticipant $participant,
        ?Collection $qualificationPositions = null,
    ): ?array {
        if (! $participant) {
            return null;
        }

        return [
            'id' => $participant->id,
            'display_name' => $this->participantDisplayName($participant),
            'qualification_position' => $qualificationPositions?->get($participant->id),
            'is_withdrawn' => $participant->status->value === 'withdrawn',
            'player_id' => $participant->player_id,
            'profile_url' => $participant->player
                ? route('public.players.show', $participant->player, false)
                : null,
        ];
    }

    private function participantDisplayName(TournamentParticipant $participant): string
    {
        if ($participant->team) {
            return $participant->team->name;
        }

        if ($participant->player) {
            $name = trim($participant->player->first_name.' '.$participant->player->last_name);

            if ($participant->player->nickname) {
                $name .= ' ('.$participant->player->nickname.')';
            }

            return $name;
        }

        return 'Učesnik #'.$participant->id;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $groups
     * @return Collection<int, array<string, mixed>>
     */
    private function publicRepechageParticipants(
        Tournament $tournament,
        Collection $groups,
    ): Collection {
        $participants = $tournament->participants()
            ->with('player')
            ->get()
            ->keyBy('id');

        return $groups
            ->flatMap(function (array $group) use ($participants): Collection {
                return collect($group['rows'])
                    ->where('qualification_status', 'repechage')
                    ->map(function (array $row) use ($group, $participants): array {
                        /** @var TournamentParticipant|null $participant */
                        $participant = $participants->get($row['participant_id']);

                        return [
                            'participant_id' => $row['participant_id'],
                            'display_name' => $row['display_name'],
                            'group_name' => $group['name'],
                            'group_rank' => $row['position'],
                            'qualification_position' => $row['qualification_position'],
                            'played' => $row['played'],
                            'wins' => $row['wins'],
                            'losses' => $row['losses'],
                            'points_difference' => $row['points_difference'],
                            'standing_points' => $row['standing_points'],
                            'repechage_outcome_status' => $row['repechage_outcome_status'],
                            'repechage_outcome_label' => $row['repechage_outcome_label'],
                            'profile_url' => $participant?->player
                                ? route('public.players.show', $participant->player, false)
                                : null,
                        ];
                    });
            })
            ->sortBy([
                ['group_name', 'asc'],
                ['group_rank', 'asc'],
                ['display_name', 'asc'],
            ])
            ->values();
    }

    private function publicKnockoutRounds(
        Tournament $tournament,
        Collection $qualificationPositions,
    ): Collection {
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
                return ($match->bracket_round ?: $match->stage->value).'-'.($match->bracket_position ?: 0);
            })
            ->map(fn (Collection $seriesMatches) => $this->publicKnockoutSeriesSummary(
                $seriesMatches,
                $qualificationPositions,
            ))
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

    private function publicKnockoutSeriesSummary(
        Collection $seriesMatches,
        Collection $qualificationPositions,
    ): array {
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
            'title' => $roundLabel.($firstMatch->bracket_position ? ' #'.$firstMatch->bracket_position : ''),
            'wins_required' => $winsRequired,
            'max_legs' => ($winsRequired * 2) - 1,
            'participant_a' => $this->participantSummary($participantA, $qualificationPositions),
            'participant_b' => $this->participantSummary($participantB, $qualificationPositions),
            'participant_a_wins' => $participantAWins,
            'participant_b_wins' => $participantBWins,
            'series_score' => $participantAWins.' : '.$participantBWins,
            'winner' => $this->participantSummary(
                $this->participantFromSeries($seriesMatches, $winnerParticipantId),
                $qualificationPositions,
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
                        ? $match->score_a.' : '.$match->score_b
                        : null,
                    'winner' => $this->participantSummary($match->winner, $qualificationPositions),
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

    private function qualificationPositions(Collection $groups): Collection
    {
        return $groups
            ->flatMap(fn (array $group) => collect($group['rows']))
            ->filter(fn (array $row) => $row['qualification_position'] !== null)
            ->mapWithKeys(fn (array $row) => [
                $row['participant_id'] => $row['qualification_position'],
            ]);
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
            'preliminary' => 5,
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
            'draft' => 'Priprema',
            'group_draw' => 'Unos učesnika',
            'ready' => 'Spreman',
            'group_stage' => 'Grupna faza',
            'repechage' => 'Repasaž',
            'knockout_draw' => 'Žreb za nokaut',
            'knockout_stage' => 'Nokaut faza',
            'finished' => 'Završen',
            default => $status,
        };
    }

    /**
     * @return array<string, string|null>
     */
    private function publicVenueData(Venue $venue): array
    {
        return [
            'name' => $venue->name,
            'slug' => $venue->slug,
            'logo_url' => $venue->logo_path
                ? '/storage/'.ltrim($venue->logo_path, '/')
                : null,
            'description' => $venue->description,
            'address' => $venue->address,
            'phone' => $venue->phone,
            'website_url' => $venue->website_url,
            'instagram_url' => $venue->instagram_url,
            'public_theme' => in_array($venue->public_theme, ['dark', 'light'], true)
                ? $venue->public_theme
                : 'dark',
        ];
    }

    private function gameTypeLabel(GameType $gameType): string
    {
        return match ($gameType) {
            GameType::DART_301 => '301',
            GameType::DART_501 => '501',
            GameType::CRICKET => 'Cricket',
            GameType::BEER_PONG => 'Beer pong',
        };
    }

    private function matchModeLabel(MatchMode $matchMode): string
    {
        return match ($matchMode) {
            MatchMode::SINGLES => '1 na 1',
            MatchMode::DOUBLES => '2 na 2',
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
            'postponed' => 'Privremeno preskočen',
            'finished' => 'Završeno',
            'voided' => 'Anulirano',
            'cancelled' => 'Otkazano',
            default => $status,
        };
    }

    private function bracketRoundLabel(?string $bracketRound): ?string
    {
        return match ($bracketRound) {
            'preliminary' => 'Preliminarna runda',
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
