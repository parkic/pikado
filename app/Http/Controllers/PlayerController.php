<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

use App\Models\Venue;
use App\Models\Player;

class PlayerController extends Controller
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

        $players = $venue->players()
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->orderBy('id')
            ->get()
            ->map(fn ($player) => [
                'id' => $player->id,
                'first_name' => $player->first_name,
                'last_name' => $player->last_name,
                'nickname' => $player->nickname,
                'notes' => $player->notes,
                'is_active' => $player->is_active,
            ]);

        return Inertia::render('Venues/Players/Index', [
            'venue' => [
                'id' => $venue->id,
                'name' => $venue->name,
                'slug' => $venue->slug,
            ],
            'players' => $players,
        ]);
    }

    public function create(Request $request, Venue $venue): Response
    {
        $user = $request->user();

        $hasVenueAccess = $user->global_role?->value === 'superadmin'
            || $user->venueUsers()
                ->where('venue_id', $venue->id)
                ->where('is_active', true)
                ->exists();

        abort_unless($hasVenueAccess, 403);

        return Inertia::render('Venues/Players/Create', [
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

        $hasVenueAccess = $user->global_role?->value === 'superadmin'
            || $user->venueUsers()
                ->where('venue_id', $venue->id)
                ->where('is_active', true)
                ->exists();

        abort_unless($hasVenueAccess, 403);

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'nickname' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['required', 'boolean'],
        ]);

        $venue->players()->create([
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'nickname' => $validated['nickname'],
            'notes' => $validated['notes'],
            'is_active' => $validated['is_active'],
        ]);

        return redirect()
            ->route('venues.players.index', ['venue' => $venue->slug])
            ->with('success', 'Igrač je uspešno dodat.');
    }

    public function edit(Request $request, Venue $venue, Player $player): Response
    {
        $user = $request->user();

        $hasVenueAccess = $user->global_role?->value === 'superadmin'
            || $user->venueUsers()
                ->where('venue_id', $venue->id)
                ->where('is_active', true)
                ->exists();

        abort_unless($hasVenueAccess, 403);
        abort_unless($player->venue_id === $venue->id, 404);

        return Inertia::render('Venues/Players/Edit', [
            'venue' => [
                'id' => $venue->id,
                'name' => $venue->name,
                'slug' => $venue->slug,
            ],
            'player' => [
                'id' => $player->id,
                'first_name' => $player->first_name,
                'last_name' => $player->last_name,
                'nickname' => $player->nickname,
                'notes' => $player->notes,
                'is_active' => $player->is_active,
            ],
        ]);
    }

    public function update(Request $request, Venue $venue, Player $player)
    {
        $user = $request->user();

        $hasVenueAccess = $user->global_role?->value === 'superadmin'
            || $user->venueUsers()
                ->where('venue_id', $venue->id)
                ->where('is_active', true)
                ->exists();

        abort_unless($hasVenueAccess, 403);
        abort_unless($player->venue_id === $venue->id, 404);

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'nickname' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['required', 'boolean'],
        ]);

        $player->update([
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'nickname' => $validated['nickname'],
            'notes' => $validated['notes'],
            'is_active' => $validated['is_active'],
        ]);

        return redirect()
            ->route('venues.players.index', ['venue' => $venue->slug])
            ->with('success', 'Igrač je uspešno izmenjen.');
    }
}
