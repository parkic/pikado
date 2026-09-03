<?php

namespace App\Http\Controllers;

use App\Enums\MatchStage;
use App\Enums\MatchStatus;
use App\Enums\RepechageOutcomeStatus;
use App\Enums\TournamentStatus;
use App\Events\TournamentLiveUpdated;
use App\Models\Tournament;
use App\Models\TournamentMatch;
use App\Models\TournamentParticipant;
use App\Models\Venue;
use App\Services\GroupStandingsCalculator;
use App\Services\KnockoutBracketGenerator;
use App\Services\KnockoutDrawService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class TournamentKnockoutController extends Controller
{
    public function index(
        Request $request,
        Venue $venue,
        Tournament $tournament,
        GroupStandingsCalculator $calculator,
        KnockoutDrawService $drawService,
    ): Response {
        $user = $request->user();

        abort_unless($user->canAccessVenue($venue), 403);
        abort_unless($tournament->venue_id === $venue->id, 404);

        $groups = collect($calculator->calculate($tournament));
        $qualificationPositions = $this->qualificationPositions($groups);

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
                        MatchStage::THIRD_PLACE->value,
                        MatchStage::FINAL->value,
                    ])
                    ->count(),
                'can_generate_knockout_bracket' => $this->canGenerateKnockoutBracket(
                    $tournament,
                    $knockoutParticipants,
                ),
                'repechage_enabled' => (bool) data_get($tournament->settings ?? [], 'repechage_enabled', false),
            ],
            'direct_qualifiers' => $directQualifiers->values(),
            'repechage_qualifiers' => $repechageQualifiers->values(),
            'knockout_participants' => $knockoutParticipants->values(),
            'knockout_draw' => array_merge($drawService->state($tournament), [
                'can_manage' => $this->canManageDraw($user, $venue),
            ]),
            'knockout_series' => $this->knockoutSeriesGroups(
                $tournament,
                $qualificationPositions,
            ),
        ]);
    }

    public function updateSeeding(
        Request $request,
        Venue $venue,
        Tournament $tournament,
        KnockoutDrawService $drawService,
    ): RedirectResponse {
        $this->authorizeTournament($request, $venue, $tournament);
        abort_unless($this->canManageDraw($request->user(), $venue), 403);

        $state = $drawService->state($tournament);
        abort_unless($tournament->status === TournamentStatus::KNOCKOUT_DRAW, 422);
        abort_unless($state['enabled'] && $state['can_customize_seeding'], 422);

        $participantIds = collect($state['seeded'])
            ->concat($state['unseeded'])
            ->pluck('participant_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $validated = $request->validate([
            'seeded_participant_ids' => ['required', 'array', 'size:'.$state['seeded_count']],
            'seeded_participant_ids.*' => [
                'required',
                'integer',
                'distinct',
                Rule::in($participantIds),
            ],
        ]);

        $drawService->saveSeeding($tournament, $validated['seeded_participant_ids']);
        event(new TournamentLiveUpdated($tournament->fresh(), 'knockout_seeding_updated'));

        return back()->with('success', 'Liste nosilaca i nenosilaca su sačuvane.');
    }

    public function drawNext(
        Request $request,
        Venue $venue,
        Tournament $tournament,
        KnockoutDrawService $drawService,
    ): RedirectResponse {
        $this->authorizeTournament($request, $venue, $tournament);
        abort_unless($this->canManageDraw($request->user(), $venue), 403);

        DB::transaction(function () use ($tournament, $drawService): void {
            $lockedTournament = Tournament::query()->lockForUpdate()->findOrFail($tournament->id);
            abort_unless($lockedTournament->status === TournamentStatus::KNOCKOUT_DRAW, 422);

            $state = $drawService->drawNext($lockedTournament);
            abort_unless($state['enabled'], 422);
        });

        event(new TournamentLiveUpdated($tournament->fresh(), 'knockout_draw_updated'));

        return back();
    }

    public function resetDraw(
        Request $request,
        Venue $venue,
        Tournament $tournament,
        KnockoutDrawService $drawService,
    ): RedirectResponse {
        $this->authorizeTournament($request, $venue, $tournament);
        abort_unless($this->canManageDraw($request->user(), $venue), 403);
        abort_unless($tournament->status === TournamentStatus::KNOCKOUT_DRAW, 422);

        $drawService->reset($tournament);
        event(new TournamentLiveUpdated($tournament->fresh(), 'knockout_draw_reset'));

        return back()->with('success', 'Izvlačenje je vraćeno na početak.');
    }

    public function generate(
        Request $request,
        Venue $venue,
        Tournament $tournament,
        KnockoutBracketGenerator $generator
    ): RedirectResponse {
        $user = $request->user();

        abort_unless($user->canAccessVenue($venue), 403);
        abort_unless($this->canManageDraw($user, $venue), 403);
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
            ->with('success', 'Generisano nokaut mečeva: '.$createdMatches.'.');
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
                        'qualification_position' => $row['qualification_position'],
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

        if ((bool) data_get($tournament->settings ?? [], 'repechage_enabled', false)) {
            $drawState = app(KnockoutDrawService::class)->state($tournament);

            if ($drawState['enabled'] && ! $drawState['complete']) {
                return false;
            }
        }

        return ! $tournament->matches()
            ->whereIn('stage', [
                MatchStage::KNOCKOUT->value,
                MatchStage::THIRD_PLACE->value,
                MatchStage::FINAL->value,
            ])
            ->exists();
    }

    private function authorizeTournament(Request $request, Venue $venue, Tournament $tournament): void
    {
        abort_unless($request->user()->canAccessVenue($venue), 403);
        abort_unless($tournament->venue_id === $venue->id, 404);
    }

    private function canManageDraw($user, Venue $venue): bool
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

    private function knockoutSeriesGroups(
        Tournament $tournament,
        Collection $qualificationPositions,
    ): Collection {
        $matches = TournamentMatch::query()
            ->with([
                'participantA.player',
                'participantA.team',
                'participantB.player',
                'participantB.team',
                'winner.player',
                'winner.team',
                'resource',
            ])
            ->where('tournament_id', $tournament->id)
            ->whereIn('stage', [
                MatchStage::KNOCKOUT->value,
                MatchStage::THIRD_PLACE->value,
                MatchStage::FINAL->value,
            ])
            ->orderBy('bracket_position')
            ->orderBy('round_robin_leg')
            ->orderBy('id')
            ->get();

        if ($matches->isEmpty()) {
            return collect();
        }

        $series = $matches
            ->groupBy(fn (TournamentMatch $match) => $match->bracket_round.'-'.$match->bracket_position)
            ->map(fn (Collection $seriesMatches) => $this->knockoutSeriesSummary(
                $seriesMatches,
                $qualificationPositions,
            ))
            ->sortBy(fn (array $series) => sprintf('%02d-%03d', $series['round_sort'], $series['position']))
            ->values();

        return $series
            ->groupBy('round_key')
            ->map(function (Collection $roundSeries) {
                $firstSeries = $roundSeries->first();

                return [
                    'round_key' => $firstSeries['round_key'],
                    'round_label' => $firstSeries['round_label'],
                    'round_sort' => $firstSeries['round_sort'],
                    'series' => $roundSeries->values(),
                ];
            })
            ->sortBy('round_sort')
            ->values();
    }

    private function knockoutSeriesSummary(
        Collection $seriesMatches,
        Collection $qualificationPositions,
    ): array {
        $seriesMatches = $seriesMatches
            ->sortBy('round_robin_leg')
            ->values();

        /** @var TournamentMatch $firstMatch */
        $firstMatch = $seriesMatches->first();

        $participantA = $firstMatch->participantA;
        $participantB = $firstMatch->participantB;

        $participantAId = $firstMatch->participant_a_id ? (int) $firstMatch->participant_a_id : null;
        $participantBId = $firstMatch->participant_b_id ? (int) $firstMatch->participant_b_id : null;

        $winsRequired = (int) ($firstMatch->wins_required ?: 1);
        $winsByParticipant = [];
        $seriesWinnerId = null;
        $finishedLegsCount = 0;

        foreach ($seriesMatches as $match) {
            if ($match->status !== MatchStatus::FINISHED) {
                continue;
            }

            if (! $match->winner_participant_id) {
                continue;
            }

            $finishedLegsCount++;

            $winnerParticipantId = (int) $match->winner_participant_id;

            $winsByParticipant[$winnerParticipantId] =
                ($winsByParticipant[$winnerParticipantId] ?? 0) + 1;

            if ($winsByParticipant[$winnerParticipantId] >= $winsRequired) {
                $seriesWinnerId = $winnerParticipantId;
                break;
            }
        }

        $participantAWins = $participantAId
            ? ($winsByParticipant[$participantAId] ?? 0)
            : 0;

        $participantBWins = $participantBId
            ? ($winsByParticipant[$participantBId] ?? 0)
            : 0;

        [$seriesStatus, $seriesStatusLabel] = $this->knockoutSeriesStatus(
            $participantA,
            $participantB,
            $seriesWinnerId,
            $finishedLegsCount,
        );

        return [
            'round_key' => $firstMatch->bracket_round,
            'round_label' => $this->bracketRoundLabel($firstMatch->bracket_round),
            'round_sort' => $this->bracketRoundSort($firstMatch->bracket_round),
            'position' => (int) $firstMatch->bracket_position,
            'title' => $this->bracketRoundLabel($firstMatch->bracket_round).' #'.$firstMatch->bracket_position,
            'wins_required' => $winsRequired,
            'max_legs' => ($winsRequired * 2) - 1,
            'participant_a' => $this->participantSummary($participantA, $qualificationPositions),
            'participant_b' => $this->participantSummary($participantB, $qualificationPositions),
            'participant_a_wins' => $participantAWins,
            'participant_b_wins' => $participantBWins,
            'series_score' => $participantAWins.' : '.$participantBWins,
            'winner' => $this->participantSummary(
                $this->participantFromSeries($seriesMatches, $seriesWinnerId),
                $qualificationPositions,
            ),
            'status' => $seriesStatus,
            'status_label' => $seriesStatusLabel,
            'legs' => $seriesMatches
                ->map(fn (TournamentMatch $match) => [
                    'id' => $match->id,
                    'leg' => (int) $match->round_robin_leg,
                    'status' => $match->status->value,
                    'status_label' => $this->matchStatusLabel($match->status),
                    'score' => $match->score_a !== null && $match->score_b !== null
                        ? $match->score_a.' : '.$match->score_b
                        : null,
                    'winner' => $this->participantSummary($match->winner, $qualificationPositions),
                    'resource_name' => $match->resource?->name,
                ])
                ->values(),
        ];
    }

    private function knockoutSeriesStatus(
        ?TournamentParticipant $participantA,
        ?TournamentParticipant $participantB,
        ?int $seriesWinnerId,
        int $finishedLegsCount,
    ): array {
        if (! $participantA || ! $participantB) {
            return ['waiting_participants', 'Čeka učesnike'];
        }

        if ($seriesWinnerId) {
            return ['finished', 'Završeno'];
        }

        if ($finishedLegsCount > 0) {
            return ['in_progress', 'U toku'];
        }

        return ['scheduled', 'Nije počelo'];
    }

    private function participantFromSeries(Collection $seriesMatches, ?int $participantId): ?TournamentParticipant
    {
        if (! $participantId) {
            return null;
        }

        foreach ($seriesMatches as $match) {
            if ($match->participantA && (int) $match->participantA->id === $participantId) {
                return $match->participantA;
            }

            if ($match->participantB && (int) $match->participantB->id === $participantId) {
                return $match->participantB;
            }

            if ($match->winner && (int) $match->winner->id === $participantId) {
                return $match->winner;
            }
        }

        return null;
    }

    private function participantSummary(
        ?TournamentParticipant $participant,
        Collection $qualificationPositions,
    ): ?array {
        if (! $participant) {
            return null;
        }

        return [
            'id' => $participant->id,
            'display_name' => $this->participantDisplayName($participant),
            'qualification_position' => $qualificationPositions->get($participant->id),
            'status' => $participant->status->value,
            'is_withdrawn' => $participant->status->value === 'withdrawn',
        ];
    }

    private function qualificationPositions(Collection $groups): Collection
    {
        return $groups
            ->flatMap(fn (array $group) => collect($group['rows']))
            ->filter(fn (array $row) => $row['qualification_position'] !== null)
            ->mapWithKeys(fn (array $row) => [
                $row['participant_id'] => $row['qualification_position'],
            ]);
    }

    private function participantDisplayName(TournamentParticipant $participant): string
    {
        if ($participant->team) {
            return $participant->team->name;
        }

        if ($participant->player) {
            $name = trim($participant->player->first_name.' '.$participant->player->last_name);

            if ($participant->player->nickname) {
                $name .= ' ('.$participant->player->nickname.')';
            }

            return $name;
        }

        return 'Učesnik #'.$participant->id;
    }

    private function bracketRoundLabel(?string $bracketRound): string
    {
        return match ($bracketRound) {
            'preliminary' => 'Preliminarna runda',
            'round_of_32' => '1/16 finala',
            'round_of_16' => '1/8 finala',
            'quarter_final' => 'Četvrtfinale',
            'semi_final' => 'Polufinale',
            'third_place' => 'Treće mesto',
            'final' => 'Finale',
            default => $bracketRound ?: 'Nokaut',
        };
    }

    private function bracketRoundSort(?string $bracketRound): int
    {
        return match ($bracketRound) {
            'preliminary' => 5,
            'round_of_32' => 10,
            'round_of_16' => 20,
            'quarter_final' => 30,
            'semi_final' => 40,
            'third_place' => 50,
            'final' => 60,
            default => 999,
        };
    }

    private function matchStatusLabel(MatchStatus $status): string
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
}
