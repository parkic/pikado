<?php

use Inertia\Inertia;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\VenueDashboardController;
use App\Http\Controllers\VenueResourceController;
use App\Http\Controllers\PlayerController;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', function () {
        $user = request()->user();

        $venueUsers = $user->venueUsers()
            ->with('venue:id,name,slug')
            ->where('is_active', true)
            ->get();

        if ($venueUsers->count() === 1) {
            return redirect()->route('venues.dashboard', [
                'venue' => $venueUsers->first()->venue->slug,
            ]);
        }

        return Inertia::render('Dashboard', [
            'userContext' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'global_role' => $user->global_role?->value,
                'venue_users' => $venueUsers->map(fn ($venueUser) => [
                    'id' => $venueUser->id,
                    'role' => $venueUser->role->value,
                    'venue' => [
                        'id' => $venueUser->venue->id,
                        'name' => $venueUser->venue->name,
                        'slug' => $venueUser->venue->slug,
                    ],
                ]),
            ],
        ]);
    })->name('dashboard');

    Route::get('venues/{venue:slug}/dashboard', VenueDashboardController::class)
    ->name('venues.dashboard');

    // Venue Resources
    Route::get('venues/{venue:slug}/resources', [VenueResourceController::class, 'index'])
        ->name('venues.resources.index');
    Route::get('venues/{venue:slug}/resources/create', [VenueResourceController::class, 'create'])
        ->name('venues.resources.create');
    Route::post('venues/{venue:slug}/resources', [VenueResourceController::class, 'store'])
        ->name('venues.resources.store');
    Route::get('venues/{venue:slug}/resources/{resource}/edit', [VenueResourceController::class, 'edit'])
        ->name('venues.resources.edit');
    Route::put('venues/{venue:slug}/resources/{resource}', [VenueResourceController::class, 'update'])
        ->name('venues.resources.update');
    Route::delete('venues/{venue:slug}/resources/{resource}', [VenueResourceController::class, 'destroy'])
        ->name('venues.resources.destroy');


    // Players
    Route::get('venues/{venue:slug}/players', [PlayerController::class, 'index'])
        ->name('venues.players.index');
    Route::get('venues/{venue:slug}/players/create', [PlayerController::class, 'create'])
        ->name('venues.players.create');
    Route::post('venues/{venue:slug}/players', [PlayerController::class, 'store'])
        ->name('venues.players.store');
    Route::get('venues/{venue:slug}/players/{player}/edit', [PlayerController::class, 'edit'])
        ->name('venues.players.edit');
    Route::put('venues/{venue:slug}/players/{player}', [PlayerController::class, 'update'])
        ->name('venues.players.update');

});

require __DIR__.'/settings.php';
