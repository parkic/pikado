<?php

namespace App\Http\Controllers;

use App\Models\Venue;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

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
}
