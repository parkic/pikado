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
        $scheduledOrder = 1;
        $visibleMatchesCount = 0;

        $matchesByGroup = $tournament->groups
            ->values()
            ->mapWithKeys(function (
                TournamentGroup $group,
                int $groupIndex
            ) use (
                $tournament,
                $resources,
                $groupSize
            ) {
                $slots = $this->slotsForGroup($group, $groupSize);

                return [
                    $group->id => [
                        'group' => $group,
                        'resource_id' => $this->resourceIdForGroup($resources, $groupIndex),
                        'matches' => $this->groupMatchesForTournament($tournament, $slots),
                    ],
                ];
            });

        $maxMatchesPerGroup = $matchesByGroup
            ->map(fn (array $groupData) => count($groupData['matches']))
            ->max() ?? 0;

        for ($matchIndex = 0; $matchIndex < $maxMatchesPerGroup; $matchIndex++) {
            foreach ($tournament->groups as $group) {
                $groupData = $matchesByGroup->get($group->id);

                if (! $groupData || ! isset($groupData['matches'][$matchIndex])) {
                    continue;
                }

                [$slotA, $slotB, $leg] = $groupData['matches'][$matchIndex];

                /** @var TournamentParticipant|null $participantA */
                $participantA = $slotA['participant'];

                /** @var TournamentParticipant|null $participantB */
                $participantB = $slotB['participant'];

                $isHidden = $participantA === null || $participantB === null;

                TournamentMatch::create([
                    'tournament_id' => $tournament->id,
                    'stage' => MatchStage::GROUP,
                    'tournament_group_id' => $group->id,
                    'participant_a_id' => $participantA?->id,
                    'participant_a_position' => $slotA['position'],
                    'participant_b_id' => $participantB?->id,
                    'participant_b_position' => $slotB['position'],
                    'is_hidden' => $isHidden,
                    'status' => MatchStatus::SCHEDULED,
                    'tournament_resource_id' => $groupData['resource_id'],
                    'scheduled_order' => $scheduledOrder,
                    'round_robin_leg' => $leg,
                    'wins_required' => 1,
                ]);

                if (! $isHidden) {
                    $visibleMatchesCount++;
                }

                $scheduledOrder++;
            }
        }

        return $visibleMatchesCount;
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
            $orderedGroupIds = $tournament->groups()
                ->orderBy('sort_order')
                ->orderBy('name')
                ->pluck('id')
                ->values();

            $groupIndex = $orderedGroupIds->search(
                $group->id,
            );

            $resources = $tournament->resources()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get();

            $resourceId = $this->resourceIdForGroup(
                $resources,
                $groupIndex === false
                    ? 0
                    : (int) $groupIndex,
            );
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
                $position = $group->name . $slotNumber;

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
            ->map(fn(array $match) => [$match[0], $match[1], 1]);

        if ($tournament->group_rounds !== GroupRounds::DOUBLE) {
            return $firstLegMatches->values()->all();
        }

        $secondLegMatches = $firstLegMatches
            ->map(fn(array $match) => [$match[1], $match[0], 2]);

        return $firstLegMatches
            ->concat($secondLegMatches)
            ->values()
            ->all();
    }

    /**
     * @param Collection<int, mixed> $participants
     * @return Collection<int, array{0: mixed, 1: mixed}>
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
                    $matches->push([$participantA, $participantB]);
                }
            }

            $fixed = array_shift($players);
            $last = array_pop($players);

            array_unshift($players, $fixed);
            array_splice($players, 1, 0, [$last]);
        }

        return $matches;
    }

    private function resourceIdForGroup(Collection $resources, int $groupIndex): ?int
    {
        if ($resources->isEmpty()) {
            return null;
        }

        /** @var TournamentResource $resource */
        $resource = $resources[$groupIndex % $resources->count()];

        return $resource->id;
    }
}
