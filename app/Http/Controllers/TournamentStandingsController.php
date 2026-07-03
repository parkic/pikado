<?php

namespace App\Http\Controllers;

use App\Enums\MatchStage;
use App\Enums\MatchStatus;
use App\Enums\ParticipantStatus;
use App\Enums\QualificationStatus;
use App\Enums\TournamentStatus;
use App\Models\Tournament;
use App\Models\TournamentMatch;
use App\Models\TournamentParticipant;
use App\Models\Venue;
use App\Services\GroupStandingsCalculator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

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
                'can_manage_withdrawals' => $this->canManageWithdrawals($user, $venue),
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

    public function withdrawParticipant(
        Request $request,
        Venue $venue,
        Tournament $tournament,
        TournamentParticipant $participant
    ): RedirectResponse {
        $user = $request->user();

        abort_unless($user->canAccessVenue($venue), 403);
        abort_unless($this->canManageWithdrawals($user, $venue), 403);
        abort_unless($tournament->venue_id === $venue->id, 404);
        abort_unless($participant->tournament_id === $tournament->id, 404);

        if ($tournament->status !== TournamentStatus::GROUP_STAGE) {
            return back()->withErrors([
                'participant' => 'U ovom koraku odustajanje podržavamo samo tokom grupne faze.',
            ]);
        }

        if ($participant->status === ParticipantStatus::WITHDRAWN) {
            return back()->with('success', 'Učesnik je već označen kao odustao.');
        }

        DB::transaction(function () use ($tournament, $participant): void {
            $participant->update([
                'status' => ParticipantStatus::WITHDRAWN,
                'withdrawn_at' => now(),
                'withdrawn_stage' => $tournament->status->value,
                'withdrawn_reason' => 'Odustao tokom grupne faze.',
                'qualification_override_status' => null,
                'repechage_outcome_status' => null,
            ]);

            $matches = $tournament->matches()
                ->where('stage', MatchStage::GROUP->value)
                ->where(function ($query) use ($participant) {
                    $query
                        ->where('participant_a_id', $participant->id)
                        ->orWhere('participant_b_id', $participant->id);
                })
                ->where('status', '!=', MatchStatus::CANCELLED->value)
                ->get();

            foreach ($matches as $match) {
                /** @var TournamentMatch $match */
                $meta = $match->meta ?? [];

                if (($meta['voided_reason'] ?? null) !== 'participant_withdrawn') {
                    $meta['previous_status'] = $match->status->value;
                    $meta['previous_finished_at'] = $match->finished_at?->toDateTimeString();
                }

                $meta['voided_reason'] = 'participant_withdrawn';
                $meta['voided_by_participant_id'] = $participant->id;
                $meta['voided_at'] = now()->toDateTimeString();

                $match->update([
                    'status' => MatchStatus::VOIDED,
                    'meta' => $meta,
                ]);
            }
        });

        return back()->with('success', 'Učesnik je označen kao odustao, a njegovi grupni mečevi su anulirani.');
    }

    public function restoreParticipant(
        Request $request,
        Venue $venue,
        Tournament $tournament,
        TournamentParticipant $participant
    ): RedirectResponse {
        $user = $request->user();

        abort_unless($user->canAccessVenue($venue), 403);
        abort_unless($this->canManageWithdrawals($user, $venue), 403);
        abort_unless($tournament->venue_id === $venue->id, 404);
        abort_unless($participant->tournament_id === $tournament->id, 404);

        if ($tournament->status !== TournamentStatus::GROUP_STAGE) {
            return back()->withErrors([
                'participant' => 'Vraćanje učesnika za sada podržavamo samo tokom grupne faze.',
            ]);
        }

        if ($participant->status !== ParticipantStatus::WITHDRAWN) {
            return back()->with('success', 'Učesnik je već aktivan.');
        }

        DB::transaction(function () use ($tournament, $participant): void {
            $participant->update([
                'status' => ParticipantStatus::ACTIVE,
                'withdrawn_at' => null,
                'withdrawn_stage' => null,
                'withdrawn_reason' => null,
            ]);

            $matches = $tournament->matches()
                ->where('stage', MatchStage::GROUP->value)
                ->where('status', MatchStatus::VOIDED->value)
                ->where('meta->voided_reason', 'participant_withdrawn')
                ->where('meta->voided_by_participant_id', $participant->id)
                ->where(function ($query) use ($participant) {
                    $query
                        ->where('participant_a_id', $participant->id)
                        ->orWhere('participant_b_id', $participant->id);
                })
                ->get();

            foreach ($matches as $match) {
                /** @var TournamentMatch $match */
                $meta = $match->meta ?? [];

                $previousStatus = MatchStatus::tryFrom((string) ($meta['previous_status'] ?? ''));

                if (! $previousStatus) {
                    $previousStatus = $match->score_a !== null
                        && $match->score_b !== null
                        && $match->winner_participant_id
                            ? MatchStatus::FINISHED
                            : MatchStatus::SCHEDULED;
                }

                $previousFinishedAt = $meta['previous_finished_at'] ?? null;

                unset(
                    $meta['previous_status'],
                    $meta['previous_finished_at'],
                    $meta['voided_reason'],
                    $meta['voided_by_participant_id'],
                    $meta['voided_at']
                );

                $match->update([
                    'status' => $previousStatus,
                    'finished_at' => $previousStatus === MatchStatus::FINISHED
                        ? $previousFinishedAt
                        : null,
                    'meta' => $meta,
                ]);
            }
        });

        return back()->with('success', 'Učesnik je vraćen u aktivne, a njegovi grupni mečevi su vraćeni.');
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
}
