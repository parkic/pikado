<?php

namespace Database\Seeders;

use App\Enums\UserGlobalRole;
use App\Enums\VenueUserRole;
use App\Models\Player;
use App\Models\Team;
use App\Models\Tournament;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueResource;
use App\Models\VenueUser;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use LogicException;

class ProductionSeeder extends Seeder
{
    public function run(): void
    {
        $adminName = trim((string) config('production.admin.name'));
        $adminEmail = trim((string) config('production.admin.email'));
        $adminPassword = (string) config('production.admin.password');

        if ($adminName === '') {
            throw new LogicException('PIKADO_ADMIN_NAME must be set.');
        }

        if (! filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
            throw new LogicException('PIKADO_ADMIN_EMAIL must contain a valid email address.');
        }

        if (mb_strlen($adminPassword) < 12) {
            throw new LogicException('PIKADO_ADMIN_PASSWORD must contain at least 12 characters.');
        }

        DB::transaction(function () use ($adminName, $adminEmail, $adminPassword): void {
            $this->ensureDatabaseContainsNoApplicationData();

            $admin = User::query()->create([
                'name' => $adminName,
                'email' => $adminEmail,
                'email_verified_at' => now(),
                'password' => Hash::make($adminPassword),
                'global_role' => UserGlobalRole::SUPERADMIN,
            ]);

            $venue = Venue::query()->create([
                'name' => 'Nosati Pub',
                'slug' => 'nosati-pub',
                'logo_path' => null,
                'description' => 'Lokal za pikado, beer pong i pub turnire.',
                'address' => 'Nušićeva 8',
                'phone' => '065 8212028',
                'website_url' => null,
                'instagram_url' => 'https://www.instagram.com/nosatipub/',
                'public_theme' => 'dark',
                'is_active' => true,
            ]);

            $this->attachSeedLogo($venue);

            VenueUser::query()->create([
                'venue_id' => $venue->id,
                'user_id' => $admin->id,
                'role' => VenueUserRole::ADMIN,
                'is_active' => true,
            ]);
        });

        $this->command?->info('Production database initialized with Nosati Pub and one superadmin.');
    }

    private function attachSeedLogo(Venue $venue): void
    {
        $disk = Storage::disk('public');
        $seedPath = 'seed/nosati-pub-logo.png';

        if (! $disk->exists($seedPath)) {
            return;
        }

        $logoPath = "venues/{$venue->id}/nosati-pub-logo.png";
        $disk->put($logoPath, $disk->get($seedPath));
        $venue->update(['logo_path' => $logoPath]);
        $disk->delete($seedPath);
    }

    private function ensureDatabaseContainsNoApplicationData(): void
    {
        $models = [
            User::class,
            Venue::class,
            VenueUser::class,
            VenueResource::class,
            Player::class,
            Team::class,
            Tournament::class,
        ];

        foreach ($models as $model) {
            if ($model::query()->withTrashed()->exists()) {
                throw new LogicException(
                    'ProductionSeeder only runs on an empty application database. It never deletes existing data.',
                );
            }
        }
    }
}
