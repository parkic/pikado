<?php

use App\Enums\GameType;
use App\Enums\GroupRounds;
use App\Enums\MatchMode;
use App\Enums\MatchStage;
use App\Enums\MatchStatus;
use App\Enums\ParticipantStatus;
use App\Enums\ParticipantType;
use App\Enums\QualificationStatus;
use App\Enums\RepechageOutcomeStatus;
use App\Enums\ResourceType;
use App\Enums\ScoringMode;
use App\Enums\TournamentStatus;
use App\Enums\VenueUserRole;
use App\Enums\WithdrawalPolicy;
use App\Events\TournamentLiveUpdated;
use App\Models\Player;
use App\Models\Tournament;
use App\Models\TournamentGroup;
use App\Models\TournamentMatch;
use App\Models\TournamentParticipant;
use App\Models\TournamentResource;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueUser;
use App\Services\GroupMatchGenerator;
use App\Services\GroupStandingsCalculator;
use App\Services\KnockoutBracketGenerator;
use App\Services\KnockoutDrawService;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia as Assert;

function createTournamentRosterFixture(int $groupSize = 3): array
{
    $venue = Venue::create([
        'name' => 'Test klub',
        'slug' => 'test-klub',
        'is_active' => true,
    ]);

    $user = User::factory()->create();

    VenueUser::create([
        'venue_id' => $venue->id,
        'user_id' => $user->id,
        'role' => VenueUserRole::ADMIN,
        'is_active' => true,
    ]);

    $tournament = Tournament::create([
        'venue_id' => $venue->id,
        'name' => 'Test turnir',
        'slug' => 'test-turnir',
        'public_code' => 'test-turnir-live',
        'game_type' => GameType::DART_301,
        'match_mode' => MatchMode::SINGLES,
        'status' => TournamentStatus::GROUP_STAGE,
        'group_rounds' => GroupRounds::SINGLE,
        'scoring_mode' => ScoringMode::POINTS_DIFFERENCE,
        'public_enabled' => true,
        'settings' => [
            'group_count' => 1,
            'group_size' => $groupSize,
        ],
        'created_by_user_id' => $user->id,
    ]);

    $group = TournamentGroup::create([
        'tournament_id' => $tournament->id,
        'name' => 'A',
        'sort_order' => 1,
    ]);

    return compact('venue', 'user', 'tournament', 'group');
}

function createTournamentParticipant(
    Venue $venue,
    Tournament $tournament,
    TournamentGroup $group,
    string $position,
    string $firstName
): TournamentParticipant {
    $player = Player::create([
        'first_name' => $firstName,
        'last_name' => 'Testić',
        'is_active' => true,
    ]);

    return TournamentParticipant::create([
        'tournament_id' => $tournament->id,
        'participant_type' => ParticipantType::PLAYER,
        'player_id' => $player->id,
        'tournament_group_id' => $group->id,
        'group_position' => $position,
        'status' => ParticipantStatus::ACTIVE,
    ]);
}

test('late participant addition activates shadow matches and a safe removal hides them again', function () {
    [
        'venue' => $venue,
        'user' => $user,
        'tournament' => $tournament,
        'group' => $group,
    ] = createTournamentRosterFixture();

    createTournamentParticipant($venue, $tournament, $group, 'A1', 'Ana');
    createTournamentParticipant($venue, $tournament, $group, 'A2', 'Bojan');

    expect(app(GroupMatchGenerator::class)->generate($tournament))->toBe(1);
    expect(TournamentMatch::query()->count())->toBe(1);
    expect(
        TournamentMatch::withoutGlobalScope('visible_matches')->count()
    )->toBe(3);

    $latePlayer = Player::create([
        'first_name' => 'Ceca',
        'last_name' => 'Testić',
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->post(route('venues.tournaments.group_draw.store', [$venue, $tournament]), [
            'existing_player_id' => $latePlayer->id,
            'group_position' => 'A3',
        ])
        ->assertRedirect(route('venues.tournaments.group_draw.show', [$venue, $tournament]));

    $lateParticipant = $tournament->participants()
        ->where('group_position', 'A3')
        ->firstOrFail();

    expect(TournamentMatch::query()->count())->toBe(3);
    expect($tournament->fresh()->status)->toBe(TournamentStatus::GROUP_STAGE);

    $this->actingAs($user)
        ->delete(route('venues.tournaments.group_draw.participants.destroy', [
            $venue,
            $tournament,
            $lateParticipant,
        ]))
        ->assertRedirect(route('venues.tournaments.group_draw.show', [$venue, $tournament]));

    expect(TournamentMatch::query()->count())->toBe(1);
    expect(
        TournamentMatch::withoutGlobalScope('visible_matches')
            ->where(function ($query) use ($lateParticipant): void {
                $query
                    ->where('participant_a_position', $lateParticipant->group_position)
                    ->orWhere('participant_b_position', $lateParticipant->group_position);
            })
            ->where('is_hidden', true)
            ->count()
    )->toBe(2);
    expect($tournament->fresh()->status)->toBe(TournamentStatus::GROUP_STAGE);
});

test('an incomplete group match stays hidden even when its legacy flag is wrong', function () {
    [
        'venue' => $venue,
        'user' => $user,
        'tournament' => $tournament,
        'group' => $group,
    ] = createTournamentRosterFixture(2);

    $participant = createTournamentParticipant(
        $venue,
        $tournament,
        $group,
        'A1',
        'Ana',
    );

    TournamentMatch::withoutGlobalScope('visible_matches')->create([
        'tournament_id' => $tournament->id,
        'stage' => MatchStage::GROUP,
        'tournament_group_id' => $group->id,
        'participant_a_id' => $participant->id,
        'participant_a_position' => 'A1',
        'participant_b_id' => null,
        'participant_b_position' => 'A2',
        'is_hidden' => false,
        'status' => MatchStatus::SCHEDULED,
        'scheduled_order' => 1,
        'round_robin_leg' => 1,
        'wins_required' => 1,
    ]);

    expect(
        TournamentMatch::withoutGlobalScope('visible_matches')->count()
    )->toBe(1);
    expect(TournamentMatch::query()->count())->toBe(0);

    $this->actingAs($user)
        ->get(route('venues.tournaments.schedule.index', [$venue, $tournament]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Venues/Tournaments/Schedule/Index')
            ->has('matches', 0)
            ->where('tournament.matches_count', 0)
        );

    $this->get(route('public.tournaments.schedule', $tournament->public_code))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Public/Tournaments/Schedule')
            ->has('matches', 0)
            ->where('tournament.matches_count', 0)
        );
});

test('a participant with a recorded result must be withdrawn instead of removed', function () {
    [
        'venue' => $venue,
        'user' => $user,
        'tournament' => $tournament,
        'group' => $group,
    ] = createTournamentRosterFixture(2);

    $participantA = createTournamentParticipant(
        $venue,
        $tournament,
        $group,
        'A1',
        'Ana',
    );
    $participantB = createTournamentParticipant(
        $venue,
        $tournament,
        $group,
        'A2',
        'Bojan',
    );

    app(GroupMatchGenerator::class)->generate($tournament);

    $this->actingAs($user)
        ->get(route('venues.tournaments.group_draw.show', [$venue, $tournament]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('tournament.groups.0.participants.0.can_remove', true)
        );

    $match = TournamentMatch::query()->firstOrFail();
    $match->update([
        'score_a' => 31,
        'score_b' => 22,
        'winner_participant_id' => $participantA->id,
        'loser_participant_id' => $participantB->id,
        'status' => MatchStatus::FINISHED,
        'finished_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('venues.tournaments.group_draw.show', [$venue, $tournament]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('tournament.groups.0.participants.0.can_remove', false)
        );

    $this->actingAs($user)
        ->from(route('venues.tournaments.group_draw.show', [$venue, $tournament]))
        ->delete(route('venues.tournaments.group_draw.participants.destroy', [
            $venue,
            $tournament,
            $participantA,
        ]))
        ->assertRedirect(route('venues.tournaments.group_draw.show', [$venue, $tournament]))
        ->assertSessionHasErrors('participant');

    expect($participantA->fresh())->not->toBeNull();
    expect($match->fresh()->status)->toBe(MatchStatus::FINISHED);
});

test('a removed player can be added again into another empty slot', function () {
    [
        'venue' => $venue,
        'user' => $user,
        'tournament' => $tournament,
        'group' => $group,
    ] = createTournamentRosterFixture(3);

    $removedParticipant = createTournamentParticipant(
        $venue,
        $tournament,
        $group,
        'A1',
        'Ana',
    );
    createTournamentParticipant($venue, $tournament, $group, 'A2', 'Bojan');

    app(GroupMatchGenerator::class)->generate($tournament);

    $this->actingAs($user)
        ->delete(route('venues.tournaments.group_draw.participants.destroy', [
            $venue,
            $tournament,
            $removedParticipant,
        ]))
        ->assertRedirect();

    $this->actingAs($user)
        ->post(route('venues.tournaments.group_draw.store', [$venue, $tournament]), [
            'existing_player_id' => $removedParticipant->player_id,
            'group_position' => 'A3',
        ])
        ->assertRedirect(route('venues.tournaments.group_draw.show', [$venue, $tournament]))
        ->assertSessionHasNoErrors();

    $restoredParticipant = $tournament->participants()
        ->where('player_id', $removedParticipant->player_id)
        ->firstOrFail();

    expect($restoredParticipant->id)->toBe($removedParticipant->id);
    expect($restoredParticipant->group_position)->toBe('A3');
    expect(
        $tournament->participants()
            ->withTrashed()
            ->where('player_id', $removedParticipant->player_id)
            ->count()
    )->toBe(1);
    expect(
        TournamentMatch::query()
            ->where(function ($query) use ($restoredParticipant): void {
                $query
                    ->where('participant_a_id', $restoredParticipant->id)
                    ->orWhere('participant_b_id', $restoredParticipant->id);
            })
            ->count()
    )->toBe(1);
});

test('knockout scheduled order starts after hidden group matches', function () {
    [
        'venue' => $venue,
        'tournament' => $tournament,
        'group' => $group,
    ] = createTournamentRosterFixture(3);

    createTournamentParticipant($venue, $tournament, $group, 'A1', 'Ana');
    createTournamentParticipant($venue, $tournament, $group, 'A2', 'Bojan');

    app(GroupMatchGenerator::class)->generate($tournament);

    $tournament->update([
        'status' => TournamentStatus::KNOCKOUT_DRAW,
        'knockout_size' => 2,
        'settings' => [
            'group_count' => 1,
            'group_size' => 3,
            'direct_qualifiers_per_group' => 2,
            'repechage_enabled' => false,
        ],
    ]);

    expect(app(KnockoutBracketGenerator::class)->generate($tournament))->toBe(5);

    $groupMaximumOrder = (int) TournamentMatch::withoutGlobalScope('visible_matches')
        ->where('tournament_id', $tournament->id)
        ->where('stage', MatchStage::GROUP->value)
        ->max('scheduled_order');
    $knockoutMinimumOrder = (int) TournamentMatch::withoutGlobalScope('visible_matches')
        ->where('tournament_id', $tournament->id)
        ->where('stage', MatchStage::FINAL->value)
        ->min('scheduled_order');

    expect($groupMaximumOrder)->toBe(3);
    expect($knockoutMinimumOrder)->toBe(4);
});

test('an unused knockout leg is hidden after a two nil series and restored if the result changes', function () {
    [
        'venue' => $venue,
        'user' => $user,
        'tournament' => $tournament,
        'group' => $group,
    ] = createTournamentRosterFixture(2);

    $participantA = createTournamentParticipant(
        $venue,
        $tournament,
        $group,
        'A1',
        'Ana',
    );
    $participantB = createTournamentParticipant(
        $venue,
        $tournament,
        $group,
        'A2',
        'Bojan',
    );

    $tournament->update([
        'status' => TournamentStatus::KNOCKOUT_STAGE,
        'knockout_size' => 2,
    ]);

    $seriesMatches = collect(range(1, 3))
        ->map(fn (int $leg) => TournamentMatch::create([
            'tournament_id' => $tournament->id,
            'stage' => MatchStage::FINAL,
            'bracket_round' => 'final',
            'bracket_position' => 1,
            'participant_a_id' => $participantA->id,
            'participant_b_id' => $participantB->id,
            'is_hidden' => false,
            'status' => MatchStatus::SCHEDULED,
            'scheduled_order' => $leg,
            'round_robin_leg' => $leg,
            'wins_required' => 2,
            'meta' => [
                'round_label' => 'Finale',
                'series_wins_required' => 2,
            ],
        ]));

    foreach ($seriesMatches->take(2) as $seriesMatch) {
        $this->actingAs($user)
            ->patch(route('venues.tournaments.schedule.matches.result', [
                $venue,
                $tournament,
                $seriesMatch,
            ]), [
                'score_a' => 31,
                'score_b' => 20,
            ])
            ->assertRedirect();
    }

    $unusedLeg = TournamentMatch::withoutGlobalScope('visible_matches')
        ->findOrFail($seriesMatches->last()->id);

    expect($unusedLeg->status)->toBe(MatchStatus::VOIDED);
    expect($unusedLeg->is_hidden)->toBeTrue();

    // Stari turniri imaju isti VOIDED red bez is_hidden zastavice.
    $unusedLeg->update(['is_hidden' => false]);

    expect(
        TournamentMatch::query()
            ->where('stage', MatchStage::FINAL->value)
            ->count()
    )->toBe(2);

    $this->get(route('public.tournaments.schedule', $tournament->public_code))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('tournament.matches_count', 2)
            ->where('tournament.finished_matches_count', 2)
            ->where('tournament.voided_matches_count', 0)
            ->has('matches', 2)
            ->where(
                'matches.0.finished_at',
                fn (mixed $finishedAt) => is_string($finishedAt)
                    && preg_match('/^\d{4}-\d{2}-\d{2}T/', $finishedAt) === 1,
            )
        );

    $this->get(route('public.tournaments.knockout', $tournament->public_code))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('tournament.knockout_matches_count', 2)
            ->where('tournament.finished_knockout_matches_count', 2)
            ->where('tournament.voided_knockout_matches_count', 0)
            ->where('rounds.0.series.0.max_legs', 3)
            ->has('rounds.0.series.0.legs', 2)
        );

    $secondLeg = $seriesMatches->get(1);

    $this->actingAs($user)
        ->patch(route('venues.tournaments.schedule.matches.result', [
            $venue,
            $tournament,
            $secondLeg,
        ]), [
            'score_a' => 20,
            'score_b' => 31,
        ])
        ->assertRedirect();

    $restoredLeg = TournamentMatch::query()->findOrFail($unusedLeg->id);

    expect($restoredLeg->status)->toBe(MatchStatus::SCHEDULED);
    expect($restoredLeg->is_hidden)->toBeFalse();
    expect($tournament->fresh()->status)->toBe(TournamentStatus::KNOCKOUT_STAGE);
});

test('a completed knockout result cannot change after its dependent series starts', function () {
    [
        'venue' => $venue,
        'user' => $user,
        'tournament' => $tournament,
        'group' => $group,
    ] = createTournamentRosterFixture(4);

    $participants = collect([
        createTournamentParticipant($venue, $tournament, $group, 'A1', 'Ana'),
        createTournamentParticipant($venue, $tournament, $group, 'A2', 'Bojan'),
        createTournamentParticipant($venue, $tournament, $group, 'A3', 'Ceca'),
        createTournamentParticipant($venue, $tournament, $group, 'A4', 'Dejan'),
    ]);

    $tournament->update(['status' => TournamentStatus::KNOCKOUT_STAGE]);

    $sourceMatch = TournamentMatch::create([
        'tournament_id' => $tournament->id,
        'stage' => MatchStage::KNOCKOUT,
        'bracket_round' => 'semi_final',
        'bracket_position' => 1,
        'participant_a_id' => $participants[0]->id,
        'participant_b_id' => $participants[1]->id,
        'score_a' => 31,
        'score_b' => 20,
        'winner_participant_id' => $participants[0]->id,
        'loser_participant_id' => $participants[1]->id,
        'status' => MatchStatus::FINISHED,
        'scheduled_order' => 1,
        'round_robin_leg' => 1,
        'wins_required' => 1,
        'finished_at' => now(),
    ]);

    TournamentMatch::create([
        'tournament_id' => $tournament->id,
        'stage' => MatchStage::FINAL,
        'bracket_round' => 'final',
        'bracket_position' => 1,
        'participant_a_id' => $participants[0]->id,
        'participant_b_id' => $participants[2]->id,
        'score_a' => 10,
        'score_b' => 5,
        'winner_participant_id' => $participants[0]->id,
        'loser_participant_id' => $participants[2]->id,
        'status' => MatchStatus::FINISHED,
        'scheduled_order' => 2,
        'round_robin_leg' => 1,
        'wins_required' => 3,
        'finished_at' => now(),
        'meta' => [
            'participant_a_source_round' => 'semi_final',
            'participant_a_source_position' => 1,
            'participant_a_source_outcome' => 'winner',
        ],
    ]);

    $this->actingAs($user)
        ->from(route('venues.tournaments.schedule.index', [$venue, $tournament]))
        ->patch(route('venues.tournaments.schedule.matches.result', [
            $venue,
            $tournament,
            $sourceMatch,
        ]), [
            'score_a' => 18,
            'score_b' => 31,
        ])
        ->assertRedirect(route('venues.tournaments.schedule.index', [$venue, $tournament]))
        ->assertSessionHasErrors('score');

    expect($sourceMatch->fresh()->winner_participant_id)->toBe($participants[0]->id);
    expect($sourceMatch->fresh()->score_a)->toBe(31);
    expect($sourceMatch->fresh()->score_b)->toBe(20);
});

test('tournament finishes only after both final and third place series are decided', function () {
    [
        'venue' => $venue,
        'user' => $user,
        'tournament' => $tournament,
        'group' => $group,
    ] = createTournamentRosterFixture(4);

    $participants = collect([
        createTournamentParticipant($venue, $tournament, $group, 'A1', 'Ana'),
        createTournamentParticipant($venue, $tournament, $group, 'A2', 'Bojan'),
        createTournamentParticipant($venue, $tournament, $group, 'A3', 'Ceca'),
        createTournamentParticipant($venue, $tournament, $group, 'A4', 'Dejan'),
    ]);

    $tournament->update(['status' => TournamentStatus::KNOCKOUT_STAGE]);

    $final = TournamentMatch::create([
        'tournament_id' => $tournament->id,
        'stage' => MatchStage::FINAL,
        'bracket_round' => 'final',
        'bracket_position' => 1,
        'participant_a_id' => $participants[0]->id,
        'participant_b_id' => $participants[1]->id,
        'status' => MatchStatus::SCHEDULED,
        'scheduled_order' => 1,
        'round_robin_leg' => 1,
        'wins_required' => 1,
    ]);
    $thirdPlace = TournamentMatch::create([
        'tournament_id' => $tournament->id,
        'stage' => MatchStage::THIRD_PLACE,
        'bracket_round' => 'third_place',
        'bracket_position' => 1,
        'participant_a_id' => $participants[2]->id,
        'participant_b_id' => $participants[3]->id,
        'status' => MatchStatus::SCHEDULED,
        'scheduled_order' => 2,
        'round_robin_leg' => 1,
        'wins_required' => 1,
    ]);

    $this->actingAs($user)
        ->patch(route('venues.tournaments.schedule.matches.result', [
            $venue,
            $tournament,
            $final,
        ]), [
            'score_a' => 31,
            'score_b' => 20,
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($tournament->fresh()->status)->toBe(TournamentStatus::KNOCKOUT_STAGE);
    expect($tournament->fresh()->finished_at)->toBeNull();

    $this->actingAs($user)
        ->patch(route('venues.tournaments.schedule.matches.result', [
            $venue,
            $tournament,
            $thirdPlace,
        ]), [
            'score_a' => 31,
            'score_b' => 24,
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($tournament->fresh()->status)->toBe(TournamentStatus::FINISHED);
    expect($tournament->fresh()->finished_at)->not->toBeNull();
});

test('manual qualification overrides replace automatic group slots without adding extra qualifiers', function () {
    [
        'venue' => $venue,
        'user' => $user,
        'tournament' => $tournament,
        'group' => $group,
    ] = createTournamentRosterFixture(5);

    $participants = collect([
        createTournamentParticipant($venue, $tournament, $group, 'A1', 'Ana'),
        createTournamentParticipant($venue, $tournament, $group, 'A2', 'Bojan'),
        createTournamentParticipant($venue, $tournament, $group, 'A3', 'Ceca'),
        createTournamentParticipant($venue, $tournament, $group, 'A4', 'Dejan'),
        createTournamentParticipant($venue, $tournament, $group, 'A5', 'Ema'),
    ]);

    $tournament->update([
        'status' => TournamentStatus::KNOCKOUT_DRAW,
        'knockout_size' => 4,
        'settings' => [
            'group_count' => 1,
            'group_size' => 5,
            'direct_qualifiers_per_group' => 4,
            'repechage_enabled' => false,
        ],
    ]);

    $this->actingAs($user)
        ->patch(route(
            'venues.tournaments.standings.participants.qualification_override',
            [$venue, $tournament, $participants->first()],
        ), [
            'qualification_override_status' => QualificationStatus::ELIMINATED->value,
        ])
        ->assertRedirect();

    $this->actingAs($user)
        ->patch(route(
            'venues.tournaments.standings.participants.qualification_override',
            [$venue, $tournament, $participants->last()],
        ), [
            'qualification_override_status' => QualificationStatus::DIRECT->value,
        ])
        ->assertRedirect();

    $rows = collect(app(GroupStandingsCalculator::class)->calculate($tournament)[0]['rows']);

    expect($rows->where('qualification_status', 'direct'))->toHaveCount(4);
    expect(
        $rows->firstWhere('participant_id', $participants->first()->id)['qualification_status']
    )->toBe('eliminated');
    expect(
        $rows->firstWhere('participant_id', $participants->last()->id)['qualification_status']
    )->toBe('direct');

    $this->actingAs($user)
        ->get(route('venues.tournaments.knockout.index', [$venue, $tournament]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('tournament.knockout_participants_count', 4)
            ->where('tournament.is_knockout_ready', true)
            ->where('tournament.can_generate_knockout_bracket', true)
        );
});

test('qualification settings must produce exactly the configured knockout size', function () {
    [
        'venue' => $venue,
        'user' => $user,
        'tournament' => $tournament,
    ] = createTournamentRosterFixture(5);

    $tournament->update(['knockout_size' => 4]);

    $this->actingAs($user)
        ->from(route('venues.tournaments.qualification.setup', [$venue, $tournament]))
        ->post(route('venues.tournaments.qualification.store', [$venue, $tournament]), [
            'direct_qualifiers_per_group' => 3,
            'repechage_enabled' => false,
            'repechage_participants_count' => null,
            'repechage_qualifiers_count' => null,
        ])
        ->assertRedirect(route('venues.tournaments.qualification.setup', [$venue, $tournament]))
        ->assertSessionHasErrors('direct_qualifiers_per_group');

    $this->actingAs($user)
        ->post(route('venues.tournaments.qualification.store', [$venue, $tournament]), [
            'direct_qualifiers_per_group' => 4,
            'repechage_enabled' => false,
            'repechage_participants_count' => null,
            'repechage_qualifiers_count' => null,
        ])
        ->assertRedirect(route('venues.tournaments.standings.index', [$venue, $tournament]))
        ->assertSessionHasNoErrors();

    expect(data_get(
        $tournament->fresh()->settings,
        'direct_qualifiers_per_group',
    ))->toBe(4);
});

test('qualification override is blocked after knockout bracket generation', function () {
    [
        'venue' => $venue,
        'user' => $user,
        'tournament' => $tournament,
        'group' => $group,
    ] = createTournamentRosterFixture(2);

    $participant = createTournamentParticipant(
        $venue,
        $tournament,
        $group,
        'A1',
        'Ana',
    );

    $tournament->update(['status' => TournamentStatus::KNOCKOUT_STAGE]);
    TournamentMatch::create([
        'tournament_id' => $tournament->id,
        'stage' => MatchStage::FINAL,
        'bracket_round' => 'final',
        'bracket_position' => 1,
        'participant_a_id' => $participant->id,
        'status' => MatchStatus::SCHEDULED,
        'scheduled_order' => 1,
        'round_robin_leg' => 1,
        'wins_required' => 1,
    ]);

    $this->actingAs($user)
        ->patch(route(
            'venues.tournaments.standings.participants.qualification_override',
            [$venue, $tournament, $participant],
        ), [
            'qualification_override_status' => QualificationStatus::ELIMINATED->value,
        ])
        ->assertRedirect()
        ->assertSessionHasErrors('qualification_override_status');

    expect($participant->fresh()->qualification_override_status)->toBeNull();
});

test('repechage outcome cannot change outside the repechage stage', function () {
    [
        'venue' => $venue,
        'user' => $user,
        'tournament' => $tournament,
        'group' => $group,
    ] = createTournamentRosterFixture(2);

    $participant = createTournamentParticipant(
        $venue,
        $tournament,
        $group,
        'A1',
        'Ana',
    );
    $tournament->update(['status' => TournamentStatus::KNOCKOUT_DRAW]);

    $this->actingAs($user)
        ->patch(route(
            'venues.tournaments.repechage.participants.outcome',
            [$venue, $tournament, $participant],
        ), [
            'repechage_outcome_status' => RepechageOutcomeStatus::ADVANCED->value,
        ])
        ->assertRedirect()
        ->assertSessionHasErrors('repechage_outcome_status');

    expect($participant->fresh()->repechage_outcome_status)->toBeNull();
});

test('active repechage is public and outcome changes are reflected immediately', function () {
    [
        'venue' => $venue,
        'user' => $user,
        'tournament' => $tournament,
        'group' => $group,
    ] = createTournamentRosterFixture(3);

    $participants = collect([
        createTournamentParticipant($venue, $tournament, $group, 'A1', 'Ana'),
        createTournamentParticipant($venue, $tournament, $group, 'A2', 'Bojan'),
        createTournamentParticipant($venue, $tournament, $group, 'A3', 'Ceca'),
    ]);

    collect([
        [$participants[0], $participants[1], 3, 1],
        [$participants[0], $participants[2], 3, 0],
        [$participants[1], $participants[2], 2, 1],
    ])->each(function (array $result, int $index) use ($tournament, $group): void {
        [$participantA, $participantB, $scoreA, $scoreB] = $result;

        TournamentMatch::create([
            'tournament_id' => $tournament->id,
            'stage' => MatchStage::GROUP,
            'tournament_group_id' => $group->id,
            'participant_a_id' => $participantA->id,
            'participant_b_id' => $participantB->id,
            'score_a' => $scoreA,
            'score_b' => $scoreB,
            'winner_participant_id' => $participantA->id,
            'loser_participant_id' => $participantB->id,
            'status' => MatchStatus::FINISHED,
            'scheduled_order' => $index + 1,
            'round_robin_leg' => 1,
            'wins_required' => 1,
            'finished_at' => now(),
        ]);
    });

    $tournament->update([
        'status' => TournamentStatus::REPECHAGE,
        'settings' => array_merge($tournament->settings ?? [], [
            'direct_qualifiers_per_group' => 0,
            'repechage_enabled' => true,
            'repechage_participants_count' => 2,
            'repechage_qualifiers_count' => 1,
        ]),
    ]);

    $this->get(route('public.tournaments.knockout', $tournament->public_code))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Public/Tournaments/Knockout')
            ->where('tournament.status', TournamentStatus::REPECHAGE->value)
            ->where('tournament.repechage_qualifiers_count', 1)
            ->has('repechage_participants', 2)
            ->where('repechage_participants.0.display_name', 'Ana Testić')
            ->where('repechage_participants.0.repechage_outcome_status', null)
            ->where(
                'repechage_participants.0.profile_url',
                route('public.players.show', $participants[0]->player, false),
            )
        );

    Event::fake([TournamentLiveUpdated::class]);

    $this->actingAs($user)
        ->patch(route(
            'venues.tournaments.repechage.participants.outcome',
            [$venue, $tournament, $participants[0]],
        ), [
            'repechage_outcome_status' => RepechageOutcomeStatus::ADVANCED->value,
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    Event::assertDispatched(
        TournamentLiveUpdated::class,
        fn (TournamentLiveUpdated $event): bool => $event->reason === 'repechage_outcome_updated',
    );

    $this->get(route('public.tournaments.knockout', $tournament->public_code))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where(
                'repechage_participants.0.repechage_outcome_status',
                RepechageOutcomeStatus::ADVANCED->value,
            )
        );

    $this->actingAs($user)
        ->post(route('venues.tournaments.repechage.complete', [$venue, $tournament]))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $this->get(route('public.tournaments.knockout', $tournament->public_code))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('tournament.status', TournamentStatus::KNOCKOUT_DRAW->value)
            ->has('repechage_participants', 0)
        );
});

test('knockout draw follows the group placement matrix and alternates resources', function () {
    [
        'venue' => $venue,
        'tournament' => $tournament,
        'group' => $firstGroup,
    ] = createTournamentRosterFixture(4);

    $groups = collect([$firstGroup]);

    foreach (range('B', 'H') as $index => $groupName) {
        $groups->push(TournamentGroup::create([
            'tournament_id' => $tournament->id,
            'name' => $groupName,
            'sort_order' => $index + 2,
        ]));
    }

    foreach ($groups as $group) {
        foreach (range(1, 4) as $rank) {
            createTournamentParticipant(
                $venue,
                $tournament,
                $group,
                $group->name.$rank,
                $group->name.$rank,
            );
        }
    }

    $resources = collect(range(1, 8))
        ->map(fn (int $board) => TournamentResource::create([
            'tournament_id' => $tournament->id,
            'name' => 'Pikado '.$board,
            'type' => ResourceType::DART_BOARD,
            'sort_order' => $board,
            'is_active' => true,
        ]));

    $tournament->update([
        'status' => TournamentStatus::KNOCKOUT_DRAW,
        'knockout_size' => 32,
        'settings' => [
            'group_count' => 8,
            'group_size' => 4,
            'direct_qualifiers_per_group' => 4,
            'repechage_enabled' => false,
            'avoid_same_group_rematch' => true,
        ],
    ]);

    expect(app(KnockoutBracketGenerator::class)->generate($tournament))->toBeGreaterThan(0);

    $firstRoundMatches = TournamentMatch::query()
        ->with(['participantA', 'participantB'])
        ->where('tournament_id', $tournament->id)
        ->where('bracket_round', 'round_of_32')
        ->where('round_robin_leg', 1)
        ->orderBy('bracket_position')
        ->get();

    expect($firstRoundMatches)->toHaveCount(16);

    expect(
        $firstRoundMatches
            ->map(fn (TournamentMatch $match) => [
                $match->participantA->group_position,
                $match->participantB->group_position,
            ])
            ->all()
    )->toBe([
        ['A1', 'D4'],
        ['B2', 'C3'],
        ['C1', 'F4'],
        ['D2', 'E3'],
        ['E1', 'H4'],
        ['F2', 'G3'],
        ['G1', 'B4'],
        ['H2', 'A3'],
        ['B1', 'G4'],
        ['A2', 'H3'],
        ['D1', 'A4'],
        ['C2', 'B3'],
        ['F1', 'C4'],
        ['E2', 'D3'],
        ['H1', 'E4'],
        ['G2', 'F3'],
    ]);

    $nextRoundMatches = TournamentMatch::query()
        ->where('tournament_id', $tournament->id)
        ->where('bracket_round', 'round_of_16')
        ->where('round_robin_leg', 1)
        ->orderBy('bracket_position')
        ->get();

    foreach ($firstRoundMatches as $index => $match) {
        expect($match->tournament_resource_id)
            ->toBe($resources[$index % $resources->count()]->id);
    }

    foreach ($nextRoundMatches as $index => $match) {
        expect($match->tournament_resource_id)
            ->toBe($resources[$index % $resources->count()]->id);
    }
});

test('knockout labels use final group rank instead of the original draw slot', function () {
    [
        'venue' => $venue,
        'user' => $user,
        'tournament' => $tournament,
        'group' => $group,
    ] = createTournamentRosterFixture(3);

    $initialA1 = createTournamentParticipant(
        $venue,
        $tournament,
        $group,
        'A1',
        'Ana',
    );
    $initialA2 = createTournamentParticipant(
        $venue,
        $tournament,
        $group,
        'A2',
        'Bojan',
    );
    $initialA3 = createTournamentParticipant(
        $venue,
        $tournament,
        $group,
        'A3',
        'Ceca',
    );

    $groupResults = [
        [$initialA3, $initialA1, 31, 20],
        [$initialA3, $initialA2, 31, 18],
        [$initialA1, $initialA2, 31, 25],
    ];

    foreach ($groupResults as $index => [$winner, $loser, $scoreA, $scoreB]) {
        TournamentMatch::create([
            'tournament_id' => $tournament->id,
            'stage' => MatchStage::GROUP,
            'tournament_group_id' => $group->id,
            'participant_a_id' => $winner->id,
            'participant_b_id' => $loser->id,
            'participant_a_position' => $winner->group_position,
            'participant_b_position' => $loser->group_position,
            'is_hidden' => false,
            'score_a' => $scoreA,
            'score_b' => $scoreB,
            'winner_participant_id' => $winner->id,
            'loser_participant_id' => $loser->id,
            'status' => MatchStatus::FINISHED,
            'scheduled_order' => $index + 1,
            'round_robin_leg' => 1,
            'wins_required' => 1,
            'finished_at' => now(),
        ]);
    }

    $standings = app(GroupStandingsCalculator::class)->calculate($tournament);

    expect($standings[0]['rows'][0]['participant_id'])->toBe($initialA3->id);
    expect($standings[0]['rows'][0]['group_position'])->toBe('A3');
    expect($standings[0]['rows'][0]['qualification_position'])->toBe('A1');
    expect($standings[0]['rows'][1]['participant_id'])->toBe($initialA1->id);
    expect($standings[0]['rows'][1]['qualification_position'])->toBe('A2');

    $tournament->update([
        'status' => TournamentStatus::KNOCKOUT_STAGE,
        'knockout_size' => 2,
    ]);

    TournamentMatch::create([
        'tournament_id' => $tournament->id,
        'stage' => MatchStage::KNOCKOUT,
        'bracket_round' => 'semi_final',
        'bracket_position' => 1,
        'participant_a_id' => $initialA3->id,
        'participant_b_id' => $initialA1->id,
        'is_hidden' => false,
        'status' => MatchStatus::SCHEDULED,
        'scheduled_order' => 4,
        'round_robin_leg' => 1,
        'wins_required' => 2,
    ]);

    $this->actingAs($user)
        ->get(route('venues.tournaments.schedule.index', [$venue, $tournament]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('matches.3.participant_a.qualification_position', 'A1')
            ->where('matches.3.participant_b.qualification_position', 'A2')
        );

    $this->get(route('public.tournaments.schedule', $tournament->public_code))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('matches.3.participant_a.qualification_position', 'A1')
            ->where('matches.3.participant_b.qualification_position', 'A2')
        );

    $this->get(route('public.tournaments.live', $tournament->public_code))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('next_matches.0.participant_a.qualification_position', 'A1')
            ->where('next_matches.0.participant_b.qualification_position', 'A2')
        );

    $this->actingAs($user)
        ->get(route('venues.tournaments.knockout.index', [$venue, $tournament]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where(
                'knockout_series.0.series.0.participant_a.qualification_position',
                'A1',
            )
            ->where(
                'knockout_series.0.series.0.participant_b.qualification_position',
                'A2',
            )
        );

    $this->get(route('public.tournaments.knockout', $tournament->public_code))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where(
                'rounds.0.series.0.participant_a.qualification_position',
                'A1',
            )
            ->where(
                'rounds.0.series.0.participant_b.qualification_position',
                'A2',
            )
        );
});

test('an empty score field is saved as zero', function () {
    [
        'venue' => $venue,
        'user' => $user,
        'tournament' => $tournament,
        'group' => $group,
    ] = createTournamentRosterFixture(2);

    $participantA = createTournamentParticipant($venue, $tournament, $group, 'A1', 'Ana');
    $participantB = createTournamentParticipant($venue, $tournament, $group, 'A2', 'Bojan');
    $match = TournamentMatch::create([
        'tournament_id' => $tournament->id,
        'stage' => MatchStage::GROUP,
        'tournament_group_id' => $group->id,
        'participant_a_id' => $participantA->id,
        'participant_b_id' => $participantB->id,
        'is_hidden' => false,
        'status' => MatchStatus::SCHEDULED,
        'scheduled_order' => 1,
        'round_robin_leg' => 1,
        'wins_required' => 1,
    ]);

    $this->actingAs($user)
        ->patch(route('venues.tournaments.schedule.matches.result', [
            $venue,
            $tournament,
            $match,
        ]), [
            'score_a' => null,
            'score_b' => 43,
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($match->fresh())
        ->score_a->toBe(0)
        ->score_b->toBe(43)
        ->winner_participant_id->toBe($participantB->id)
        ->status->toBe(MatchStatus::FINISHED);
});

test('a postponed match leaves the live boards and can be restored in place', function () {
    [
        'venue' => $venue,
        'user' => $user,
        'tournament' => $tournament,
        'group' => $group,
    ] = createTournamentRosterFixture(2);

    $participantA = createTournamentParticipant($venue, $tournament, $group, 'A1', 'Ana');
    $participantB = createTournamentParticipant($venue, $tournament, $group, 'A2', 'Bojan');
    $resource = TournamentResource::create([
        'tournament_id' => $tournament->id,
        'name' => 'Levi pikado',
        'type' => ResourceType::DART_BOARD,
        'sort_order' => 1,
        'is_active' => true,
    ]);
    $match = TournamentMatch::create([
        'tournament_id' => $tournament->id,
        'stage' => MatchStage::GROUP,
        'tournament_group_id' => $group->id,
        'participant_a_id' => $participantA->id,
        'participant_b_id' => $participantB->id,
        'is_hidden' => false,
        'status' => MatchStatus::SCHEDULED,
        'tournament_resource_id' => $resource->id,
        'scheduled_order' => 7,
        'round_robin_leg' => 1,
        'wins_required' => 1,
    ]);

    $this->actingAs($user)
        ->patch(route('venues.tournaments.schedule.matches.postponement', [
            $venue,
            $tournament,
            $match,
        ]), ['postponed' => true])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($match->fresh())
        ->status->toBe(MatchStatus::POSTPONED)
        ->scheduled_order->toBe(7);

    $this->get(route('public.tournaments.live', $tournament->public_code))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('current_matches', 0)
            ->has('postponed_matches', 1)
            ->where('postponed_matches.0.id', $match->id)
        );

    $this->actingAs($user)
        ->patch(route('venues.tournaments.schedule.matches.postponement', [
            $venue,
            $tournament,
            $match,
        ]), ['postponed' => false])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($match->fresh())
        ->status->toBe(MatchStatus::SCHEDULED)
        ->scheduled_order->toBe(7);
});

test('non power of two knockout sizes use a real preliminary round without bye matches', function (int $knockoutSize, int $preliminarySeriesCount, string $mainRound) {
    [
        'venue' => $venue,
        'tournament' => $tournament,
        'group' => $group,
    ] = createTournamentRosterFixture($knockoutSize);

    foreach (range(1, $knockoutSize) as $position) {
        createTournamentParticipant(
            $venue,
            $tournament,
            $group,
            'A'.$position,
            sprintf('Igrac %02d', $position),
        );
    }

    $tournament->update([
        'status' => TournamentStatus::KNOCKOUT_DRAW,
        'knockout_size' => $knockoutSize,
        'settings' => [
            'group_count' => 1,
            'group_size' => $knockoutSize,
            'direct_qualifiers_per_group' => $knockoutSize,
            'repechage_enabled' => false,
            'avoid_same_group_rematch' => true,
        ],
    ]);

    expect(app(KnockoutBracketGenerator::class)->generate($tournament))
        ->toBeGreaterThan(0);

    $preliminaryMatches = TournamentMatch::query()
        ->where('tournament_id', $tournament->id)
        ->where('bracket_round', 'preliminary')
        ->get();
    $firstMainRoundMatches = TournamentMatch::query()
        ->where('tournament_id', $tournament->id)
        ->where('bracket_round', $mainRound)
        ->get();

    expect($preliminaryMatches)
        ->toHaveCount($preliminarySeriesCount * 3)
        ->each(fn ($match) => $match
            ->participant_a_id->not->toBeNull()
            ->participant_b_id->not->toBeNull()
        );
    expect($firstMainRoundMatches)->not->toBeEmpty();
    expect(
        TournamentMatch::query()
            ->where('tournament_id', $tournament->id)
            ->whereNotNull('participant_a_id')
            ->whereNull('participant_b_id')
            ->where('bracket_round', 'preliminary')
            ->count(),
    )->toBe(0);
})->with([
    'Top 12' => [12, 4, 'quarter_final'],
    'Top 20' => [20, 4, 'round_of_16'],
    'Top 24' => [24, 8, 'round_of_16'],
]);

test('live current matches always follow resource sort order', function () {
    [
        'venue' => $venue,
        'tournament' => $tournament,
        'group' => $group,
    ] = createTournamentRosterFixture(4);

    $participants = collect([
        createTournamentParticipant($venue, $tournament, $group, 'A1', 'Ana'),
        createTournamentParticipant($venue, $tournament, $group, 'A2', 'Bojan'),
        createTournamentParticipant($venue, $tournament, $group, 'A3', 'Ceca'),
        createTournamentParticipant($venue, $tournament, $group, 'A4', 'Dejan'),
    ]);

    $leftResource = TournamentResource::create([
        'tournament_id' => $tournament->id,
        'name' => 'Levi pikado',
        'type' => ResourceType::DART_BOARD,
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $rightResource = TournamentResource::create([
        'tournament_id' => $tournament->id,
        'name' => 'Desni pikado',
        'type' => ResourceType::DART_BOARD,
        'sort_order' => 2,
        'is_active' => true,
    ]);

    TournamentMatch::create([
        'tournament_id' => $tournament->id,
        'stage' => MatchStage::GROUP,
        'tournament_group_id' => $group->id,
        'participant_a_id' => $participants[0]->id,
        'participant_b_id' => $participants[1]->id,
        'participant_a_position' => 'A1',
        'participant_b_position' => 'A2',
        'is_hidden' => false,
        'status' => MatchStatus::SCHEDULED,
        'tournament_resource_id' => $rightResource->id,
        'scheduled_order' => 1,
        'round_robin_leg' => 1,
        'wins_required' => 1,
    ]);

    TournamentMatch::create([
        'tournament_id' => $tournament->id,
        'stage' => MatchStage::GROUP,
        'tournament_group_id' => $group->id,
        'participant_a_id' => $participants[2]->id,
        'participant_b_id' => $participants[3]->id,
        'participant_a_position' => 'A3',
        'participant_b_position' => 'A4',
        'is_hidden' => false,
        'status' => MatchStatus::SCHEDULED,
        'tournament_resource_id' => $leftResource->id,
        'scheduled_order' => 2,
        'round_robin_leg' => 1,
        'wins_required' => 1,
    ]);

    $this->get(route('public.tournaments.live', $tournament->public_code))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Public/Tournaments/Live')
            ->where('current_matches.0.resource_name', 'Levi pikado')
            ->where('current_matches.1.resource_name', 'Desni pikado')
        );
});

test('admin schedule marks the exact matches shown now and next on the public live screen', function () {
    [
        'venue' => $venue,
        'user' => $user,
        'tournament' => $tournament,
        'group' => $group,
    ] = createTournamentRosterFixture(6);

    $participants = collect(range(1, 6))->map(fn (int $position) => createTournamentParticipant(
        $venue,
        $tournament,
        $group,
        'A'.$position,
        'Igrač'.$position,
    ));
    $leftResource = TournamentResource::create([
        'tournament_id' => $tournament->id,
        'name' => 'Levi pikado',
        'type' => ResourceType::DART_BOARD,
        'sort_order' => 1,
        'is_active' => true,
    ]);
    $rightResource = TournamentResource::create([
        'tournament_id' => $tournament->id,
        'name' => 'Desni pikado',
        'type' => ResourceType::DART_BOARD,
        'sort_order' => 2,
        'is_active' => true,
    ]);

    $matches = collect([
        [$participants[0], $participants[1], $rightResource, 1],
        [$participants[2], $participants[3], $leftResource, 2],
        [$participants[4], $participants[5], $leftResource, 3],
    ])->map(fn (array $data) => TournamentMatch::create([
        'tournament_id' => $tournament->id,
        'stage' => MatchStage::GROUP,
        'tournament_group_id' => $group->id,
        'participant_a_id' => $data[0]->id,
        'participant_b_id' => $data[1]->id,
        'is_hidden' => false,
        'status' => MatchStatus::SCHEDULED,
        'tournament_resource_id' => $data[2]->id,
        'scheduled_order' => $data[3],
        'round_robin_leg' => 1,
        'wins_required' => 1,
    ]));

    $this->get(route('public.tournaments.live', $tournament->public_code))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('current_matches.0.id', $matches[1]->id)
            ->where('current_matches.1.id', $matches[0]->id)
            ->where('next_matches.0.id', $matches[2]->id)
        );

    $this->actingAs($user)
        ->get(route('venues.tournaments.schedule.index', [$venue, $tournament]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('matches.0.live_queue', 'current')
            ->where('matches.0.live_queue_order', 2)
            ->where('matches.1.live_queue', 'current')
            ->where('matches.1.live_queue_order', 1)
            ->where('matches.2.live_queue', 'next')
            ->where('matches.2.live_queue_order', 1)
        );

    Event::fake([TournamentLiveUpdated::class]);

    $this->actingAs($user)
        ->patch(route('venues.tournaments.schedule.matches.resource', [
            $venue,
            $tournament,
            $matches[2],
        ]), ['tournament_resource_id' => $rightResource->id])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    Event::assertDispatched(
        TournamentLiveUpdated::class,
        fn (TournamentLiveUpdated $event) => $event->tournament->is($tournament)
            && $event->reason === 'match_resource_updated',
    );
});

test('tournament date defaults to today and can be explicitly selected', function () {
    [
        'venue' => $venue,
        'user' => $user,
    ] = createTournamentRosterFixture(2);

    $this->actingAs($user)
        ->get(route('venues.tournaments.create', $venue))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('options.default_date', now()->toDateString())
        );

    $this->actingAs($user)
        ->post(route('venues.tournaments.store', $venue), [
            'name' => 'Turnir sa datumom',
            'tournament_date' => '2026-09-12',
            'game_type' => GameType::DART_301->value,
            'match_mode' => MatchMode::SINGLES->value,
            'group_rounds' => GroupRounds::SINGLE->value,
            'group_count' => 2,
            'group_size' => 4,
            'direct_qualifiers_per_group' => 4,
            'repechage_enabled' => false,
            'repechage_participants_count' => null,
            'repechage_qualifiers_count' => null,
            'knockout_size' => 8,
            'public_enabled' => true,
            'resource_ids' => [],
        ])
        ->assertSessionHasNoErrors();

    expect(Tournament::query()->where('name', 'Turnir sa datumom')->firstOrFail())
        ->tournament_date->toDateString()->toBe('2026-09-12');
});

test('repechage lucky draw fills seeded pairs and becomes the generated bracket order', function () {
    [
        'venue' => $venue,
        'user' => $user,
        'tournament' => $tournament,
        'group' => $group,
    ] = createTournamentRosterFixture(4);

    $participants = collect(range(1, 4))->map(fn (int $position) => createTournamentParticipant(
        $venue,
        $tournament,
        $group,
        'A'.$position,
        'Igrac '.$position,
    )
    );

    $participants->slice(2)->each(fn (TournamentParticipant $participant) => $participant->update(['repechage_outcome_status' => RepechageOutcomeStatus::ADVANCED])
    );

    $tournament->update([
        'status' => TournamentStatus::KNOCKOUT_DRAW,
        'knockout_size' => 4,
        'settings' => [
            'group_count' => 1,
            'group_size' => 4,
            'direct_qualifiers_per_group' => 2,
            'repechage_enabled' => true,
            'repechage_participants_count' => 2,
            'repechage_qualifiers_count' => 2,
        ],
    ]);

    $initialState = app(KnockoutDrawService::class)->state($tournament->fresh());
    expect($initialState)
        ->enabled->toBeTrue()
        ->complete->toBeFalse()
        ->seeded_count->toBe(2);

    foreach (range(1, 4) as $draw) {
        $this->actingAs($user)
            ->post(route('venues.tournaments.knockout.draw.next', [$venue, $tournament]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();
    }

    $completedState = app(KnockoutDrawService::class)->state($tournament->fresh());
    expect($completedState)
        ->complete->toBeTrue()
        ->remaining_count->toBe(0);

    $this->get(route('public.tournaments.knockout', $tournament->public_code))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('knockout_draw.complete', true)
            ->has('knockout_draw.slots', 2)
        );

    $this->actingAs($user)
        ->post(route('venues.tournaments.knockout.generate', [$venue, $tournament]))
        ->assertRedirect(route('venues.tournaments.schedule.index', [$venue, $tournament]))
        ->assertSessionHasNoErrors();

    $firstRound = TournamentMatch::query()
        ->where('tournament_id', $tournament->id)
        ->where('bracket_round', 'semi_final')
        ->where('round_robin_leg', 1)
        ->orderBy('bracket_position')
        ->get();

    expect($firstRound)->toHaveCount(2);

    foreach ($completedState['slots'] as $index => $slot) {
        expect($firstRound[$index]->participant_a_id)
            ->toBe($slot['seeded']['participant_id']);
        expect($firstRound[$index]->participant_b_id)
            ->toBe($slot['unseeded']['participant_id']);
    }
});

test('manual withdrawal keeps played results and leaves remaining matches editable with suggestions', function () {
    [
        'venue' => $venue,
        'user' => $user,
        'tournament' => $tournament,
        'group' => $group,
    ] = createTournamentRosterFixture(3);

    $participantA = createTournamentParticipant($venue, $tournament, $group, 'A1', 'Ana');
    $participantB = createTournamentParticipant($venue, $tournament, $group, 'A2', 'Bojan');
    createTournamentParticipant($venue, $tournament, $group, 'A3', 'Ceca');
    app(GroupMatchGenerator::class)->generate($tournament);

    $played = TournamentMatch::query()
        ->where(function ($query) use ($participantA, $participantB): void {
            $query
                ->where(function ($pair) use ($participantA, $participantB): void {
                    $pair->where('participant_a_id', $participantA->id)
                        ->where('participant_b_id', $participantB->id);
                })
                ->orWhere(function ($pair) use ($participantA, $participantB): void {
                    $pair->where('participant_a_id', $participantB->id)
                        ->where('participant_b_id', $participantA->id);
                });
        })
        ->firstOrFail();
    $played->update([
        'score_a' => $played->participant_a_id === $participantA->id ? 31 : 20,
        'score_b' => $played->participant_b_id === $participantA->id ? 31 : 20,
        'winner_participant_id' => $participantA->id,
        'loser_participant_id' => $participantB->id,
        'status' => MatchStatus::FINISHED,
        'finished_at' => now(),
    ]);

    $this->actingAs($user)
        ->patch(route('venues.tournaments.standings.participants.withdraw', [
            $venue,
            $tournament,
            $participantA,
        ]), [
            'withdrawal_policy' => WithdrawalPolicy::KEEP_PLAYED_MANUAL_REST->value,
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($played->fresh()->status)->toBe(MatchStatus::FINISHED);
    expect(
        TournamentMatch::query()
            ->where('status', MatchStatus::POSTPONED->value)
            ->where(function ($query) use ($participantA): void {
                $query->where('participant_a_id', $participantA->id)
                    ->orWhere('participant_b_id', $participantA->id);
            })
            ->count()
    )->toBe(1);

    $this->actingAs($user)
        ->get(route('venues.tournaments.schedule.index', [$venue, $tournament]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('matches.0.participant_a.score_suggestion.matches_count', 1)
        );
});

test('admin schedule exposes and completes the group stage after the final result', function () {
    [
        'venue' => $venue,
        'user' => $user,
        'tournament' => $tournament,
        'group' => $group,
    ] = createTournamentRosterFixture(3);

    foreach (range(1, 3) as $position) {
        createTournamentParticipant(
            $venue,
            $tournament,
            $group,
            'A'.$position,
            'Igrač '.$position,
        );
    }

    app(GroupMatchGenerator::class)->generate($tournament);

    $this->actingAs($user)
        ->get(route('venues.tournaments.schedule.index', [$venue, $tournament]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('tournament.group_matches_count', 3)
            ->where('tournament.completed_group_matches_count', 0)
            ->where('tournament.can_complete_group_stage', false)
            ->where('tournament.next_stage_after_groups', 'knockout_draw')
        );

    foreach (TournamentMatch::query()->orderBy('scheduled_order')->get() as $index => $match) {
        $this->actingAs($user)
            ->patch(route('venues.tournaments.schedule.matches.result', [
                $venue,
                $tournament,
                $match,
            ]), [
                'score_a' => 31 + $index,
                'score_b' => 20,
            ])
            ->assertSessionHasNoErrors();
    }

    $this->actingAs($user)
        ->get(route('venues.tournaments.schedule.index', [$venue, $tournament]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('tournament.completed_group_matches_count', 3)
            ->where('tournament.can_complete_group_stage', true)
        );

    $this->actingAs($user)
        ->post(route('venues.tournaments.complete_group_stage', [$venue, $tournament]))
        ->assertRedirect(route('venues.tournaments.show', [$venue, $tournament]))
        ->assertSessionHasNoErrors();

    expect($tournament->fresh()->status)->toBe(TournamentStatus::KNOCKOUT_DRAW);
});

test('admin schedule announces repechage as the next stage', function () {
    [
        'venue' => $venue,
        'user' => $user,
        'tournament' => $tournament,
        'group' => $group,
    ] = createTournamentRosterFixture(2);

    $tournament->update([
        'settings' => array_merge($tournament->settings, [
            'repechage_enabled' => true,
        ]),
    ]);

    $participantA = createTournamentParticipant($venue, $tournament, $group, 'A1', 'Ana');
    $participantB = createTournamentParticipant($venue, $tournament, $group, 'A2', 'Bojan');
    app(GroupMatchGenerator::class)->generate($tournament);

    $match = TournamentMatch::query()->firstOrFail();
    $match->update([
        'score_a' => 31,
        'score_b' => 20,
        'winner_participant_id' => $participantA->id,
        'loser_participant_id' => $participantB->id,
        'status' => MatchStatus::FINISHED,
        'finished_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('venues.tournaments.schedule.index', [$venue, $tournament]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('tournament.can_complete_group_stage', true)
            ->where('tournament.next_stage_after_groups', 'repechage')
        );

    $this->actingAs($user)
        ->post(route('venues.tournaments.complete_group_stage', [$venue, $tournament]))
        ->assertSessionHasNoErrors();

    expect($tournament->fresh()->status)->toBe(TournamentStatus::REPECHAGE);
});

test('a public player profile shows tournament and match history', function () {
    [
        'venue' => $venue,
        'tournament' => $tournament,
        'group' => $group,
    ] = createTournamentRosterFixture(2);

    $participantA = createTournamentParticipant($venue, $tournament, $group, 'A1', 'Ana');
    $participantB = createTournamentParticipant($venue, $tournament, $group, 'A2', 'Bojan');
    TournamentMatch::create([
        'tournament_id' => $tournament->id,
        'stage' => MatchStage::GROUP,
        'tournament_group_id' => $group->id,
        'participant_a_id' => $participantA->id,
        'participant_b_id' => $participantB->id,
        'score_a' => 31,
        'score_b' => 20,
        'winner_participant_id' => $participantA->id,
        'loser_participant_id' => $participantB->id,
        'status' => MatchStatus::FINISHED,
        'scheduled_order' => 1,
        'round_robin_leg' => 1,
        'wins_required' => 1,
        'finished_at' => now(),
    ]);

    $this->get(route('public.players.show', $participantA->player))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Public/Players/Show')
            ->where('player.display_name', 'Ana Testić')
            ->where('player.tournaments_count', 1)
            ->where('player.matches_count', 1)
            ->where('player.wins_count', 1)
            ->has('tournaments', 1)
            ->has('matches', 1)
            ->where('matches.0.opponent_name', 'Bojan Testić')
        );
});

test('public player profiles use the tournament date and podium placement labels', function () {
    [
        'venue' => $venue,
        'tournament' => $tournament,
        'group' => $group,
    ] = createTournamentRosterFixture(2);

    $tournament->update(['tournament_date' => '2026-08-26']);
    $participantA = createTournamentParticipant($venue, $tournament, $group, 'A1', 'Ana');
    $participantB = createTournamentParticipant($venue, $tournament, $group, 'A2', 'Bojan');

    TournamentMatch::create([
        'tournament_id' => $tournament->id,
        'stage' => MatchStage::FINAL,
        'bracket_round' => 'final',
        'bracket_position' => 1,
        'participant_a_id' => $participantA->id,
        'participant_b_id' => $participantB->id,
        'score_a' => 1,
        'score_b' => 0,
        'winner_participant_id' => $participantA->id,
        'loser_participant_id' => $participantB->id,
        'status' => MatchStatus::FINISHED,
        'scheduled_order' => 1,
        'round_robin_leg' => 1,
        'wins_required' => 1,
        'finished_at' => now(),
    ]);

    $this->get(route('public.players.show', $participantA->player))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('tournaments.0.date', '26.08.2026.')
            ->where('tournaments.0.result_label', '1. mesto')
        );

    $this->get(route('public.players.show', $participantB->player))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('tournaments.0.result_label', '2. mesto')
        );
});
