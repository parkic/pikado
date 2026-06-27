<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

use App\Models\Venue;
use App\Models\Team;

class TeamController extends Controller
{
    public function index(Request $request, Venue $venue): Response
    {
        $user = $request->user();

        abort_unless($user->canAccessVenue($venue), 403);

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

    public function create(Request $request, Venue $venue): Response
    {
        $user = $request->user();

        abort_unless($user->canAccessVenue($venue), 403);

        return Inertia::render('Venues/Teams/Create', [
            'venue' => [
                'id' => $venue->id,
                'name' => $venue->name,
                'slug' => $venue->slug,
            ],
        ]);
    }

    public function store(Request $request, Venue $venue)
    {
        $user = $request->user();

        abort_unless($user->canAccessVenue($venue), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['required', 'boolean'],
        ]);

        $venue->teams()->create([
            'name' => $validated['name'],
            'notes' => $validated['notes'],
            'is_active' => $validated['is_active'],
        ]);

        return redirect()
            ->route('venues.teams.index', ['venue' => $venue->slug])
            ->with('success', 'Tim je uspešno dodat.');
    }

    public function edit(Request $request, Venue $venue, Team $team): Response
    {
        $user = $request->user();

        abort_unless($user->canAccessVenue($venue), 403);
        abort_unless($team->venue_id === $venue->id, 404);

        return Inertia::render('Venues/Teams/Edit', [
            'venue' => [
                'id' => $venue->id,
                'name' => $venue->name,
                'slug' => $venue->slug,
            ],
            'team' => [
                'id' => $team->id,
                'name' => $team->name,
                'notes' => $team->notes,
                'is_active' => $team->is_active,
            ],
        ]);
    }

    public function update(Request $request, Venue $venue, Team $team)
    {
        $user = $request->user();

        abort_unless($user->canAccessVenue($venue), 403);
        abort_unless($team->venue_id === $venue->id, 404);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['required', 'boolean'],
        ]);

        $team->update([
            'name' => $validated['name'],
            'notes' => $validated['notes'],
            'is_active' => $validated['is_active'],
        ]);

        return redirect()
            ->route('venues.teams.index', ['venue' => $venue->slug])
            ->with('success', 'Tim je uspešno izmenjen.');
    }

    public function destroy(Request $request, Venue $venue, Team $team)
    {
        $user = $request->user();

        abort_unless($user->canAccessVenue($venue), 403);
        abort_unless($team->venue_id === $venue->id, 404);

        $team->delete();

        return redirect()
            ->route('venues.teams.index', ['venue' => $venue->slug])
            ->with('success', 'Tim je uspešno obrisan.');
    }
}
