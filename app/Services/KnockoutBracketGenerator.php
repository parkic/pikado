<?php

namespace App\Services;

use App\Enums\MatchStage;
use App\Enums\MatchStatus;
use App\Enums\RepechageOutcomeStatus;
use App\Models\Tournament;
use App\Models\TournamentMatch;
use App\Models\TournamentResource;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class KnockoutBracketGenerator
{
    public function __construct(
        private readonly GroupStandingsCalculator $standingsCalculator,
    ) {
    }

    public function generate(Tournament $tournament): int
    {
        return DB::transaction(function () use ($tournament): int {
            $participants = $this->knockoutParticipants($tournament);
            $knockoutSize = (int) $tournament->knockout_size;

            if ($knockoutSize < 2 || $participants->count() !== $knockoutSize) {
                return 0;
            }

            $resources = $tournament->resources()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get();

            $roundDefinitions = $this->roundDefinitions($knockoutSize);
            $firstRoundPairs = $this->firstRoundPairs($participants, $tournament);

            $scheduledOrder = ((int) $tournament->matches()->max('scheduled_order')) + 1;
            $resourceIndex = 0;
            $createdMatches = 0;

            foreach ($roundDefinitions as $roundIndex => $roundDefinition) {
                $legsCount = ($roundDefinition['wins_required'] * 2) - 1;

                for ($leg = 1; $leg <= $legsCount; $leg++) {
                    for ($position = 1; $position <= $roundDefinition['matches_count']; $position++) {
                        $participantAId = null;
                        $participantBId = null;

                        $meta = [
                            'round_label' => $roundDefinition['label'],
                            'series_wins_required' => $roundDefinition['wins_required'],
                        ];

                        if ($roundDefinition['source_round']) {
                            $meta['participant_a_source_round'] = $roundDefinition['source_round'];
                            $meta['participant_a_source_position'] = ($position * 2) - 1;
                            $meta['participant_a_source_outcome'] = $roundDefinition['source_outcome'];

                            $meta['participant_b_source_round'] = $roundDefinition['source_round'];
                            $meta['participant_b_source_position'] = $position * 2;
                            $meta['participant_b_source_outcome'] = $roundDefinition['source_outcome'];
                        }

                        if ($roundDefinition['key'] === 'third_place') {
                            $meta['participant_a_source_round'] = $roundDefinition['source_round'];
                            $meta['participant_a_source_position'] = 1;
                            $meta['participant_a_source_outcome'] = 'loser';

                            $meta['participant_b_source_round'] = $roundDefinition['source_round'];
                            $meta['participant_b_source_position'] = 2;
                            $meta['participant_b_source_outcome'] = 'loser';
                        }

                        if ($roundIndex === 0) {
                            $pair = $firstRoundPairs[$position - 1] ?? null;

                            if ($pair) {
                                $participantAId = $pair[0]['participant_id'];
                                $participantBId = $pair[1]['participant_id'];

                                $meta['participant_a_seed'] = $pair[0]['seed'];
                                $meta['participant_b_seed'] = $pair[1]['seed'];
                                $meta['participant_a_group'] = $pair[0]['group_name'];
                                $meta['participant_b_group'] = $pair[1]['group_name'];
                                $meta['participant_a_source'] = $pair[0]['source'];
                                $meta['participant_b_source'] = $pair[1]['source'];
                            }
                        }

                        TournamentMatch::create([
                            'tournament_id' => $tournament->id,
                            'stage' => $roundDefinition['stage'],
                            'bracket_round' => $roundDefinition['key'],
                            'bracket_position' => $position,
                            'participant_a_id' => $participantAId,
                            'participant_b_id' => $participantBId,
                            'status' => MatchStatus::SCHEDULED,
                            'tournament_resource_id' => $this->resourceIdForMatch($resources, $resourceIndex),
                            'scheduled_order' => $scheduledOrder,
                            'round_robin_leg' => $leg,
                            'wins_required' => $roundDefinition['wins_required'],
                            'meta' => $meta,
                        ]);

                        $scheduledOrder++;
                        $resourceIndex++;
                        $createdMatches++;
                    }
                }
            }

            return $createdMatches;
        });
    }

    private function knockoutParticipants(Tournament $tournament): Collection
    {
        $groups = collect($this->standingsCalculator->calculate($tournament));

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

        return $directQualifiers
            ->concat($repechageQualifiers)
            ->values()
            ->map(function (array $participant, int $index) {
                $participant['seed'] = $index + 1;

                return $participant;
            });
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
                    ]);
            })
            ->values();
    }

    private function firstRoundPairs(Collection $participants, Tournament $tournament): array
    {
        $participants = $participants->values();
        $half = (int) ($participants->count() / 2);

        $topSeeds = $participants->take($half)->values();
        $bottomSeeds = $participants->slice($half)->reverse()->values();

        $avoidSameGroup = (bool) data_get(
            $tournament->settings ?? [],
            'avoid_same_group_rematch',
            true,
        );

        $pairs = [];

        foreach ($topSeeds as $topSeed) {
            $candidateIndex = 0;

            if ($avoidSameGroup) {
                $candidateIndex = $bottomSeeds->search(function (array $bottomSeed) use ($topSeed) {
                    return $bottomSeed['group_name'] !== $topSeed['group_name'];
                });

                if ($candidateIndex === false) {
                    $candidateIndex = 0;
                }
            }

            $bottomSeed = $bottomSeeds->splice($candidateIndex, 1)->first();

            if ($bottomSeed) {
                $pairs[] = [$topSeed, $bottomSeed];
            }
        }

        return $pairs;
    }

    private function roundDefinitions(int $knockoutSize): array
    {
        $standardRounds = [];
        $matchesCount = (int) ($knockoutSize / 2);

        while ($matchesCount >= 1) {
            $standardRounds[] = [
                'key' => $this->roundKey($matchesCount),
                'label' => $this->roundLabel($matchesCount),
                'matches_count' => $matchesCount,
                'stage' => $matchesCount === 1
                    ? MatchStage::FINAL
                    : MatchStage::KNOCKOUT,
                'wins_required' => $matchesCount === 1 ? 3 : 2,
                'source_round' => null,
                'source_outcome' => 'winner',
            ];

            $matchesCount = (int) ($matchesCount / 2);
        }

        foreach ($standardRounds as $index => $round) {
            if ($index === 0) {
                continue;
            }

            $standardRounds[$index]['source_round'] = $standardRounds[$index - 1]['key'];
        }

        if (count($standardRounds) < 3) {
            return $standardRounds;
        }

        $finalRound = array_pop($standardRounds);
        $semiFinalRound = $standardRounds[array_key_last($standardRounds)];

        $thirdPlaceRound = [
            'key' => 'third_place',
            'label' => 'Treće mesto',
            'matches_count' => 1,
            'stage' => MatchStage::THIRD_PLACE,
            'wins_required' => 2,
            'source_round' => $semiFinalRound['key'],
            'source_outcome' => 'loser',
        ];

        return [
            ...$standardRounds,
            $thirdPlaceRound,
            $finalRound,
        ];
    }

    private function roundKey(int $matchesCount): string
    {
        return match ($matchesCount) {
            16 => 'round_of_32',
            8 => 'round_of_16',
            4 => 'quarter_final',
            2 => 'semi_final',
            1 => 'final',
            default => 'knockout_round_' . $matchesCount,
        };
    }

    private function roundLabel(int $matchesCount): string
    {
        return match ($matchesCount) {
            16 => '1/16 finala',
            8 => '1/8 finala',
            4 => 'Četvrtfinale',
            2 => 'Polufinale',
            1 => 'Finale',
            default => 'Nokaut runda',
        };
    }

    private function resourceIdForMatch(Collection $resources, int $resourceIndex): ?int
    {
        if ($resources->isEmpty()) {
            return null;
        }

        /** @var TournamentResource $resource */
        $resource = $resources[$resourceIndex % $resources->count()];

        return $resource->id;
    }
}
