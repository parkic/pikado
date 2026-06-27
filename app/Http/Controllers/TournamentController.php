<?php

namespace App\Http\Controllers;

use App\Enums\GameType;
use App\Enums\GroupRounds;
use App\Enums\MatchMode;
use App\Enums\ScoringMode;
use App\Enums\TournamentStatus;
use App\Models\Tournament;
use App\Models\TournamentResource;
use App\Models\Venue;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class TournamentController extends Controller
{
    public function index(Request $request, Venue $venue): Response
    {
        $user = $request->user();

        abort_unless($user->canAccessVenue($venue), 403);

        $tournaments = $venue->tournaments()
            ->withCount('resources')
            ->latest()
            ->get()
            ->map(fn (Tournament $tournament) => [
                'id' => $tournament->id,
                'name' => $tournament->name,
                'slug' => $tournament->slug,
                'public_code' => $tournament->public_code,
                'game_type' => $tournament->game_type->value,
                'game_type_label' => $this->gameTypeLabel($tournament->game_type),
                'match_mode' => $tournament->match_mode->value,
                'match_mode_label' => $this->matchModeLabel($tournament->match_mode),
                'status' => $tournament->status->value,
                'status_label' => $this->statusLabel($tournament->status),
                'knockout_size' => $tournament->knockout_size,
                'public_enabled' => $tournament->public_enabled,
                'resources_count' => $tournament->resources_count,
                'created_at' => $tournament->created_at?->format('d.m.Y. H:i'),
            ]);

        return Inertia::render('Venues/Tournaments/Index', [
            'venue' => [
                'id' => $venue->id,
                'name' => $venue->name,
                'slug' => $venue->slug,
            ],
            'tournaments' => $tournaments,
        ]);
    }

    public function create(Request $request, Venue $venue): Response
    {
        $user = $request->user();

        abort_unless($user->canAccessVenue($venue), 403);

        $resources = $venue->venueResources()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn ($resource) => [
                'id' => $resource->id,
                'name' => $resource->name,
                'type' => $resource->type->value,
                'type_label' => $this->resourceTypeLabel($resource->type->value),
                'sort_order' => $resource->sort_order,
            ]);

        return Inertia::render('Venues/Tournaments/Create', [
            'venue' => [
                'id' => $venue->id,
                'name' => $venue->name,
                'slug' => $venue->slug,
            ],
            'resources' => $resources,
            'options' => [
                'game_types' => [
                    ['value' => GameType::DART_301->value, 'label' => '301'],
                    ['value' => GameType::DART_501->value, 'label' => '501'],
                    ['value' => GameType::CRICKET->value, 'label' => 'Cricket'],
                    ['value' => GameType::BEER_PONG->value, 'label' => 'Beer Pong'],
                ],
                'match_modes' => [
                    ['value' => MatchMode::SINGLES->value, 'label' => '1v1'],
                    ['value' => MatchMode::DOUBLES->value, 'label' => '2v2'],
                ],
                'group_rounds' => [
                    ['value' => GroupRounds::SINGLE->value, 'label' => 'Jednokružno'],
                    ['value' => GroupRounds::DOUBLE->value, 'label' => 'Dvokružno'],
                ],
                'knockout_sizes' => [
                    ['value' => 8, 'label' => 'Top 8'],
                    ['value' => 16, 'label' => 'Top 16'],
                    ['value' => 32, 'label' => 'Top 32'],
                ],
            ],
        ]);
    }

    public function show(Request $request, Venue $venue, Tournament $tournament): Response
    {
        $user = $request->user();

        abort_unless($user->canAccessVenue($venue), 403);
        abort_unless($tournament->venue_id === $venue->id, 404);

        $tournament->load([
            'createdBy',
            'resources' => fn ($query) => $query
                ->orderBy('sort_order')
                ->orderBy('name'),
        ]);

        return Inertia::render('Venues/Tournaments/Show', [
            'venue' => [
                'id' => $venue->id,
                'name' => $venue->name,
                'slug' => $venue->slug,
            ],
            'tournament' => [
                'id' => $tournament->id,
                'name' => $tournament->name,
                'slug' => $tournament->slug,
                'public_code' => $tournament->public_code,
                'game_type' => $tournament->game_type->value,
                'game_type_label' => $this->gameTypeLabel($tournament->game_type),
                'match_mode' => $tournament->match_mode->value,
                'match_mode_label' => $this->matchModeLabel($tournament->match_mode),
                'status' => $tournament->status->value,
                'status_label' => $this->statusLabel($tournament->status),
                'group_rounds' => $tournament->group_rounds->value,
                'knockout_size' => $tournament->knockout_size,
                'public_enabled' => $tournament->public_enabled,
                'scoring_mode' => $tournament->scoring_mode->value,
                'settings' => $tournament->settings ?? [],
                'created_by' => $tournament->createdBy?->name,
                'created_at' => $tournament->created_at?->format('d.m.Y. H:i'),
                'resources' => $tournament->resources->map(fn ($resource) => [
                    'id' => $resource->id,
                    'name' => $resource->name,
                    'type' => $resource->type->value,
                    'type_label' => $this->resourceTypeLabel($resource->type->value),
                    'sort_order' => $resource->sort_order,
                    'is_active' => $resource->is_active,
                ]),
            ],
        ]);
    }

    public function store(Request $request, Venue $venue): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user->canAccessVenue($venue), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'game_type' => ['required', Rule::in(array_column(GameType::cases(), 'value'))],
            'match_mode' => ['required', Rule::in(array_column(MatchMode::cases(), 'value'))],
            'group_rounds' => ['required', Rule::in(array_column(GroupRounds::cases(), 'value'))],
            'knockout_size' => ['nullable', 'integer', Rule::in([8, 16, 32])],
            'public_enabled' => ['required', 'boolean'],
            'resource_ids' => ['array'],
            'resource_ids.*' => ['integer'],
        ]);

        $resourceIds = collect($validated['resource_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $venueResources = $venue->venueResources()
            ->whereIn('id', $resourceIds)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        if ($resourceIds->count() !== $venueResources->count()) {
            return back()
                ->withErrors([
                    'resource_ids' => 'Neki od izabranih resources ne pripada ovom lokalu ili nisu aktivni.',
                ])
                ->withInput();
        }

        DB::transaction(function () use ($validated, $venue, $venueResources, $user): void {
            $gameType = GameType::from($validated['game_type']);

            $tournament = Tournament::create([
                'venue_id' => $venue->id,
                'name' => $validated['name'],
                'slug' => $this->uniqueSlug($venue, $validated['name']),
                'public_code' => $this->uniquePublicCode(),
                'game_type' => $gameType,
                'match_mode' => MatchMode::from($validated['match_mode']),
                'status' => TournamentStatus::DRAFT,
                'group_rounds' => GroupRounds::from($validated['group_rounds']),
                'scoring_mode' => $this->defaultScoringMode($gameType),
                'knockout_size' => $validated['knockout_size'] ?? null,
                'public_enabled' => $validated['public_enabled'],
                'settings' => [
                    'group_count' => null,
                    'group_size' => null,
                    'direct_qualifiers_per_group' => null,
                    'best_position_qualifiers' => [],
                    'repechage_enabled' => false,
                    'repechage_qualifiers_count' => null,
                    'avoid_same_group_rematch' => true,
                ],
                'created_by_user_id' => $user->id,
            ]);

            foreach ($venueResources as $venueResource) {
                TournamentResource::create([
                    'tournament_id' => $tournament->id,
                    'venue_resource_id' => $venueResource->id,
                    'name' => $venueResource->name,
                    'type' => $venueResource->type,
                    'sort_order' => $venueResource->sort_order,
                    'is_active' => $venueResource->is_active,
                ]);
            }
        });

        return redirect()
            ->route('venues.tournaments.index', $venue)
            ->with('success', 'Turnir je sačuvan kao draft.');
    }

    private function uniqueSlug(Venue $venue, string $name): string
    {
        $baseSlug = Str::slug($name);

        if ($baseSlug === '') {
            $baseSlug = 'turnir';
        }

        $slug = $baseSlug;
        $counter = 2;

        while (
            $venue->tournaments()
                ->withTrashed()
                ->where('slug', $slug)
                ->exists()
        ) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    private function uniquePublicCode(): string
    {
        do {
            $code = Str::lower(Str::random(8));
        } while (
            Tournament::withTrashed()
                ->where('public_code', $code)
                ->exists()
        );

        return $code;
    }

    private function defaultScoringMode(GameType $gameType): ScoringMode
    {
        return match ($gameType) {
            GameType::BEER_PONG => ScoringMode::WINNER_ONLY,
            GameType::DART_301,
            GameType::DART_501,
            GameType::CRICKET => ScoringMode::POINTS_DIFFERENCE,
        };
    }

    private function gameTypeLabel(GameType $gameType): string
    {
        return match ($gameType) {
            GameType::DART_301 => '301',
            GameType::DART_501 => '501',
            GameType::CRICKET => 'Cricket',
            GameType::BEER_PONG => 'Beer Pong',
        };
    }

    private function matchModeLabel(MatchMode $matchMode): string
    {
        return match ($matchMode) {
            MatchMode::SINGLES => '1v1',
            MatchMode::DOUBLES => '2v2',
        };
    }

    private function statusLabel(TournamentStatus $status): string
    {
        return match ($status) {
            TournamentStatus::DRAFT => 'Draft',
            TournamentStatus::GROUP_DRAW => 'Izvlačenje grupa',
            TournamentStatus::READY => 'Spreman',
            TournamentStatus::GROUP_STAGE => 'Grupna faza',
            TournamentStatus::REPECHAGE => 'Repasaž',
            TournamentStatus::KNOCKOUT_DRAW => 'Žreb za nokaut',
            TournamentStatus::KNOCKOUT_STAGE => 'Nokaut faza',
            TournamentStatus::FINISHED => 'Završen',
        };
    }

    private function resourceTypeLabel(string $type): string
    {
        return match ($type) {
            'dart_board' => 'Pikado',
            'beer_pong_table' => 'Beer pong sto',
            'other' => 'Ostalo',
            default => $type,
        };
    }
}
