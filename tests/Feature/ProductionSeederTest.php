<?php

use App\Enums\UserGlobalRole;
use App\Enums\VenueUserRole;
use App\Models\Player;
use App\Models\Team;
use App\Models\Tournament;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueResource;
use App\Models\VenueUser;
use Database\Seeders\ProductionSeeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

test('production seeder creates only Nosati Pub and its superadmin', function () {
    Storage::fake('public');
    Storage::disk('public')->put('seed/nosati-pub-logo.png', 'production-logo');

    config()->set('production.admin', [
        'name' => 'Vlada',
        'email' => 'vlada@example.com',
        'password' => 'a-long-production-password',
    ]);

    $this->seed(ProductionSeeder::class);

    $admin = User::query()->sole();
    $venue = Venue::query()->sole();
    $membership = VenueUser::query()->sole();

    expect($admin->name)->toBe('Vlada')
        ->and($admin->email)->toBe('vlada@example.com')
        ->and($admin->email_verified_at)->not->toBeNull()
        ->and($admin->global_role)->toBe(UserGlobalRole::SUPERADMIN)
        ->and(Hash::check('a-long-production-password', $admin->password))->toBeTrue()
        ->and($venue->name)->toBe('Nosati Pub')
        ->and($venue->slug)->toBe('nosati-pub')
        ->and($venue->logo_path)->toBe('venues/1/nosati-pub-logo.png')
        ->and($venue->address)->toBe('Nušićeva 8')
        ->and($venue->instagram_url)->toBe('https://www.instagram.com/nosatipub/')
        ->and($membership->venue_id)->toBe($venue->id)
        ->and($membership->user_id)->toBe($admin->id)
        ->and($membership->role)->toBe(VenueUserRole::ADMIN)
        ->and(VenueResource::query()->count())->toBe(0)
        ->and(Player::query()->count())->toBe(0)
        ->and(Team::query()->count())->toBe(0)
        ->and(Tournament::query()->count())->toBe(0);

    Storage::disk('public')->assertExists($venue->logo_path);
    Storage::disk('public')->assertMissing('seed/nosati-pub-logo.png');
});

test('production seeder refuses to touch a database that already has application data', function () {
    config()->set('production.admin', [
        'name' => 'Vlada',
        'email' => 'vlada@example.com',
        'password' => 'a-long-production-password',
    ]);

    User::factory()->create();

    expect(fn () => $this->seed(ProductionSeeder::class))
        ->toThrow(LogicException::class, 'It never deletes existing data');
});
