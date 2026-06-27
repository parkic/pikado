<?php

use Inertia\Inertia;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', function () {
        $user = request()->user();

        $venueUsers = $user->venueUsers()
            ->with('venue:id,name,slug')
            ->where('is_active', true)
            ->get()
            ->map(fn ($venueUser) => [
                'id' => $venueUser->id,
                'role' => $venueUser->role->value,
                'venue' => [
                    'id' => $venueUser->venue->id,
                    'name' => $venueUser->venue->name,
                    'slug' => $venueUser->venue->slug,
                ],
            ]);

        return Inertia::render('Dashboard', [
            'userContext' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'global_role' => $user->global_role?->value,
                'venue_users' => $venueUsers,
            ],
        ]);
    })->name('dashboard');
});

require __DIR__.'/settings.php';
