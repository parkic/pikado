<?php

namespace App\Http\Controllers;

use App\Enums\MatchStage;
use App\Enums\MatchStatus;
use App\Enums\RepechageOutcomeStatus;
use App\Enums\TournamentStatus;
use App\Models\Tournament;
use App\Models\TournamentMatch;
use App\Models\TournamentParticipant;
use App\Models\Venue;
use App\Services\GroupStandingsCalculator;
use App\Services\KnockoutBracketGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class TournamentKnockoutController extends Controller
{
    public function index(
        Request $request,
        Venue $venue,
        Tournament $tournament,
        GroupStandingsCalculator $calculator
    ): Response {
        $user = $request->user();

        abort_unless($user->canAccessVenue($venue), 403);
        abort_unless($tournament->venue_id === $venue->id, 404);

        $groups = collect($calculator->calculate($tournament));

        $directQualifiers = $this->participantsByStatus($groups, 'direct')
            ->map(fn (array $participant) => array_merge($participant, [
                'source' => 'direct',
                'source_label' => 'Direktan prolaz',
            ]));

        $repechageQualifiers = $this->participantsByStatus($groups, 'repechage')
            ->filter(fn (array $participant) => $participant['repechage_outcome_status'] === RepechageOutcomeStatus::ADVANCED->value)
            ->map(fn (array $participant) => array_merge($participant, [
                'source' => 'repechage',
                'source_label' => 'Prošao iz repasaža',
            ]));

        $knockoutParticipants = $directQualifiers
            ->concat($repechageQualifiers)
            ->values()
            ->map(function (array $participant, int $index) {
                $participant['seed'] = $index + 1;

                return $participant;
            });

        $knockoutSize = $tournament->knockout_size ?? 0;

        return Inertia::render('Venues/Tournaments/Knockout/Index', [
            'venue' => [
                'id' => $venue->id,
                'name' => $venue->name,
                'slug' => $venue->slug,
            ],
            'tournament' => [
                'id' => $tournament->id,
                'name' => $tournament->name,
                'slug' => $tournament->slug,
                'status' => $tournament->status->value,
                'status_label' => $tournament->status->value,
                'knockout_size' => $knockoutSize,
                'knockout_participants_count' => $knockoutParticipants->count(),
                'direct_qualifiers_count' => $directQualifiers->count(),
                'repechage_qualifiers_count' => $repechageQualifiers->count(),
                'is_knockout_ready' => $knockoutSize > 0 && $knockoutParticipants->count() === $knockoutSize,
                'knockout_matches_count' => $tournament->matches()
                    ->whereIn('stage', [
                        MatchStage::KNOCKOUT->value,
                        MatchStage::THIRD_PLACE->value,
                        MatchStage::FINAL->value,
                    ])
                    ->count(),
                'can_generate_knockout_bracket' => $this->canGenerateKnockoutBracket(
                    $tournament,
                    $knockoutParticipants,
                ),
            ],
            'direct_qualifiers' => $directQualifiers->values(),
            'repechage_qualifiers' => $repechageQualifiers->values(),
            'knockout_participants' => $knockoutParticipants->values(),
            'knockout_series' => $this->knockoutSeriesGroups($tournament),
        ]);
    }

    public function generate(
        Request $request,
        Venue $venue,
        Tournament $tournament,
        KnockoutBracketGenerator $generator
    ): RedirectResponse {
        $user = $request->user();

        abort_unless($user->canAccessVenue($venue), 403);
        abort_unless($tournament->venue_id === $venue->id, 404);

        $groups = collect(app(GroupStandingsCalculator::class)->calculate($tournament));

        $directQualifiers = $this->participantsByStatus($groups, 'direct')
            ->map(fn (array $participant) => array_merge($participant, [
                'source' => 'direct',
                'source_label' => 'Direktan prolaz',
            ]));

        $repechageQualifiers = $this->participantsByStatus($groups, 'repechage')
            ->filter(fn (array $participant) => $participant['repechage_outcome_status'] === RepechageOutcomeStatus::ADVANCED->value)
            ->map(fn (array $participant) => array_merge($participant, [
                'source' => 'repechage',
                'source_label' => 'Prošao iz repasaža',
            ]));

        $knockoutParticipants = $directQualifiers
            ->concat($repechageQualifiers)
            ->values();

        if (! $this->canGenerateKnockoutBracket($tournament, $knockoutParticipants)) {
            return back()->withErrors([
                'knockout' => 'Nokaut kostur ne može da se generiše. Proveri status turnira, broj učesnika i da li kostur već postoji.',
            ]);
        }

        $createdMatches = $generator->generate($tournament);

        $tournament->update([
            'status' => TournamentStatus::KNOCKOUT_STAGE,
        ]);

        return redirect()
            ->route('venues.tournaments.schedule.index', [$venue, $tournament])
            ->with('success', 'Generisano nokaut mečeva: ' . $createdMatches . '.');
    }

    private function participantsByStatus(Collection $groups, string $status): Collection
    {
        return $groups
            ->flatMap(function (array $group) use ($status) {
                return collect($group['rows'])
                    ->filter(fn (array $row) => $row['qualification_status'] === $status)
                    ->map(fn (array $row) => [
                        'participant_id' => $row['participant_id'],
                        'group_name' => $group['name'],
                        'group_position' => $row['group_position'],
                        'group_rank' => $row['position'],
                        'display_name' => $row['display_name'],
                        'played' => $row['played'],
                        'wins' => $row['wins'],
                        'losses' => $row['losses'],
                        'points_for' => $row['points_for'],
                        'points_against' => $row['points_against'],
                        'points_difference' => $row['points_difference'],
                        'standing_points' => $row['standing_points'],
                        'repechage_outcome_status' => $row['repechage_outcome_status'] ?? null,
                        'repechage_outcome_label' => $row['repechage_outcome_label'] ?? 'Neodlučeno',
                    ]);
            })
            ->values();
    }

    private function canGenerateKnockoutBracket(Tournament $tournament, Collection $knockoutParticipants): bool
    {
        if ($tournament->status !== TournamentStatus::KNOCKOUT_DRAW) {
            return false;
        }

        $knockoutSize = (int) $tournament->knockout_size;

        if ($knockoutSize < 2) {
            return false;
        }

        if ($knockoutParticipants->count() !== $knockoutSize) {
            return false;
        }

        return ! $tournament->matches()
            ->whereIn('stage', [
                MatchStage::KNOCKOUT->value,
                MatchStage::THIRD_PLACE->value,
                MatchStage::FINAL->value,
            ])
            ->exists();
    }

    private function knockoutSeriesGroups(Tournament $tournament): Collection
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

        if ($matches->isEmpty()) {
            return collect();
        }

        $series = $matches
            ->groupBy(fn (TournamentMatch $match) => $match->bracket_round . '-' . $match->bracket_position)
            ->map(fn (Collection $seriesMatches) => $this->knockoutSeriesSummary($seriesMatches))
            ->sortBy(fn (array $series) => sprintf('%02d-%03d', $series['round_sort'], $series['position']))
            ->values();

        return $series
            ->groupBy('round_key')
            ->map(function (Collection $roundSeries) {
                $firstSeries = $roundSeries->first();

                return [
                    'round_key' => $firstSeries['round_key'],
                    'round_label' => $firstSeries['round_label'],
                    'round_sort' => $firstSeries['round_sort'],
                    'series' => $roundSeries->values(),
                ];
            })
            ->sortBy('round_sort')
            ->values();
    }

    private function knockoutSeriesSummary(Collection $seriesMatches): array
    {
        $seriesMatches = $seriesMatches
            ->sortBy('round_robin_leg')
            ->values();

        /** @var TournamentMatch $firstMatch */
        $firstMatch = $seriesMatches->first();

        $participantA = $firstMatch->participantA;
        $participantB = $firstMatch->participantB;

        $participantAId = $firstMatch->participant_a_id ? (int) $firstMatch->participant_a_id : null;
        $participantBId = $firstMatch->participant_b_id ? (int) $firstMatch->participant_b_id : null;

        $winsRequired = (int) ($firstMatch->wins_required ?: 1);
        $winsByParticipant = [];
        $seriesWinnerId = null;
        $finishedLegsCount = 0;

        foreach ($seriesMatches as $match) {
            if ($match->status !== MatchStatus::FINISHED) {
                continue;
            }

            if (! $match->winner_participant_id) {
                continue;
            }

            $finishedLegsCount++;

            $winnerParticipantId = (int) $match->winner_participant_id;

            $winsByParticipant[$winnerParticipantId] =
                ($winsByParticipant[$winnerParticipantId] ?? 0) + 1;

            if ($winsByParticipant[$winnerParticipantId] >= $winsRequired) {
                $seriesWinnerId = $winnerParticipantId;
                break;
            }
        }

        $participantAWins = $participantAId
            ? ($winsByParticipant[$participantAId] ?? 0)
            : 0;

        $participantBWins = $participantBId
            ? ($winsByParticipant[$participantBId] ?? 0)
            : 0;

        [$seriesStatus, $seriesStatusLabel] = $this->knockoutSeriesStatus(
            $participantA,
            $participantB,
            $seriesWinnerId,
            $finishedLegsCount,
        );

        return [
            'round_key' => $firstMatch->bracket_round,
            'round_label' => $this->bracketRoundLabel($firstMatch->bracket_round),
            'round_sort' => $this->bracketRoundSort($firstMatch->bracket_round),
            'position' => (int) $firstMatch->bracket_position,
            'title' => $this->bracketRoundLabel($firstMatch->bracket_round) . ' #' . $firstMatch->bracket_position,
            'wins_required' => $winsRequired,
            'max_legs' => $seriesMatches->count(),
            'participant_a' => $this->participantSummary($participantA),
            'participant_b' => $this->participantSummary($participantB),
            'participant_a_wins' => $participantAWins,
            'participant_b_wins' => $participantBWins,
            'series_score' => $participantAWins . ' : ' . $participantBWins,
            'winner' => $this->participantSummary(
                $this->participantFromSeries($seriesMatches, $seriesWinnerId)
            ),
            'status' => $seriesStatus,
            'status_label' => $seriesStatusLabel,
            'legs' => $seriesMatches
                ->map(fn (TournamentMatch $match) => [
                    'id' => $match->id,
                    'leg' => (int) $match->round_robin_leg,
                    'status' => $match->status->value,
                    'status_label' => $this->matchStatusLabel($match->status),
                    'score' => $match->score_a !== null && $match->score_b !== null
                        ? $match->score_a . ' : ' . $match->score_b
                        : null,
                    'winner' => $this->participantSummary($match->winner),
                    'resource_name' => $match->resource?->name,
                ])
                ->values(),
        ];
    }

    private function knockoutSeriesStatus(
        ?TournamentParticipant $participantA,
        ?TournamentParticipant $participantB,
        ?int $seriesWinnerId,
        int $finishedLegsCount,
    ): array {
        if (! $participantA || ! $participantB) {
            return ['waiting_participants', 'Čeka učesnike'];
        }

        if ($seriesWinnerId) {
            return ['finished', 'Završeno'];
        }

        if ($finishedLegsCount > 0) {
            return ['in_progress', 'U toku'];
        }

        return ['scheduled', 'Nije počelo'];
    }

    private function participantFromSeries(Collection $seriesMatches, ?int $participantId): ?TournamentParticipant
    {
        if (! $participantId) {
            return null;
        }

        foreach ($seriesMatches as $match) {
            if ($match->participantA && (int) $match->participantA->id === $participantId) {
                return $match->participantA;
            }

            if ($match->participantB && (int) $match->participantB->id === $participantId) {
                return $match->participantB;
            }

            if ($match->winner && (int) $match->winner->id === $participantId) {
                return $match->winner;
            }
        }

        return null;
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
            'status' => $participant->status->value,
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

    private function bracketRoundLabel(?string $bracketRound): string
    {
        return match ($bracketRound) {
            'round_of_32' => '1/16 finala',
            'round_of_16' => '1/8 finala',
            'quarter_final' => 'Četvrtfinale',
            'semi_final' => 'Polufinale',
            'third_place' => 'Treće mesto',
            'final' => 'Finale',
            default => $bracketRound ?: 'Nokaut',
        };
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

    private function matchStatusLabel(MatchStatus $status): string
    {
        return match ($status) {
            MatchStatus::SCHEDULED => 'Zakazano',
            MatchStatus::IN_PROGRESS => 'U toku',
            MatchStatus::FINISHED => 'Završeno',
            MatchStatus::VOIDED => 'Anulirano',
            MatchStatus::CANCELLED => 'Otkazano',
        };
    }
}
