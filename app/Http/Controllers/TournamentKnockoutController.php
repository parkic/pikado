<?php

namespace App\Http\Controllers;

use App\Enums\RepechageOutcomeStatus;
use App\Models\Tournament;
use App\Models\Venue;
use App\Services\GroupStandingsCalculator;
use App\Services\KnockoutBracketGenerator;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;
use App\Enums\MatchStage;
use App\Enums\TournamentStatus;

class TournamentKnockoutController extends Controller
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

        $directQualifiers = $this->participantsByStatus($groups, 'direct')
            ->map(fn (array $participant) => array_merge($participant, [
                'source' => 'direct',
                'source_label' => 'Direktan prolaz',
            ]));

        $repechageQualifiers = $this->participantsByStatus($groups, 'repechage')
            ->filter(fn (array $participant) => $participant['repechage_outcome_status'] === RepechageOutcomeStatus::ADVANCED->value)
            ->map(fn (array $participant) => array_merge($participant, [
                'source' => 'repechage',
                'source_label' => 'Prošao iz repasaža',
            ]));

        $knockoutParticipants = $directQualifiers
            ->concat($repechageQualifiers)
            ->values()
            ->map(function (array $participant, int $index) {
                $participant['seed'] = $index + 1;

                return $participant;
            });

        $knockoutSize = $tournament->knockout_size ?? 0;

        return Inertia::render('Venues/Tournaments/Knockout/Index', [
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
                'knockout_size' => $knockoutSize,
                'knockout_participants_count' => $knockoutParticipants->count(),
                'direct_qualifiers_count' => $directQualifiers->count(),
                'repechage_qualifiers_count' => $repechageQualifiers->count(),
                'is_knockout_ready' => $knockoutSize > 0 && $knockoutParticipants->count() === $knockoutSize,
                'knockout_matches_count' => $tournament->matches()
                    ->whereIn('stage', [
                        MatchStage::KNOCKOUT->value,
                        MatchStage::FINAL->value,
                    ])
                    ->count(),
                'can_generate_knockout_bracket' => $this->canGenerateKnockoutBracket(
                    $tournament,
                    $knockoutParticipants,
                ),
            ],
            'direct_qualifiers' => $directQualifiers->values(),
            'repechage_qualifiers' => $repechageQualifiers->values(),
            'knockout_participants' => $knockoutParticipants->values(),
        ]);
    }

    public function generate(
        Request $request,
        Venue $venue,
        Tournament $tournament,
        KnockoutBracketGenerator $generator
    ): RedirectResponse {
        $user = $request->user();

        abort_unless($user->canAccessVenue($venue), 403);
        abort_unless($tournament->venue_id === $venue->id, 404);

        $groups = collect(app(GroupStandingsCalculator::class)->calculate($tournament));

        $directQualifiers = $this->participantsByStatus($groups, 'direct')
            ->map(fn (array $participant) => array_merge($participant, [
                'source' => 'direct',
                'source_label' => 'Direktan prolaz',
            ]));

        $repechageQualifiers = $this->participantsByStatus($groups, 'repechage')
            ->filter(fn (array $participant) => $participant['repechage_outcome_status'] === RepechageOutcomeStatus::ADVANCED->value)
            ->map(fn (array $participant) => array_merge($participant, [
                'source' => 'repechage',
                'source_label' => 'Prošao iz repasaža',
            ]));

        $knockoutParticipants = $directQualifiers
            ->concat($repechageQualifiers)
            ->values();

        if (! $this->canGenerateKnockoutBracket($tournament, $knockoutParticipants)) {
            return back()->withErrors([
                'knockout' => 'Nokaut kostur ne može da se generiše. Proveri status turnira, broj učesnika i da li kostur već postoji.',
            ]);
        }

        $createdMatches = $generator->generate($tournament);

        $tournament->update([
            'status' => TournamentStatus::KNOCKOUT_STAGE,
        ]);

        return redirect()
            ->route('venues.tournaments.schedule.index', [$venue, $tournament])
            ->with('success', 'Generisano nokaut mečeva: ' . $createdMatches . '.');
    }

    private function participantsByStatus(Collection $groups, string $status): Collection
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
            ->values();
    }

    private function canGenerateKnockoutBracket(Tournament $tournament, Collection $knockoutParticipants): bool
    {
        if ($tournament->status !== TournamentStatus::KNOCKOUT_DRAW) {
            return false;
        }

        $knockoutSize = (int) $tournament->knockout_size;

        if ($knockoutSize < 2) {
            return false;
        }

        if ($knockoutParticipants->count() !== $knockoutSize) {
            return false;
        }

        return ! $tournament->matches()
            ->whereIn('stage', [
                MatchStage::KNOCKOUT->value,
                MatchStage::FINAL->value,
            ])
            ->exists();
    }
}
