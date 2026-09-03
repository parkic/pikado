<?php

namespace App\Http\Controllers;

use App\Enums\MatchStage;
use App\Enums\MatchStatus;
use App\Enums\ParticipantStatus;
use App\Enums\QualificationStatus;
use App\Enums\TournamentStatus;
use App\Enums\WinReason;
use App\Enums\WithdrawalPolicy;
use App\Events\TournamentLiveUpdated;
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
        GroupStandingsCalculator $calculator,
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
                'repechage_enabled' => (bool) data_get($tournament->settings ?? [], 'repechage_enabled', false),
            ],
            'groups' => $calculator->calculate($tournament),
        ]);
    }

    public function updateQualificationOverride(
        Request $request,
        Venue $venue,
        Tournament $tournament,
        TournamentParticipant $participant,
    ): RedirectResponse {
        $user = $request->user();

        abort_unless($user->canAccessVenue($venue), 403);
        abort_unless($tournament->venue_id === $venue->id, 404);
        abort_unless($participant->tournament_id === $tournament->id, 404);

        $knockoutAlreadyGenerated = TournamentMatch::withoutGlobalScope('visible_matches')
            ->where('tournament_id', $tournament->id)
            ->whereIn('stage', [
                MatchStage::KNOCKOUT->value,
                MatchStage::THIRD_PLACE->value,
                MatchStage::FINAL->value,
            ])
            ->exists();

        if (
            ! in_array($tournament->status, [
                TournamentStatus::GROUP_STAGE,
                TournamentStatus::KNOCKOUT_DRAW,
            ], true)
            || $knockoutAlreadyGenerated
        ) {
            return back()->withErrors([
                'qualification_override_status' => 'Status prolaza više ne može da se menja nakon generisanja nokaut kostura.',
            ]);
        }

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

        event(new TournamentLiveUpdated($tournament->fresh(), 'qualification_override_updated'));

        return back()->with('success', 'Status prolaza je promenjen.');
    }

    public function withdrawParticipant(
        Request $request,
        Venue $venue,
        Tournament $tournament,
        TournamentParticipant $participant,
    ): RedirectResponse {
        $user = $request->user();

        abort_unless($user->canAccessVenue($venue), 403);
        abort_unless($this->canManageWithdrawals($user, $venue), 403);
        abort_unless($tournament->venue_id === $venue->id, 404);
        abort_unless($participant->tournament_id === $tournament->id, 404);

        if ($tournament->status !== TournamentStatus::GROUP_STAGE) {
            return back()->withErrors([
                'participant' => 'U ovom koraku odustajanje je dostupno samo tokom grupne faze.',
            ]);
        }

        if ($participant->status === ParticipantStatus::WITHDRAWN) {
            return back()->with('success', 'Učesnik je već označen kao odustao.');
        }

        $validated = $request->validate([
            'withdrawal_policy' => [
                'nullable',
                Rule::in(array_column(WithdrawalPolicy::cases(), 'value')),
            ],
        ]);
        $withdrawalPolicy = WithdrawalPolicy::from(
            $validated['withdrawal_policy'] ?? WithdrawalPolicy::VOID_ALL->value,
        );

        DB::transaction(function () use ($tournament, $participant, $withdrawalPolicy): void {
            $participant->update([
                'status' => ParticipantStatus::WITHDRAWN,
                'withdrawn_at' => now(),
                'withdrawn_stage' => $tournament->status->value,
                'withdrawn_reason' => 'Odustao tokom grupne faze.',
                'withdrawal_policy' => $withdrawalPolicy,
                'qualification_override_status' => null,
                'repechage_outcome_status' => null,
            ]);

            $matches = TournamentMatch::withoutGlobalScope('visible_matches')
                ->where('tournament_id', $tournament->id)
                ->where('stage', MatchStage::GROUP->value)
                ->where(function ($query) use ($participant): void {
                    $query
                        ->where('participant_a_id', $participant->id)
                        ->orWhere('participant_b_id', $participant->id);
                })
                ->where('status', '!=', MatchStatus::CANCELLED->value)
                ->get();

            foreach ($matches as $match) {
                /** @var TournamentMatch $match */
                if (
                    $match->status === MatchStatus::FINISHED
                    && $withdrawalPolicy !== WithdrawalPolicy::VOID_ALL
                ) {
                    continue;
                }

                $meta = $match->meta ?? [];
                $meta['withdrawal_previous_state'] = [
                    'status' => $match->status->value,
                    'score_a' => $match->score_a,
                    'score_b' => $match->score_b,
                    'winner_participant_id' => $match->winner_participant_id,
                    'loser_participant_id' => $match->loser_participant_id,
                    'win_reason' => $match->win_reason?->value,
                    'finished_at' => $match->finished_at?->toDateTimeString(),
                    'is_hidden' => $match->is_hidden,
                ];
                $meta['withdrawal_by_participant_id'] = $participant->id;
                $meta['withdrawal_policy'] = $withdrawalPolicy->value;
                $meta['withdrawal_applied_at'] = now()->toIso8601String();

                if ($withdrawalPolicy === WithdrawalPolicy::VOID_ALL) {
                    $meta['voided_reason'] = 'participant_withdrawn';
                    $meta['voided_by_participant_id'] = $participant->id;
                    $meta['voided_at'] = now()->toDateTimeString();
                    $match->update([
                        'status' => MatchStatus::VOIDED,
                        'meta' => $meta,
                    ]);

                    continue;
                }

                if ($withdrawalPolicy === WithdrawalPolicy::KEEP_PLAYED_MANUAL_REST) {
                    $meta['postponed_reason'] = 'withdrawal_manual_result';
                    $match->update([
                        'status' => MatchStatus::POSTPONED,
                        'meta' => $meta,
                    ]);

                    continue;
                }

                if (! $match->participant_a_id || ! $match->participant_b_id) {
                    continue;
                }

                $participantAStats = $this->scoreStatsForParticipant(
                    $tournament,
                    (int) $match->participant_a_id,
                );
                $participantBStats = $this->scoreStatsForParticipant(
                    $tournament,
                    (int) $match->participant_b_id,
                );
                $scoreA = $participantAStats['average'];
                $scoreB = $participantBStats['average'];

                if ($scoreA === $scoreB) {
                    $winnerParticipantId = (int) $match->participant_a_id === $participant->id
                        ? (int) $match->participant_b_id
                        : (int) $match->participant_a_id;
                } else {
                    $winnerParticipantId = $scoreA > $scoreB
                        ? (int) $match->participant_a_id
                        : (int) $match->participant_b_id;
                }

                $loserParticipantId = $winnerParticipantId === (int) $match->participant_a_id
                    ? (int) $match->participant_b_id
                    : (int) $match->participant_a_id;
                $meta['result_source'] = 'withdrawal_average';
                $meta['score_suggestions'] = [
                    'participant_a' => $participantAStats,
                    'participant_b' => $participantBStats,
                ];

                $match->update([
                    'score_a' => $scoreA,
                    'score_b' => $scoreB,
                    'winner_participant_id' => $winnerParticipantId,
                    'loser_participant_id' => $loserParticipantId,
                    'status' => MatchStatus::FINISHED,
                    'win_reason' => WinReason::MANUAL_OVERRIDE,
                    'finished_at' => now(),
                    'meta' => $meta,
                ]);
            }
        });

        event(new TournamentLiveUpdated($tournament->fresh(), 'participant_withdrawn'));

        return back()->with('success', match ($withdrawalPolicy) {
            WithdrawalPolicy::VOID_ALL => 'Učesnik je odustao, a svi njegovi grupni mečevi su anulirani.',
            WithdrawalPolicy::KEEP_PLAYED_AVERAGE_REST => 'Odigrani mečevi su zadržani, a preostali obračunati prema proseku.',
            WithdrawalPolicy::KEEP_PLAYED_MANUAL_REST => 'Odigrani mečevi su zadržani, a preostali čekaju ručni unos administratora.',
        });
    }

    public function restoreParticipant(
        Request $request,
        Venue $venue,
        Tournament $tournament,
        TournamentParticipant $participant,
    ): RedirectResponse {
        $user = $request->user();

        abort_unless($user->canAccessVenue($venue), 403);
        abort_unless($this->canManageWithdrawals($user, $venue), 403);
        abort_unless($tournament->venue_id === $venue->id, 404);
        abort_unless($participant->tournament_id === $tournament->id, 404);

        if ($tournament->status !== TournamentStatus::GROUP_STAGE) {
            return back()->withErrors([
                'participant' => 'Vraćanje učesnika je dostupno samo tokom grupne faze.',
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
                'withdrawal_policy' => null,
            ]);

            $matches = TournamentMatch::withoutGlobalScope('visible_matches')
                ->where('tournament_id', $tournament->id)
                ->where('stage', MatchStage::GROUP->value)
                ->where(function ($query) use ($participant): void {
                    $query
                        ->where('meta->withdrawal_by_participant_id', $participant->id)
                        ->orWhere(function ($legacyQuery) use ($participant): void {
                            $legacyQuery
                                ->where('meta->voided_reason', 'participant_withdrawn')
                                ->where('meta->voided_by_participant_id', $participant->id);
                        });
                })
                ->get();

            foreach ($matches as $match) {
                /** @var TournamentMatch $match */
                $meta = $match->meta ?? [];
                $previousState = $meta['withdrawal_previous_state'] ?? null;

                if (is_array($previousState)) {
                    $previousStatus = MatchStatus::tryFrom(
                        (string) ($previousState['status'] ?? ''),
                    ) ?? MatchStatus::SCHEDULED;
                    $match->update([
                        'status' => $previousStatus,
                        'score_a' => $previousState['score_a'] ?? null,
                        'score_b' => $previousState['score_b'] ?? null,
                        'winner_participant_id' => $previousState['winner_participant_id'] ?? null,
                        'loser_participant_id' => $previousState['loser_participant_id'] ?? null,
                        'win_reason' => $previousState['win_reason'] ?? null,
                        'finished_at' => $previousState['finished_at'] ?? null,
                        'is_hidden' => (bool) ($previousState['is_hidden'] ?? false),
                    ]);
                } else {
                    $previousStatus = MatchStatus::tryFrom((string) ($meta['previous_status'] ?? ''));

                    if (! $previousStatus) {
                        $previousStatus = $match->score_a !== null
                            && $match->score_b !== null
                            && $match->winner_participant_id
                                ? MatchStatus::FINISHED
                                : MatchStatus::SCHEDULED;
                    }

                    $match->update([
                        'status' => $previousStatus,
                        'finished_at' => $previousStatus === MatchStatus::FINISHED
                            ? ($meta['previous_finished_at'] ?? null)
                            : null,
                    ]);
                }

                unset(
                    $meta['withdrawal_previous_state'],
                    $meta['withdrawal_by_participant_id'],
                    $meta['withdrawal_policy'],
                    $meta['withdrawal_applied_at'],
                    $meta['postponed_reason'],
                    $meta['result_source'],
                    $meta['score_suggestions'],
                    $meta['previous_status'],
                    $meta['previous_finished_at'],
                    $meta['voided_reason'],
                    $meta['voided_by_participant_id'],
                    $meta['voided_at'],
                );

                $match->update(['meta' => $meta ?: null]);
            }
        });

        event(new TournamentLiveUpdated($tournament->fresh(), 'participant_restored'));

        return back()->with('success', 'Učesnik je vraćen u aktivne, a njegovi grupni mečevi su vraćeni.');
    }

    /** @return array{average: int, median: int, matches_count: int} */
    private function scoreStatsForParticipant(Tournament $tournament, int $participantId): array
    {
        $scores = TournamentMatch::withoutGlobalScope('visible_matches')
            ->where('tournament_id', $tournament->id)
            ->where('stage', MatchStage::GROUP->value)
            ->where('status', MatchStatus::FINISHED->value)
            ->where(function ($query) use ($participantId): void {
                $query
                    ->where('participant_a_id', $participantId)
                    ->orWhere('participant_b_id', $participantId);
            })
            ->get()
            ->reject(fn (TournamentMatch $match) => data_get($match->meta, 'result_source') === 'withdrawal_average')
            ->map(fn (TournamentMatch $match): int => (int) $match->participant_a_id === $participantId
                    ? (int) $match->score_a
                    : (int) $match->score_b
            )
            ->sort()
            ->values();

        if ($scores->isEmpty()) {
            return ['average' => 0, 'median' => 0, 'matches_count' => 0];
        }

        $middle = intdiv($scores->count(), 2);
        $median = $scores->count() % 2 === 0
            ? (int) round(($scores[$middle - 1] + $scores[$middle]) / 2)
            : (int) $scores[$middle];

        return [
            'average' => (int) round($scores->average()),
            'median' => $median,
            'matches_count' => $scores->count(),
        ];
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
