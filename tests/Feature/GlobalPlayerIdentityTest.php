<?php

use App\Enums\GameType;
use App\Enums\GroupRounds;
use App\Enums\MatchMode;
use App\Enums\ParticipantStatus;
use App\Enums\ParticipantType;
use App\Enums\ScoringMode;
use App\Enums\TournamentStatus;
use App\Enums\VenueUserRole;
use App\Models\Player;
use App\Models\Tournament;
use App\Models\TournamentGroup;
use App\Models\TournamentParticipant;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueUser;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;

function createGlobalPlayerVenue(string $name, string $slug): Venue
{
    return Venue::query()->create([
        'name' => $name,
        'slug' => $slug,
        'is_active' => true,
    ]);
}

function createGlobalPlayerTournament(Venue $venue, User $user, string $suffix): Tournament
{
    return Tournament::query()->create([
        'venue_id' => $venue->id,
        'name' => 'Turnir '.$suffix,
        'slug' => 'turnir-'.$suffix,
        'public_code' => 'player-'.$suffix,
        'game_type' => GameType::DART_301,
        'match_mode' => MatchMode::SINGLES,
        'status' => TournamentStatus::GROUP_DRAW,
        'group_rounds' => GroupRounds::SINGLE,
        'scoring_mode' => ScoringMode::POINTS_DIFFERENCE,
        'public_enabled' => true,
        'settings' => ['group_count' => 1, 'group_size' => 2],
        'created_by_user_id' => $user->id,
    ]);
}

function attachGlobalPlayerAdmin(User $user, Venue $venue): void
{
    VenueUser::query()->create([
        'venue_id' => $venue->id,
        'user_id' => $user->id,
        'role' => VenueUserRole::ADMIN,
        'is_active' => true,
    ]);
}

test('players are stored outside venues', function () {
    expect(Schema::hasColumn('players', 'venue_id'))->toBeFalse();

    $venue = createGlobalPlayerVenue('Prvi lokal', 'prvi-lokal');
    $player = Player::query()->create([
        'first_name' => 'Ana',
        'last_name' => 'Anić',
        'is_active' => true,
    ]);

    $venue->forceDelete();

    $this->assertDatabaseHas('players', [
        'id' => $player->id,
        'deleted_at' => null,
    ]);
});

test('one public player profile aggregates appearances from multiple venues', function () {
    $user = User::factory()->create();
    $firstVenue = createGlobalPlayerVenue('Prvi lokal', 'prvi-lokal');
    $secondVenue = createGlobalPlayerVenue('Drugi lokal', 'drugi-lokal');
    $player = Player::query()->create([
        'first_name' => 'Mila',
        'last_name' => 'Milić',
        'is_active' => true,
    ]);

    foreach ([$firstVenue, $secondVenue] as $index => $venue) {
        $tournament = createGlobalPlayerTournament($venue, $user, 'global-'.$index);
        TournamentParticipant::query()->create([
            'tournament_id' => $tournament->id,
            'participant_type' => ParticipantType::PLAYER,
            'player_id' => $player->id,
            'status' => ParticipantStatus::ACTIVE,
        ]);
    }

    $this->get(route('public.players.show', $player))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Public/Players/Show')
            ->where('app.name', 'Pikado')
            ->where('player.tournaments_count', 2)
            ->has('tournaments', 2)
            ->where('tournaments', fn ($tournaments) => collect($tournaments)
                ->pluck('venue_name')
                ->sort()
                ->values()
                ->all() === ['Drugi lokal', 'Prvi lokal'])
        );
});

test('venue admin sees the global directory including players from another venue', function () {
    $admin = User::factory()->create();
    $adminVenue = createGlobalPlayerVenue('Admin lokal', 'admin-lokal');
    $otherVenue = createGlobalPlayerVenue('Drugi lokal', 'drugi-lokal');
    attachGlobalPlayerAdmin($admin, $adminVenue);

    $player = Player::query()->create([
        'first_name' => 'Globalni',
        'last_name' => 'Igrač',
        'is_active' => true,
    ]);
    $tournament = createGlobalPlayerTournament($otherVenue, $admin, 'drugi-lokal');
    TournamentParticipant::query()->create([
        'tournament_id' => $tournament->id,
        'participant_type' => ParticipantType::PLAYER,
        'player_id' => $player->id,
        'status' => ParticipantStatus::ACTIVE,
    ]);

    $this->actingAs($admin)
        ->get(route('players.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Venues/Players/Index')
            ->has('players', 1)
            ->where('players.0.id', $player->id)
            ->where('players.0.tournaments_count', 1)
            ->where('players.0.venues_count', 1)
        );
});

test('an existing global player can be selected for a tournament at any venue', function () {
    $admin = User::factory()->create();
    $venue = createGlobalPlayerVenue('Novi lokal', 'novi-lokal');
    attachGlobalPlayerAdmin($admin, $venue);
    $tournament = createGlobalPlayerTournament($venue, $admin, 'izbor');
    $group = TournamentGroup::query()->create([
        'tournament_id' => $tournament->id,
        'name' => 'A',
        'sort_order' => 1,
    ]);
    $player = Player::query()->create([
        'first_name' => 'Postojeći',
        'last_name' => 'Igrač',
        'is_active' => true,
    ]);

    $this->actingAs($admin)
        ->post(route('venues.tournaments.group_draw.store', [$venue, $tournament]), [
            'existing_player_id' => $player->id,
            'group_position' => 'A1',
        ])
        ->assertRedirect(route('venues.tournaments.group_draw.show', [$venue, $tournament]));

    $this->assertDatabaseHas('tournament_participants', [
        'tournament_id' => $tournament->id,
        'tournament_group_id' => $group->id,
        'player_id' => $player->id,
    ]);
});

test('typing the same name creates a separate identity unless an existing player is selected', function () {
    $admin = User::factory()->create();
    $venue = createGlobalPlayerVenue('Novi lokal', 'novi-lokal');
    attachGlobalPlayerAdmin($admin, $venue);
    $tournament = createGlobalPlayerTournament($venue, $admin, 'bez-spajanja');
    TournamentGroup::query()->create([
        'tournament_id' => $tournament->id,
        'name' => 'A',
        'sort_order' => 1,
    ]);
    Player::query()->create([
        'first_name' => 'Isto',
        'last_name' => 'Ime',
        'is_active' => true,
    ]);

    $this->actingAs($admin)
        ->post(route('venues.tournaments.group_draw.store', [$venue, $tournament]), [
            'first_name' => 'Isto',
            'last_name' => 'Ime',
            'group_position' => 'A1',
        ])
        ->assertSessionHasNoErrors();

    expect(Player::query()
        ->where('first_name', 'Isto')
        ->where('last_name', 'Ime')
        ->count())->toBe(2);
});

test('a player with tournament history cannot be deleted', function () {
    $admin = User::factory()->create();
    $venue = createGlobalPlayerVenue('Novi lokal', 'novi-lokal');
    attachGlobalPlayerAdmin($admin, $venue);
    $player = Player::query()->create([
        'first_name' => 'Istorijski',
        'last_name' => 'Igrač',
        'is_active' => true,
    ]);
    $tournament = createGlobalPlayerTournament($venue, $admin, 'istorija');
    TournamentParticipant::query()->create([
        'tournament_id' => $tournament->id,
        'participant_type' => ParticipantType::PLAYER,
        'player_id' => $player->id,
        'status' => ParticipantStatus::ACTIVE,
    ]);

    $this->actingAs($admin)
        ->delete(route('players.destroy', $player))
        ->assertSessionHasErrors('player');

    $this->assertDatabaseHas('players', [
        'id' => $player->id,
        'deleted_at' => null,
    ]);
});

test('legacy venue profile URLs permanently redirect to the global profile', function () {
    $venue = createGlobalPlayerVenue('Stari lokal', 'stari-lokal');
    $player = Player::query()->create([
        'first_name' => 'Stari',
        'last_name' => 'Link',
        'is_active' => true,
    ]);

    $this->get(route('public.players.legacy', [$venue, $player]))
        ->assertStatus(301)
        ->assertRedirect(route('public.players.show', $player));
});
