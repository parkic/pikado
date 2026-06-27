<?php

namespace Database\Seeders;

use App\Enums\ResourceType;
use App\Enums\UserGlobalRole;
use App\Enums\VenueUserRole;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueResource;
use App\Models\VenueUser;
use Illuminate\Database\Seeder;

class LocalDevelopmentSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::query()->first();

        if (! $user) {
            $this->command?->warn('No users found. Register a user first, then run this seeder again.');

            return;
        }

        $user->update([
            'global_role' => UserGlobalRole::SUPERADMIN,
        ]);

        $venue = Venue::query()->updateOrCreate(
            ['slug' => 'nosati-pub'],
            [
                'name' => 'Nosati Pub',
                'description' => 'Lokal za pikado, beer pong i pub turnire.',
                'instagram_url' => null,
                'is_active' => true,
            ]
        );

        VenueUser::query()->updateOrCreate(
            [
                'venue_id' => $venue->id,
                'user_id' => $user->id,
                'role' => VenueUserRole::ADMIN,
            ],
            [
                'is_active' => true,
            ]
        );

        $resources = [
            [
                'name' => 'Levi pikado',
                'type' => ResourceType::DART_BOARD,
                'sort_order' => 1,
            ],
            [
                'name' => 'Desni pikado',
                'type' => ResourceType::DART_BOARD,
                'sort_order' => 2,
            ],
            [
                'name' => 'Beer pong sto 1',
                'type' => ResourceType::BEER_PONG_TABLE,
                'sort_order' => 3,
            ],
            [
                'name' => 'Beer pong sto 2',
                'type' => ResourceType::BEER_PONG_TABLE,
                'sort_order' => 4,
            ],
        ];

        foreach ($resources as $resource) {
            VenueResource::query()->updateOrCreate(
                [
                    'venue_id' => $venue->id,
                    'name' => $resource['name'],
                ],
                [
                    'type' => $resource['type'],
                    'sort_order' => $resource['sort_order'],
                    'is_active' => true,
                ]
            );
        }
    }
}
