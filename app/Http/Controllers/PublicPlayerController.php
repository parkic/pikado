<?php

namespace App\Http\Controllers;

use App\Enums\MatchStatus;
use App\Enums\ParticipantStatus;
use App\Models\Player;
use App\Models\TournamentMatch;
use App\Models\TournamentParticipant;
use App\Models\Venue;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class PublicPlayerController extends Controller
{
    public function show(Player $player): Response
    {
        abort_unless($player->is_active, 404);

        $participations = TournamentParticipant::query()
            ->with(['tournament.venue', 'group'])
            ->where('player_id', $player->id)
            ->whereHas('tournament', fn ($query) => $query->where('public_enabled', true))
            ->get()
            ->sortByDesc(fn (TournamentParticipant $participant) => $participant->tournament->tournament_date
                    ?? $participant->tournament->started_at
                    ?? $participant->tournament->created_at
            )
            ->values();

        $matches = TournamentMatch::query()
            ->with([
                'tournament.venue',
                'group',
                'participantA.player',
                'participantA.team',
                'participantB.player',
                'participantB.team',
            ])
            ->whereIn('tournament_id', $participations->pluck('tournament_id'))
            ->where(function ($query) use ($participations): void {
                $participantIds = $participations->pluck('id');
                $query->whereIn('participant_a_id', $participantIds)
                    ->orWhereIn('participant_b_id', $participantIds);
            })
            ->where('status', MatchStatus::FINISHED->value)
            ->orderByDesc('finished_at')
            ->orderByDesc('id')
            ->get();

        $matchesByTournament = $matches->groupBy('tournament_id');
        $tournaments = $participations->map(function (TournamentParticipant $participant) use ($matchesByTournament): array {
            $tournament = $participant->tournament;
            $participantMatches = collect($matchesByTournament->get($tournament->id, collect()));
            $wins = $participantMatches
                ->where('winner_participant_id', $participant->id)
                ->count();

            return [
                'id' => $tournament->id,
                'name' => $tournament->name,
                'venue_name' => $tournament->venue->name,
                'date' => ($tournament->tournament_date
                    ?? $tournament->started_at
                    ?? $tournament->created_at)?->format('d.m.Y.'),
                'participants_count' => $tournament->participants()->count(),
                'result_label' => $this->tournamentResultLabel($participant, $participantMatches),
                'matches_played' => $participantMatches->count(),
                'wins' => $wins,
                'losses' => $participantMatches->count() - $wins,
                'public_url' => route('public.tournaments.live', $tournament->public_code, false),
            ];
        });

        $matchHistory = $matches->map(function (TournamentMatch $match) use ($participations): array {
            $participant = $participations->firstWhere('tournament_id', $match->tournament_id);
            $isParticipantA = (int) $match->participant_a_id === (int) $participant?->id;
            $opponent = $isParticipantA ? $match->participantB : $match->participantA;

            return [
                'id' => $match->id,
                'tournament_name' => $match->tournament->name,
                'tournament_url' => route('public.tournaments.live', $match->tournament->public_code, false),
                'venue_name' => $match->tournament->venue->name,
                'date' => ($match->finished_at ?? $match->created_at)?->format('d.m.Y.'),
                'stage_label' => $this->stageLabel($match),
                'opponent_name' => $this->participantDisplayName($opponent),
                'score_for' => $isParticipantA ? $match->score_a : $match->score_b,
                'score_against' => $isParticipantA ? $match->score_b : $match->score_a,
                'won' => (int) $match->winner_participant_id === (int) $participant?->id,
            ];
        });

        $wins = $matchHistory->where('won', true)->count();

        return Inertia::render('Public/Players/Show', [
            'app' => [
                'name' => 'Pikado',
                'public_theme' => 'dark',
            ],
            'player' => [
                'id' => $player->id,
                'display_name' => $this->playerDisplayName($player),
                'first_name' => $player->first_name,
                'last_name' => $player->last_name,
                'nickname' => $player->nickname,
                'tournaments_count' => $tournaments->count(),
                'matches_count' => $matchHistory->count(),
                'wins_count' => $wins,
                'losses_count' => $matchHistory->count() - $wins,
                'win_rate' => $matchHistory->isNotEmpty()
                    ? (int) round(($wins / $matchHistory->count()) * 100)
                    : 0,
            ],
            'tournaments' => $tournaments,
            'matches' => $matchHistory,
        ]);
    }

    public function legacy(Venue $venue, Player $player): RedirectResponse
    {
        return redirect()->route('public.players.show', $player, 301);
    }

    private function tournamentResultLabel(
        TournamentParticipant $participant,
        Collection $matches,
    ): string {
        if ($participant->status === ParticipantStatus::WITHDRAWN) {
            return 'Odustao';
        }

        $finalMatches = $matches->where('bracket_round', 'final');

        if ($finalMatches->isNotEmpty()) {
            return $this->wonSeries($participant, $finalMatches) ? '1. mesto' : '2. mesto';
        }

        $thirdPlaceMatches = $matches->where('bracket_round', 'third_place');

        if ($thirdPlaceMatches->isNotEmpty()) {
            return $this->wonSeries($participant, $thirdPlaceMatches) ? '3. mesto' : 'Top 4';
        }

        $roundPriority = [
            'semi_final' => 'Top 4',
            'quarter_final' => 'Top 8',
            'round_of_16' => 'Top 16',
            'round_of_32' => 'Top 32',
            'preliminary' => 'Preliminarna runda',
        ];

        foreach ($roundPriority as $round => $label) {
            if ($matches->contains('bracket_round', $round)) {
                return $label;
            }
        }

        return 'Grupna faza';
    }

    private function wonSeries(TournamentParticipant $participant, Collection $matches): bool
    {
        $winsRequired = (int) ($matches->first()?->wins_required ?: 1);

        return $matches->where('winner_participant_id', $participant->id)->count() >= $winsRequired;
    }

    private function stageLabel(TournamentMatch $match): string
    {
        if ($match->group) {
            return $match->group->name;
        }

        return match ($match->bracket_round) {
            'preliminary' => 'Preliminarna runda',
            'round_of_32' => '1/16 finala',
            'round_of_16' => '1/8 finala',
            'quarter_final' => 'Četvrtfinale',
            'semi_final' => 'Polufinale',
            'third_place' => 'Treće mesto',
            'final' => 'Finale',
            default => 'Meč',
        };
    }

    private function playerDisplayName(Player $player): string
    {
        $name = trim($player->first_name.' '.$player->last_name);

        return $player->nickname ? $name.' ('.$player->nickname.')' : $name;
    }

    private function participantDisplayName(?TournamentParticipant $participant): string
    {
        if (! $participant) {
            return '—';
        }

        if ($participant->team) {
            return $participant->team->name;
        }

        return $participant->player
            ? $this->playerDisplayName($participant->player)
            : 'Učesnik #'.$participant->id;
    }
}
