<?php

namespace App\Http\Controllers;

use App\Enums\MatchMode;
use App\Enums\ParticipantStatus;
use App\Enums\ParticipantType;
use App\Models\Player;
use App\Models\Team;
use App\Models\Tournament;
use App\Models\TournamentGroup;
use App\Models\TournamentParticipant;
use App\Models\Venue;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class TournamentGroupDrawController extends Controller
{
    public function show(Request $request, Venue $venue, Tournament $tournament): Response
    {
        $user = $request->user();

        abort_unless($user->canAccessVenue($venue), 403);
        abort_unless($tournament->venue_id === $venue->id, 404);

        $this->loadTournamentForDraw($tournament);

        $nextSlot = $this->nextEmptySlot($tournament);

        return Inertia::render('Venues/Tournaments/GroupDraw', [
            'venue' => [
                'id' => $venue->id,
                'name' => $venue->name,
                'slug' => $venue->slug,
            ],
            'tournament' => [
                'id' => $tournament->id,
                'name' => $tournament->name,
                'slug' => $tournament->slug,
                'match_mode' => $tournament->match_mode->value,
                'match_mode_label' => $tournament->match_mode === MatchMode::SINGLES ? '1v1' : '2v2',
                'settings' => $tournament->settings ?? [],
                'groups_count' => $tournament->groups->count(),
                'participants_count' => $tournament->participants()->count(),
                'total_slots' => $this->totalSlots($tournament),
                'next_slot' => $nextSlot ? [
                    'group_id' => $nextSlot['group']->id,
                    'group_name' => $nextSlot['group']->name,
                    'slot_number' => $nextSlot['slot_number'],
                    'group_position' => $nextSlot['group_position'],
                ] : null,
                'groups' => $tournament->groups->map(fn (TournamentGroup $group) => [
                    'id' => $group->id,
                    'name' => $group->name,
                    'sort_order' => $group->sort_order,
                    'participants' => $group->participants->map(fn (TournamentParticipant $participant) => [
                        'id' => $participant->id,
                        'participant_type' => $participant->participant_type->value,
                        'group_position' => $participant->group_position,
                        'status' => $participant->status->value,
                        'display_name' => $this->participantDisplayName($participant),
                    ]),
                ]),
            ],
        ]);
    }

    public function store(Request $request, Venue $venue, Tournament $tournament): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user->canAccessVenue($venue), 403);
        abort_unless($tournament->venue_id === $venue->id, 404);

        $this->loadTournamentForDraw($tournament);

        $requestedGroupPosition = trim((string) $request->input('group_position', ''));

        $targetSlot = $requestedGroupPosition !== ''
            ? $this->slotFromGroupPosition($tournament, $requestedGroupPosition)
            : $this->nextEmptySlot($tournament);

        if (! $targetSlot) {
            return back()
                ->withErrors([
                    'slot' => 'Izabrano mesto ne postoji ili nema više slobodnih mesta u grupama.',
                ]);
        }

        if (
            $tournament->participants()
                ->where('group_position', $targetSlot['group_position'])
                ->exists()
        ) {
            return back()
                ->withErrors([
                    'slot' => 'Izabrano mesto je već popunjeno.',
                ]);
        }

        if ($tournament->match_mode === MatchMode::SINGLES) {
            $validated = $request->validate([
                'first_name' => ['required', 'string', 'max:255'],
                'last_name' => ['required', 'string', 'max:255'],
                'nickname' => ['nullable', 'string', 'max:255'],
            ]);

            $this->storePlayerParticipant($venue, $tournament, $targetSlot, $validated);
        } else {
            $validated = $request->validate([
                'team_name' => ['required', 'string', 'max:255'],
            ]);

            $this->storeTeamParticipant($venue, $tournament, $targetSlot, $validated);
        }

        return redirect()
            ->route('venues.tournaments.group_draw.show', [$venue, $tournament])
            ->with('success', 'Učesnik je dodat u ' . $targetSlot['group_position'] . '.');
    }

    public function destroyParticipant(
        Request $request,
        Venue $venue,
        Tournament $tournament,
        TournamentParticipant $participant
    ): RedirectResponse {
        $user = $request->user();

        abort_unless($user->canAccessVenue($venue), 403);
        abort_unless($tournament->venue_id === $venue->id, 404);
        abort_unless($participant->tournament_id === $tournament->id, 404);

        $groupPosition = $participant->group_position;

        $participant->delete();

        return redirect()
            ->route('venues.tournaments.group_draw.show', [$venue, $tournament])
            ->with('success', 'Učesnik je uklonjen iz ' . $groupPosition . '.');
    }

    public function editParticipant(
        Request $request,
        Venue $venue,
        Tournament $tournament,
        TournamentParticipant $participant
    ): Response {
        $user = $request->user();

        abort_unless($user->canAccessVenue($venue), 403);
        abort_unless($tournament->venue_id === $venue->id, 404);
        abort_unless($participant->tournament_id === $tournament->id, 404);

        $participant->load(['player', 'team']);

        return Inertia::render('Venues/Tournaments/EditGroupDrawParticipant', [
            'venue' => [
                'id' => $venue->id,
                'name' => $venue->name,
                'slug' => $venue->slug,
            ],
            'tournament' => [
                'id' => $tournament->id,
                'name' => $tournament->name,
                'slug' => $tournament->slug,
                'match_mode' => $tournament->match_mode->value,
                'match_mode_label' => $tournament->match_mode === MatchMode::SINGLES ? '1v1' : '2v2',
            ],
            'participant' => [
                'id' => $participant->id,
                'participant_type' => $participant->participant_type->value,
                'group_position' => $participant->group_position,
                'display_name' => $this->participantDisplayName($participant),
                'player' => $participant->player ? [
                    'id' => $participant->player->id,
                    'first_name' => $participant->player->first_name,
                    'last_name' => $participant->player->last_name,
                    'nickname' => $participant->player->nickname,
                ] : null,
                'team' => $participant->team ? [
                    'id' => $participant->team->id,
                    'name' => $participant->team->name,
                ] : null,
            ],
        ]);
    }

    public function updateParticipant(
        Request $request,
        Venue $venue,
        Tournament $tournament,
        TournamentParticipant $participant
    ): RedirectResponse {
        $user = $request->user();

        abort_unless($user->canAccessVenue($venue), 403);
        abort_unless($tournament->venue_id === $venue->id, 404);
        abort_unless($participant->tournament_id === $tournament->id, 404);

        $participant->load(['player', 'team']);

        if ($participant->participant_type === ParticipantType::PLAYER) {
            abort_unless($participant->player !== null, 404);

            $validated = $request->validate([
                'first_name' => ['required', 'string', 'max:255'],
                'last_name' => ['required', 'string', 'max:255'],
                'nickname' => ['nullable', 'string', 'max:255'],
            ]);

            $participant->player->update([
                'first_name' => trim($validated['first_name']),
                'last_name' => trim($validated['last_name']),
                'nickname' => isset($validated['nickname']) && trim($validated['nickname']) !== ''
                    ? trim($validated['nickname'])
                    : null,
            ]);
        } else {
            abort_unless($participant->team !== null, 404);

            $validated = $request->validate([
                'team_name' => ['required', 'string', 'max:255'],
            ]);

            $participant->team->update([
                'name' => trim($validated['team_name']),
            ]);
        }

        return redirect()
            ->route('venues.tournaments.group_draw.show', [$venue, $tournament])
            ->with('success', 'Učesnik je izmenjen.');
    }

    private function storePlayerParticipant(Venue $venue, Tournament $tournament, array $nextSlot, array $validated): void
    {
        $firstName = trim($validated['first_name']);
        $lastName = trim($validated['last_name']);
        $nickname = isset($validated['nickname']) && trim($validated['nickname']) !== ''
            ? trim($validated['nickname'])
            : null;

        DB::transaction(function () use ($venue, $tournament, $nextSlot, $firstName, $lastName, $nickname): void {
            $playerQuery = Player::query()
                ->where('venue_id', $venue->id)
                ->where('first_name', $firstName)
                ->where('last_name', $lastName);

            if ($nickname === null) {
                $playerQuery->whereNull('nickname');
            } else {
                $playerQuery->where('nickname', $nickname);
            }

            $player = $playerQuery->first();

            if (! $player) {
                $player = Player::create([
                    'venue_id' => $venue->id,
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'nickname' => $nickname,
                    'is_active' => true,
                ]);
            }

            if (
                $tournament->participants()
                    ->where('participant_type', ParticipantType::PLAYER->value)
                    ->where('player_id', $player->id)
                    ->exists()
            ) {
                throw ValidationException::withMessages([
                    'first_name' => 'Ovaj igrač je već dodat u turnir.',
                ]);
            }

            $slotParticipant = $tournament->participants()
                ->withTrashed()
                ->where('group_position', $nextSlot['group_position'])
                ->first();

            if ($slotParticipant) {
                $slotParticipant->forceFill([
                    'participant_type' => ParticipantType::PLAYER,
                    'player_id' => $player->id,
                    'team_id' => null,
                    'tournament_group_id' => $nextSlot['group']->id,
                    'group_position' => $nextSlot['group_position'],
                    'status' => ParticipantStatus::ACTIVE,
                    'withdrawn_at' => null,
                    'withdrawn_stage' => null,
                    'withdrawn_reason' => null,
                ]);

                if ($slotParticipant->trashed()) {
                    $slotParticipant->restore();
                } else {
                    $slotParticipant->save();
                }

                return;
            }

            TournamentParticipant::create([
                'tournament_id' => $tournament->id,
                'participant_type' => ParticipantType::PLAYER,
                'player_id' => $player->id,
                'tournament_group_id' => $nextSlot['group']->id,
                'group_position' => $nextSlot['group_position'],
                'status' => ParticipantStatus::ACTIVE,
            ]);
        });
    }

    private function storeTeamParticipant(Venue $venue, Tournament $tournament, array $nextSlot, array $validated): void
    {
        $teamName = trim($validated['team_name']);

        DB::transaction(function () use ($venue, $tournament, $nextSlot, $teamName): void {
            $team = Team::query()
                ->where('venue_id', $venue->id)
                ->where('name', $teamName)
                ->first();

            if (! $team) {
                $team = Team::create([
                    'venue_id' => $venue->id,
                    'name' => $teamName,
                    'is_active' => true,
                ]);
            }

            if (
                $tournament->participants()
                    ->where('participant_type', ParticipantType::TEAM->value)
                    ->where('team_id', $team->id)
                    ->exists()
            ) {
                throw ValidationException::withMessages([
                    'team_name' => 'Ova ekipa je već dodata u turnir.',
                ]);
            }

            $slotParticipant = $tournament->participants()
                ->withTrashed()
                ->where('group_position', $nextSlot['group_position'])
                ->first();

            if ($slotParticipant) {
                $slotParticipant->forceFill([
                    'participant_type' => ParticipantType::TEAM,
                    'player_id' => null,
                    'team_id' => $team->id,
                    'tournament_group_id' => $nextSlot['group']->id,
                    'group_position' => $nextSlot['group_position'],
                    'status' => ParticipantStatus::ACTIVE,
                    'withdrawn_at' => null,
                    'withdrawn_stage' => null,
                    'withdrawn_reason' => null,
                ]);

                if ($slotParticipant->trashed()) {
                    $slotParticipant->restore();
                } else {
                    $slotParticipant->save();
                }

                return;
            }

            TournamentParticipant::create([
                'tournament_id' => $tournament->id,
                'participant_type' => ParticipantType::TEAM,
                'team_id' => $team->id,
                'tournament_group_id' => $nextSlot['group']->id,
                'group_position' => $nextSlot['group_position'],
                'status' => ParticipantStatus::ACTIVE,
            ]);
        });
    }

    private function loadTournamentForDraw(Tournament $tournament): void
    {
        $tournament->load([
            'groups' => fn ($query) => $query
                ->orderBy('sort_order')
                ->orderBy('name'),
            'groups.participants' => fn ($query) => $query
                ->orderBy('group_position')
                ->orderBy('id'),
            'groups.participants.player',
            'groups.participants.team',
        ]);
    }

    private function nextEmptySlot(Tournament $tournament): ?array
    {
        $groupSize = (int) data_get($tournament->settings ?? [], 'group_size', 0);

        if ($groupSize < 1 || $tournament->groups->isEmpty()) {
            return null;
        }

        $filledPositions = $tournament->participants()
            ->whereNotNull('group_position')
            ->pluck('group_position')
            ->all();

        foreach (range(1, $groupSize) as $slotNumber) {
            foreach ($tournament->groups as $group) {
                $groupPosition = $group->name . $slotNumber;

                if (! in_array($groupPosition, $filledPositions, true)) {
                    return [
                        'group' => $group,
                        'slot_number' => $slotNumber,
                        'group_position' => $groupPosition,
                    ];
                }
            }
        }

        return null;
    }

    private function totalSlots(Tournament $tournament): int
    {
        $groupSize = (int) data_get($tournament->settings ?? [], 'group_size', 0);

        return $tournament->groups->count() * $groupSize;
    }

    private function participantDisplayName(TournamentParticipant $participant): string
    {
        if ($participant->player) {
            $name = trim($participant->player->first_name . ' ' . $participant->player->last_name);

            if ($participant->player->nickname) {
                return $name . ' (' . $participant->player->nickname . ')';
            }

            return $name;
        }

        if ($participant->team) {
            return $participant->team->name;
        }

        return 'Nepoznat učesnik';
    }

    private function slotFromGroupPosition(Tournament $tournament, string $groupPosition): ?array
    {
        $groupSize = (int) data_get($tournament->settings ?? [], 'group_size', 0);

        if ($groupSize < 1 || $tournament->groups->isEmpty()) {
            return null;
        }

        $groupPosition = strtoupper(trim($groupPosition));

        if (! preg_match('/^([A-Z]+)([0-9]+)$/', $groupPosition, $matches)) {
            return null;
        }

        $groupName = $matches[1];
        $slotNumber = (int) $matches[2];

        if ($slotNumber < 1 || $slotNumber > $groupSize) {
            return null;
        }

        $group = $tournament->groups->firstWhere('name', $groupName);

        if (! $group) {
            return null;
        }

        return [
            'group' => $group,
            'slot_number' => $slotNumber,
            'group_position' => $groupName . $slotNumber,
        ];
    }
}
