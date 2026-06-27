<?php

namespace App\Http\Controllers;

use App\Models\Venue;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TeamController extends Controller
{
    public function index(Request $request, Venue $venue): Response
    {
        $user = $request->user();

        $hasVenueAccess = $user->global_role?->value === 'superadmin'
            || $user->venueUsers()
                ->where('venue_id', $venue->id)
                ->where('is_active', true)
                ->exists();

        abort_unless($hasVenueAccess, 403);

        $teams = $venue->teams()
            ->orderBy('name')
            ->orderBy('id')
            ->get()
            ->map(fn ($team) => [
                'id' => $team->id,
                'name' => $team->name,
                'notes' => $team->notes,
                'is_active' => $team->is_active,
            ]);

        return Inertia::render('Venues/Teams/Index', [
            'venue' => [
                'id' => $venue->id,
                'name' => $venue->name,
                'slug' => $venue->slug,
            ],
            'teams' => $teams,
        ]);
    }
}
