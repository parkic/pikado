<?php

namespace App\Http\Controllers;

use App\Enums\MatchMode;
use App\Enums\MatchStage;
use App\Enums\MatchStatus;
use App\Enums\ParticipantStatus;
use App\Enums\ParticipantType;
use App\Enums\TournamentStatus;
use App\Events\TournamentLiveUpdated;
use App\Models\Player;
use App\Models\Team;
use App\Models\Tournament;
use App\Models\TournamentGroup;
use App\Models\TournamentMatch;
use App\Models\TournamentParticipant;
use App\Models\Venue;
use App\Services\GroupMatchGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
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

        $replacementMatches = TournamentMatch::withoutGlobalScope('visible_matches')
            ->where('tournament_id', $tournament->id)
            ->where('stage', MatchStage::GROUP->value)
            ->get();

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
                'status' => $tournament->status->value,
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
                        'can_replace' => $this->participantReplacementValidationError(
                            $tournament,
                            $participant,
                            $replacementMatches,
                        ) === null,
                        'can_remove' => $this->participantReplacementValidationError(
                            $tournament,
                            $participant,
                            $replacementMatches,
                        ) === null,
                    ]),
                ]),
                'can_manage_withdrawals' => $this->canManageWithdrawals($user, $venue),
            ],
            'available_players' => $this->availablePlayers($tournament),
            'available_teams' => $this->availableTeams($venue, $tournament),
        ]);
    }

    public function store(Request $request, Venue $venue, Tournament $tournament, GroupMatchGenerator $generator): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user->canAccessVenue($venue), 403);
        abort_unless($tournament->venue_id === $venue->id, 404);

        if (! $this->canAddParticipant($tournament)) {
            return back()->withErrors([
                'participant' => 'Učesnici više ne mogu da se dodaju u trenutnoj fazi turnira.',
            ]);
        }

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
                'existing_player_id' => [
                    'nullable',
                    'integer',
                ],
                'first_name' => [
                    'required_without:existing_player_id',
                    'nullable',
                    'string',
                    'max:255',
                ],
                'last_name' => [
                    'required_without:existing_player_id',
                    'nullable',
                    'string',
                    'max:255',
                ],
                'nickname' => [
                    'nullable',
                    'string',
                    'max:255',
                ],
            ]);
        } else {
            $validated = $request->validate([
                'existing_team_id' => [
                    'nullable',
                    'integer',
                ],
                'team_name' => [
                    'required_without:existing_team_id',
                    'nullable',
                    'string',
                    'max:255',
                ],
            ]);
        }

        $createdMatches = DB::transaction(function () use (
            $venue,
            $tournament,
            $targetSlot,
            $validated,
            $generator,
        ): int {
            if (
                $tournament->match_mode
                === MatchMode::SINGLES
            ) {
                $this->storePlayerParticipant(
                    $tournament,
                    $targetSlot,
                    $validated,
                );
            } else {
                $this->storeTeamParticipant(
                    $venue,
                    $tournament,
                    $targetSlot,
                    $validated,
                );
            }

            $participant = $tournament
                ->participants()
                ->where(
                    'group_position',
                    $targetSlot['group_position'],
                )
                ->firstOrFail();

            if (
                $tournament->status
                !== TournamentStatus::GROUP_STAGE
            ) {
                return 0;
            }

            return $generator->generateForParticipant(
                $tournament,
                $participant,
            );
        });

        $this->syncParticipantRosterStatus($tournament);

        event(new TournamentLiveUpdated(
            $tournament->fresh(),
            'participant_added',
        ));

        $successMessage = 'Učesnik je dodat u '
            .$targetSlot['group_position']
            .'.';

        if (
            $tournament->status
            === TournamentStatus::GROUP_STAGE
        ) {
            $successMessage .= sprintf(
                ' Aktivirano je mečeva na njegovim rezervisanim pozicijama u rasporedu: %d.',
                $createdMatches,
            );
        } elseif (
            $tournament->status
            === TournamentStatus::READY
        ) {
            $successMessage .= ' Sva mesta su popunjena i turnir je spreman za generisanje mečeva.';
        }

        return redirect()
            ->route('venues.tournaments.group_draw.show', [$venue, $tournament])
            ->with('success', $successMessage);
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

        if (! $this->canManageParticipantRoster($tournament)) {
            return back()->withErrors([
                'participant' => 'Učesnici ne mogu da se uklanjaju u trenutnoj fazi turnira.',
            ]);
        }

        $groupPosition = $participant->group_position;

        $matches = TournamentMatch::withoutGlobalScope('visible_matches')
            ->where('tournament_id', $tournament->id)
            ->where('stage', MatchStage::GROUP->value)
            ->get();

        $removalError = $this->participantReplacementValidationError(
            $tournament,
            $participant,
            $matches,
        );

        if ($removalError !== null) {
            return back()->withErrors([
                'participant' => $removalError.' Umesto uklanjanja označi učesnika kao odustalog.',
            ]);
        }

        DB::transaction(function () use ($tournament, $participant, $matches): void {
            if ($tournament->status === TournamentStatus::GROUP_STAGE) {
                $participantMatches = $matches->filter(
                    fn (TournamentMatch $match): bool => (int) $match->participant_a_id === $participant->id
                        || (int) $match->participant_b_id === $participant->id
                );

                foreach ($participantMatches as $match) {
                    $updates = [
                        'is_hidden' => true,
                        'score_a' => null,
                        'score_b' => null,
                        'winner_participant_id' => null,
                        'loser_participant_id' => null,
                        'status' => MatchStatus::SCHEDULED,
                        'win_reason' => null,
                        'meta' => null,
                        'started_at' => null,
                        'finished_at' => null,
                    ];

                    if ((int) $match->participant_a_id === $participant->id) {
                        $updates['participant_a_id'] = null;
                    }

                    if ((int) $match->participant_b_id === $participant->id) {
                        $updates['participant_b_id'] = null;
                    }

                    $match->update($updates);
                }
            }

            $participant->delete();
        });

        $this->syncParticipantRosterStatus($tournament);

        event(new TournamentLiveUpdated(
            $tournament->fresh(),
            'participant_removed',
        ));

        return redirect()
            ->route('venues.tournaments.group_draw.show', [$venue, $tournament])
            ->with(
                'success',
                'Učesnik je uklonjen iz '.$groupPosition.'. Rezervisani mečevi tog slota ostaju skriveni do novog unosa.',
            );
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

    public function replaceParticipant(
        Request $request,
        Venue $venue,
        Tournament $tournament,
        TournamentParticipant $participant
    ): Response|RedirectResponse {
        $user = $request->user();

        abort_unless($user->canAccessVenue($venue), 403);
        abort_unless($tournament->venue_id === $venue->id, 404);
        abort_unless($participant->tournament_id === $tournament->id, 404);

        $replacementError = $this->participantReplacementValidationError(
            $tournament,
            $participant,
        );

        if ($replacementError !== null) {
            return redirect()
                ->route('venues.tournaments.group_draw.show', [$venue, $tournament])
                ->withErrors(['participant' => $replacementError]);
        }

        $participant->load(['player', 'team']);

        return Inertia::render('Venues/Tournaments/ReplaceGroupDrawParticipant', [
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
                'match_mode' => $tournament->match_mode->value,
                'match_mode_label' => $tournament->match_mode === MatchMode::SINGLES ? '1v1' : '2v2',
            ],
            'participant' => [
                'id' => $participant->id,
                'participant_type' => $participant->participant_type->value,
                'group_position' => $participant->group_position,
                'status' => $participant->status->value,
                'display_name' => $this->participantDisplayName($participant),
            ],
            'available_players' => $this->availablePlayers($tournament),
            'available_teams' => $this->availableTeams($venue, $tournament),
        ]);
    }

    public function updateParticipantReplacement(
        Request $request,
        Venue $venue,
        Tournament $tournament,
        TournamentParticipant $participant
    ): RedirectResponse {
        $user = $request->user();

        abort_unless($user->canAccessVenue($venue), 403);
        abort_unless($tournament->venue_id === $venue->id, 404);
        abort_unless($participant->tournament_id === $tournament->id, 404);

        $replacementError = $this->participantReplacementValidationError(
            $tournament,
            $participant,
        );

        if ($replacementError !== null) {
            return back()->withErrors([
                'participant' => $replacementError,
            ]);
        }

        if ($tournament->match_mode === MatchMode::SINGLES) {
            $validated = $request->validate([
                'existing_player_id' => ['nullable', 'integer'],
                'first_name' => ['required_without:existing_player_id', 'nullable', 'string', 'max:255'],
                'last_name' => ['required_without:existing_player_id', 'nullable', 'string', 'max:255'],
                'nickname' => ['nullable', 'string', 'max:255'],
            ]);

            $replacementPlayer = $this->resolveReplacementPlayer(
                $tournament,
                $participant,
                $validated,
            );

            DB::transaction(function () use (
                $tournament,
                $participant,
                $replacementPlayer
            ): void {
                $this->forgetRemovedPlayerParticipant(
                    $tournament,
                    $participant,
                    $replacementPlayer,
                );
                $this->restoreMatchesVoidedByParticipant($tournament, $participant);

                $participant->update([
                    'participant_type' => ParticipantType::PLAYER,
                    'player_id' => $replacementPlayer->id,
                    'team_id' => null,
                    'status' => ParticipantStatus::ACTIVE,
                    'withdrawn_at' => null,
                    'withdrawn_stage' => null,
                    'withdrawn_reason' => null,
                    'qualification_override_status' => null,
                    'repechage_outcome_status' => null,
                ]);
            });
        } else {
            $validated = $request->validate([
                'existing_team_id' => ['nullable', 'integer'],
                'team_name' => ['required_without:existing_team_id', 'nullable', 'string', 'max:255'],
            ]);

            $replacementTeam = $this->resolveReplacementTeam(
                $venue,
                $tournament,
                $participant,
                $validated,
            );

            DB::transaction(function () use (
                $tournament,
                $participant,
                $replacementTeam
            ): void {
                $this->forgetRemovedTeamParticipant(
                    $tournament,
                    $participant,
                    $replacementTeam,
                );
                $this->restoreMatchesVoidedByParticipant($tournament, $participant);

                $participant->update([
                    'participant_type' => ParticipantType::TEAM,
                    'player_id' => null,
                    'team_id' => $replacementTeam->id,
                    'status' => ParticipantStatus::ACTIVE,
                    'withdrawn_at' => null,
                    'withdrawn_stage' => null,
                    'withdrawn_reason' => null,
                    'qualification_override_status' => null,
                    'repechage_outcome_status' => null,
                ]);
            });
        }

        event(new TournamentLiveUpdated(
            $tournament->fresh(),
            'participant_replaced',
        ));

        return redirect()
            ->route('venues.tournaments.group_draw.show', [$venue, $tournament])
            ->with(
                'success',
                'Učesnik u slotu '.$participant->group_position.' je uspešno zamenjen.',
            );
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
                'existing_team_id' => ['nullable', 'integer'],
                'team_name' => ['required_without:existing_team_id', 'nullable', 'string', 'max:255'],
            ]);

            $participant->team->update([
                'name' => trim($validated['team_name']),
            ]);
        }

        return redirect()
            ->route('venues.tournaments.group_draw.show', [$venue, $tournament])
            ->with('success', 'Učesnik je izmenjen.');
    }

    private function storePlayerParticipant(Tournament $tournament, array $nextSlot, array $validated): void
    {
        DB::transaction(function () use ($tournament, $nextSlot, $validated): void {
            $existingPlayerId = $validated['existing_player_id'] ?? null;

            if ($existingPlayerId) {
                $player = Player::query()
                    ->where('is_active', true)
                    ->findOrFail($existingPlayerId);
            } else {
                $firstName = trim((string) ($validated['first_name'] ?? ''));
                $lastName = trim((string) ($validated['last_name'] ?? ''));
                $nickname = isset($validated['nickname']) && trim((string) $validated['nickname']) !== ''
                    ? trim((string) $validated['nickname'])
                    : null;

                // A matching name is not proof that this is the same human.
                // Reuse only happens when an admin explicitly selects an existing profile.
                $player = Player::create([
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
                    'existing_player_id' => 'Ovaj igrač je već dodat u turnir.',
                    'first_name' => 'Ovaj igrač je već dodat u turnir.',
                ]);
            }

            $slotParticipant = $tournament->participants()
                ->withTrashed()
                ->where('group_position', $nextSlot['group_position'])
                ->first();
            $removedPlayerParticipant = $tournament->participants()
                ->onlyTrashed()
                ->where('participant_type', ParticipantType::PLAYER->value)
                ->where('player_id', $player->id)
                ->first();

            if (
                $removedPlayerParticipant
                && (! $slotParticipant || $removedPlayerParticipant->id !== $slotParticipant->id)
            ) {
                $slotParticipant?->forceDelete();
                $slotParticipant = $removedPlayerParticipant;
            }

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
        DB::transaction(function () use ($venue, $tournament, $nextSlot, $validated): void {
            $existingTeamId = $validated['existing_team_id'] ?? null;

            if ($existingTeamId) {
                $team = Team::query()
                    ->where('venue_id', $venue->id)
                    ->where('is_active', true)
                    ->findOrFail($existingTeamId);
            } else {
                $teamName = trim((string) ($validated['team_name'] ?? ''));

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
            }

            if (
                $tournament->participants()
                    ->where('participant_type', ParticipantType::TEAM->value)
                    ->where('team_id', $team->id)
                    ->exists()
            ) {
                throw ValidationException::withMessages([
                    'existing_team_id' => 'Ova ekipa je već dodata u turnir.',
                    'team_name' => 'Ova ekipa je već dodata u turnir.',
                ]);
            }

            $slotParticipant = $tournament->participants()
                ->withTrashed()
                ->where('group_position', $nextSlot['group_position'])
                ->first();
            $removedTeamParticipant = $tournament->participants()
                ->onlyTrashed()
                ->where('participant_type', ParticipantType::TEAM->value)
                ->where('team_id', $team->id)
                ->first();

            if (
                $removedTeamParticipant
                && (! $slotParticipant || $removedTeamParticipant->id !== $slotParticipant->id)
            ) {
                $slotParticipant?->forceDelete();
                $slotParticipant = $removedTeamParticipant;
            }

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
                $groupPosition = $group->name.$slotNumber;

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
            $name = trim($participant->player->first_name.' '.$participant->player->last_name);

            if ($participant->player->nickname) {
                return $name.' ('.$participant->player->nickname.')';
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
            'group_position' => $groupName.$slotNumber,
        ];
    }

    private function availablePlayers(Tournament $tournament): array
    {
        $usedPlayerIds = $tournament->participants()
            ->where('participant_type', ParticipantType::PLAYER->value)
            ->whereNotNull('player_id')
            ->pluck('player_id');

        return Player::query()
            ->where('is_active', true)
            ->whereNotIn('id', $usedPlayerIds)
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->orderBy('nickname')
            ->get()
            ->map(fn (Player $player) => [
                'id' => $player->id,
                'first_name' => $player->first_name,
                'last_name' => $player->last_name,
                'nickname' => $player->nickname,
                'display_name' => trim($player->first_name.' '.$player->last_name)
                    .($player->nickname ? ' ('.$player->nickname.')' : ''),
            ])
            ->values()
            ->all();
    }

    private function availableTeams(Venue $venue, Tournament $tournament): array
    {
        $usedTeamIds = $tournament->participants()
            ->where('participant_type', ParticipantType::TEAM->value)
            ->whereNotNull('team_id')
            ->pluck('team_id');

        return Team::query()
            ->where('venue_id', $venue->id)
            ->where('is_active', true)
            ->whereNotIn('id', $usedTeamIds)
            ->orderBy('name')
            ->get()
            ->map(fn (Team $team) => [
                'id' => $team->id,
                'name' => $team->name,
            ])
            ->values()
            ->all();
    }

    private function canManageParticipantRoster(
        Tournament $tournament
    ): bool {
        return in_array(
            $tournament->status,
            [
                TournamentStatus::DRAFT,
                TournamentStatus::GROUP_DRAW,
                TournamentStatus::READY,
                TournamentStatus::GROUP_STAGE,
            ],
            true,
        );
    }

    private function canAddParticipant(
        Tournament $tournament
    ): bool {
        return in_array(
            $tournament->status,
            [
                TournamentStatus::DRAFT,
                TournamentStatus::GROUP_DRAW,
                TournamentStatus::READY,
                TournamentStatus::GROUP_STAGE,
            ],
            true,
        );
    }

    private function syncParticipantRosterStatus(
        Tournament $tournament
    ): void {
        if (! in_array($tournament->status, [
            TournamentStatus::DRAFT,
            TournamentStatus::GROUP_DRAW,
            TournamentStatus::READY,
        ], true)) {
            return;
        }

        $totalSlots = $this->totalSlots($tournament);

        if ($totalSlots < 1) {
            return;
        }

        $participantsCount = $tournament
            ->participants()
            ->count();

        if ($participantsCount === 0) {
            $nextStatus = TournamentStatus::DRAFT;
        } elseif ($participantsCount >= $totalSlots) {
            $nextStatus = TournamentStatus::READY;
        } else {
            $nextStatus = TournamentStatus::GROUP_DRAW;
        }

        if ($tournament->status === $nextStatus) {
            return;
        }

        $tournament->update([
            'status' => $nextStatus,
        ]);
    }

    private function participantReplacementValidationError(
        Tournament $tournament,
        TournamentParticipant $participant,
        ?Collection $matches = null
    ): ?string {
        if (! in_array($tournament->status, [
            TournamentStatus::DRAFT,
            TournamentStatus::GROUP_DRAW,
            TournamentStatus::READY,
            TournamentStatus::GROUP_STAGE,
        ], true)) {
            return 'Učesnik više ne može da se zameni u trenutnoj fazi turnira.';
        }

        if (! $participant->tournament_group_id || ! $participant->group_position) {
            return 'Učesnik nema validnu grupu ili slot.';
        }

        $matches ??= TournamentMatch::withoutGlobalScope('visible_matches')
            ->where('tournament_id', $tournament->id)
            ->where('stage', MatchStage::GROUP->value)
            ->get();

        $participantMatches = $matches->filter(function (TournamentMatch $match) use ($participant): bool {
            return (int) $match->participant_a_id === $participant->id
                || (int) $match->participant_b_id === $participant->id;
        });

        foreach ($participantMatches as $match) {
            if (in_array($match->status, [
                MatchStatus::IN_PROGRESS,
                MatchStatus::FINISHED,
            ], true)) {
                return 'Učesnik ne može da se zameni jer je već započeo ili završio grupni meč.';
            }

            if ($match->score_a !== null || $match->score_b !== null || $match->winner_participant_id !== null) {
                return 'Učesnik ne može da se zameni jer već ima evidentiran rezultat.';
            }

            $meta = $match->meta ?? [];

            if (
                $match->status === MatchStatus::VOIDED
                && (int) ($meta['voided_by_participant_id'] ?? 0) === $participant->id
                && in_array($meta['previous_status'] ?? null, [
                    MatchStatus::IN_PROGRESS->value,
                    MatchStatus::FINISHED->value,
                ], true)
            ) {
                return 'Učesnik ne može da se zameni jer je pre odustajanja već igrao grupni meč.';
            }
        }

        return null;
    }

    private function canManageWithdrawals($user, Venue $venue): bool
    {
        if ($user->global_role?->value === 'superadmin') {
            return true;
        }

        return $user->venueUsers()
            ->where('venue_id', $venue->id)
            ->where('role', 'admin')
            ->where('is_active', true)
            ->exists();
    }

    private function resolveReplacementPlayer(
        Tournament $tournament,
        TournamentParticipant $participant,
        array $validated
    ): Player {
        $existingPlayerId = $validated['existing_player_id'] ?? null;

        if ($existingPlayerId) {
            $player = Player::query()
                ->where('is_active', true)
                ->findOrFail($existingPlayerId);
        } else {
            $firstName = trim((string) ($validated['first_name'] ?? ''));
            $lastName = trim((string) ($validated['last_name'] ?? ''));
            $nickname = isset($validated['nickname']) && trim((string) $validated['nickname']) !== ''
                ? trim((string) $validated['nickname'])
                : null;

            $player = Player::create([
                'first_name' => $firstName,
                'last_name' => $lastName,
                'nickname' => $nickname,
                'is_active' => true,
            ]);
        }

        $alreadyUsed = $tournament->participants()
            ->where('id', '!=', $participant->id)
            ->where('participant_type', ParticipantType::PLAYER->value)
            ->where('player_id', $player->id)
            ->exists();

        if ($alreadyUsed) {
            throw ValidationException::withMessages([
                'existing_player_id' => 'Ovaj igrač je već dodat u turnir.',
                'first_name' => 'Ovaj igrač je već dodat u turnir.',
            ]);
        }

        return $player;
    }

    private function resolveReplacementTeam(
        Venue $venue,
        Tournament $tournament,
        TournamentParticipant $participant,
        array $validated
    ): Team {
        $existingTeamId = $validated['existing_team_id'] ?? null;

        if ($existingTeamId) {
            $team = Team::query()
                ->where('venue_id', $venue->id)
                ->where('is_active', true)
                ->findOrFail($existingTeamId);
        } else {
            $teamName = trim((string) ($validated['team_name'] ?? ''));

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
        }

        $alreadyUsed = $tournament->participants()
            ->where('id', '!=', $participant->id)
            ->where('participant_type', ParticipantType::TEAM->value)
            ->where('team_id', $team->id)
            ->exists();

        if ($alreadyUsed) {
            throw ValidationException::withMessages([
                'existing_team_id' => 'Ova ekipa je već dodata u turnir.',
                'team_name' => 'Ova ekipa je već dodata u turnir.',
            ]);
        }

        return $team;
    }

    private function forgetRemovedPlayerParticipant(
        Tournament $tournament,
        TournamentParticipant $currentParticipant,
        Player $replacementPlayer,
    ): void {
        $tournament->participants()
            ->onlyTrashed()
            ->where('id', '!=', $currentParticipant->id)
            ->where('participant_type', ParticipantType::PLAYER->value)
            ->where('player_id', $replacementPlayer->id)
            ->get()
            ->each(fn (TournamentParticipant $participant) => $participant->forceDelete());
    }

    private function forgetRemovedTeamParticipant(
        Tournament $tournament,
        TournamentParticipant $currentParticipant,
        Team $replacementTeam,
    ): void {
        $tournament->participants()
            ->onlyTrashed()
            ->where('id', '!=', $currentParticipant->id)
            ->where('participant_type', ParticipantType::TEAM->value)
            ->where('team_id', $replacementTeam->id)
            ->get()
            ->each(fn (TournamentParticipant $participant) => $participant->forceDelete());
    }

    private function restoreMatchesVoidedByParticipant(
        Tournament $tournament,
        TournamentParticipant $participant
    ): void {
        $matches = TournamentMatch::withoutGlobalScope('visible_matches')
            ->where('tournament_id', $tournament->id)
            ->where('stage', MatchStage::GROUP->value)
            ->where(function ($query) use ($participant): void {
                $query
                    ->where('participant_a_id', $participant->id)
                    ->orWhere('participant_b_id', $participant->id);
            })
            ->get();

        foreach ($matches as $match) {
            if ($match->status !== MatchStatus::VOIDED) {
                continue;
            }

            $meta = $match->meta ?? [];

            if (
                ($meta['voided_reason'] ?? null) !== 'participant_withdrawn'
                || (int) ($meta['voided_by_participant_id'] ?? 0) !== $participant->id
            ) {
                continue;
            }

            $previousStatus = MatchStatus::tryFrom(
                (string) ($meta['previous_status'] ?? ''),
            ) ?? MatchStatus::SCHEDULED;

            $previousFinishedAt = $meta['previous_finished_at'] ?? null;

            unset(
                $meta['previous_status'],
                $meta['previous_finished_at'],
                $meta['voided_reason'],
                $meta['voided_by_participant_id'],
                $meta['voided_at'],
            );

            $match->update([
                'status' => $previousStatus,
                'finished_at' => $previousStatus === MatchStatus::FINISHED
                    ? $previousFinishedAt
                    : null,
                'meta' => $meta ?: null,
            ]);
        }
    }
}
