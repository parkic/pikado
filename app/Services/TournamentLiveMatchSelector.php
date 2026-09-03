<?php

namespace App\Services;

use App\Enums\MatchStatus;
use App\Models\TournamentMatch;
use Illuminate\Support\Collection;

class TournamentLiveMatchSelector
{
    /**
     * @param  Collection<int, TournamentMatch>  $matches
     * @return array{current: Collection<int, TournamentMatch>, next: Collection<int, TournamentMatch>}
     */
    public function select(Collection $matches, int $nextLimit = 8): array
    {
        $scheduledMatches = $matches
            ->filter(fn (TournamentMatch $match) => $match->status === MatchStatus::SCHEDULED)
            ->sortBy(fn (TournamentMatch $match) => [
                $match->scheduled_order ?? PHP_INT_MAX,
                $match->id,
            ])
            ->values();

        $current = $scheduledMatches
            ->filter(fn (TournamentMatch $match) => $match->tournament_resource_id !== null)
            ->unique('tournament_resource_id')
            ->sort(fn (TournamentMatch $left, TournamentMatch $right): int => [
                $left->resource?->sort_order ?? PHP_INT_MAX,
                mb_strtolower($left->resource?->name ?? ''),
            ] <=> [
                $right->resource?->sort_order ?? PHP_INT_MAX,
                mb_strtolower($right->resource?->name ?? ''),
            ])
            ->values();

        $currentSeriesKeys = $current
            ->map(fn (TournamentMatch $match) => $this->seriesKey($match))
            ->all();

        $next = $scheduledMatches
            ->reject(fn (TournamentMatch $match) => in_array(
                $this->seriesKey($match),
                $currentSeriesKeys,
                true,
            ))
            ->unique(fn (TournamentMatch $match) => $this->seriesKey($match))
            ->take($nextLimit)
            ->values();

        return compact('current', 'next');
    }

    public function seriesKey(TournamentMatch $match): string
    {
        $participantIds = collect([
            $match->participant_a_id,
            $match->participant_b_id,
        ])
            ->filter()
            ->sort()
            ->implode('-');

        return implode('|', [
            $match->stage->value,
            $match->tournament_group_id ?? '',
            $match->bracket_round ?? '',
            $match->bracket_position ?? '',
            $participantIds,
        ]);
    }
}
