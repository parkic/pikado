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
        private readonly KnockoutDrawService $drawService,
    ) {}

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

            $scheduledOrder = ((int) TournamentMatch::withoutGlobalScope('visible_matches')
                ->where('tournament_id', $tournament->id)
                ->max('scheduled_order')) + 1;
            $createdMatches = 0;

            if (! $this->isPowerOfTwo($knockoutSize)) {
                return $this->generateWithPreliminaryRound(
                    $tournament,
                    $participants,
                    $resources,
                    $scheduledOrder,
                );
            }

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
                            'tournament_resource_id' => $this->resourceIdForPosition(
                                $resources,
                                $position,
                            ),
                            'scheduled_order' => $scheduledOrder,
                            'round_robin_leg' => $leg,
                            'wins_required' => $roundDefinition['wins_required'],
                            'meta' => $meta,
                        ]);

                        $scheduledOrder++;
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
                        'qualification_position' => $row['qualification_position'],
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

    /**
     * @param  Collection<int, array<string, mixed>>  $participants
     * @return array<int, array{0: array<string, mixed>, 1: array<string, mixed>}>
     */
    private function firstRoundPairs(Collection $participants, Tournament $tournament): array
    {
        $participants = $participants->values();
        $drawPairs = $this->completedDrawPairs($participants, $tournament);

        if ($drawPairs !== null) {
            return $drawPairs;
        }

        $groupPlacementPairs = $this->groupPlacementPairs($participants);

        if ($groupPlacementPairs !== null) {
            return $groupPlacementPairs;
        }

        $matchesCount = (int) ($participants->count() / 2);
        $regionsCount = $this->bracketSeparationRegions($participants, $matchesCount);
        $regionCapacity = (int) ($participants->count() / $regionsCount);
        $regions = array_fill(0, $regionsCount, []);

        $avoidSameGroup = (bool) data_get(
            $tournament->settings ?? [],
            'avoid_same_group_rematch',
            true,
        );

        $groupedParticipants = $participants->groupBy('group_name');
        $groupIndex = 0;

        foreach ($groupedParticipants as $groupParticipants) {
            $groupParticipants = $groupParticipants
                ->sortBy([
                    ['group_rank', 'asc'],
                    ['seed', 'asc'],
                ])
                ->values();

            foreach ($groupParticipants as $participantIndex => $participant) {
                $preferredRegion = ($groupIndex + $participantIndex) % $regionsCount;
                $availableRegions = array_values(array_filter(
                    range(0, $regionsCount - 1),
                    fn (int $regionIndex) => count($regions[$regionIndex]) < $regionCapacity,
                ));

                usort(
                    $availableRegions,
                    function (int $left, int $right) use (
                        $regions,
                        $regionsCount,
                        $preferredRegion,
                        $participant,
                    ): int {
                        $leftScore = [
                            $this->regionContainsGroup($regions[$left], $participant['group_name']) ? 1 : 0,
                            count($regions[$left]),
                            ($left - $preferredRegion + $regionsCount) % $regionsCount,
                            $left,
                        ];
                        $rightScore = [
                            $this->regionContainsGroup($regions[$right], $participant['group_name']) ? 1 : 0,
                            count($regions[$right]),
                            ($right - $preferredRegion + $regionsCount) % $regionsCount,
                            $right,
                        ];

                        return $leftScore <=> $rightScore;
                    },
                );

                $targetRegion = $availableRegions[0];
                $regions[$targetRegion][] = $participant;
            }

            $groupIndex++;
        }

        $pairs = [];

        foreach ($regions as $regionParticipants) {
            $regionParticipants = collect($regionParticipants)
                ->sortBy([
                    ['group_rank', 'asc'],
                    ['seed', 'asc'],
                ])
                ->values();

            $half = (int) ($regionParticipants->count() / 2);
            $topSeeds = $regionParticipants->take($half)->values();
            $bottomSeeds = $regionParticipants->slice($half)->reverse()->values();

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
        }

        return $pairs;
    }

    /**
     * The lucky draw owns the exact first-round pair order when every
     * participant in this round was drawn. Smaller preliminary subsets fall
     * back to the normal anti-rematch pairing algorithm.
     *
     * @param  Collection<int, array<string, mixed>>  $participants
     * @return array<int, array{0: array<string, mixed>, 1: array<string, mixed>}>|null
     */
    private function completedDrawPairs(Collection $participants, Tournament $tournament): ?array
    {
        $state = $this->drawService->state($tournament);

        if (! $state['enabled'] || ! $state['complete']) {
            return null;
        }

        $participantMap = $participants->keyBy('participant_id');
        $slotIds = collect($state['slots'])
            ->flatMap(fn (array $slot): array => [
                $slot['seeded']['participant_id'] ?? null,
                $slot['unseeded']['participant_id'] ?? null,
            ])
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->values();

        if ($slotIds->sort()->values()->all() !== $participantMap->keys()->map(fn ($id) => (int) $id)->sort()->values()->all()) {
            return null;
        }

        return collect($state['slots'])
            ->map(function (array $slot) use ($participantMap): array {
                return [
                    $participantMap->get((int) $slot['seeded']['participant_id']),
                    $participantMap->get((int) $slot['unseeded']['participant_id']),
                ];
            })
            ->all();
    }

    /**
     * Raspored za grupe sa po četiri prolaznika:
     * A1-D4 / B2-C3, C1-F4 / D2-E3, ...
     *
     * Svaka dva uzastopna para predstavljaju jednu granu kostura i dele isti
     * pikado sa mečem u narednoj rundi.
     *
     * @param  Collection<int, array<string, mixed>>  $participants
     * @return array<int, array{0: array<string, mixed>, 1: array<string, mixed>}>|null
     */
    private function groupPlacementPairs(Collection $participants): ?array
    {
        $groupNames = $participants
            ->pluck('group_name')
            ->unique()
            ->values();
        $groupsCount = $groupNames->count();

        if (
            $groupsCount < 4
            || $groupsCount % 2 !== 0
            || $participants->count() !== $groupsCount * 4
        ) {
            return null;
        }

        $participantsByGroupAndDrawRank = collect();

        foreach ($groupNames as $groupName) {
            $groupParticipants = $participants
                ->where('group_name', $groupName)
                ->sortBy([
                    ['group_rank', 'asc'],
                    ['seed', 'asc'],
                ])
                ->values();

            if ($groupParticipants->count() !== 4) {
                return null;
            }

            foreach ($groupParticipants as $index => $participant) {
                $participantsByGroupAndDrawRank->put(
                    $groupName.':'.($index + 1),
                    $participant,
                );
            }
        }

        $winnerGroupIndexes = collect(range(0, $groupsCount - 1))
            ->partition(fn (int $groupIndex) => $groupIndex % 2 === 0)
            ->flatten()
            ->values();
        $fourthPlaceOffset = (int) ($groupsCount / 2) - 1;
        $pairs = [];

        foreach ($winnerGroupIndexes as $winnerGroupIndex) {
            $direction = $winnerGroupIndex % 2 === 0 ? 1 : -1;
            $fourthPlaceGroupIndex = $this->positiveModulo(
                $winnerGroupIndex + ($direction * $fourthPlaceOffset),
                $groupsCount,
            );
            $runnerUpGroupIndex = $this->positiveModulo(
                $winnerGroupIndex + $direction,
                $groupsCount,
            );
            $thirdPlaceGroupIndex = $this->positiveModulo(
                $winnerGroupIndex + ($direction * 2),
                $groupsCount,
            );

            $winnerGroupName = $groupNames[$winnerGroupIndex];
            $fourthPlaceGroupName = $groupNames[$fourthPlaceGroupIndex];
            $runnerUpGroupName = $groupNames[$runnerUpGroupIndex];
            $thirdPlaceGroupName = $groupNames[$thirdPlaceGroupIndex];

            $pairs[] = [
                $participantsByGroupAndDrawRank[$winnerGroupName.':1'],
                $participantsByGroupAndDrawRank[$fourthPlaceGroupName.':4'],
            ];
            $pairs[] = [
                $participantsByGroupAndDrawRank[$runnerUpGroupName.':2'],
                $participantsByGroupAndDrawRank[$thirdPlaceGroupName.':3'],
            ];
        }

        return $pairs;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $participants
     */
    private function bracketSeparationRegions(Collection $participants, int $matchesCount): int
    {
        $largestGroupSize = (int) $participants
            ->countBy('group_name')
            ->max();
        $regionsCount = 1;

        while ($regionsCount < $largestGroupSize && $regionsCount < $matchesCount) {
            $regionsCount *= 2;
        }

        return min($regionsCount, $matchesCount);
    }

    /**
     * @param  array<int, array<string, mixed>>  $participants
     */
    private function regionContainsGroup(array $participants, string $groupName): bool
    {
        foreach ($participants as $participant) {
            if ($participant['group_name'] === $groupName) {
                return true;
            }
        }

        return false;
    }

    private function positiveModulo(int $value, int $modulo): int
    {
        return (($value % $modulo) + $modulo) % $modulo;
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

    private function isPowerOfTwo(int $value): bool
    {
        return $value > 0 && ($value & ($value - 1)) === 0;
    }

    /**
     * Builds Top 12/20/24 without visible bye matches. Lower seeds play a
     * preliminary round and the best seeds enter the next round directly.
     *
     * @param  Collection<int, array<string, mixed>>  $participants
     */
    private function generateWithPreliminaryRound(
        Tournament $tournament,
        Collection $participants,
        Collection $resources,
        int $scheduledOrder,
    ): int {
        $participants = $participants
            ->sortBy([
                ['group_rank', 'asc'],
                ['standing_points', 'desc'],
                ['wins', 'desc'],
                ['points_difference', 'desc'],
                ['points_for', 'desc'],
                ['display_name', 'asc'],
            ])
            ->values()
            ->map(function (array $participant, int $index): array {
                $participant['seed'] = $index + 1;

                return $participant;
            });

        $drawState = $this->drawService->state($tournament);

        if ($drawState['enabled'] && $drawState['complete']) {
            $drawOrder = collect($drawState['drawn_seeded_ids'])
                ->concat($drawState['drawn_unseeded_ids'])
                ->values()
                ->flip();

            $participants = $participants
                ->sortBy(fn (array $participant): int => (int) $drawOrder->get($participant['participant_id'], PHP_INT_MAX))
                ->values()
                ->map(function (array $participant, int $index): array {
                    $participant['seed'] = $index + 1;

                    return $participant;
                });
        }

        $bracketCapacity = 1;

        while ($bracketCapacity < $participants->count()) {
            $bracketCapacity *= 2;
        }

        $mainRoundParticipantsCount = (int) ($bracketCapacity / 2);
        $preliminaryMatchesCount = $participants->count() - $mainRoundParticipantsCount;
        $directParticipantsCount = $bracketCapacity - $participants->count();
        $directParticipants = $participants->take($directParticipantsCount)->values();
        $preliminaryParticipants = $participants
            ->slice($directParticipantsCount)
            ->values();
        $preliminaryPairs = collect(
            $this->firstRoundPairs($preliminaryParticipants, $tournament),
        );
        $orderedPreliminaryPairs = collect();

        foreach ($directParticipants->take($preliminaryMatchesCount) as $directParticipant) {
            $pairIndex = $preliminaryPairs->search(
                fn (array $pair): bool => $pair[0]['group_name'] !== $directParticipant['group_name']
                    && $pair[1]['group_name'] !== $directParticipant['group_name'],
            );

            if ($pairIndex === false) {
                $pairIndex = 0;
            }

            $orderedPreliminaryPairs->push(
                $preliminaryPairs->splice((int) $pairIndex, 1)->first(),
            );
        }

        $createdMatches = 0;
        $preliminaryWinsRequired = 2;
        $preliminaryLegsCount = ($preliminaryWinsRequired * 2) - 1;

        for ($leg = 1; $leg <= $preliminaryLegsCount; $leg++) {
            foreach ($orderedPreliminaryPairs as $index => $pair) {
                $position = $index + 1;

                TournamentMatch::create([
                    'tournament_id' => $tournament->id,
                    'stage' => MatchStage::KNOCKOUT,
                    'bracket_round' => 'preliminary',
                    'bracket_position' => $position,
                    'participant_a_id' => $pair[0]['participant_id'],
                    'participant_b_id' => $pair[1]['participant_id'],
                    'status' => MatchStatus::SCHEDULED,
                    'tournament_resource_id' => $this->resourceIdForPosition(
                        $resources,
                        $position,
                    ),
                    'scheduled_order' => $scheduledOrder++,
                    'round_robin_leg' => $leg,
                    'wins_required' => $preliminaryWinsRequired,
                    'meta' => [
                        'round_label' => 'Preliminarna runda',
                        'series_wins_required' => $preliminaryWinsRequired,
                        'participant_a_seed' => $pair[0]['seed'],
                        'participant_b_seed' => $pair[1]['seed'],
                        'participant_a_group' => $pair[0]['group_name'],
                        'participant_b_group' => $pair[1]['group_name'],
                        'participant_a_source' => $pair[0]['source'],
                        'participant_b_source' => $pair[1]['source'],
                    ],
                ]);

                $createdMatches++;
            }
        }

        $mainRoundDefinitions = $this->roundDefinitions($mainRoundParticipantsCount);
        $remainingDirectParticipants = $directParticipants
            ->slice($preliminaryMatchesCount)
            ->values();
        $remainingDirectPairs = $remainingDirectParticipants->isEmpty()
            ? collect()
            : collect($this->firstRoundPairs($remainingDirectParticipants, $tournament));
        $firstMainRoundSlots = collect();

        foreach ($directParticipants->take($preliminaryMatchesCount) as $index => $participant) {
            $firstMainRoundSlots->push([
                'participant_a' => $participant,
                'participant_b' => null,
                'participant_b_source_position' => $index + 1,
            ]);
        }

        foreach ($remainingDirectPairs as $pair) {
            $firstMainRoundSlots->push([
                'participant_a' => $pair[0],
                'participant_b' => $pair[1],
                'participant_b_source_position' => null,
            ]);
        }

        foreach ($mainRoundDefinitions as $roundIndex => $roundDefinition) {
            $legsCount = ($roundDefinition['wins_required'] * 2) - 1;

            for ($leg = 1; $leg <= $legsCount; $leg++) {
                for ($position = 1; $position <= $roundDefinition['matches_count']; $position++) {
                    $participantAId = null;
                    $participantBId = null;
                    $meta = [
                        'round_label' => $roundDefinition['label'],
                        'series_wins_required' => $roundDefinition['wins_required'],
                    ];

                    if ($roundIndex === 0) {
                        $slot = $firstMainRoundSlots[$position - 1];
                        $participantAId = $slot['participant_a']['participant_id'];
                        $participantBId = $slot['participant_b']['participant_id'] ?? null;
                        $meta['participant_a_seed'] = $slot['participant_a']['seed'];
                        $meta['participant_a_group'] = $slot['participant_a']['group_name'];

                        if ($slot['participant_b']) {
                            $meta['participant_b_seed'] = $slot['participant_b']['seed'];
                            $meta['participant_b_group'] = $slot['participant_b']['group_name'];
                        } else {
                            $meta['participant_b_source_round'] = 'preliminary';
                            $meta['participant_b_source_position'] = $slot['participant_b_source_position'];
                            $meta['participant_b_source_outcome'] = 'winner';
                        }
                    } elseif ($roundDefinition['source_round']) {
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

                    TournamentMatch::create([
                        'tournament_id' => $tournament->id,
                        'stage' => $roundDefinition['stage'],
                        'bracket_round' => $roundDefinition['key'],
                        'bracket_position' => $position,
                        'participant_a_id' => $participantAId,
                        'participant_b_id' => $participantBId,
                        'status' => MatchStatus::SCHEDULED,
                        'tournament_resource_id' => $this->resourceIdForPosition(
                            $resources,
                            $position,
                        ),
                        'scheduled_order' => $scheduledOrder++,
                        'round_robin_leg' => $leg,
                        'wins_required' => $roundDefinition['wins_required'],
                        'meta' => $meta,
                    ]);

                    $createdMatches++;
                }
            }
        }

        return $createdMatches;
    }

    private function roundKey(int $matchesCount): string
    {
        return match ($matchesCount) {
            16 => 'round_of_32',
            8 => 'round_of_16',
            4 => 'quarter_final',
            2 => 'semi_final',
            1 => 'final',
            default => 'knockout_round_'.$matchesCount,
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

    private function resourceIdForPosition(
        Collection $resources,
        int $position,
    ): ?int {
        if ($resources->isEmpty()) {
            return null;
        }

        $resourceIndex = $position - 1;

        /** @var TournamentResource $resource */
        $resource = $resources[$resourceIndex % $resources->count()];

        return $resource->id;
    }
}
