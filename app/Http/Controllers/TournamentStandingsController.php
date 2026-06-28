<?php

namespace App\Http\Controllers;

use App\Models\Tournament;
use App\Models\Venue;
use App\Services\GroupStandingsCalculator;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use App\Enums\QualificationStatus;
use App\Models\TournamentParticipant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\Rule;

class TournamentStandingsController extends Controller
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

        return Inertia::render('Venues/Tournaments/Standings/Index', [
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
            ],
            'groups' => $calculator->calculate($tournament),
        ]);
    }

    public function updateQualificationOverride(
        Request $request,
        Venue $venue,
        Tournament $tournament,
        TournamentParticipant $participant
    ): RedirectResponse {
        $user = $request->user();

        abort_unless($user->canAccessVenue($venue), 403);
        abort_unless($tournament->venue_id === $venue->id, 404);
        abort_unless($participant->tournament_id === $tournament->id, 404);

        $validated = $request->validate([
            'qualification_override_status' => [
                'nullable',
                Rule::in([
                    QualificationStatus::DIRECT->value,
                    QualificationStatus::REPECHAGE->value,
                    QualificationStatus::ELIMINATED->value,
                ]),
            ],
        ]);

        $participant->update([
            'qualification_override_status' => $validated['qualification_override_status'] ?: null,
        ]);

        return back()->with('success', 'Status prolaza je promenjen.');
    }
}
