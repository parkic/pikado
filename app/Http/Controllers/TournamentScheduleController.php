<?php

namespace App\Http\Controllers;

use App\Enums\MatchStage;
use App\Enums\MatchStatus;
use App\Enums\WinReason;
use App\Enums\TournamentStatus;

use App\Models\Tournament;
use App\Models\TournamentMatch;
use App\Models\TournamentParticipant;
use App\Models\TournamentResource;
use App\Models\Venue;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;


class TournamentScheduleController extends Controller
{
    public function index(Request $request, Venue $venue, Tournament $tournament): Response
    {
        $user = $request->user();

        abort_unless($user->canAccessVenue($venue), 403);
        abort_unless($tournament->venue_id === $venue->id, 404);

        $matches = $tournament->matches()
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
            ->get()
            ->map(fn (TournamentMatch $match) => [
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
                    'display_name' => $this->participantDisplayName($match->participantA),
                    'status' => $match->participantA->status->value,
                    'is_withdrawn' => $match->participantA->status->value === 'withdrawn',
                ] : null,
                'participant_b' => $match->participantB ? [
                    'id' => $match->participantB->id,
                    'group_position' => $match->participantB->group_position,
                    'display_name' => $this->participantDisplayName($match->participantB),
                    'status' => $match->participantB->status->value,
                    'is_withdrawn' => $match->participantB->status->value === 'withdrawn',
                ] : null,
                'score_a' => $match->score_a,
                'score_b' => $match->score_b,
                'winner' => $match->winner ? [
                    'id' => $match->winner->id,
                    'display_name' => $this->participantDisplayName($match->winner),
                ] : null,
                'status' => $match->status->value,
                'status_label' => $this->statusLabel($match->status),
                'resource' => $match->resource ? [
                    'id' => $match->resource->id,
                    'name' => $match->resource->name,
                    'type' => $match->resource->type->value,
                ] : null,
            ]);

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
                'status_label' => $tournament->status->value,
                'matches_count' => $matches->count(),
                'group_matches_count' => $matches
                    ->where('stage', MatchStage::GROUP->value)
                    ->count(),
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

        return back()->with('success', 'Resource za meč je promenjen.');
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
            'score_a' => ['required', 'integer', 'min:0', 'max:999'],
            'score_b' => ['required', 'integer', 'min:0', 'max:999'],
            'winner_participant_id' => ['nullable', 'integer'],
        ]);

        if (! $match->participant_a_id || ! $match->participant_b_id) {
            return back()->withErrors([
                'score' => 'Meč nema oba učesnika i rezultat ne može biti unet.',
            ]);
        }

        $scoreA = (int) $validated['score_a'];
        $scoreB = (int) $validated['score_b'];

        if ($scoreA === $scoreB) {
            $winnerParticipantId = isset($validated['winner_participant_id'])
                ? (int) $validated['winner_participant_id']
                : null;

            if (! in_array($winnerParticipantId, [
                $match->participant_a_id,
                $match->participant_b_id,
            ], true)) {
                return back()->withErrors([
                    'winner_participant_id' => 'Kod nerešenog rezultata moraš da izabereš pobednika.',
                ]);
            }
        } else {
            $winnerParticipantId = $scoreA > $scoreB
                ? $match->participant_a_id
                : $match->participant_b_id;
        }

        $loserParticipantId = $winnerParticipantId === $match->participant_a_id
            ? $match->participant_b_id
            : $match->participant_a_id;

        $match->update([
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

        $this->resolveKnockoutSeries($match->fresh());

        return back()->with('success', 'Rezultat je sačuvan.');
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
            MatchStatus::FINISHED => 'Završeno',
            MatchStatus::VOIDED => 'Anulirano',
            MatchStatus::CANCELLED => 'Otkazano',
        };
    }

    private function bracketRoundLabel(?string $bracketRound): ?string
    {
        return match ($bracketRound) {
            'round_of_32' => '1/16 finala',
            'round_of_16' => '1/8 finala',
            'quarter_final' => 'Četvrtfinale',
            'semi_final' => 'Polufinale',
            'third_place' => 'Treće mesto',
            'final' => 'Finale',
            default => $bracketRound,
        };
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

        $seriesMatches = TournamentMatch::query()
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

            if ($match->stage === MatchStage::FINAL) {
                $this->syncTournamentFinishedStatus($match, null);
            }

            return;
        }

        if ($match->stage === MatchStage::FINAL) {
            $this->syncTournamentFinishedStatus($match, $seriesWinnerId);

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

        $match->update([
            'score_a' => null,
            'score_b' => null,
            'winner_participant_id' => null,
            'loser_participant_id' => null,
            'status' => MatchStatus::SCHEDULED,
            'win_reason' => null,
            'finished_at' => null,
        ]);
    }

    private function voidSeriesMatch(TournamentMatch $match): void
    {
        if ($match->status === MatchStatus::VOIDED) {
            return;
        }

        if ($match->status === MatchStatus::FINISHED) {
            return;
        }

        $match->update([
            'score_a' => null,
            'score_b' => null,
            'winner_participant_id' => null,
            'loser_participant_id' => null,
            'status' => MatchStatus::VOIDED,
            'win_reason' => null,
            'finished_at' => null,
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
                ->where('meta->' . $sourceRoundMetaKey, $sourceMatch->bracket_round)
                ->where('meta->' . $sourcePositionMetaKey, $sourceMatch->bracket_position)
                ->where('meta->' . $sourceOutcomeMetaKey, $outcome)
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

    private function syncTournamentFinishedStatus(TournamentMatch $match, ?int $finalWinnerParticipantId): void
    {
        $tournament = $match->tournament;

        if (! $tournament) {
            return;
        }

        if ($finalWinnerParticipantId) {
            $tournament->update([
                'status' => TournamentStatus::FINISHED,
                'finished_at' => now(),
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
}
