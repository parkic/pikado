<?php

namespace App\Http\Controllers;

use App\Enums\GameType;
use App\Enums\GroupRounds;
use App\Enums\MatchMode;
use App\Enums\MatchStage;
use App\Enums\MatchStatus;
use App\Enums\ScoringMode;
use App\Enums\TournamentStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

use App\Services\GroupMatchGenerator;

use App\Models\Tournament;
use App\Models\TournamentResource;
use App\Models\Venue;
use App\Models\TournamentGroup;
use App\Models\TournamentParticipant;
use App\Models\TournamentMatch;

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
            'groups' => fn ($query) => $query
                ->orderBy('sort_order')
                ->orderBy('name'),
            'groups.participants' => fn ($query) => $query
                ->orderBy('group_position')
                ->orderBy('id'),
            'groups.participants.player',
            'groups.participants.team',
        ]);

        $tournament->loadCount([
            'groups',
            'participants',
            'matches as group_matches_count' => fn ($query) => $query
                ->where('stage', MatchStage::GROUP->value),
            'matches as finished_group_matches_count' => fn ($query) => $query
                ->where('stage', MatchStage::GROUP->value)
                ->where('status', MatchStatus::FINISHED->value),
        ]);

        $totalSlots = $this->totalGroupSlots($tournament);

        $podium = $this->tournamentPodium($tournament);

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
                'groups_count' => $tournament->groups_count,
                'participants_count' => $tournament->participants_count,
                'total_slots' => $totalSlots,
                'group_matches_count' => $tournament->group_matches_count,
                'finished_group_matches_count' => $tournament->finished_group_matches_count,
                'can_start_group_draw' => $this->canStartGroupDraw($tournament, $totalSlots),
                'can_mark_ready' => $this->canMarkReady($tournament, $totalSlots),
                'can_generate_group_matches' => $this->canGenerateGroupMatches($tournament, $totalSlots),
                'can_complete_group_stage' => $this->canCompleteGroupStage($tournament),
                'next_stage_after_groups' => $this->nextStageAfterGroups($tournament),
                'podium' => $podium,
                'groups' => $tournament->groups->map(fn (TournamentGroup $group) => [
                    'id' => $group->id,
                    'name' => $group->name,
                    'sort_order' => $group->sort_order,
                    'participants' => $group->participants->map(fn (TournamentParticipant $participant) => [
                        'id' => $participant->id,
                        'participant_type' => $participant->participant_type->value,
                        'group_position' => $participant->group_position,
                        'status' => $participant->status->value,
                        'display_name' => $this->participantDisplayName($participant),
                    ]),
                ]),
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

    public function setupGroups(Request $request, Venue $venue, Tournament $tournament): Response
    {
        $user = $request->user();

        abort_unless($user->canAccessVenue($venue), 403);
        abort_unless($tournament->venue_id === $venue->id, 404);

        $tournament->load([
            'groups' => fn ($query) => $query
                ->orderBy('sort_order')
                ->orderBy('name'),
        ]);

        return Inertia::render('Venues/Tournaments/SetupGroups', [
            'venue' => [
                'id' => $venue->id,
                'name' => $venue->name,
                'slug' => $venue->slug,
            ],
            'tournament' => [
                'id' => $tournament->id,
                'name' => $tournament->name,
                'slug' => $tournament->slug,
                'status' => $tournament->status->value,
                'status_label' => $this->statusLabel($tournament->status),
                'settings' => $tournament->settings ?? [],
                'participants_count' => $tournament->participants()->count(),
                'groups' => $tournament->groups->map(fn (TournamentGroup $group) => [
                    'id' => $group->id,
                    'name' => $group->name,
                    'sort_order' => $group->sort_order,
                ]),
            ],
        ]);
    }

    public function storeGroups(Request $request, Venue $venue, Tournament $tournament): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user->canAccessVenue($venue), 403);
        abort_unless($tournament->venue_id === $venue->id, 404);

        if ($tournament->participants()->exists()) {
            return back()
                ->withErrors([
                    'group_count' => 'Grupe ne mogu da se menjaju kada turnir već ima učesnike.',
                ])
                ->withInput();
        }

        $validated = $request->validate([
            'group_count' => ['required', 'integer', 'min:1', 'max:32'],
            'group_size' => ['required', 'integer', 'min:2', 'max:16'],
        ]);

        DB::transaction(function () use ($tournament, $validated): void {
            $groupCount = (int) $validated['group_count'];
            $groupSize = (int) $validated['group_size'];

            $desiredGroupNames = collect(range(1, $groupCount))
                ->map(fn (int $index) => $this->groupNameFromIndex($index));

            $existingGroups = $tournament->groups()
                ->withTrashed()
                ->get()
                ->keyBy('name');

            foreach ($desiredGroupNames as $index => $groupName) {
                $group = $existingGroups->get($groupName);

                if ($group) {
                    $group->sort_order = $index + 1;

                    if ($group->trashed()) {
                        $group->restore();
                    }

                    $group->save();

                    continue;
                }

                TournamentGroup::create([
                    'tournament_id' => $tournament->id,
                    'name' => $groupName,
                    'sort_order' => $index + 1,
                ]);
            }

            $tournament->groups()
                ->whereNotIn('name', $desiredGroupNames->all())
                ->get()
                ->each(fn (TournamentGroup $group) => $group->delete());

            $settings = $tournament->settings ?? [];

            $settings['group_count'] = $groupCount;
            $settings['group_size'] = $groupSize;

            $tournament->update([
                'settings' => $settings,
            ]);
        });

        return redirect()
            ->route('venues.tournaments.show', [$venue, $tournament])
            ->with('success', 'Grupe su sačuvane.');
    }

    public function setupQualification(Request $request, Venue $venue, Tournament $tournament): Response
    {
        $user = $request->user();

        abort_unless($user->canAccessVenue($venue), 403);
        abort_unless($tournament->venue_id === $venue->id, 404);

        return Inertia::render('Venues/Tournaments/SetupQualification', [
            'venue' => [
                'id' => $venue->id,
                'name' => $venue->name,
                'slug' => $venue->slug,
            ],
            'tournament' => [
                'id' => $tournament->id,
                'name' => $tournament->name,
                'slug' => $tournament->slug,
                'settings' => $tournament->settings ?? [],
                'groups_count' => $tournament->groups()->count(),
                'participants_count' => $tournament->participants()->count(),
            ],
        ]);
    }

    public function storeQualification(Request $request, Venue $venue, Tournament $tournament): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user->canAccessVenue($venue), 403);
        abort_unless($tournament->venue_id === $venue->id, 404);

        $validated = $request->validate([
            'direct_qualifiers_per_group' => ['required', 'integer', 'min:0', 'max:16'],
            'repechage_enabled' => ['required', 'boolean'],
            'repechage_participants_count' => ['nullable', 'integer', 'min:0', 'max:128'],
            'repechage_qualifiers_count' => ['nullable', 'integer', 'min:0', 'max:64'],
        ]);

        $groupsCount = $tournament->groups()->count();
        $groupSize = (int) data_get($tournament->settings ?? [], 'group_size', 0);

        $directQualifiersPerGroup = (int) $validated['direct_qualifiers_per_group'];
        $repechageEnabled = (bool) $validated['repechage_enabled'];
        $repechageParticipantsCount = $validated['repechage_participants_count'] !== null
            ? (int) $validated['repechage_participants_count']
            : 0;

        if ($repechageEnabled && $repechageParticipantsCount > 0) {
            if ($groupsCount < 1) {
                return back()->withErrors([
                    'repechage_participants_count' => 'Prvo moraš da podesiš grupe.',
                ]);
            }

            if ($repechageParticipantsCount % $groupsCount !== 0) {
                return back()->withErrors([
                    'repechage_participants_count' => 'Ukupan broj učesnika u repasažu mora biti deljiv sa brojem grupa.',
                ]);
            }

            $repechagePerGroup = (int) ($repechageParticipantsCount / $groupsCount);
            $availableRepechageSlotsPerGroup = max(0, $groupSize - $directQualifiersPerGroup);

            if ($groupSize > 0 && $repechagePerGroup > $availableRepechageSlotsPerGroup) {
                return back()->withErrors([
                    'repechage_participants_count' => 'Previše učesnika za repasaž. Po grupi nema dovoljno učesnika posle direktnog prolaza.',
                ]);
            }
        }

        $settings = $tournament->settings ?? [];

        $settings['direct_qualifiers_per_group'] = (int) $validated['direct_qualifiers_per_group'];
        $settings['repechage_enabled'] = (bool) $validated['repechage_enabled'];
        $settings['repechage_participants_count'] = $validated['repechage_participants_count'] !== null
            ? (int) $validated['repechage_participants_count']
            : null;
        $settings['repechage_qualifiers_count'] = $validated['repechage_qualifiers_count'] !== null
            ? (int) $validated['repechage_qualifiers_count']
            : null;

        $tournament->update([
            'settings' => $settings,
        ]);

        return redirect()
            ->route('venues.tournaments.standings.index', [$venue, $tournament])
            ->with('success', 'Podešavanja prolaza iz grupe su sačuvana.');
    }

    public function startGroupDraw(Request $request, Venue $venue, Tournament $tournament): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user->canAccessVenue($venue), 403);
        abort_unless($tournament->venue_id === $venue->id, 404);

        $totalSlots = $this->totalGroupSlots($tournament);

        if (! $this->canStartGroupDraw($tournament, $totalSlots)) {
            return back()->withErrors([
                'status' => 'Group Draw ne može da se pokrene dok grupe nisu podešene ili status turnira nije validan.',
            ]);
        }

        $tournament->update([
            'status' => TournamentStatus::GROUP_DRAW,
        ]);

        return redirect()
            ->route('venues.tournaments.show', [$venue, $tournament])
            ->with('success', 'Group Draw je pokrenut.');
    }

    public function markReady(Request $request, Venue $venue, Tournament $tournament): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user->canAccessVenue($venue), 403);
        abort_unless($tournament->venue_id === $venue->id, 404);

        $totalSlots = $this->totalGroupSlots($tournament);

        if (! $this->canMarkReady($tournament, $totalSlots)) {
            return back()->withErrors([
                'status' => 'Turnir ne može biti označen kao spreman dok sva mesta u grupama nisu popunjena.',
            ]);
        }

        $tournament->update([
            'status' => TournamentStatus::READY,
        ]);

        return redirect()
            ->route('venues.tournaments.show', [$venue, $tournament])
            ->with('success', 'Turnir je označen kao spreman.');
    }

    public function generateGroupMatches(
        Request $request,
        Venue $venue,
        Tournament $tournament,
        GroupMatchGenerator $generator
    ): RedirectResponse {
        $user = $request->user();

        abort_unless($user->canAccessVenue($venue), 403);
        abort_unless($tournament->venue_id === $venue->id, 404);

        $totalSlots = $this->totalGroupSlots($tournament);

        if (! $this->canGenerateGroupMatches($tournament, $totalSlots)) {
            return back()->withErrors([
                'matches' => 'Grupni mečevi ne mogu da se generišu. Turnir mora biti spreman, grupe popunjene i bez već generisanih grupnih mečeva.',
            ]);
        }

        $createdMatches = $generator->generate($tournament);

        $tournament->update([
            'status' => TournamentStatus::GROUP_STAGE,
        ]);

        return redirect()
            ->route('venues.tournaments.show', [$venue, $tournament])
            ->with('success', 'Generisano grupnih mečeva: ' . $createdMatches . '.');
    }

    public function completeGroupStage(Request $request, Venue $venue, Tournament $tournament): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user->canAccessVenue($venue), 403);
        abort_unless($tournament->venue_id === $venue->id, 404);

        if (! $this->canCompleteGroupStage($tournament)) {
            return back()->withErrors([
                'group_stage' => 'Grupna faza ne može biti završena dok svi grupni mečevi nisu završeni.',
            ]);
        }

        $nextStatus = $this->nextStageAfterGroups($tournament) === 'repechage'
            ? TournamentStatus::REPECHAGE
            : TournamentStatus::KNOCKOUT_DRAW;

        $tournament->update([
            'status' => $nextStatus,
        ]);

        return redirect()
            ->route('venues.tournaments.show', [$venue, $tournament])
            ->with('success', 'Grupna faza je završena.');
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

    private function groupNameFromIndex(int $index): string
    {
        $name = '';

        while ($index > 0) {
            $index--;

            $name = chr(65 + ($index % 26)) . $name;
            $index = intdiv($index, 26);
        }

        return $name;
    }

    private function participantDisplayName(TournamentParticipant $participant): string
    {
        if ($participant->player) {
            $name = trim($participant->player->first_name . ' ' . $participant->player->last_name);

            if ($participant->player->nickname) {
                return $name . ' (' . $participant->player->nickname . ')';
            }

            return $name;
        }

        if ($participant->team) {
            return $participant->team->name;
        }

        return 'Nepoznat učesnik';
    }

    private function tournamentPodium(Tournament $tournament): array
    {
        $final = $this->knockoutSeriesResult($tournament, MatchStage::FINAL);
        $thirdPlace = $this->knockoutSeriesResult($tournament, MatchStage::THIRD_PLACE);

        return [
            'champion' => $final['winner'],
            'second_place' => $final['loser'],
            'third_place' => $thirdPlace['winner'],
            'fourth_place' => $thirdPlace['loser'],
            'final_score' => $final['score'],
            'third_place_score' => $thirdPlace['score'],
            'is_complete' => $final['winner'] !== null && $thirdPlace['winner'] !== null,
        ];
    }

    private function knockoutSeriesResult(Tournament $tournament, MatchStage $stage): array
    {
        $allMatches = TournamentMatch::query()
            ->with([
                'participantA.player',
                'participantA.team',
                'participantB.player',
                'participantB.team',
            ])
            ->where('tournament_id', $tournament->id)
            ->where('stage', $stage->value)
            ->orderBy('bracket_position')
            ->orderBy('round_robin_leg')
            ->orderBy('id')
            ->get();

        if ($allMatches->isEmpty()) {
            return [
                'winner' => null,
                'loser' => null,
                'score' => null,
            ];
        }

        $firstMatch = $allMatches->first();

        $seriesMatches = $allMatches
            ->filter(fn (TournamentMatch $match) => $match->bracket_round === $firstMatch->bracket_round
                && (int) $match->bracket_position === (int) $firstMatch->bracket_position)
            ->values();

        $winsRequired = (int) ($firstMatch->wins_required ?: 1);

        $winsByParticipant = [];
        $seriesWinnerId = null;

        foreach ($seriesMatches as $seriesMatch) {
            if ($seriesMatch->status !== MatchStatus::FINISHED) {
                continue;
            }

            if (! $seriesMatch->winner_participant_id) {
                continue;
            }

            $winnerParticipantId = (int) $seriesMatch->winner_participant_id;

            $winsByParticipant[$winnerParticipantId] =
                ($winsByParticipant[$winnerParticipantId] ?? 0) + 1;

            if ($winsByParticipant[$winnerParticipantId] >= $winsRequired) {
                $seriesWinnerId = $winnerParticipantId;
                break;
            }
        }

        if (! $seriesWinnerId) {
            return [
                'winner' => null,
                'loser' => null,
                'score' => null,
            ];
        }

        $seriesLoserId = $this->seriesOpponentId($seriesMatches, $seriesWinnerId);

        $winnerWins = $winsByParticipant[$seriesWinnerId] ?? 0;
        $loserWins = $seriesLoserId
            ? ($winsByParticipant[$seriesLoserId] ?? 0)
            : 0;

        return [
            'winner' => $this->participantSummary(
                $this->participantFromSeries($seriesMatches, $seriesWinnerId)
            ),
            'loser' => $this->participantSummary(
                $this->participantFromSeries($seriesMatches, $seriesLoserId)
            ),
            'score' => $winnerWins . ':' . $loserWins,
        ];
    }

    private function seriesOpponentId($seriesMatches, int $winnerParticipantId): ?int
    {
        foreach ($seriesMatches as $seriesMatch) {
            if ($seriesMatch->participant_a_id && (int) $seriesMatch->participant_a_id !== $winnerParticipantId) {
                return (int) $seriesMatch->participant_a_id;
            }

            if ($seriesMatch->participant_b_id && (int) $seriesMatch->participant_b_id !== $winnerParticipantId) {
                return (int) $seriesMatch->participant_b_id;
            }
        }

        return null;
    }

    private function participantFromSeries($seriesMatches, ?int $participantId): ?TournamentParticipant
    {
        if (! $participantId) {
            return null;
        }

        foreach ($seriesMatches as $seriesMatch) {
            if ($seriesMatch->participantA && (int) $seriesMatch->participantA->id === $participantId) {
                return $seriesMatch->participantA;
            }

            if ($seriesMatch->participantB && (int) $seriesMatch->participantB->id === $participantId) {
                return $seriesMatch->participantB;
            }
        }

        return null;
    }

    private function participantSummary(?TournamentParticipant $participant): ?array
    {
        if (! $participant) {
            return null;
        }

        return [
            'id' => $participant->id,
            'display_name' => $this->participantDisplayName($participant),
            'group_position' => $participant->group_position,
        ];
    }

    private function totalGroupSlots(Tournament $tournament): int
    {
        $groupSize = (int) data_get($tournament->settings ?? [], 'group_size', 0);

        if ($groupSize < 1) {
            return 0;
        }

        return $tournament->groups()->count() * $groupSize;
    }

    private function canStartGroupDraw(Tournament $tournament, int $totalSlots): bool
    {
        return $totalSlots > 0
            && in_array($tournament->status, [
                TournamentStatus::DRAFT,
                TournamentStatus::GROUP_DRAW,
            ], true);
    }

    private function canMarkReady(Tournament $tournament, int $totalSlots): bool
    {
        if ($totalSlots < 1) {
            return false;
        }

        if (! in_array($tournament->status, [
            TournamentStatus::DRAFT,
            TournamentStatus::GROUP_DRAW,
            TournamentStatus::READY,
        ], true)) {
            return false;
        }

        return $tournament->participants()->count() >= $totalSlots;
    }

    private function canGenerateGroupMatches(Tournament $tournament, int $totalSlots): bool
    {
        if ($tournament->status !== TournamentStatus::READY) {
            return false;
        }

        if ($totalSlots < 1) {
            return false;
        }

        if ($tournament->participants()->count() < $totalSlots) {
            return false;
        }

        return ! $tournament->matches()
            ->where('stage', MatchStage::GROUP->value)
            ->exists();
    }

    private function canCompleteGroupStage(Tournament $tournament): bool
    {
        if ($tournament->status !== TournamentStatus::GROUP_STAGE) {
            return false;
        }

        $groupMatchesCount = $tournament->matches()
            ->where('stage', MatchStage::GROUP->value)
            ->count();

        if ($groupMatchesCount < 1) {
            return false;
        }

        $unfinishedGroupMatchesCount = $tournament->matches()
            ->where('stage', MatchStage::GROUP->value)
            ->where('status', '!=', MatchStatus::FINISHED->value)
            ->count();

        return $unfinishedGroupMatchesCount === 0;
    }

    private function nextStageAfterGroups(Tournament $tournament): string
    {
        $repechageEnabled = (bool) data_get(
            $tournament->settings ?? [],
            'repechage_enabled',
            false,
        );

        return $repechageEnabled ? 'repechage' : 'knockout_draw';
    }
}
