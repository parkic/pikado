<?php

namespace App\Http\Controllers;

use App\Enums\GameType;
use App\Enums\MatchStatus;
use App\Enums\TournamentStatus;
use App\Models\Player;
use App\Models\Tournament;
use App\Models\Venue;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class VenueDashboardController extends Controller
{
    public function __invoke(Request $request, Venue $venue): Response
    {
        $user = $request->user();

        abort_unless($user->canAccessVenue($venue), 403);

        $venue->load([
            'venueResources' => fn ($query) => $query
                ->where('is_active', true)
                ->orderBy('sort_order'),
        ])->loadCount('teams');

        $withOperationalCounts = [
            'participants',
            'matches',
            'matches as finished_matches_count' => fn ($query) => $query
                ->where('status', MatchStatus::FINISHED->value),
        ];

        $activeTournaments = $venue->tournaments()
            ->where('status', '!=', TournamentStatus::FINISHED->value)
            ->withCount($withOperationalCounts)
            ->latest()
            ->limit(4)
            ->get();

        $recentTournaments = $venue->tournaments()
            ->where('status', TournamentStatus::FINISHED->value)
            ->withCount($withOperationalCounts)
            ->latest('finished_at')
            ->limit(5)
            ->get();

        return Inertia::render('Venues/Dashboard', [
            'venue' => [
                'id' => $venue->id,
                'name' => $venue->name,
                'slug' => $venue->slug,
                'description' => $venue->description,
                'instagram_url' => $venue->instagram_url,
                'global_players_count' => Player::query()->count(),
                'teams_count' => $venue->teams_count,
                'resources' => $venue->venueResources->map(fn ($resource) => [
                    'id' => $resource->id,
                    'name' => $resource->name,
                    'type' => $resource->type->value,
                    'sort_order' => $resource->sort_order,
                ]),
            ],
            'activeTournaments' => $this->tournamentSummaries(
                $activeTournaments,
                $venue,
            ),
            'recentTournaments' => $this->tournamentSummaries(
                $recentTournaments,
                $venue,
            ),
        ]);
    }

    /**
     * @param  Collection<int, Tournament>  $tournaments
     * @return Collection<int, array<string, mixed>>
     */
    private function tournamentSummaries(
        Collection $tournaments,
        Venue $venue,
    ): Collection {
        return $tournaments->map(function (Tournament $tournament) use ($venue) {
            return [
                'id' => $tournament->id,
                'name' => $tournament->name,
                'slug' => $tournament->slug,
                'public_code' => $tournament->public_code,
                'public_enabled' => $tournament->public_enabled,
                'game_type_label' => $this->gameTypeLabel($tournament->game_type),
                'match_mode_label' => $tournament->match_mode->value === 'singles'
                    ? '1v1'
                    : '2v2',
                'status' => $tournament->status->value,
                'status_label' => $this->statusLabel($tournament->status),
                'participants_count' => $tournament->participants_count,
                'matches_count' => $tournament->matches_count,
                'finished_matches_count' => $tournament->finished_matches_count,
                'created_at' => $tournament->created_at?->format('d.m.Y.'),
                'finished_at' => $tournament->finished_at?->format('d.m.Y.'),
                'action_url' => $this->tournamentActionUrl(
                    $venue,
                    $tournament,
                ),
                'action_label' => $this->tournamentActionLabel(
                    $tournament->status,
                ),
                'public_url' => route(
                    'public.tournaments.live',
                    $tournament->public_code,
                ),
            ];
        });
    }

    private function tournamentActionUrl(
        Venue $venue,
        Tournament $tournament,
    ): string {
        $routeName = match ($tournament->status) {
            TournamentStatus::DRAFT,
            TournamentStatus::GROUP_DRAW => 'venues.tournaments.group_draw.show',
            TournamentStatus::GROUP_STAGE,
            TournamentStatus::KNOCKOUT_STAGE => 'venues.tournaments.schedule.index',
            TournamentStatus::REPECHAGE => 'venues.tournaments.repechage.index',
            TournamentStatus::KNOCKOUT_DRAW => 'venues.tournaments.knockout.index',
            TournamentStatus::READY,
            TournamentStatus::FINISHED => 'venues.tournaments.show',
        };

        return route($routeName, [$venue, $tournament]);
    }

    private function tournamentActionLabel(TournamentStatus $status): string
    {
        return match ($status) {
            TournamentStatus::DRAFT => 'Dodaj učesnike',
            TournamentStatus::GROUP_DRAW => 'Nastavi unos',
            TournamentStatus::READY => 'Pokreni mečeve',
            TournamentStatus::GROUP_STAGE => 'Unesi rezultate',
            TournamentStatus::REPECHAGE => 'Otvori repasaž',
            TournamentStatus::KNOCKOUT_DRAW => 'Napravi nokaut',
            TournamentStatus::KNOCKOUT_STAGE => 'Nastavi nokaut',
            TournamentStatus::FINISHED => 'Pogledaj turnir',
        };
    }

    private function statusLabel(TournamentStatus $status): string
    {
        return match ($status) {
            TournamentStatus::DRAFT => 'Priprema',
            TournamentStatus::GROUP_DRAW => 'Unos učesnika',
            TournamentStatus::READY => 'Spreman',
            TournamentStatus::GROUP_STAGE => 'Grupna faza',
            TournamentStatus::REPECHAGE => 'Repasaž',
            TournamentStatus::KNOCKOUT_DRAW => 'Žreb za nokaut',
            TournamentStatus::KNOCKOUT_STAGE => 'Nokaut faza',
            TournamentStatus::FINISHED => 'Završen',
        };
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
}
