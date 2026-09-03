<?php

namespace App\Services;

use App\Enums\GroupRounds;
use App\Enums\MatchStage;
use App\Enums\MatchStatus;
use App\Enums\ParticipantStatus;
use App\Models\Tournament;
use App\Models\TournamentGroup;
use App\Models\TournamentMatch;
use App\Models\TournamentParticipant;
use App\Models\TournamentResource;
use Illuminate\Support\Collection;

class GroupMatchGenerator
{
    public function generate(Tournament $tournament): int
    {
        $tournament->load([
            'groups' => fn ($query) => $query
                ->orderBy('sort_order')
                ->orderBy('name'),
            'groups.participants' => fn ($query) => $query
                ->where('status', ParticipantStatus::ACTIVE->value)
                ->orderBy('group_position')
                ->orderBy('id'),
            'resources' => fn ($query) => $query
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name'),
        ]);

        $groupSize = (int) data_get($tournament->settings ?? [], 'group_size', 0);

        if ($groupSize < 2) {
            return 0;
        }

        $resources = $tournament->resources;
        $resourceIdsByGroup = $this->resourceIdsByGroup(
            $tournament,
            $tournament->groups,
            $resources,
        );
        $matchesByGroup = $tournament->groups
            ->values()
            ->mapWithKeys(function (TournamentGroup $group) use (
                $tournament,
                $resourceIdsByGroup,
                $groupSize
            ) {
                $slots = $this->slotsForGroup($group, $groupSize);

                return [
                    $group->id => [
                        'group' => $group,
                        'resource_id' => $resourceIdsByGroup->get($group->id),
                        'matches' => $this->groupMatchesForTournament($tournament, $slots),
                    ],
                ];
            });

        $maxMatchesPerGroup = $matchesByGroup
            ->map(fn (array $groupData) => count($groupData['matches']))
            ->max() ?? 0;

        $matchEntries = collect();
        $originalOrder = 1;

        for ($matchIndex = 0; $matchIndex < $maxMatchesPerGroup; $matchIndex++) {
            foreach ($tournament->groups as $group) {
                $groupData = $matchesByGroup->get($group->id);

                if (! $groupData || ! isset($groupData['matches'][$matchIndex])) {
                    continue;
                }

                [$slotA, $slotB, $leg, $round] = $groupData['matches'][$matchIndex];

                /** @var TournamentParticipant|null $participantA */
                $participantA = $slotA['participant'];

                /** @var TournamentParticipant|null $participantB */
                $participantB = $slotB['participant'];

                $matchEntries->push([
                    'group' => $group,
                    'resource_id' => $groupData['resource_id'],
                    'slot_a' => $slotA,
                    'slot_b' => $slotB,
                    'leg' => $leg,
                    'round' => $round,
                    'is_hidden' => $participantA === null || $participantB === null,
                    'original_order' => $originalOrder,
                ]);

                $originalOrder++;
            }
        }

        $scheduledOrder = 1;
        $visibleMatchesCount = 0;

        foreach ($this->fairMatchOrder($matchEntries, $resources) as $entry) {
            /** @var TournamentGroup $group */
            $group = $entry['group'];

            /** @var TournamentParticipant|null $participantA */
            $participantA = $entry['slot_a']['participant'];

            /** @var TournamentParticipant|null $participantB */
            $participantB = $entry['slot_b']['participant'];

            TournamentMatch::create([
                'tournament_id' => $tournament->id,
                'stage' => MatchStage::GROUP,
                'tournament_group_id' => $group->id,
                'participant_a_id' => $participantA?->id,
                'participant_a_position' => $entry['slot_a']['position'],
                'participant_b_id' => $participantB?->id,
                'participant_b_position' => $entry['slot_b']['position'],
                'is_hidden' => $entry['is_hidden'],
                'status' => MatchStatus::SCHEDULED,
                'tournament_resource_id' => $entry['resource_id'],
                'scheduled_order' => $scheduledOrder,
                'round_robin_leg' => $entry['leg'],
                'wins_required' => 1,
            ]);

            if (! $entry['is_hidden']) {
                $visibleMatchesCount++;
            }

            $scheduledOrder++;
        }

        return $visibleMatchesCount;
    }

    /**
     * Pravi fer redosled bez menjanja parova ili table kojoj grupa pripada.
     * Svaka tabla dobija svoj red, a zatim se redovi tabli prepliću kako bi
     * i globalni raspored ostao pregledan. Skriveni mečevi za prazne slotove
     * ostaju sačuvani, ali ne utiču na redosled stvarnih učesnika.
     *
     * @param  Collection<int, array{
     *     group: TournamentGroup,
     *     resource_id: int|null,
     *     slot_a: array{position: string, participant: TournamentParticipant|null},
     *     slot_b: array{position: string, participant: TournamentParticipant|null},
     *     leg: int,
     *     round: int,
     *     is_hidden: bool,
     *     original_order: int
     * }>  $entries
     * @param  Collection<int, TournamentResource>  $resources
     * @return Collection<int, array<string, mixed>>
     */
    private function fairMatchOrder(Collection $entries, Collection $resources): Collection
    {
        if ($entries->isEmpty()) {
            return collect();
        }

        $resourceKeys = $resources
            ->map(fn (TournamentResource $resource): string => 'resource-'.$resource->id)
            ->filter(fn (string $key): bool => $entries->contains(
                fn (array $entry): bool => $this->resourceKey($entry['resource_id']) === $key,
            ))
            ->values();

        if ($entries->contains(fn (array $entry): bool => $entry['resource_id'] === null)) {
            $resourceKeys->push('unassigned');
        }

        $queues = $resourceKeys->mapWithKeys(function (string $resourceKey) use ($entries): array {
            $resourceEntries = $entries
                ->filter(fn (array $entry): bool => $this->resourceKey($entry['resource_id']) === $resourceKey)
                ->values();

            return [$resourceKey => $this->fairResourceOrder($resourceEntries)->all()];
        });

        $ordered = collect();
        $maximumQueueLength = $queues
            ->map(fn (array $queue): int => count($queue))
            ->max() ?? 0;

        for ($index = 0; $index < $maximumQueueLength; $index++) {
            foreach ($resourceKeys as $resourceKey) {
                $queue = $queues->get($resourceKey, []);

                if (isset($queue[$index])) {
                    $ordered->push($queue[$index]);
                }
            }
        }

        return $ordered;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $entries
     * @return Collection<int, array<string, mixed>>
     */
    private function fairResourceOrder(Collection $entries): Collection
    {
        $ordered = collect();
        $playedCount = [];
        $lastPlayedAt = [];
        $previousParticipantIds = [];
        $groupScheduledCount = [];
        $sequenceIndex = 0;
        $legs = $entries->pluck('leg')->unique()->sort()->values();

        foreach ($legs as $leg) {
            $legEntries = $entries
                ->where('leg', $leg)
                ->sortBy('original_order')
                ->values();
            $remaining = $legEntries
                ->where('is_hidden', false)
                ->values();
            $hidden = $legEntries
                ->where('is_hidden', true)
                ->values();
            $groupsWithHiddenMatches = $hidden
                ->map(fn (array $entry): int => $entry['group']->id)
                ->unique();
            $groupTotals = $remaining
                ->countBy(fn (array $entry): int => $entry['group']->id)
                ->all();

            while ($remaining->isNotEmpty()) {
                $bestIndex = 0;
                $bestScore = null;
                $minimumRoundByGroup = $remaining
                    ->groupBy(fn (array $entry): int => $entry['group']->id)
                    ->map(fn (Collection $groupEntries): int => (int) $groupEntries->min('round'));

                foreach ($remaining as $index => $entry) {
                    if (
                        ! $groupsWithHiddenMatches->contains($entry['group']->id)
                        && $entry['round'] !== $minimumRoundByGroup->get($entry['group']->id)
                    ) {
                        continue;
                    }

                    $score = $this->fairnessScore(
                        $entry,
                        $playedCount,
                        $lastPlayedAt,
                        $previousParticipantIds,
                        $groupScheduledCount,
                        $groupTotals,
                    );

                    if ($bestScore === null || $this->compareScores($score, $bestScore) < 0) {
                        $bestIndex = $index;
                        $bestScore = $score;
                    }
                }

                $selected = $remaining->get($bestIndex);
                $remaining->forget($bestIndex);
                $remaining = $remaining->values();
                $participantIds = $this->participantIds($selected);

                $ordered->push($selected);
                $groupId = $selected['group']->id;
                $groupScheduledCount[$groupId] = ($groupScheduledCount[$groupId] ?? 0) + 1;

                foreach ($participantIds as $participantId) {
                    $playedCount[$participantId] = ($playedCount[$participantId] ?? 0) + 1;
                    $lastPlayedAt[$participantId] = $sequenceIndex;
                }

                $previousParticipantIds = $participantIds;
                $sequenceIndex++;
            }

            foreach ($hidden as $entry) {
                $ordered->push($entry);
            }
        }

        return $ordered;
    }

    /**
     * @param  array<string, mixed>  $entry
     * @param  array<int, int>  $playedCount
     * @param  array<int, int>  $lastPlayedAt
     * @param  array<int, int>  $previousParticipantIds
     * @param  array<int, int>  $groupScheduledCount
     * @param  array<int, int>  $groupTotals
     * @return array<int, int|float>
     */
    private function fairnessScore(
        array $entry,
        array $playedCount,
        array $lastPlayedAt,
        array $previousParticipantIds,
        array $groupScheduledCount,
        array $groupTotals,
    ): array {
        $participantIds = $this->participantIds($entry);
        $counts = array_map(
            fn (int $participantId): int => $playedCount[$participantId] ?? 0,
            $participantIds,
        );
        $lastPlayed = array_map(
            fn (int $participantId): int => $lastPlayedAt[$participantId] ?? -1_000_000,
            $participantIds,
        );
        $groupId = $entry['group']->id;
        $groupProgress = ($groupScheduledCount[$groupId] ?? 0)
            / max(1, $groupTotals[$groupId] ?? 1);

        return [
            count(array_intersect($participantIds, $previousParticipantIds)) > 0 ? 1 : 0,
            $groupProgress,
            min($lastPlayed),
            max($lastPlayed),
            max($counts),
            array_sum($counts),
            $entry['original_order'],
        ];
    }

    /** @param array<string, mixed> $entry @return array<int, int> */
    private function participantIds(array $entry): array
    {
        return collect([
            $entry['slot_a']['participant']?->id,
            $entry['slot_b']['participant']?->id,
        ])->filter()->map(fn (int $id): int => $id)->values()->all();
    }

    /**
     * @param  array<int, int|float>  $left
     * @param  array<int, int|float>  $right
     */
    private function compareScores(array $left, array $right): int
    {
        foreach ($left as $index => $value) {
            $comparison = $value <=> $right[$index];

            if ($comparison !== 0) {
                return $comparison;
            }
        }

        return 0;
    }

    private function resourceKey(?int $resourceId): string
    {
        return $resourceId === null ? 'unassigned' : 'resource-'.$resourceId;
    }

    public function generateForParticipant(
        Tournament $tournament,
        TournamentParticipant $participant
    ): int {
        $groupPosition = trim((string) $participant->group_position);

        if ($groupPosition === '') {
            return 0;
        }

        $slotMatches = TournamentMatch::withoutGlobalScope('visible_matches')
            ->where('tournament_id', $tournament->id)
            ->where('stage', MatchStage::GROUP->value)
            ->where('tournament_group_id', $participant->tournament_group_id)
            ->where(function ($query) use ($groupPosition): void {
                $query
                    ->where('participant_a_position', $groupPosition)
                    ->orWhere('participant_b_position', $groupPosition);
            })
            ->orderBy('scheduled_order')
            ->orderBy('id')
            ->get();

        /*
        * Stari turnir nema unapred napravljene shadow mečeve.
        * U tom slučaju zadržavamo prethodno ponašanje.
        */
        if ($slotMatches->isEmpty()) {
            return $this->generateLegacyForParticipant(
                $tournament,
                $participant
            );
        }

        $activatedMatchesCount = 0;

        foreach ($slotMatches as $match) {
            $participantAId = $match->participant_a_position === $groupPosition
                ? $participant->id
                : $match->participant_a_id;

            $participantBId = $match->participant_b_position === $groupPosition
                ? $participant->id
                : $match->participant_b_id;

            $isHidden = ! $participantAId || ! $participantBId;

            $match->update([
                'participant_a_id' => $participantAId,
                'participant_b_id' => $participantBId,
                'is_hidden' => $isHidden,
            ]);

            if (! $isHidden) {
                $activatedMatchesCount++;
            }
        }

        return $activatedMatchesCount;
    }

    private function generateLegacyForParticipant(
        Tournament $tournament,
        TournamentParticipant $participant
    ): int {
        $group = TournamentGroup::query()
            ->where('tournament_id', $tournament->id)
            ->findOrFail($participant->tournament_group_id);

        $opponents = $group->participants()
            ->where(
                'status',
                ParticipantStatus::ACTIVE->value,
            )
            ->where('id', '!=', $participant->id)
            ->orderBy('group_position')
            ->orderBy('id')
            ->get();

        if ($opponents->isEmpty()) {
            return 0;
        }

        /*
        * Pokušavamo da koristimo isti resource koji već
        * koristi grupa u postojećem rasporedu.
        */
        $resourceId = TournamentMatch::query()
            ->where('tournament_id', $tournament->id)
            ->where('stage', MatchStage::GROUP->value)
            ->where('tournament_group_id', $group->id)
            ->whereNotNull('tournament_resource_id')
            ->value('tournament_resource_id');

        if ($resourceId !== null) {
            $resourceId = (int) $resourceId;
        }

        /*
        * Fallback ako grupa iz nekog razloga još nema
        * nijedan meč sa dodeljenim resursom.
        */
        if ($resourceId === null) {
            $groups = $tournament->groups()
                ->with(['participants' => fn ($query) => $query
                    ->where('status', ParticipantStatus::ACTIVE->value)])
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get();

            $resources = $tournament->resources()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get();

            $resourceId = $this->resourceIdsByGroup(
                $tournament,
                $groups,
                $resources,
            )->get($group->id);
        }

        $scheduledOrder = (
            (int) (
                TournamentMatch::withoutGlobalScope('visible_matches')
                    ->where(
                        'tournament_id',
                        $tournament->id,
                    )
                    ->where(
                        'stage',
                        MatchStage::GROUP->value,
                    )
                    ->max('scheduled_order')
                ?? 0
            )
        ) + 1;

        $legs = $tournament->group_rounds
            === GroupRounds::DOUBLE
            ? [1, 2]
            : [1];

        $createdMatches = 0;

        foreach ($opponents as $opponent) {
            foreach ($legs as $leg) {
                if ($leg === 1) {
                    $participantA = $participant;
                    $participantB = $opponent;
                } else {
                    $participantA = $opponent;
                    $participantB = $participant;
                }

                /*
                * Zaštita od dupliranja ako se metoda iz
                * nekog razloga pozove više puta.
                */
                $matchAlreadyExists =
                    TournamentMatch::query()
                        ->where(
                            'tournament_id',
                            $tournament->id,
                        )
                        ->where(
                            'stage',
                            MatchStage::GROUP->value,
                        )
                        ->where(
                            'tournament_group_id',
                            $group->id,
                        )
                        ->where(
                            'round_robin_leg',
                            $leg,
                        )
                        ->where(function ($query) use (
                            $participantA,
                            $participantB,
                        ): void {
                            $query
                                ->where(function (
                                    $pairQuery
                                ) use (
                                    $participantA,
                                    $participantB,
                                ): void {
                                    $pairQuery
                                        ->where(
                                            'participant_a_id',
                                            $participantA->id,
                                        )
                                        ->where(
                                            'participant_b_id',
                                            $participantB->id,
                                        );
                                })
                                ->orWhere(function (
                                    $pairQuery
                                ) use (
                                    $participantA,
                                    $participantB,
                                ): void {
                                    $pairQuery
                                        ->where(
                                            'participant_a_id',
                                            $participantB->id,
                                        )
                                        ->where(
                                            'participant_b_id',
                                            $participantA->id,
                                        );
                                });
                        })
                        ->exists();

                if ($matchAlreadyExists) {
                    continue;
                }

                TournamentMatch::create([
                    'tournament_id' => $tournament->id,
                    'stage' => MatchStage::GROUP,
                    'tournament_group_id' => $group->id,
                    'participant_a_id' => $participantA->id,
                    'participant_a_position' => $participantA->group_position,
                    'participant_b_id' => $participantB->id,
                    'participant_b_position' => $participantB->group_position,
                    'is_hidden' => false,
                    'status' => MatchStatus::SCHEDULED,
                    'tournament_resource_id' => $resourceId,
                    'scheduled_order' => $scheduledOrder,
                    'round_robin_leg' => $leg,
                    'wins_required' => 1,
                ]);

                $scheduledOrder++;
                $createdMatches++;
            }
        }

        return $createdMatches;
    }

    /**
     * @return Collection<int, array{
     *     position: string,
     *     participant: TournamentParticipant|null
     * }>
     */
    private function slotsForGroup(
        TournamentGroup $group,
        int $groupSize
    ): Collection {
        $participantsByPosition = $group->participants->keyBy('group_position');

        return collect(range(1, $groupSize))
            ->map(function (int $slotNumber) use (
                $group,
                $participantsByPosition
            ): array {
                $position = $group->name.$slotNumber;

                return [
                    'position' => $position,
                    'participant' => $participantsByPosition->get($position),
                ];
            });
    }

    /**
     * @param Collection<int, array{
     *     position: string,
     *     participant: TournamentParticipant|null
     * }> $participants
     */
    private function groupMatchesForTournament(Tournament $tournament, Collection $participants): array
    {
        $firstLegMatches = $this->roundRobinMatches($participants)
            ->map(fn (array $match) => [$match[0], $match[1], 1, $match[2]]);

        if ($tournament->group_rounds !== GroupRounds::DOUBLE) {
            return $firstLegMatches->values()->all();
        }

        $secondLegMatches = $firstLegMatches
            ->map(fn (array $match) => [$match[1], $match[0], 2, $match[3]]);

        return $firstLegMatches
            ->concat($secondLegMatches)
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, mixed>  $participants
     * @return Collection<int, array{0: mixed, 1: mixed, 2: int}>
     */
    private function roundRobinMatches(Collection $participants): Collection
    {
        $players = $participants->values()->all();

        if (count($players) < 2) {
            return collect();
        }

        if (count($players) % 2 !== 0) {
            $players[] = null;
        }

        $matches = collect();

        $playerCount = count($players);
        $roundCount = $playerCount - 1;

        for ($round = 0; $round < $roundCount; $round++) {
            for ($i = 0; $i < $playerCount / 2; $i++) {
                $participantA = $players[$i];
                $participantB = $players[$playerCount - 1 - $i];

                if ($participantA !== null && $participantB !== null) {
                    $matches->push([$participantA, $participantB, $round + 1]);
                }
            }

            $fixed = array_shift($players);
            $last = array_pop($players);

            array_unshift($players, $fixed);
            array_splice($players, 1, 0, [$last]);
        }

        return $matches;
    }

    /**
     * Dodeljuje cele, susedne grupe resursima tako da procenjeni broj
     * vidljivih mečeva bude što ravnomerniji. Time se izbegava da kod
     * neparnog broja grupa A/C/E završe na istoj tabli.
     *
     * @param  Collection<int, TournamentGroup>  $groups
     * @param  Collection<int, TournamentResource>  $resources
     * @return Collection<int, int|null>
     */
    private function resourceIdsByGroup(
        Tournament $tournament,
        Collection $groups,
        Collection $resources
    ): Collection {
        $groups = $groups->values();
        $resources = $resources->values();

        if ($groups->isEmpty()) {
            return collect();
        }

        if ($resources->isEmpty()) {
            return $groups->mapWithKeys(
                fn (TournamentGroup $group): array => [$group->id => null],
            );
        }

        $legs = $tournament->group_rounds === GroupRounds::DOUBLE ? 2 : 1;
        $weights = $groups->map(function (TournamentGroup $group) use ($legs): int {
            $participantCount = $group->participants->count();
            $visibleMatches = intdiv(
                $participantCount * max(0, $participantCount - 1),
                2,
            ) * $legs;

            return max(1, $visibleMatches);
        })->values();

        $assignments = collect();
        $groupOffset = 0;
        $remainingWeight = $weights->sum();
        $resourceCount = min($resources->count(), $groups->count());

        for ($resourceIndex = 0; $resourceIndex < $resourceCount; $resourceIndex++) {
            /** @var TournamentResource $resource */
            $resource = $resources[$resourceIndex];
            $remainingResources = $resourceCount - $resourceIndex;
            $remainingGroups = $groups->count() - $groupOffset;
            $groupsToKeep = $remainingResources - 1;
            $maximumGroupsForResource = $remainingGroups - $groupsToKeep;

            if ($remainingResources === 1) {
                $groupsForResource = $maximumGroupsForResource;
            } else {
                $targetWeight = $remainingWeight / $remainingResources;
                $groupsForResource = 0;
                $assignedWeight = 0;

                while ($groupsForResource < $maximumGroupsForResource) {
                    $nextWeight = $weights[$groupOffset + $groupsForResource];

                    if (
                        $groupsForResource > 0
                        && abs($assignedWeight - $targetWeight)
                            <= abs(($assignedWeight + $nextWeight) - $targetWeight)
                    ) {
                        break;
                    }

                    $assignedWeight += $nextWeight;
                    $groupsForResource++;
                }

                $groupsForResource = max(1, $groupsForResource);
            }

            $assignedWeight = 0;

            for ($index = 0; $index < $groupsForResource; $index++) {
                /** @var TournamentGroup $group */
                $group = $groups[$groupOffset + $index];
                $assignments->put($group->id, $resource->id);
                $assignedWeight += $weights[$groupOffset + $index];
            }

            $groupOffset += $groupsForResource;
            $remainingWeight -= $assignedWeight;
        }

        return $assignments;
    }
}
