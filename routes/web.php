<?php

use Inertia\Inertia;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\VenueDashboardController;
use App\Http\Controllers\VenueResourceController;

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

    Route::get('venues/{venue:slug}/resources', [VenueResourceController::class, 'index'])
        ->name('venues.resources.index');

    Route::get('venues/{venue:slug}/resources/create', [VenueResourceController::class, 'create'])
        ->name('venues.resources.create');

    Route::post('venues/{venue:slug}/resources', [VenueResourceController::class, 'store'])
        ->name('venues.resources.store');

});

require __DIR__.'/settings.php';
