<?php

namespace App\Http\Controllers;

use App\Enums\GameType;
use App\Enums\MatchMode;
use App\Enums\TournamentStatus;
use App\Models\Tournament;
use App\Models\Venue;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TournamentController extends Controller
{
    public function index(Request $request, Venue $venue): Response
    {
        $user = $request->user();

        abort_unless($user->canAccessVenue($venue), 403);

        $tournaments = $venue->tournaments()
            ->withCount('resources')
            ->latest()
            ->get()
            ->map(fn (Tournament $tournament) => [
                'id' => $tournament->id,
                'name' => $tournament->name,
                'slug' => $tournament->slug,
                'public_code' => $tournament->public_code,
                'game_type' => $tournament->game_type->value,
                'game_type_label' => $this->gameTypeLabel($tournament->game_type),
                'match_mode' => $tournament->match_mode->value,
                'match_mode_label' => $this->matchModeLabel($tournament->match_mode),
                'status' => $tournament->status->value,
                'status_label' => $this->statusLabel($tournament->status),
                'knockout_size' => $tournament->knockout_size,
                'public_enabled' => $tournament->public_enabled,
                'resources_count' => $tournament->resources_count,
                'created_at' => $tournament->created_at?->format('d.m.Y. H:i'),
            ]);

        return Inertia::render('Venues/Tournaments/Index', [
            'venue' => [
                'id' => $venue->id,
                'name' => $venue->name,
                'slug' => $venue->slug,
            ],
            'tournaments' => $tournaments,
        ]);
    }

    private function gameTypeLabel(GameType $gameType): string
    {
        return match ($gameType) {
            GameType::DART_301 => '301',
            GameType::DART_501 => '501',
            GameType::CRICKET => 'Cricket',
            GameType::BEER_PONG => 'Beer Pong',
        };
    }

    private function matchModeLabel(MatchMode $matchMode): string
    {
        return match ($matchMode) {
            MatchMode::SINGLES => '1v1',
            MatchMode::DOUBLES => '2v2',
        };
    }

    private function statusLabel(TournamentStatus $status): string
    {
        return match ($status) {
            TournamentStatus::DRAFT => 'Draft',
            TournamentStatus::GROUP_DRAW => 'Izvlačenje grupa',
            TournamentStatus::READY => 'Spreman',
            TournamentStatus::GROUP_STAGE => 'Grupna faza',
            TournamentStatus::REPECHAGE => 'Repasaž',
            TournamentStatus::KNOCKOUT_DRAW => 'Žreb za nokaut',
            TournamentStatus::KNOCKOUT_STAGE => 'Nokaut faza',
            TournamentStatus::FINISHED => 'Završen',
        };
    }
}
