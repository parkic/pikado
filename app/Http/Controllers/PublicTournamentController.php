<?php

namespace App\Http\Controllers;

use App\Enums\MatchStatus;
use App\Enums\MatchStage;
use App\Models\Tournament;
use App\Models\TournamentMatch;
use App\Models\TournamentParticipant;
use Inertia\Inertia;
use Inertia\Response;
use App\Services\GroupStandingsCalculator;

class PublicTournamentController extends Controller
{
    public function live(string $publicCode): Response
    {
        $tournament = Tournament::query()
            ->with([
                'venue',
            ])
            ->where('public_code', $publicCode)
            ->where('public_enabled', true)
            ->firstOrFail();

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

        $activeMatches = (clone $matchesQuery)
            ->where('status', MatchStatus::IN_PROGRESS->value)
            ->orderBy('scheduled_order')
            ->orderBy('id')
            ->limit(6)
            ->get()
            ->map(fn (TournamentMatch $match) => $this->matchSummary($match))
            ->values();

        $nextMatches = (clone $matchesQuery)
            ->where('status', MatchStatus::SCHEDULED->value)
            ->orderBy('scheduled_order')
            ->orderBy('id')
            ->limit(8)
            ->get()
            ->map(fn (TournamentMatch $match) => $this->matchSummary($match))
            ->values();

        $recentMatches = (clone $matchesQuery)
            ->where('status', MatchStatus::FINISHED->value)
            ->orderByDesc('finished_at')
            ->orderByDesc('updated_at')
            ->limit(8)
            ->get()
            ->map(fn (TournamentMatch $match) => $this->matchSummary($match))
            ->values();

        return Inertia::render('Public/Tournaments/Live', [
            'venue' => [
                'name' => $tournament->venue->name,
                'slug' => $tournament->venue->slug,
                'logo_path' => $tournament->venue->logo_path,
            ],
            'tournament' => [
                'id' => $tournament->id,
                'name' => $tournament->name,
                'public_code' => $tournament->public_code,
                'game_type' => $tournament->game_type->value,
                'match_mode' => $tournament->match_mode->value,
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
            'active_matches' => $activeMatches,
            'next_matches' => $nextMatches,
            'recent_matches' => $recentMatches,
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
            ->map(function (array $group) use ($groupMatches) {
                return [
                    'id' => $group['id'],
                    'name' => $group['name'],
                    'matches_count' => $group['matches_count'],
                    'finished_matches_count' => $group['finished_matches_count'],
                    'rows' => $group['rows'],
                    'matches' => collect($groupMatches->get($group['id'], collect()))
                        ->map(fn (TournamentMatch $match) => $this->matchSummary($match))
                        ->values(),
                ];
            })
            ->values();

        return Inertia::render('Public/Tournaments/Groups', [
            'venue' => [
                'name' => $tournament->venue->name,
                'slug' => $tournament->venue->slug,
                'logo_path' => $tournament->venue->logo_path,
            ],
            'tournament' => [
                'id' => $tournament->id,
                'name' => $tournament->name,
                'public_code' => $tournament->public_code,
                'game_type' => $tournament->game_type->value,
                'match_mode' => $tournament->match_mode->value,
                'status' => $tournament->status->value,
                'status_label' => $this->tournamentStatusLabel($tournament->status->value),
            ],
            'groups' => $groups,
        ]);
    }

    private function matchSummary(TournamentMatch $match): array
    {
        return [
            'id' => $match->id,
            'stage' => $match->stage->value,
            'stage_label' => $this->matchStageLabel($match->stage->value),
            'group_name' => $match->group?->name,
            'bracket_round' => $match->bracket_round,
            'bracket_round_label' => $this->bracketRoundLabel($match->bracket_round),
            'bracket_position' => $match->bracket_position,
            'round_robin_leg' => $match->round_robin_leg,
            'wins_required' => $match->wins_required,
            'participant_a' => $this->participantSummary($match->participantA),
            'participant_b' => $this->participantSummary($match->participantB),
            'score_a' => $match->score_a,
            'score_b' => $match->score_b,
            'winner' => $this->participantSummary($match->winner),
            'status' => $match->status->value,
            'status_label' => $this->matchStatusLabel($match->status->value),
            'resource_name' => $match->resource?->name,
        ];
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

    private function tournamentStatusLabel(string $status): string
    {
        return match ($status) {
            'draft' => 'Draft',
            'group_draw' => 'Izvlačenje grupa',
            'ready' => 'Spreman',
            'group_stage' => 'Grupna faza',
            'repechage' => 'Repasaž',
            'knockout_draw' => 'Žreb za nokaut',
            'knockout_stage' => 'Nokaut faza',
            'finished' => 'Završen',
            default => $status,
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
            'finished' => 'Završeno',
            'voided' => 'Anulirano',
            'cancelled' => 'Otkazano',
            default => $status,
        };
    }

    private function bracketRoundLabel(?string $bracketRound): ?string
    {
        return match ($bracketRound) {
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
