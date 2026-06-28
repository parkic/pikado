<?php

use Inertia\Inertia;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\VenueDashboardController;
use App\Http\Controllers\VenueResourceController;
use App\Http\Controllers\PlayerController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\TournamentController;
use App\Http\Controllers\TournamentGroupDrawController;
use App\Http\Controllers\TournamentScheduleController;

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
    Route::delete('venues/{venue:slug}/players/{player}', [PlayerController::class, 'destroy'])
        ->name('venues.players.destroy');


    // Teams
    Route::get('venues/{venue:slug}/teams', [TeamController::class, 'index'])
        ->name('venues.teams.index');
    Route::get('venues/{venue:slug}/teams/create', [TeamController::class, 'create'])
        ->name('venues.teams.create');
    Route::post('venues/{venue:slug}/teams', [TeamController::class, 'store'])
        ->name('venues.teams.store');
    Route::get('venues/{venue:slug}/teams/{team}/edit', [TeamController::class, 'edit'])
        ->name('venues.teams.edit');
    Route::put('venues/{venue:slug}/teams/{team}', [TeamController::class, 'update'])
        ->name('venues.teams.update');
    Route::delete('venues/{venue:slug}/teams/{team}', [TeamController::class, 'destroy'])
        ->name('venues.teams.destroy');


    // Tournaments
    Route::get('venues/{venue:slug}/tournaments', [TournamentController::class, 'index'])
        ->name('venues.tournaments.index');
    Route::get('venues/{venue:slug}/tournaments/create', [TournamentController::class, 'create'])
        ->name('venues.tournaments.create');
    Route::post('venues/{venue:slug}/tournaments', [TournamentController::class, 'store'])
        ->name('venues.tournaments.store');
    Route::get('venues/{venue:slug}/tournaments/{tournament:slug}/groups/setup', [TournamentController::class, 'setupGroups'])
        ->scopeBindings()
        ->name('venues.tournaments.groups.setup');
    Route::post('venues/{venue:slug}/tournaments/{tournament:slug}/groups/setup', [TournamentController::class, 'storeGroups'])
        ->scopeBindings()
        ->name('venues.tournaments.groups.store');
    Route::get('venues/{venue:slug}/tournaments/{tournament:slug}/group-draw', [TournamentGroupDrawController::class, 'show'])
        ->scopeBindings()
        ->name('venues.tournaments.group_draw.show');
    Route::post('venues/{venue:slug}/tournaments/{tournament:slug}/group-draw', [TournamentGroupDrawController::class, 'store'])
        ->scopeBindings()
        ->name('venues.tournaments.group_draw.store');
    Route::delete('venues/{venue:slug}/tournaments/{tournament:slug}/group-draw/participants/{participant}', [TournamentGroupDrawController::class, 'destroyParticipant'])
        ->scopeBindings()
        ->name('venues.tournaments.group_draw.participants.destroy');
    Route::get('venues/{venue:slug}/tournaments/{tournament:slug}/group-draw/participants/{participant}/edit', [TournamentGroupDrawController::class, 'editParticipant'])
        ->scopeBindings()
        ->name('venues.tournaments.group_draw.participants.edit');
    Route::put('venues/{venue:slug}/tournaments/{tournament:slug}/group-draw/participants/{participant}', [TournamentGroupDrawController::class, 'updateParticipant'])
        ->scopeBindings()
        ->name('venues.tournaments.group_draw.participants.update');
    Route::post('venues/{venue:slug}/tournaments/{tournament:slug}/start-group-draw', [TournamentController::class, 'startGroupDraw'])
        ->scopeBindings()
        ->name('venues.tournaments.start_group_draw');
    Route::post('venues/{venue:slug}/tournaments/{tournament:slug}/mark-ready', [TournamentController::class, 'markReady'])
        ->scopeBindings()
        ->name('venues.tournaments.mark_ready');
    Route::post('venues/{venue:slug}/tournaments/{tournament:slug}/generate-group-matches', [TournamentController::class, 'generateGroupMatches'])
        ->scopeBindings()
        ->name('venues.tournaments.generate_group_matches');

    Route::get('venues/{venue:slug}/tournaments/{tournament:slug}/schedule', [TournamentScheduleController::class, 'index'])
        ->scopeBindings()
        ->name('venues.tournaments.schedule.index');
    Route::patch('venues/{venue:slug}/tournaments/{tournament:slug}/schedule/matches/{match}/resource', [TournamentScheduleController::class, 'updateResource'])
        ->scopeBindings()
        ->name('venues.tournaments.schedule.matches.resource');
    Route::get('venues/{venue:slug}/tournaments/{tournament:slug}', [TournamentController::class, 'show'])
        ->name('venues.tournaments.show');

});

require __DIR__.'/settings.php';
