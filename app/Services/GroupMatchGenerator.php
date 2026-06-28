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

        $resources = $tournament->resources;
        $scheduledOrder = 1;
        $createdMatches = 0;

        $matchesByGroup = $tournament->groups
            ->values()
            ->mapWithKeys(function (TournamentGroup $group, int $groupIndex) use ($tournament, $resources) {
                return [
                    $group->id => [
                        'group' => $group,
                        'resource_id' => $this->resourceIdForGroup($resources, $groupIndex),
                        'matches' => $this->groupMatchesForTournament($tournament, $group->participants),
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

                [$participantA, $participantB, $leg] = $groupData['matches'][$matchIndex];

                TournamentMatch::create([
                    'tournament_id' => $tournament->id,
                    'stage' => MatchStage::GROUP,
                    'tournament_group_id' => $group->id,
                    'participant_a_id' => $participantA->id,
                    'participant_b_id' => $participantB->id,
                    'status' => MatchStatus::SCHEDULED,
                    'tournament_resource_id' => $groupData['resource_id'],
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
     * @param Collection<int, TournamentParticipant> $participants
     * @return array<int, array{0: TournamentParticipant, 1: TournamentParticipant, 2: int}>
     */
    private function groupMatchesForTournament(Tournament $tournament, Collection $participants): array
    {
        $firstLegMatches = $this->roundRobinMatches($participants)
            ->map(fn (array $match) => [$match[0], $match[1], 1]);

        if ($tournament->group_rounds !== GroupRounds::DOUBLE) {
            return $firstLegMatches->values()->all();
        }

        $secondLegMatches = $firstLegMatches
            ->map(fn (array $match) => [$match[1], $match[0], 2]);

        return $firstLegMatches
            ->concat($secondLegMatches)
            ->values()
            ->all();
    }

    /**
     * @param Collection<int, TournamentParticipant> $participants
     * @return Collection<int, array{0: TournamentParticipant, 1: TournamentParticipant}>
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
