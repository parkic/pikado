<?php

use App\Enums\GameType;
use App\Enums\GroupRounds;
use App\Enums\MatchMode;
use App\Enums\ParticipantStatus;
use App\Enums\ParticipantType;
use App\Enums\ResourceType;
use App\Enums\ScoringMode;
use App\Enums\TournamentStatus;
use App\Enums\UserGlobalRole;
use App\Enums\VenueUserRole;
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
use Illuminate\Support\Collection;

/**
 * @param  Collection<int, TournamentMatch>  $matches
 * @return array{maximum_initial_wait: int, maximum_between_wait: int, minimum_between_wait: int}
 */
function groupScheduleWaitMetrics(Collection $matches): array
{
    $positionsByParticipant = [];

    foreach ($matches->sortBy('scheduled_order')->values() as $position => $match) {
        foreach ([$match->participant_a_id, $match->participant_b_id] as $participantId) {
            if ($participantId !== null) {
                $positionsByParticipant[$participantId][] = $position;
            }
        }
    }

    $initialWaits = [];
    $betweenWaits = [];

    foreach ($positionsByParticipant as $positions) {
        $initialWaits[] = $positions[0];

        foreach (array_slice($positions, 1) as $index => $position) {
            $betweenWaits[] = $position - $positions[$index] - 1;
        }
    }

    return [
        'maximum_initial_wait' => max($initialWaits ?: [0]),
        'maximum_between_wait' => max($betweenWaits ?: [0]),
        'minimum_between_wait' => min($betweenWaits ?: [0]),
    ];
}

function createDeletableTournament(Venue $venue, User $creator, string $suffix): Tournament
{
    return Tournament::create([
        'venue_id' => $venue->id,
        'name' => 'Turnir '.$suffix,
        'slug' => 'turnir-'.$suffix,
        'public_code' => 'public-'.$suffix,
        'game_type' => GameType::DART_301,
        'match_mode' => MatchMode::SINGLES,
        'status' => TournamentStatus::DRAFT,
        'group_rounds' => GroupRounds::SINGLE,
        'scoring_mode' => ScoringMode::POINTS_DIFFERENCE,
        'public_enabled' => true,
        'settings' => [],
        'created_by_user_id' => $creator->id,
    ]);
}

test('venue admin can delete a tournament without deleting venue resources', function () {
    $venue = Venue::create([
        'name' => 'Nosati Pub',
        'slug' => 'nosati-pub',
        'is_active' => true,
    ]);
    $admin = User::factory()->create();
    VenueUser::create([
        'venue_id' => $venue->id,
        'user_id' => $admin->id,
        'role' => VenueUserRole::ADMIN,
        'is_active' => true,
    ]);
    $venueResource = $venue->venueResources()->create([
        'name' => 'Levi pikado',
        'type' => ResourceType::DART_BOARD,
        'sort_order' => 1,
        'is_active' => true,
    ]);
    $tournament = createDeletableTournament($venue, $admin, 'admin-delete');

    $this->actingAs($admin)
        ->delete(route('venues.tournaments.destroy', [$venue, $tournament]))
        ->assertRedirect(route('venues.tournaments.index', $venue))
        ->assertSessionHasNoErrors();

    $this->assertSoftDeleted('tournaments', ['id' => $tournament->id]);
    $this->assertDatabaseHas('venue_resources', [
        'id' => $venueResource->id,
        'deleted_at' => null,
    ]);
    $this->get(route('public.tournaments.live', $tournament->public_code))
        ->assertNotFound();
});

test('scorekeeper cannot delete a tournament', function () {
    $venue = Venue::create([
        'name' => 'Nosati Pub',
        'slug' => 'nosati-pub',
        'is_active' => true,
    ]);
    $scorekeeper = User::factory()->create();
    VenueUser::create([
        'venue_id' => $venue->id,
        'user_id' => $scorekeeper->id,
        'role' => VenueUserRole::SCOREKEEPER,
        'is_active' => true,
    ]);
    $tournament = createDeletableTournament(
        $venue,
        $scorekeeper,
        'scorekeeper-denied',
    );

    $this->actingAs($scorekeeper)
        ->delete(route('venues.tournaments.destroy', [$venue, $tournament]))
        ->assertForbidden();

    $this->assertDatabaseHas('tournaments', [
        'id' => $tournament->id,
        'deleted_at' => null,
    ]);
});

test('superadmin can delete a tournament without a venue membership', function () {
    $venue = Venue::create([
        'name' => 'Nosati Pub',
        'slug' => 'nosati-pub',
        'is_active' => true,
    ]);
    $superadmin = User::factory()->create([
        'global_role' => UserGlobalRole::SUPERADMIN,
    ]);
    $tournament = createDeletableTournament(
        $venue,
        $superadmin,
        'superadmin-delete',
    );

    $this->actingAs($superadmin)
        ->delete(route('venues.tournaments.destroy', [$venue, $tournament]))
        ->assertRedirect(route('venues.tournaments.index', $venue));

    $this->assertSoftDeleted('tournaments', ['id' => $tournament->id]);
});

test('five uneven groups are assigned to two boards by visible match load', function () {
    $venue = Venue::create([
        'name' => 'Nosati Pub',
        'slug' => 'nosati-pub',
        'is_active' => true,
    ]);
    $admin = User::factory()->create();
    $tournament = Tournament::create([
        'venue_id' => $venue->id,
        'name' => 'Pet grupa',
        'slug' => 'pet-grupa',
        'public_code' => 'pet-grupa-live',
        'game_type' => GameType::DART_301,
        'match_mode' => MatchMode::SINGLES,
        'status' => TournamentStatus::READY,
        'group_rounds' => GroupRounds::SINGLE,
        'scoring_mode' => ScoringMode::POINTS_DIFFERENCE,
        'public_enabled' => true,
        'settings' => ['group_count' => 5, 'group_size' => 7],
        'created_by_user_id' => $admin->id,
    ]);
    $leftBoard = TournamentResource::create([
        'tournament_id' => $tournament->id,
        'name' => 'Levi pikado',
        'type' => ResourceType::DART_BOARD,
        'sort_order' => 1,
        'is_active' => true,
    ]);
    $rightBoard = TournamentResource::create([
        'tournament_id' => $tournament->id,
        'name' => 'Desni pikado',
        'type' => ResourceType::DART_BOARD,
        'sort_order' => 2,
        'is_active' => true,
    ]);
    $participantCounts = [7, 7, 6, 6, 6];

    foreach ($participantCounts as $groupIndex => $participantCount) {
        $groupName = chr(65 + $groupIndex);
        $group = TournamentGroup::create([
            'tournament_id' => $tournament->id,
            'name' => $groupName,
            'sort_order' => $groupIndex + 1,
        ]);

        foreach (range(1, $participantCount) as $position) {
            $player = Player::create([
                'first_name' => $groupName.$position,
                'last_name' => 'Igrač',
                'is_active' => true,
            ]);
            TournamentParticipant::create([
                'tournament_id' => $tournament->id,
                'participant_type' => ParticipantType::PLAYER,
                'player_id' => $player->id,
                'tournament_group_id' => $group->id,
                'group_position' => $groupName.$position,
                'status' => ParticipantStatus::ACTIVE,
            ]);
        }
    }

    expect(app(GroupMatchGenerator::class)->generate($tournament))->toBe(87);

    $matches = TournamentMatch::query()
        ->with('group')
        ->where('tournament_id', $tournament->id)
        ->get();

    expect($matches->where('tournament_resource_id', $leftBoard->id))
        ->toHaveCount(42)
        ->and(
            $matches
                ->where('tournament_resource_id', $leftBoard->id)
                ->pluck('group.name')
                ->unique()
                ->values()
                ->all(),
        )->toBe(['A', 'B'])
        ->and($matches->where('tournament_resource_id', $rightBoard->id))
        ->toHaveCount(45)
        ->and(
            $matches
                ->where('tournament_resource_id', $rightBoard->id)
                ->pluck('group.name')
                ->unique()
                ->values()
                ->all(),
        )->toBe(['C', 'D', 'E']);

    $leftMetrics = groupScheduleWaitMetrics(
        $matches->where('tournament_resource_id', $leftBoard->id),
    );
    $rightMetrics = groupScheduleWaitMetrics(
        $matches->where('tournament_resource_id', $rightBoard->id),
    );
    $globalMetrics = groupScheduleWaitMetrics($matches);

    expect($leftMetrics['maximum_between_wait'])->toBeLessThanOrEqual(11)
        ->and($rightMetrics['maximum_between_wait'])->toBeLessThanOrEqual(11)
        ->and($leftMetrics['minimum_between_wait'])->toBeGreaterThanOrEqual(1)
        ->and($rightMetrics['minimum_between_wait'])->toBeGreaterThanOrEqual(1)
        ->and($globalMetrics['maximum_between_wait'])->toBeLessThanOrEqual(23);
});

test('group schedules stay fair in simulations with thirty to forty players', function (
    array $participantCounts,
) {
    $totalParticipants = array_sum($participantCounts);
    $venue = Venue::create([
        'name' => 'Simulacija '.$totalParticipants,
        'slug' => 'simulacija-'.$totalParticipants,
        'is_active' => true,
    ]);
    $admin = User::factory()->create();
    $tournament = Tournament::create([
        'venue_id' => $venue->id,
        'name' => 'Simulacija '.$totalParticipants,
        'slug' => 'simulacija-'.$totalParticipants,
        'public_code' => 'simulacija-'.$totalParticipants.'-live',
        'game_type' => GameType::DART_301,
        'match_mode' => MatchMode::SINGLES,
        'status' => TournamentStatus::READY,
        'group_rounds' => GroupRounds::SINGLE,
        'scoring_mode' => ScoringMode::POINTS_DIFFERENCE,
        'public_enabled' => true,
        'settings' => [
            'group_count' => count($participantCounts),
            'group_size' => max($participantCounts),
        ],
        'created_by_user_id' => $admin->id,
    ]);
    $boards = collect(range(1, 2))->map(fn (int $index) => TournamentResource::create([
        'tournament_id' => $tournament->id,
        'name' => 'Pikado '.$index,
        'type' => ResourceType::DART_BOARD,
        'sort_order' => $index,
        'is_active' => true,
    ]));

    foreach ($participantCounts as $groupIndex => $participantCount) {
        $groupName = chr(65 + $groupIndex);
        $group = TournamentGroup::create([
            'tournament_id' => $tournament->id,
            'name' => $groupName,
            'sort_order' => $groupIndex + 1,
        ]);

        foreach (range(1, $participantCount) as $position) {
            $player = Player::create([
                'first_name' => $groupName.$position,
                'last_name' => 'Simulacija',
                'is_active' => true,
            ]);
            TournamentParticipant::create([
                'tournament_id' => $tournament->id,
                'participant_type' => ParticipantType::PLAYER,
                'player_id' => $player->id,
                'tournament_group_id' => $group->id,
                'group_position' => $groupName.$position,
                'status' => ParticipantStatus::ACTIVE,
            ]);
        }
    }

    $expectedMatchCount = collect($participantCounts)
        ->sum(fn (int $count): int => intdiv($count * ($count - 1), 2));

    expect(app(GroupMatchGenerator::class)->generate($tournament))
        ->toBe($expectedMatchCount);

    $matches = TournamentMatch::query()
        ->where('tournament_id', $tournament->id)
        ->where('is_hidden', false)
        ->orderBy('scheduled_order')
        ->get();

    foreach ($boards as $board) {
        $metrics = groupScheduleWaitMetrics(
            $matches->where('tournament_resource_id', $board->id),
        );

        expect($metrics['maximum_initial_wait'])->toBeLessThanOrEqual(12)
            ->and($metrics['maximum_between_wait'])->toBeLessThanOrEqual(15)
            ->and($metrics['minimum_between_wait'])->toBeGreaterThanOrEqual(1);
    }

    expect(groupScheduleWaitMetrics($matches)['maximum_between_wait'])
        ->toBeLessThanOrEqual(31);
})->with([
    '30 players in five even groups' => [[6, 6, 6, 6, 6]],
    '34 players in five uneven groups' => [[7, 7, 7, 7, 6]],
    '36 players in six even groups' => [[6, 6, 6, 6, 6, 6]],
    '40 players in five even groups' => [[8, 8, 8, 8, 8]],
]);
