<?php

use App\Http\Controllers\PlayerController;
use App\Http\Controllers\PublicPlayerController;
use App\Http\Controllers\PublicTournamentController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\TournamentController;
use App\Http\Controllers\TournamentGroupDrawController;
use App\Http\Controllers\TournamentKnockoutController;
use App\Http\Controllers\TournamentRepechageController;
use App\Http\Controllers\TournamentScheduleController;
use App\Http\Controllers\TournamentStandingsController;
use App\Http\Controllers\VenueDashboardController;
use App\Http\Controllers\VenueResourceController;
use App\Http\Controllers\VenueSettingsController;
use App\Models\Player;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::inertia('/', 'Welcome')->name('home');

Route::get('/t/{publicCode}/live', [PublicTournamentController::class, 'live'])
    ->name('public.tournaments.live');
Route::get('/t/{publicCode}/groups', [PublicTournamentController::class, 'groups'])
    ->name('public.tournaments.groups');
Route::get('/t/{publicCode}/schedule', [PublicTournamentController::class, 'schedule'])
    ->name('public.tournaments.schedule');
Route::get('/t/{publicCode}/knockout', [PublicTournamentController::class, 'knockout'])
    ->name('public.tournaments.knockout');
Route::get('/players/{player}', [PublicPlayerController::class, 'show'])
    ->name('public.players.show');
Route::get('/v/{venue:slug}/players/{player}', [PublicPlayerController::class, 'legacy'])
    ->name('public.players.legacy');

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

    Route::get('venues/{venue:slug}/settings', [VenueSettingsController::class, 'edit'])
        ->name('venues.settings.edit');
    Route::put('venues/{venue:slug}/settings', [VenueSettingsController::class, 'update'])
        ->name('venues.settings.update');

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

    // Global player directory. Players belong to the app, not to a venue.
    Route::get('admin/players', [PlayerController::class, 'index'])->name('players.index');
    Route::get('admin/players/create', [PlayerController::class, 'create'])->name('players.create');
    Route::post('admin/players', [PlayerController::class, 'store'])->name('players.store');
    Route::get('admin/players/{player}/edit', [PlayerController::class, 'edit'])->name('players.edit');
    Route::put('admin/players/{player}', [PlayerController::class, 'update'])->name('players.update');
    Route::delete('admin/players/{player}', [PlayerController::class, 'destroy'])->name('players.destroy');

    // Preserve old bookmarks while keeping the canonical administration outside venues.
    Route::get('venues/{venue:slug}/players', fn () => redirect()->route('players.index'))
        ->name('venues.players.index');
    Route::get('venues/{venue:slug}/players/create', fn () => redirect()->route('players.create'))
        ->name('venues.players.create');
    Route::get('venues/{venue:slug}/players/{player}/edit', fn ($venue, Player $player) => redirect()->route('players.edit', $player))
        ->name('venues.players.edit');

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
    Route::delete('venues/{venue:slug}/tournaments/{tournament:slug}', [TournamentController::class, 'destroy'])
        ->scopeBindings()
        ->name('venues.tournaments.destroy');
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

    Route::get('venues/{venue:slug}/tournaments/{tournament:slug}/group-draw/participants/{participant}/replace', [TournamentGroupDrawController::class, 'replaceParticipant'])
        ->scopeBindings()
        ->name('venues.tournaments.group_draw.participants.replace');

    Route::put('venues/{venue:slug}/tournaments/{tournament:slug}/group-draw/participants/{participant}/replace', [TournamentGroupDrawController::class, 'updateParticipantReplacement'])
        ->scopeBindings()
        ->name('venues.tournaments.group_draw.participants.replace.update');

    Route::post('venues/{venue:slug}/tournaments/{tournament:slug}/start-group-draw', [TournamentController::class, 'startGroupDraw'])
        ->scopeBindings()
        ->name('venues.tournaments.start_group_draw');
    Route::post('venues/{venue:slug}/tournaments/{tournament:slug}/mark-ready', [TournamentController::class, 'markReady'])
        ->scopeBindings()
        ->name('venues.tournaments.mark_ready');
    Route::post('venues/{venue:slug}/tournaments/{tournament:slug}/complete-group-stage', [TournamentController::class, 'completeGroupStage'])
        ->scopeBindings()
        ->name('venues.tournaments.complete_group_stage');
    Route::post('venues/{venue:slug}/tournaments/{tournament:slug}/generate-group-matches', [TournamentController::class, 'generateGroupMatches'])
        ->scopeBindings()
        ->name('venues.tournaments.generate_group_matches');

    Route::get('venues/{venue:slug}/tournaments/{tournament:slug}/schedule', [TournamentScheduleController::class, 'index'])
        ->scopeBindings()
        ->name('venues.tournaments.schedule.index');
    Route::patch('venues/{venue:slug}/tournaments/{tournament:slug}/schedule/matches/{match}/resource', [TournamentScheduleController::class, 'updateResource'])
        ->scopeBindings()
        ->name('venues.tournaments.schedule.matches.resource');
    Route::patch('venues/{venue:slug}/tournaments/{tournament:slug}/schedule/matches/{match}/result', [TournamentScheduleController::class, 'updateResult'])
        ->scopeBindings()
        ->name('venues.tournaments.schedule.matches.result');
    Route::patch('venues/{venue:slug}/tournaments/{tournament:slug}/schedule/matches/{match}/postponement', [TournamentScheduleController::class, 'updatePostponement'])
        ->scopeBindings()
        ->name('venues.tournaments.schedule.matches.postponement');
    Route::get('venues/{venue:slug}/tournaments/{tournament:slug}/standings', [TournamentStandingsController::class, 'index'])
        ->scopeBindings()
        ->name('venues.tournaments.standings.index');
    Route::patch('venues/{venue:slug}/tournaments/{tournament:slug}/standings/participants/{participant}/qualification-override', [TournamentStandingsController::class, 'updateQualificationOverride'])
        ->scopeBindings()
        ->name('venues.tournaments.standings.participants.qualification_override');

    Route::patch('venues/{venue:slug}/tournaments/{tournament:slug}/standings/participants/{participant}/withdraw', [TournamentStandingsController::class, 'withdrawParticipant'])
        ->scopeBindings()
        ->name('venues.tournaments.standings.participants.withdraw');

    Route::patch('venues/{venue:slug}/tournaments/{tournament:slug}/standings/participants/{participant}/restore', [TournamentStandingsController::class, 'restoreParticipant'])
        ->scopeBindings()
        ->name('venues.tournaments.standings.participants.restore');

    Route::get('venues/{venue:slug}/tournaments/{tournament:slug}/qualification/setup', [TournamentController::class, 'setupQualification'])
        ->scopeBindings()
        ->name('venues.tournaments.qualification.setup');
    Route::post('venues/{venue:slug}/tournaments/{tournament:slug}/qualification/setup', [TournamentController::class, 'storeQualification'])
        ->scopeBindings()
        ->name('venues.tournaments.qualification.store');

    // Repechage
    Route::get('venues/{venue:slug}/tournaments/{tournament:slug}/repechage', [TournamentRepechageController::class, 'index'])
        ->scopeBindings()
        ->name('venues.tournaments.repechage.index');
    Route::patch('venues/{venue:slug}/tournaments/{tournament:slug}/repechage/participants/{participant}/outcome', [TournamentRepechageController::class, 'updateOutcome'])
        ->scopeBindings()
        ->name('venues.tournaments.repechage.participants.outcome');
    Route::post('venues/{venue:slug}/tournaments/{tournament:slug}/repechage/complete', [TournamentRepechageController::class, 'complete'])
        ->scopeBindings()
        ->name('venues.tournaments.repechage.complete');

    // Knockout Bracket
    Route::get('venues/{venue:slug}/tournaments/{tournament:slug}/knockout', [TournamentKnockoutController::class, 'index'])
        ->scopeBindings()
        ->name('venues.tournaments.knockout.index');
    Route::post('venues/{venue:slug}/tournaments/{tournament:slug}/knockout/generate', [TournamentKnockoutController::class, 'generate'])
        ->scopeBindings()
        ->name('venues.tournaments.knockout.generate');
    Route::patch('venues/{venue:slug}/tournaments/{tournament:slug}/knockout/seeding', [TournamentKnockoutController::class, 'updateSeeding'])
        ->scopeBindings()
        ->name('venues.tournaments.knockout.seeding.update');
    Route::post('venues/{venue:slug}/tournaments/{tournament:slug}/knockout/draw/next', [TournamentKnockoutController::class, 'drawNext'])
        ->scopeBindings()
        ->name('venues.tournaments.knockout.draw.next');
    Route::delete('venues/{venue:slug}/tournaments/{tournament:slug}/knockout/draw', [TournamentKnockoutController::class, 'resetDraw'])
        ->scopeBindings()
        ->name('venues.tournaments.knockout.draw.reset');

    Route::patch('venues/{venue:slug}/tournaments/{tournament:slug}/knockout/matches/{match}/participants/{participant}/walkover', [TournamentScheduleController::class, 'applyKnockoutWalkover'])
        ->withoutScopedBindings()
        ->name('venues.tournaments.knockout.matches.participants.walkover');

    Route::get('venues/{venue:slug}/tournaments/{tournament:slug}', [TournamentController::class, 'show'])
        ->scopeBindings()
        ->name('venues.tournaments.show');

});

require __DIR__.'/settings.php';
