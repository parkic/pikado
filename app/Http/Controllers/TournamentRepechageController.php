<?php

namespace App\Http\Controllers;

use App\Models\Tournament;
use App\Models\Venue;
use App\Models\TournamentParticipant;
use App\Services\GroupStandingsCalculator;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use App\Enums\RepechageOutcomeStatus;
use App\Enums\TournamentStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\Rule;

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

        $advancedFromRepechageCount = collect($repechageParticipants)
            ->where('repechage_outcome_status', RepechageOutcomeStatus::ADVANCED->value)
            ->count();

        $eliminatedFromRepechageCount = collect($repechageParticipants)
            ->where('repechage_outcome_status', RepechageOutcomeStatus::ELIMINATED->value)
            ->count();

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
                'repechage_advanced_count' => $advancedFromRepechageCount,
                'repechage_eliminated_count' => $eliminatedFromRepechageCount,
                'can_complete_repechage' => $this->canCompleteRepechage(
                    $tournament,
                    $repechageParticipants,
                ),
            ],
            'direct_qualifiers' => $directQualifiers,
            'repechage_participants' => $repechageParticipants,
            'eliminated_participants' => $eliminatedParticipants,
        ]);
    }

    public function updateOutcome(
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
            'repechage_outcome_status' => [
                'nullable',
                Rule::in([
                    RepechageOutcomeStatus::ADVANCED->value,
                    RepechageOutcomeStatus::ELIMINATED->value,
                ]),
            ],
        ]);

        $groups = collect(app(GroupStandingsCalculator::class)->calculate($tournament));

        $repechageParticipantIds = collect($this->participantsByStatus($groups, 'repechage'))
            ->pluck('participant_id')
            ->map(fn ($participantId) => (int) $participantId);

        if (! $repechageParticipantIds->contains($participant->id)) {
            return back()->withErrors([
                'repechage_outcome_status' => 'Ovaj učesnik trenutno nije u repasažu.',
            ]);
        }

        $newStatus = $validated['repechage_outcome_status'] ?: null;

        if ($newStatus === RepechageOutcomeStatus::ADVANCED->value) {
            $qualifiersLimit = (int) data_get(
                $tournament->settings ?? [],
                'repechage_qualifiers_count',
                0,
            );

            if ($qualifiersLimit < 1) {
                return back()->withErrors([
                    'repechage_outcome_status' => 'Prvo podesi koliko učesnika prolazi iz repasaža.',
                ]);
            }

            $alreadyAdvancedCount = $tournament->participants()
                ->where('id', '!=', $participant->id)
                ->where('repechage_outcome_status', RepechageOutcomeStatus::ADVANCED->value)
                ->count();

            if ($alreadyAdvancedCount >= $qualifiersLimit) {
                return back()->withErrors([
                    'repechage_outcome_status' => 'Već je označen maksimalan broj učesnika koji prolaze iz repasaža.',
                ]);
            }
        }

        $participant->update([
            'repechage_outcome_status' => $newStatus,
        ]);

        return back()->with('success', 'Ishod repasaža je sačuvan.');
    }

    public function complete(
        Request $request,
        Venue $venue,
        Tournament $tournament
    ): RedirectResponse {
        $user = $request->user();

        abort_unless($user->canAccessVenue($venue), 403);
        abort_unless($tournament->venue_id === $venue->id, 404);

        $groups = collect(app(GroupStandingsCalculator::class)->calculate($tournament));
        $repechageParticipants = $this->participantsByStatus($groups, 'repechage');

        if (! $this->canCompleteRepechage($tournament, $repechageParticipants)) {
            return back()->withErrors([
                'repechage' => 'Repasaž ne može biti završen dok nije označen tačan broj učesnika koji prolaze dalje.',
            ]);
        }

        $repechageParticipantIds = collect($repechageParticipants)
            ->pluck('participant_id')
            ->map(fn ($participantId) => (int) $participantId)
            ->values();

        TournamentParticipant::query()
            ->where('tournament_id', $tournament->id)
            ->whereIn('id', $repechageParticipantIds)
            ->whereNull('repechage_outcome_status')
            ->update([
                'repechage_outcome_status' => RepechageOutcomeStatus::ELIMINATED->value,
            ]);

        $tournament->update([
            'status' => TournamentStatus::KNOCKOUT_DRAW,
        ]);

        return redirect()
            ->route('venues.tournaments.show', [$venue, $tournament])
            ->with('success', 'Repasaž je završen. Turnir je spreman za nokaut žreb.');
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
                        'repechage_outcome_status' => $row['repechage_outcome_status'] ?? null,
                        'repechage_outcome_label' => $row['repechage_outcome_label'] ?? 'Neodlučeno',
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

    private function canCompleteRepechage(Tournament $tournament, array $repechageParticipants): bool
    {
        if ($tournament->status !== TournamentStatus::REPECHAGE) {
            return false;
        }

        $qualifiersLimit = (int) data_get(
            $tournament->settings ?? [],
            'repechage_qualifiers_count',
            0,
        );

        if ($qualifiersLimit < 1) {
            return false;
        }

        $advancedCount = collect($repechageParticipants)
            ->where('repechage_outcome_status', RepechageOutcomeStatus::ADVANCED->value)
            ->count();

        return $advancedCount === $qualifiersLimit;
    }
}
