<?php

namespace App\Http\Controllers;

use App\Models\Player;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PlayerController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorizeManagement($request);

        $players = Player::query()
            ->select('players.*')
            ->selectSub(
                fn ($query) => $query
                    ->from('tournament_participants')
                    ->join('tournaments', 'tournaments.id', '=', 'tournament_participants.tournament_id')
                    ->whereColumn('tournament_participants.player_id', 'players.id')
                    ->whereNull('tournament_participants.deleted_at')
                    ->whereNull('tournaments.deleted_at')
                    ->selectRaw('COUNT(DISTINCT tournaments.venue_id)'),
                'venues_count',
            )
            ->withCount('tournamentParticipants')
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
                'tournaments_count' => $player->tournament_participants_count,
                'venues_count' => (int) $player->venues_count,
                'public_url' => route('public.players.show', $player, false),
                'can_delete' => $player->tournament_participants_count === 0,
            ]);

        return Inertia::render('Venues/Players/Index', [
            'players' => $players,
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorizeManagement($request);

        return Inertia::render('Venues/Players/Create');
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeManagement($request);

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'nickname' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['required', 'boolean'],
        ]);

        Player::query()->create([
            'first_name' => trim($validated['first_name']),
            'last_name' => trim($validated['last_name']),
            'nickname' => filled($validated['nickname'] ?? null) ? trim($validated['nickname']) : null,
            'notes' => filled($validated['notes'] ?? null) ? trim($validated['notes']) : null,
            'is_active' => $validated['is_active'],
        ]);

        return redirect()
            ->route('players.index')
            ->with('success', 'Igrač je uspešno dodat.');
    }

    public function edit(Request $request, Player $player): Response
    {
        $this->authorizeManagement($request);

        return Inertia::render('Venues/Players/Edit', [
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

    public function update(Request $request, Player $player): RedirectResponse
    {
        $this->authorizeManagement($request);

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'nickname' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['required', 'boolean'],
        ]);

        $player->update([
            'first_name' => trim($validated['first_name']),
            'last_name' => trim($validated['last_name']),
            'nickname' => filled($validated['nickname'] ?? null) ? trim($validated['nickname']) : null,
            'notes' => filled($validated['notes'] ?? null) ? trim($validated['notes']) : null,
            'is_active' => $validated['is_active'],
        ]);

        return redirect()
            ->route('players.index')
            ->with('success', 'Igrač je uspešno izmenjen.');
    }

    public function destroy(Request $request, Player $player): RedirectResponse
    {
        $this->authorizeManagement($request);

        if ($player->tournamentParticipants()->withTrashed()->exists()) {
            return back()->withErrors([
                'player' => 'Igrač ima istoriju turnira i ne može biti obrisan. Možeš ga označiti kao neaktivnog.',
            ]);
        }

        $player->delete();

        return redirect()
            ->route('players.index')
            ->with('success', 'Igrač je uspešno obrisan.');
    }

    private function authorizeManagement(Request $request): void
    {
        abort_unless($request->user()->canManagePlayers(), 403);
    }
}
