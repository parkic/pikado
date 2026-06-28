<?php

namespace App\Http\Controllers;

use App\Models\Tournament;
use App\Models\Venue;
use App\Services\GroupStandingsCalculator;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TournamentRepechageController extends Controller
{
    public function index(
        Request $request,
        Venue $venue,
        Tournament $tournament,
        GroupStandingsCalculator $calculator
    ): Response {
        $user = $request->user();

        abort_unless($user->canAccessVenue($venue), 403);
        abort_unless($tournament->venue_id === $venue->id, 404);

        $groups = collect($calculator->calculate($tournament));

        $directQualifiers = $this->participantsByStatus($groups, 'direct');
        $repechageParticipants = $this->participantsByStatus($groups, 'repechage');
        $eliminatedParticipants = $this->participantsByStatus($groups, 'eliminated');

        return Inertia::render('Venues/Tournaments/Repechage/Index', [
            'venue' => [
                'id' => $venue->id,
                'name' => $venue->name,
                'slug' => $venue->slug,
            ],
            'tournament' => [
                'id' => $tournament->id,
                'name' => $tournament->name,
                'slug' => $tournament->slug,
                'status' => $tournament->status->value,
                'status_label' => $tournament->status->value,
                'settings' => $tournament->settings ?? [],
                'repechage_qualifiers_count' => (int) data_get(
                    $tournament->settings ?? [],
                    'repechage_qualifiers_count',
                    0,
                ),
            ],
            'direct_qualifiers' => $directQualifiers,
            'repechage_participants' => $repechageParticipants,
            'eliminated_participants' => $eliminatedParticipants,
        ]);
    }

    private function participantsByStatus($groups, string $status): array
    {
        return $groups
            ->flatMap(function (array $group) use ($status) {
                return collect($group['rows'])
                    ->filter(fn (array $row) => $row['qualification_status'] === $status)
                    ->map(fn (array $row) => [
                        'participant_id' => $row['participant_id'],
                        'group_name' => $group['name'],
                        'group_position' => $row['group_position'],
                        'group_rank' => $row['position'],
                        'display_name' => $row['display_name'],
                        'played' => $row['played'],
                        'wins' => $row['wins'],
                        'losses' => $row['losses'],
                        'points_for' => $row['points_for'],
                        'points_against' => $row['points_against'],
                        'points_difference' => $row['points_difference'],
                        'standing_points' => $row['standing_points'],
                    ]);
            })
            ->sortBy([
                ['standing_points', 'desc'],
                ['points_difference', 'desc'],
                ['points_for', 'desc'],
                ['display_name', 'asc'],
            ])
            ->values()
            ->all();
    }
}
