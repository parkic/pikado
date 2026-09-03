<?php

namespace App\Http\Controllers;

use App\Enums\MatchStage;
use App\Enums\MatchStatus;
use App\Enums\ParticipantStatus;
use App\Enums\TournamentStatus;
use App\Enums\WinReason;
use App\Events\TournamentLiveUpdated;
use App\Models\Tournament;
use App\Models\TournamentMatch;
use App\Models\TournamentParticipant;
use App\Models\TournamentResource;
use App\Models\Venue;
use App\Services\GroupStandingsCalculator;
use App\Services\TournamentLiveMatchSelector;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class TournamentScheduleController extends Controller
{
    public function index(
        Request $request,
        Venue $venue,
        Tournament $tournament,
        GroupStandingsCalculator $calculator,
        TournamentLiveMatchSelector $liveMatchSelector,
    ): Response {
        $user = $request->user();

        abort_unless($user->canAccessVenue($venue), 403);
        abort_unless($tournament->venue_id === $venue->id, 404);

        $qualificationPositions = collect($calculator->calculate($tournament))
            ->flatMap(fn (array $group) => collect($group['rows']))
            ->filter(fn (array $row) => $row['qualification_position'] !== null)
            ->mapWithKeys(fn (array $row) => [
                $row['participant_id'] => $row['qualification_position'],
            ]);
        $scoreSuggestions = $this->groupScoreSuggestions($tournament);

        $matchModels = $tournament->matches()
            ->with([
                'group',
                'resource',
                'participantA.player',
                'participantA.team',
                'participantB.player',
                'participantB.team',
                'winner.player',
                'winner.team',
            ])
            ->orderBy('scheduled_order')
            ->orderBy('id')
            ->get();

        $liveMatches = $liveMatchSelector->select($matchModels);
        $currentLiveOrder = $liveMatches['current']
            ->values()
            ->mapWithKeys(fn (TournamentMatch $match, int $index) => [$match->id => $index + 1]);
        $nextLiveOrder = $liveMatches['next']
            ->values()
            ->mapWithKeys(fn (TournamentMatch $match, int $index) => [$match->id => $index + 1]);

        $matches = $matchModels->map(fn (TournamentMatch $match) => [
            'id' => $match->id,
            'stage' => $match->stage->value,
            'stage_label' => $this->stageLabel($match->stage),
            'group_name' => $match->group?->name,
            'scheduled_order' => $match->scheduled_order,
            'round_robin_leg' => $match->round_robin_leg,
            'wins_required' => $match->wins_required,
            'bracket_round' => $match->bracket_round,
            'bracket_round_label' => $this->bracketRoundLabel($match->bracket_round),
            'bracket_position' => $match->bracket_position,
            'participant_a' => $match->participantA ? [
                'id' => $match->participantA->id,
                'group_position' => $match->participantA->group_position,
                'qualification_position' => $qualificationPositions->get($match->participantA->id),
                'display_name' => $this->participantDisplayName($match->participantA),
                'status' => $match->participantA->status->value,
                'is_withdrawn' => $match->participantA->status->value === 'withdrawn',
                'withdrawal_policy' => $match->participantA->withdrawal_policy?->value,
                'score_suggestion' => $scoreSuggestions->get($match->participantA->id),
            ] : null,
            'participant_b' => $match->participantB ? [
                'id' => $match->participantB->id,
                'group_position' => $match->participantB->group_position,
                'qualification_position' => $qualificationPositions->get($match->participantB->id),
                'display_name' => $this->participantDisplayName($match->participantB),
                'status' => $match->participantB->status->value,
                'is_withdrawn' => $match->participantB->status->value === 'withdrawn',
                'withdrawal_policy' => $match->participantB->withdrawal_policy?->value,
                'score_suggestion' => $scoreSuggestions->get($match->participantB->id),
            ] : null,
            'score_a' => $match->score_a,
            'score_b' => $match->score_b,
            'winner' => $match->winner ? [
                'id' => $match->winner->id,
                'display_name' => $this->participantDisplayName($match->winner),
            ] : null,
            'status' => $match->status->value,
            'status_label' => $this->statusLabel($match->status),
            'live_queue' => $currentLiveOrder->has($match->id)
                ? 'current'
                : ($nextLiveOrder->has($match->id) ? 'next' : null),
            'live_queue_order' => $currentLiveOrder->get($match->id)
                ?? $nextLiveOrder->get($match->id),
            'resource' => $match->resource ? [
                'id' => $match->resource->id,
                'name' => $match->resource->name,
                'type' => $match->resource->type->value,
            ] : null,
        ]);

        $groupMatches = $matches->where('stage', MatchStage::GROUP->value);
        $completedGroupMatches = $groupMatches->whereIn('status', [
            MatchStatus::FINISHED->value,
            MatchStatus::VOIDED->value,
            MatchStatus::CANCELLED->value,
        ]);
        $canCompleteGroupStage = $tournament->status === TournamentStatus::GROUP_STAGE
            && $groupMatches->isNotEmpty()
            && $completedGroupMatches->count() === $groupMatches->count();
        $repechageEnabled = (bool) data_get(
            $tournament->settings ?? [],
            'repechage_enabled',
            false,
        );

        return Inertia::render('Venues/Tournaments/Schedule/Index', [
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
                'status_label' => $this->tournamentStatusLabel($tournament->status),
                'matches_count' => $matches->count(),
                'group_matches_count' => $matches
                    ->where('stage', MatchStage::GROUP->value)
                    ->count(),
                'completed_group_matches_count' => $completedGroupMatches->count(),
                'finished_matches_count' => $matches
                    ->where('status', MatchStatus::FINISHED->value)
                    ->count(),
                'can_complete_group_stage' => $canCompleteGroupStage,
                'next_stage_after_groups' => $repechageEnabled
                    ? 'repechage'
                    : 'knockout_draw',
                'repechage_enabled' => $repechageEnabled,
            ],
            'matches' => $matches,
            'resources' => $tournament->resources()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get()
                ->map(fn (TournamentResource $resource) => [
                    'id' => $resource->id,
                    'name' => $resource->name,
                    'type' => $resource->type->value,
                    'type_label' => $resource->type->value,
                ])
                ->values(),
        ]);
    }

    public function updateResource(
        Request $request,
        Venue $venue,
        Tournament $tournament,
        TournamentMatch $match
    ): RedirectResponse {
        $user = $request->user();

        abort_unless($user->canAccessVenue($venue), 403);
        abort_unless($tournament->venue_id === $venue->id, 404);
        abort_unless($match->tournament_id === $tournament->id, 404);

        $validated = $request->validate([
            'tournament_resource_id' => [
                'nullable',
                'integer',
                Rule::exists('tournament_resources', 'id')
                    ->where('tournament_id', $tournament->id),
            ],
        ]);

        $match->update([
            'tournament_resource_id' => $validated['tournament_resource_id'] ?? null,
        ]);

        event(new TournamentLiveUpdated($tournament->fresh(), 'match_resource_updated'));

        return back()->with('success', 'Oprema za meč je promenjena.');
    }

    public function updateResult(
        Request $request,
        Venue $venue,
        Tournament $tournament,
        TournamentMatch $match
    ): RedirectResponse {
        $user = $request->user();

        abort_unless($user->canAccessVenue($venue), 403);
        abort_unless($tournament->venue_id === $venue->id, 404);
        abort_unless($match->tournament_id === $tournament->id, 404);

        $validated = $request->validate([
            'score_a' => ['nullable', 'integer', 'min:0', 'max:999'],
            'score_b' => ['nullable', 'integer', 'min:0', 'max:999'],
            'winner_participant_id' => ['nullable', 'integer'],
        ]);

        $scoreA = (int) ($validated['score_a'] ?? 0);
        $scoreB = (int) ($validated['score_b'] ?? 0);
        $requestedWinnerParticipantId = isset($validated['winner_participant_id'])
            ? (int) $validated['winner_participant_id']
            : null;

        $resultChanged = DB::transaction(function () use (
            $tournament,
            $match,
            $scoreA,
            $scoreB,
            $requestedWinnerParticipantId,
        ): bool {
            $lockedTournament = Tournament::query()
                ->whereKey($tournament->id)
                ->lockForUpdate()
                ->firstOrFail();

            $lockedMatch = TournamentMatch::withoutGlobalScope('visible_matches')
                ->where('tournament_id', $lockedTournament->id)
                ->whereKey($match->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertResultCanBeUpdated($lockedTournament, $lockedMatch);

            if (! $lockedMatch->participant_a_id || ! $lockedMatch->participant_b_id) {
                throw ValidationException::withMessages([
                    'score' => 'Meč nema oba učesnika i rezultat ne može biti unet.',
                ]);
            }

            if ($scoreA === $scoreB) {
                $winnerParticipantId = $requestedWinnerParticipantId;

                if (! in_array($winnerParticipantId, [
                    (int) $lockedMatch->participant_a_id,
                    (int) $lockedMatch->participant_b_id,
                ], true)) {
                    throw ValidationException::withMessages([
                        'winner_participant_id' => 'Kod nerešenog rezultata moraš da izabereš pobednika.',
                    ]);
                }
            } else {
                $winnerParticipantId = $scoreA > $scoreB
                    ? (int) $lockedMatch->participant_a_id
                    : (int) $lockedMatch->participant_b_id;
            }

            $resultIsUnchanged = $lockedMatch->status === MatchStatus::FINISHED
                && (int) $lockedMatch->score_a === $scoreA
                && (int) $lockedMatch->score_b === $scoreB
                && (int) $lockedMatch->winner_participant_id === $winnerParticipantId;

            if ($resultIsUnchanged) {
                return false;
            }

            if (
                $lockedMatch->status === MatchStatus::FINISHED
                && $this->dependentSeriesHasStarted($lockedMatch)
            ) {
                throw ValidationException::withMessages([
                    'score' => 'Rezultat više ne može da se promeni jer je naredna povezana serija već počela.',
                ]);
            }

            $loserParticipantId = $winnerParticipantId === (int) $lockedMatch->participant_a_id
                ? (int) $lockedMatch->participant_b_id
                : (int) $lockedMatch->participant_a_id;

            $lockedMatch->update([
                'score_a' => $scoreA,
                'score_b' => $scoreB,
                'winner_participant_id' => $winnerParticipantId,
                'loser_participant_id' => $loserParticipantId,
                'status' => MatchStatus::FINISHED,
                'win_reason' => $scoreA === $scoreB
                    ? WinReason::MANUAL_OVERRIDE
                    : WinReason::NORMAL,
                'finished_at' => now(),
            ]);

            if ($lockedMatch->stage !== MatchStage::GROUP) {
                $this->knockoutSeriesMatches($lockedMatch)
                    ->filter(fn (TournamentMatch $seriesMatch) => $seriesMatch->status === MatchStatus::POSTPONED
                        && $seriesMatch->id !== $lockedMatch->id
                    )
                    ->each(function (TournamentMatch $seriesMatch): void {
                        $meta = $seriesMatch->meta ?? [];
                        unset(
                            $meta['postponed_at'],
                            $meta['postponed_by_user_id'],
                        );

                        $seriesMatch->update([
                            'status' => MatchStatus::SCHEDULED,
                            'meta' => $meta,
                        ]);
                    });
            }

            $this->resolveKnockoutSeries($lockedMatch->fresh());

            return true;
        }, 3);

        if ($resultChanged) {
            event(new TournamentLiveUpdated($tournament->fresh(), 'match_result_updated'));
        }

        return back()->with(
            'success',
            $resultChanged ? 'Rezultat je sačuvan.' : 'Rezultat je već sačuvan.',
        );
    }

    public function updatePostponement(
        Request $request,
        Venue $venue,
        Tournament $tournament,
        TournamentMatch $match,
    ): RedirectResponse {
        $user = $request->user();

        abort_unless($user->canAccessVenue($venue), 403);
        abort_unless($tournament->venue_id === $venue->id, 404);
        abort_unless($match->tournament_id === $tournament->id, 404);

        $validated = $request->validate([
            'postponed' => ['required', 'boolean'],
        ]);

        if (in_array($match->status, [
            MatchStatus::FINISHED,
            MatchStatus::VOIDED,
            MatchStatus::CANCELLED,
        ], true)) {
            throw ValidationException::withMessages([
                'postponed' => 'Završen, anuliran ili otkazan meč ne može privremeno da se preskoči.',
            ]);
        }

        $postponed = (bool) $validated['postponed'];
        $matches = in_array($match->stage, [
            MatchStage::KNOCKOUT,
            MatchStage::THIRD_PLACE,
            MatchStage::FINAL,
        ], true)
            ? $this->knockoutSeriesMatches($match)
            : collect([$match]);

        DB::transaction(function () use ($matches, $postponed, $user): void {
            $matches
                ->reject(fn (TournamentMatch $seriesMatch) => in_array(
                    $seriesMatch->status,
                    [MatchStatus::FINISHED, MatchStatus::VOIDED, MatchStatus::CANCELLED],
                    true,
                ))
                ->each(function (TournamentMatch $seriesMatch) use ($postponed, $user): void {
                    $meta = $seriesMatch->meta ?? [];

                    if ($postponed) {
                        $meta['postponed_at'] = now()->toIso8601String();
                        $meta['postponed_by_user_id'] = $user->id;
                    } else {
                        unset(
                            $meta['postponed_at'],
                            $meta['postponed_by_user_id'],
                        );
                    }

                    $seriesMatch->update([
                        'status' => $postponed
                            ? MatchStatus::POSTPONED
                            : MatchStatus::SCHEDULED,
                        'meta' => $meta,
                    ]);
                });
        });

        event(new TournamentLiveUpdated(
            $tournament->fresh(),
            $postponed ? 'match_postponed' : 'match_resumed',
        ));

        return back()->with(
            'success',
            $postponed
                ? 'Meč je privremeno preskočen i sklonjen sa aktivnih tabli.'
                : 'Meč je vraćen u redovan raspored.',
        );
    }

    public function applyKnockoutWalkover(
        Request $request,
        Venue $venue,
        Tournament $tournament,
        TournamentMatch $match,
        TournamentParticipant $participant
    ): RedirectResponse {
        $user = $request->user();

        abort_unless($user->canAccessVenue($venue), 403);
        abort_unless($tournament->venue_id === $venue->id, 404);
        abort_unless($match->tournament_id === $tournament->id, 404);
        abort_unless($participant->tournament_id === $tournament->id, 404);

        if ($tournament->status !== TournamentStatus::KNOCKOUT_STAGE) {
            return back()->withErrors([
                'walkover' => 'Walkover može da se primeni samo dok je nokaut faza u toku.',
            ]);
        }

        if (! in_array($match->stage, [
            MatchStage::KNOCKOUT,
            MatchStage::THIRD_PLACE,
            MatchStage::FINAL,
        ], true)) {
            return back()->withErrors([
                'walkover' => 'Walkover može da se primeni samo na nokaut, treće mesto ili finale.',
            ]);
        }

        if (! $match->bracket_round || ! $match->bracket_position) {
            return back()->withErrors([
                'walkover' => 'Ovaj meč ne pripada validnoj nokaut seriji.',
            ]);
        }

        $seriesMatches = $this->knockoutSeriesMatches($match);

        if ($seriesMatches->isEmpty()) {
            return back()->withErrors([
                'walkover' => 'Nokaut serija nije pronađena.',
            ]);
        }

        /** @var TournamentMatch $firstMatch */
        $firstMatch = $seriesMatches->first();

        if (! $firstMatch->participant_a_id || ! $firstMatch->participant_b_id) {
            return back()->withErrors([
                'walkover' => 'Serija nema oba učesnika.',
            ]);
        }

        if (! in_array($participant->id, [
            (int) $firstMatch->participant_a_id,
            (int) $firstMatch->participant_b_id,
        ], true)) {
            return back()->withErrors([
                'walkover' => 'Izabrani učesnik ne pripada ovoj nokaut seriji.',
            ]);
        }

        if ($this->dependentSeriesHasStarted($firstMatch)) {
            return back()->withErrors([
                'walkover' => 'Ne možeš promeniti ovu seriju jer je sledeća povezana serija već počela.',
            ]);
        }

        $winnerParticipantId = (int) $participant->id === (int) $firstMatch->participant_a_id
            ? (int) $firstMatch->participant_b_id
            : (int) $firstMatch->participant_a_id;

        $winnerParticipant = TournamentParticipant::query()
            ->where('tournament_id', $tournament->id)
            ->find($winnerParticipantId);

        if (! $winnerParticipant) {
            return back()->withErrors([
                'walkover' => 'Protivnik nije pronađen.',
            ]);
        }

        if ($winnerParticipant->status === ParticipantStatus::WITHDRAWN) {
            return back()->withErrors([
                'walkover' => 'Protivnik je već označen kao odustao.',
            ]);
        }

        $winsRequired = (int) ($firstMatch->wins_required ?: 1);

        $existingWinnerWins = $seriesMatches
            ->filter(fn (TournamentMatch $seriesMatch) => $seriesMatch->status === MatchStatus::FINISHED)
            ->filter(fn (TournamentMatch $seriesMatch) => (int) $seriesMatch->winner_participant_id === $winnerParticipantId)
            ->count();

        $walkoverWinsNeeded = max(0, $winsRequired - $existingWinnerWins);

        $availableUnfinishedMatchesCount = $seriesMatches
            ->reject(fn (TournamentMatch $seriesMatch) => $seriesMatch->status === MatchStatus::FINISHED)
            ->count();

        if ($walkoverWinsNeeded > $availableUnfinishedMatchesCount) {
            return back()->withErrors([
                'walkover' => 'Nema dovoljno neodigranih partija da se serija završi walkoverom.',
            ]);
        }

        $walkoverWinsNeeded = max(0, $winsRequired - $existingWinnerWins);

        DB::transaction(function () use (
            $tournament,
            $seriesMatches,
            $participant,
            $winnerParticipantId,
            $walkoverWinsNeeded
        ): void {
            $participant->update([
                'status' => ParticipantStatus::WITHDRAWN,
                'withdrawn_at' => $participant->withdrawn_at ?? now(),
                'withdrawn_stage' => $tournament->status->value,
                'withdrawn_reason' => 'Odustao tokom nokaut faze.',
            ]);

            $walkoverWinsApplied = 0;

            foreach ($seriesMatches->values() as $seriesMatch) {
                /** @var TournamentMatch $seriesMatch */
                $meta = $seriesMatch->meta ?? [];

                if ($seriesMatch->status === MatchStatus::FINISHED) {
                    continue;
                }

                if ($walkoverWinsApplied < $walkoverWinsNeeded) {
                    $scoreA = (int) $seriesMatch->participant_a_id === $winnerParticipantId ? 1 : 0;
                    $scoreB = (int) $seriesMatch->participant_b_id === $winnerParticipantId ? 1 : 0;

                    $meta['walkover'] = true;
                    $meta['walkover_reason'] = 'opponent_withdrew';
                    $meta['withdrawn_participant_id'] = $participant->id;
                    $meta['walkover_at'] = now()->toDateTimeString();

                    $seriesMatch->update([
                        'score_a' => $scoreA,
                        'score_b' => $scoreB,
                        'winner_participant_id' => $winnerParticipantId,
                        'loser_participant_id' => $participant->id,
                        'is_hidden' => false,
                        'status' => MatchStatus::FINISHED,
                        'win_reason' => WinReason::OPPONENT_WITHDREW,
                        'finished_at' => now(),
                        'meta' => $meta,
                    ]);

                    $walkoverWinsApplied++;

                    continue;
                }

                $meta['voided_reason'] = 'series_decided_by_walkover';
                $meta['withdrawn_participant_id'] = $participant->id;
                $meta['voided_at'] = now()->toDateTimeString();

                $seriesMatch->update([
                    'score_a' => null,
                    'score_b' => null,
                    'winner_participant_id' => null,
                    'loser_participant_id' => null,
                    'is_hidden' => true,
                    'status' => MatchStatus::VOIDED,
                    'win_reason' => null,
                    'finished_at' => null,
                    'meta' => $meta,
                ]);
            }
        });

        $this->resolveKnockoutSeries($firstMatch->fresh());

        event(new TournamentLiveUpdated($tournament->fresh(), 'knockout_walkover_applied'));

        return back()->with('success', 'Walkover je primenjen i protivnik je prošao dalje.');
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

    private function groupScoreSuggestions(Tournament $tournament): Collection
    {
        $scoresByParticipant = [];

        TournamentMatch::withoutGlobalScope('visible_matches')
            ->where('tournament_id', $tournament->id)
            ->where('stage', MatchStage::GROUP->value)
            ->where('status', MatchStatus::FINISHED->value)
            ->orderBy('id')
            ->get()
            ->reject(fn (TournamentMatch $match) => data_get($match->meta, 'result_source') === 'withdrawal_average')
            ->each(function (TournamentMatch $match) use (&$scoresByParticipant): void {
                if ($match->participant_a_id && $match->score_a !== null) {
                    $scoresByParticipant[$match->participant_a_id][] = (int) $match->score_a;
                }

                if ($match->participant_b_id && $match->score_b !== null) {
                    $scoresByParticipant[$match->participant_b_id][] = (int) $match->score_b;
                }
            });

        return collect($scoresByParticipant)->map(function (array $scores): array {
            sort($scores);
            $count = count($scores);
            $middle = intdiv($count, 2);
            $median = $count % 2 === 0
                ? (int) round(($scores[$middle - 1] + $scores[$middle]) / 2)
                : $scores[$middle];

            return [
                'average' => (int) round(array_sum($scores) / $count),
                'median' => $median,
                'matches_count' => $count,
            ];
        });
    }

    private function stageLabel(MatchStage $stage): string
    {
        return match ($stage) {
            MatchStage::GROUP => 'Grupa',
            MatchStage::KNOCKOUT => 'Nokaut',
            MatchStage::THIRD_PLACE => 'Treće mesto',
            MatchStage::FINAL => 'Finale',
        };
    }

    private function statusLabel(MatchStatus $status): string
    {
        return match ($status) {
            MatchStatus::SCHEDULED => 'Zakazano',
            MatchStatus::IN_PROGRESS => 'U toku',
            MatchStatus::POSTPONED => 'Privremeno preskočen',
            MatchStatus::FINISHED => 'Završeno',
            MatchStatus::VOIDED => 'Anulirano',
            MatchStatus::CANCELLED => 'Otkazano',
        };
    }

    private function tournamentStatusLabel(TournamentStatus $status): string
    {
        return match ($status) {
            TournamentStatus::DRAFT => 'Priprema',
            TournamentStatus::GROUP_DRAW => 'Unos učesnika',
            TournamentStatus::READY => 'Spreman',
            TournamentStatus::GROUP_STAGE => 'Grupna faza',
            TournamentStatus::REPECHAGE => 'Repasaž',
            TournamentStatus::KNOCKOUT_DRAW => 'Žreb za nokaut',
            TournamentStatus::KNOCKOUT_STAGE => 'Nokaut faza',
            TournamentStatus::FINISHED => 'Završen',
        };
    }

    private function bracketRoundLabel(?string $bracketRound): ?string
    {
        return match ($bracketRound) {
            'preliminary' => 'Preliminarna runda',
            'round_of_32' => '1/16 finala',
            'round_of_16' => '1/8 finala',
            'quarter_final' => 'Četvrtfinale',
            'semi_final' => 'Polufinale',
            'third_place' => 'Treće mesto',
            'final' => 'Finale',
            default => $bracketRound,
        };
    }

    private function knockoutSeriesMatches(TournamentMatch $match): Collection
    {
        return TournamentMatch::withoutGlobalScope('visible_matches')
            ->where('tournament_id', $match->tournament_id)
            ->where('stage', $match->stage->value)
            ->where('bracket_round', $match->bracket_round)
            ->where('bracket_position', $match->bracket_position)
            ->orderBy('round_robin_leg')
            ->orderBy('id')
            ->get();
    }

    private function assertResultCanBeUpdated(
        Tournament $tournament,
        TournamentMatch $match,
    ): void {
        if (in_array($match->status, [MatchStatus::VOIDED, MatchStatus::CANCELLED], true)) {
            throw ValidationException::withMessages([
                'score' => 'Rezultat ne može da se unese za anuliran ili otkazan meč.',
            ]);
        }

        if (
            $match->stage === MatchStage::GROUP
            && $tournament->status !== TournamentStatus::GROUP_STAGE
        ) {
            throw ValidationException::withMessages([
                'score' => 'Rezultati grupnih mečeva mogu da se menjaju samo tokom grupne faze.',
            ]);
        }

        if (
            in_array($match->stage, [MatchStage::KNOCKOUT, MatchStage::THIRD_PLACE, MatchStage::FINAL], true)
            && ! in_array($tournament->status, [TournamentStatus::KNOCKOUT_STAGE, TournamentStatus::FINISHED], true)
        ) {
            throw ValidationException::withMessages([
                'score' => 'Rezultati nokaut mečeva mogu da se menjaju samo tokom nokaut faze.',
            ]);
        }
    }

    private function dependentSeriesHasStarted(TournamentMatch $sourceMatch): bool
    {
        if ($sourceMatch->stage !== MatchStage::KNOCKOUT) {
            return false;
        }

        return TournamentMatch::withoutGlobalScope('visible_matches')
            ->where('tournament_id', $sourceMatch->tournament_id)
            ->where(function ($query): void {
                $query
                    ->whereIn('status', [
                        MatchStatus::IN_PROGRESS->value,
                        MatchStatus::FINISHED->value,
                    ])
                    ->orWhereNotNull('score_a')
                    ->orWhereNotNull('score_b')
                    ->orWhereNotNull('winner_participant_id');
            })
            ->where(function ($query) use ($sourceMatch) {
                $query
                    ->where(function ($slotQuery) use ($sourceMatch) {
                        $slotQuery
                            ->where('meta->participant_a_source_round', $sourceMatch->bracket_round)
                            ->where('meta->participant_a_source_position', $sourceMatch->bracket_position);
                    })
                    ->orWhere(function ($slotQuery) use ($sourceMatch) {
                        $slotQuery
                            ->where('meta->participant_b_source_round', $sourceMatch->bracket_round)
                            ->where('meta->participant_b_source_position', $sourceMatch->bracket_position);
                    });
            })
            ->exists();
    }

    private function resolveKnockoutSeries(TournamentMatch $match): void
    {
        if (! in_array($match->stage, [
            MatchStage::KNOCKOUT,
            MatchStage::THIRD_PLACE,
            MatchStage::FINAL,
        ], true)) {
            return;
        }

        if (! $match->bracket_round || ! $match->bracket_position) {
            return;
        }

        $seriesMatches = TournamentMatch::withoutGlobalScope('visible_matches')
            ->where('tournament_id', $match->tournament_id)
            ->where('stage', $match->stage->value)
            ->where('bracket_round', $match->bracket_round)
            ->where('bracket_position', $match->bracket_position)
            ->orderBy('round_robin_leg')
            ->orderBy('id')
            ->get();

        $winsRequired = (int) ($match->wins_required ?: 1);

        if ($winsRequired < 1) {
            return;
        }

        $winsByParticipant = [];
        $seriesWinnerId = null;
        $seriesLoserId = null;

        foreach ($seriesMatches as $seriesMatch) {
            if ($seriesWinnerId) {
                $this->voidSeriesMatch($seriesMatch);

                continue;
            }

            if ($seriesMatch->status === MatchStatus::VOIDED) {
                $this->restoreVoidedSeriesMatch($seriesMatch);
                $seriesMatch->refresh();
            }

            if ($seriesMatch->status !== MatchStatus::FINISHED) {
                continue;
            }

            if (! $seriesMatch->winner_participant_id) {
                continue;
            }

            $winnerParticipantId = (int) $seriesMatch->winner_participant_id;

            $winsByParticipant[$winnerParticipantId] =
                ($winsByParticipant[$winnerParticipantId] ?? 0) + 1;

            if ($winsByParticipant[$winnerParticipantId] >= $winsRequired) {
                $seriesWinnerId = $winnerParticipantId;
                $seriesLoserId = $this->seriesLoserId($seriesMatches, $seriesWinnerId);
            }
        }

        if (! $seriesWinnerId) {
            $this->clearNextSeriesSlot($match, 'winner');
            $this->clearNextSeriesSlot($match, 'loser');

            if (in_array($match->stage, [MatchStage::FINAL, MatchStage::THIRD_PLACE], true)) {
                $this->syncTournamentFinishedStatus($match);
            }

            return;
        }

        if (in_array($match->stage, [MatchStage::FINAL, MatchStage::THIRD_PLACE], true)) {
            $this->syncTournamentFinishedStatus($match);

            return;
        }

        if ($match->stage === MatchStage::KNOCKOUT) {
            $this->syncNextSeriesSlot($match, 'winner', $seriesWinnerId);

            if ($seriesLoserId) {
                $this->syncNextSeriesSlot($match, 'loser', $seriesLoserId);
            }
        }
    }

    private function restoreVoidedSeriesMatch(TournamentMatch $match): void
    {
        if ($match->status !== MatchStatus::VOIDED) {
            return;
        }

        $meta = $match->meta ?? [];

        unset(
            $meta['voided_reason'],
            $meta['voided_at'],
        );

        $match->update([
            'score_a' => null,
            'score_b' => null,
            'winner_participant_id' => null,
            'loser_participant_id' => null,
            'is_hidden' => false,
            'status' => MatchStatus::SCHEDULED,
            'win_reason' => null,
            'finished_at' => null,
            'meta' => $meta ?: null,
        ]);
    }

    private function voidSeriesMatch(TournamentMatch $match): void
    {
        if ($match->status === MatchStatus::VOIDED) {
            if (! $match->is_hidden) {
                $match->update(['is_hidden' => true]);
            }

            return;
        }

        if ($match->status === MatchStatus::FINISHED) {
            return;
        }

        $meta = $match->meta ?? [];
        $meta['voided_reason'] = 'series_already_decided';
        $meta['voided_at'] = now()->toDateTimeString();

        $match->update([
            'score_a' => null,
            'score_b' => null,
            'winner_participant_id' => null,
            'loser_participant_id' => null,
            'is_hidden' => true,
            'status' => MatchStatus::VOIDED,
            'win_reason' => null,
            'finished_at' => null,
            'meta' => $meta,
        ]);
    }

    private function seriesLoserId(Collection $seriesMatches, int $seriesWinnerId): ?int
    {
        foreach ($seriesMatches as $seriesMatch) {
            if ($seriesMatch->participant_a_id && (int) $seriesMatch->participant_a_id !== $seriesWinnerId) {
                return (int) $seriesMatch->participant_a_id;
            }

            if ($seriesMatch->participant_b_id && (int) $seriesMatch->participant_b_id !== $seriesWinnerId) {
                return (int) $seriesMatch->participant_b_id;
            }
        }

        return null;
    }

    private function clearNextSeriesSlot(TournamentMatch $sourceMatch, string $outcome): void
    {
        $this->syncNextSeriesSlot($sourceMatch, $outcome, null);
    }

    private function syncNextSeriesSlot(
        TournamentMatch $sourceMatch,
        string $outcome,
        ?int $participantId
    ): void {
        foreach (['participant_a_id', 'participant_b_id'] as $slot) {
            $sourceRoundMetaKey = $slot === 'participant_a_id'
                ? 'participant_a_source_round'
                : 'participant_b_source_round';

            $sourcePositionMetaKey = $slot === 'participant_a_id'
                ? 'participant_a_source_position'
                : 'participant_b_source_position';

            $sourceOutcomeMetaKey = $slot === 'participant_a_id'
                ? 'participant_a_source_outcome'
                : 'participant_b_source_outcome';

            $nextSeriesMatches = TournamentMatch::query()
                ->where('tournament_id', $sourceMatch->tournament_id)
                ->where('meta->'.$sourceRoundMetaKey, $sourceMatch->bracket_round)
                ->where('meta->'.$sourcePositionMetaKey, $sourceMatch->bracket_position)
                ->where('meta->'.$sourceOutcomeMetaKey, $outcome)
                ->orderBy('round_robin_leg')
                ->orderBy('id')
                ->get();

            if ($nextSeriesMatches->isEmpty()) {
                continue;
            }

            if ($nextSeriesMatches->contains(fn (TournamentMatch $match) => $match->status === MatchStatus::FINISHED)) {
                continue;
            }

            foreach ($nextSeriesMatches as $nextSeriesMatch) {
                $nextSeriesMatch->update([
                    $slot => $participantId,
                ]);
            }
        }
    }

    private function syncTournamentFinishedStatus(TournamentMatch $match): void
    {
        $tournament = $match->tournament;

        if (! $tournament) {
            return;
        }

        $finalIsDecided = $this->stageSeriesIsDecided($tournament, MatchStage::FINAL);
        $thirdPlaceExists = TournamentMatch::withoutGlobalScope('visible_matches')
            ->where('tournament_id', $tournament->id)
            ->where('stage', MatchStage::THIRD_PLACE->value)
            ->exists();
        $thirdPlaceIsDecided = ! $thirdPlaceExists
            || $this->stageSeriesIsDecided($tournament, MatchStage::THIRD_PLACE);

        if ($finalIsDecided && $thirdPlaceIsDecided) {
            $tournament->update([
                'status' => TournamentStatus::FINISHED,
                'finished_at' => $tournament->finished_at ?? now(),
            ]);

            return;
        }

        if ($tournament->status === TournamentStatus::FINISHED) {
            $tournament->update([
                'status' => TournamentStatus::KNOCKOUT_STAGE,
                'finished_at' => null,
            ]);
        }
    }

    private function stageSeriesIsDecided(Tournament $tournament, MatchStage $stage): bool
    {
        $seriesMatches = TournamentMatch::withoutGlobalScope('visible_matches')
            ->where('tournament_id', $tournament->id)
            ->where('stage', $stage->value)
            ->orderBy('round_robin_leg')
            ->orderBy('id')
            ->get();

        if ($seriesMatches->isEmpty()) {
            return false;
        }

        $winsRequired = max(1, (int) ($seriesMatches->first()->wins_required ?: 1));
        $mostWins = (int) $seriesMatches
            ->where('status', MatchStatus::FINISHED)
            ->whereNotNull('winner_participant_id')
            ->countBy(fn (TournamentMatch $seriesMatch) => (int) $seriesMatch->winner_participant_id)
            ->max();

        return $mostWins >= $winsRequired;
    }
}
