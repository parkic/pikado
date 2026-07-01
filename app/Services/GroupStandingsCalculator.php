<?php

namespace App\Services;

use App\Enums\MatchStage;
use App\Enums\MatchStatus;
use App\Models\Tournament;
use App\Models\TournamentGroup;
use App\Models\TournamentMatch;
use App\Models\TournamentParticipant;
use Illuminate\Support\Collection;

class GroupStandingsCalculator
{
    public function calculate(Tournament $tournament): array
    {
        $tournament->load([
            'groups' => fn ($query) => $query
                ->orderBy('sort_order')
                ->orderBy('name'),
            'groups.participants' => fn ($query) => $query
                ->orderBy('group_position')
                ->orderBy('id'),
            'groups.participants.player',
            'groups.participants.team',
            'groups.matches' => fn ($query) => $query
                ->where('stage', MatchStage::GROUP->value)
                ->orderBy('scheduled_order')
                ->orderBy('id'),
        ]);

        $groups = $tournament->groups
            ->map(fn (TournamentGroup $group) => $this->calculateGroup($group))
            ->values();

        return $this->applyQualificationStatuses($groups, $tournament)
            ->values()
            ->all();
    }

    private function calculateGroup(TournamentGroup $group): array
    {
        $rows = [];

        foreach ($group->participants as $participant) {
            $rows[$participant->id] = [
                'participant_id' => $participant->id,
                'group_position' => $participant->group_position,
                'display_name' => $this->participantDisplayName($participant),
                'played' => 0,
                'wins' => 0,
                'losses' => 0,
                'points_for' => 0,
                'points_against' => 0,
                'points_difference' => 0,
                'standing_points' => 0,
                'qualification_override_status' => $participant->qualification_override_status?->value,
                'repechage_outcome_status' => $participant->repechage_outcome_status?->value,
                'repechage_outcome_label' => $this->repechageOutcomeLabel($participant->repechage_outcome_status?->value),
            ];
        }

        $finishedMatches = $group->matches
            ->filter(fn (TournamentMatch $match) => $match->status === MatchStatus::FINISHED);

        foreach ($finishedMatches as $match) {
            if (
                ! $match->participant_a_id
                || ! $match->participant_b_id
                || $match->score_a === null
                || $match->score_b === null
            ) {
                continue;
            }

            if (! isset($rows[$match->participant_a_id], $rows[$match->participant_b_id])) {
                continue;
            }

            $participantAId = $match->participant_a_id;
            $participantBId = $match->participant_b_id;

            $scoreA = (int) $match->score_a;
            $scoreB = (int) $match->score_b;

            $rows[$participantAId]['played']++;
            $rows[$participantAId]['points_for'] += $scoreA;
            $rows[$participantAId]['points_against'] += $scoreB;

            $rows[$participantBId]['played']++;
            $rows[$participantBId]['points_for'] += $scoreB;
            $rows[$participantBId]['points_against'] += $scoreA;

            $winnerParticipantId = $match->winner_participant_id;
            $loserParticipantId = $match->loser_participant_id;

            if (! $winnerParticipantId || ! $loserParticipantId) {
                continue;
            }

            if (! isset($rows[$winnerParticipantId], $rows[$loserParticipantId])) {
                continue;
            }

            $rows[$winnerParticipantId]['wins']++;
            $rows[$winnerParticipantId]['standing_points'] += 1;

            $rows[$loserParticipantId]['losses']++;
        }

        foreach ($rows as $participantId => $row) {
            $rows[$participantId]['points_difference'] =
                $row['points_for'] - $row['points_against'];
        }

        $sortedRows = collect($rows)
            ->sortBy([
                ['standing_points', 'desc'],
                ['points_difference', 'desc'],
                ['points_for', 'desc'],
                ['display_name', 'asc'],
            ])
            ->values()
            ->map(function (array $row, int $index) {
                $row['position'] = $index + 1;
                $row['qualification_status'] = 'eliminated';
                $row['qualification_label'] = 'Ispao';

                return $row;
            })
            ->all();

        return [
            'id' => $group->id,
            'name' => $group->name,
            'matches_count' => $group->matches->count(),
            'finished_matches_count' => $finishedMatches->count(),
            'rows' => $sortedRows,
        ];
    }

    /**
     * @param Collection<int, array<string, mixed>> $groups
     * @return Collection<int, array<string, mixed>>
     */
    private function applyQualificationStatuses(Collection $groups, Tournament $tournament): Collection
    {
        $settings = $tournament->settings ?? [];

        $directQualifiersPerGroup = (int) data_get($settings, 'direct_qualifiers_per_group', 0);
        $repechageEnabled = (bool) data_get($settings, 'repechage_enabled', false);
        $repechageParticipantsCount = (int) data_get($settings, 'repechage_participants_count', 0);

        $groupsCount = $groups->count();

        $repechagePerGroup = 0;

        if (
            $repechageEnabled
            && $groupsCount > 0
            && $repechageParticipantsCount > 0
            && $repechageParticipantsCount % $groupsCount === 0
        ) {
            $repechagePerGroup = (int) ($repechageParticipantsCount / $groupsCount);
        }

        return $groups->map(function (array $group) use (
            $directQualifiersPerGroup,
            $repechageEnabled,
            $repechagePerGroup
        ) {
            $group['rows'] = collect($group['rows'])
                ->map(function (array $row) use (
                    $directQualifiersPerGroup,
                    $repechageEnabled,
                    $repechagePerGroup
                ) {
                    if ($directQualifiersPerGroup > 0 && $row['position'] <= $directQualifiersPerGroup) {
                        $row['qualification_status'] = 'direct';
                        $row['qualification_label'] = 'Direktan prolaz';

                        return $row;
                    }

                    if ($row['qualification_override_status']) {
                        $row['qualification_status'] = $row['qualification_override_status'];
                        $row['qualification_label'] = $this->qualificationLabel($row['qualification_status']);
                        $row['qualification_is_manual'] = true;

                        return $row;
                    }

                    $row['qualification_is_manual'] = false;

                    $firstRepechagePosition = $directQualifiersPerGroup + 1;
                    $lastRepechagePosition = $directQualifiersPerGroup + $repechagePerGroup;

                    if (
                        $repechageEnabled
                        && $repechagePerGroup > 0
                        && $row['position'] >= $firstRepechagePosition
                        && $row['position'] <= $lastRepechagePosition
                    ) {
                        $row['qualification_status'] = 'repechage';
                        $row['qualification_label'] = 'Repasaž';

                        return $row;
                    }

                    $row['qualification_status'] = 'eliminated';
                    $row['qualification_label'] = 'Ispao';

                    return $row;
                })
                ->values()
                ->all();

            return $group;
        });
    }

    private function participantDisplayName(TournamentParticipant $participant): string
    {
        if ($participant->player) {
            $name = trim($participant->player->first_name . ' ' . $participant->player->last_name);

            if ($participant->player->nickname) {
                return $name . ' (' . $participant->player->nickname . ')';
            }

            return $name;
        }

        if ($participant->team) {
            return $participant->team->name;
        }

        return 'Nepoznat učesnik';
    }

    private function qualificationLabel(string $status): string
    {
        return match ($status) {
            'direct' => 'Direktan prolaz',
            'repechage' => 'Repasaž',
            'eliminated' => 'Ispao',
            default => '-',
        };
    }

    private function repechageOutcomeLabel(?string $status): string
    {
        return match ($status) {
            'advanced' => 'Prošao iz repasaža',
            'eliminated' => 'Ispao posle repasaža',
            default => 'Neodlučeno',
        };
    }
}
