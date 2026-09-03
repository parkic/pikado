<?php

use App\Enums\GameType;
use App\Enums\GroupRounds;
use App\Enums\MatchMode;
use App\Enums\ScoringMode;
use App\Enums\TournamentStatus;
use App\Enums\VenueUserRole;
use App\Models\Tournament;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueUser;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

function createVenueSettingsFixture(): array
{
    $venue = Venue::create([
        'name' => 'Arena Pub',
        'slug' => 'arena-pub',
        'is_active' => true,
    ]);

    $user = User::factory()->create();

    VenueUser::create([
        'venue_id' => $venue->id,
        'user_id' => $user->id,
        'role' => VenueUserRole::ADMIN,
        'is_active' => true,
    ]);

    return compact('venue', 'user');
}

test('venue member can update public branding and upload a logo', function () {
    Storage::fake('public');

    ['venue' => $venue, 'user' => $user] = createVenueSettingsFixture();

    $logo = UploadedFile::fake()->createWithContent(
        'arena-logo.png',
        base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
            true,
        ),
    );

    $this->actingAs($user)
        ->put(route('venues.settings.update', $venue), [
            'name' => 'Arena Sports Pub',
            'description' => 'Turniri, sport i dobro društvo.',
            'address' => 'Bulevar oslobođenja 10, Novi Sad',
            'phone' => '+381 60 123 4567',
            'website_url' => 'https://arena.example.com',
            'instagram_url' => 'https://instagram.com/arenapub',
            'public_theme' => 'light',
            'logo' => $logo,
            'remove_logo' => false,
        ])
        ->assertRedirect(route('venues.settings.edit', $venue))
        ->assertSessionHas('success');

    $venue->refresh();

    expect($venue->name)->toBe('Arena Sports Pub')
        ->and($venue->address)->toBe('Bulevar oslobođenja 10, Novi Sad')
        ->and($venue->phone)->toBe('+381 60 123 4567')
        ->and($venue->website_url)->toBe('https://arena.example.com')
        ->and($venue->instagram_url)->toBe('https://instagram.com/arenapub')
        ->and($venue->public_theme)->toBe('light')
        ->and($venue->logo_path)->not->toBeNull();

    Storage::disk('public')->assertExists($venue->logo_path);
});

test('user without venue access cannot open or update venue settings', function () {
    ['venue' => $venue] = createVenueSettingsFixture();
    $outsider = User::factory()->create();

    $this->actingAs($outsider)
        ->get(route('venues.settings.edit', $venue))
        ->assertForbidden();

    $this->actingAs($outsider)
        ->put(route('venues.settings.update', $venue), [
            'name' => 'Neovlašćena izmena',
            'public_theme' => 'dark',
        ])
        ->assertForbidden();
});

test('public tournament pages receive venue theme and branding', function () {
    Storage::fake('public');

    ['venue' => $venue, 'user' => $user] = createVenueSettingsFixture();

    $venue->update([
        'description' => 'Mesto za turnire.',
        'address' => 'Centar 1, Novi Sad',
        'phone' => '+381 11 222 333',
        'website_url' => 'https://arena.example.com',
        'instagram_url' => 'https://instagram.com/arenapub',
        'public_theme' => 'light',
        'logo_path' => "venues/{$venue->id}/logo.png",
    ]);

    Storage::disk('public')->put($venue->logo_path, 'logo');

    $tournament = Tournament::create([
        'venue_id' => $venue->id,
        'name' => 'TV turnir',
        'slug' => 'tv-turnir',
        'public_code' => 'tv-turnir-public',
        'game_type' => GameType::DART_301,
        'match_mode' => MatchMode::SINGLES,
        'status' => TournamentStatus::GROUP_STAGE,
        'group_rounds' => GroupRounds::SINGLE,
        'scoring_mode' => ScoringMode::POINTS_DIFFERENCE,
        'public_enabled' => true,
        'settings' => [],
        'created_by_user_id' => $user->id,
    ]);

    $this->get(route('public.tournaments.live', $tournament->public_code))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Public/Tournaments/Live')
            ->where('venue.name', 'Arena Pub')
            ->where('venue.address', 'Centar 1, Novi Sad')
            ->where('venue.phone', '+381 11 222 333')
            ->where('venue.website_url', 'https://arena.example.com')
            ->where('venue.instagram_url', 'https://instagram.com/arenapub')
            ->where('venue.public_theme', 'light')
            ->where('tournament.game_type', '301')
            ->where('tournament.match_mode', '1 na 1')
            ->has('venue.logo_url')
        );
});
