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
        $resourceIndex = 0;
        $scheduledOrder = 1;
        $createdMatches = 0;

        $roundsByGroup = $tournament->groups
            ->mapWithKeys(function (TournamentGroup $group) {
                return [
                    $group->id => $this->roundRobinRounds($group->participants),
                ];
            });

        $maxRounds = $roundsByGroup
            ->map(fn (array $rounds) => count($rounds))
            ->max() ?? 0;

        $legs = $tournament->group_rounds === GroupRounds::DOUBLE ? [1, 2] : [1];

        foreach ($legs as $leg) {
            for ($roundIndex = 0; $roundIndex < $maxRounds; $roundIndex++) {
                foreach ($tournament->groups as $group) {
                    $rounds = $roundsByGroup->get($group->id, []);

                    if (! isset($rounds[$roundIndex])) {
                        continue;
                    }

                    foreach ($rounds[$roundIndex] as [$participantA, $participantB]) {
                        if ($leg === 2) {
                            [$participantA, $participantB] = [$participantB, $participantA];
                        }

                        TournamentMatch::create([
                            'tournament_id' => $tournament->id,
                            'stage' => MatchStage::GROUP,
                            'tournament_group_id' => $group->id,
                            'participant_a_id' => $participantA->id,
                            'participant_b_id' => $participantB->id,
                            'status' => MatchStatus::SCHEDULED,
                            'tournament_resource_id' => $this->resourceIdForMatch($resources, $resourceIndex),
                            'scheduled_order' => $scheduledOrder,
                            'round_robin_leg' => $leg,
                            'wins_required' => 1,
                        ]);

                        $resourceIndex++;
                        $scheduledOrder++;
                        $createdMatches++;
                    }
                }
            }
        }

        return $createdMatches;
    }

    /**
     * @param Collection<int, TournamentParticipant> $participants
     * @return array<int, array<int, array{0: TournamentParticipant, 1: TournamentParticipant}>>
     */
    private function roundRobinRounds(Collection $participants): array
    {
        $players = $participants->values()->all();

        if (count($players) < 2) {
            return [];
        }

        if (count($players) % 2 !== 0) {
            $players[] = null;
        }

        $rounds = [];
        $playerCount = count($players);
        $roundCount = $playerCount - 1;

        for ($round = 0; $round < $roundCount; $round++) {
            $pairs = [];

            for ($i = 0; $i < $playerCount / 2; $i++) {
                $participantA = $players[$i];
                $participantB = $players[$playerCount - 1 - $i];

                if ($participantA !== null && $participantB !== null) {
                    $pairs[] = [$participantA, $participantB];
                }
            }

            $rounds[] = $pairs;

            $fixed = array_shift($players);
            $last = array_pop($players);

            array_unshift($players, $fixed);
            array_splice($players, 1, 0, [$last]);
        }

        return $rounds;
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
