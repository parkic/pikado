<?php

namespace App\Http\Controllers;

use App\Enums\GameType;
use App\Enums\GroupRounds;
use App\Enums\MatchMode;
use App\Enums\MatchStage;
use App\Enums\MatchStatus;
use App\Enums\ParticipantStatus;
use App\Enums\ScoringMode;
use App\Enums\TournamentStatus;
use App\Models\Tournament;
use App\Models\TournamentGroup;
use App\Models\TournamentMatch;
use App\Models\TournamentParticipant;
use App\Models\TournamentResource;
use App\Models\Venue;
use App\Services\GroupMatchGenerator;
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

        $canDeleteTournament = $user->canAdministerVenue($venue);

        $tournaments = $venue->tournaments()
            ->withCount([
                'resources',
                'participants',
                'matches',
                'matches as finished_matches_count' => fn ($query) => $query
                    ->where('status', MatchStatus::FINISHED->value),
            ])
            ->latest()
            ->get()
            ->map(fn (Tournament $tournament) => [
                'id' => $tournament->id,
                'name' => $tournament->name,
                'tournament_date' => $tournament->tournament_date?->format('d.m.Y.'),
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
                'participants_count' => $tournament->participants_count,
                'matches_count' => $tournament->matches_count,
                'finished_matches_count' => $tournament->finished_matches_count,
                'can_delete' => $canDeleteTournament,
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
                'default_date' => now()->toDateString(),
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
                    ['value' => 12, 'label' => 'Top 12'],
                    ['value' => 16, 'label' => 'Top 16'],
                    ['value' => 20, 'label' => 'Top 20'],
                    ['value' => 24, 'label' => 'Top 24'],
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
                'tournament_date' => $tournament->tournament_date?->format('d.m.Y.'),
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
                'can_mark_ready' => $this->canMarkReady($tournament),
                'can_generate_group_matches' => $this->canGenerateGroupMatches($tournament),
                'can_complete_group_stage' => $this->canCompleteGroupStage($tournament),
                'can_delete' => $user->canAdministerVenue($venue),
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

    public function destroy(
        Request $request,
        Venue $venue,
        Tournament $tournament
    ): RedirectResponse {
        $user = $request->user();

        abort_unless($user->canAdministerVenue($venue), 403);
        abort_unless($tournament->venue_id === $venue->id, 404);

        DB::transaction(fn () => $tournament->delete());

        return redirect()
            ->route('venues.tournaments.index', $venue)
            ->with('success', 'Turnir „'.$tournament->name.'” je obrisan.');
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

        if (! in_array($tournament->status, [
            TournamentStatus::DRAFT,
            TournamentStatus::GROUP_DRAW,
            TournamentStatus::READY,
            TournamentStatus::GROUP_STAGE,
        ], true)) {
            return back()->withErrors([
                'qualification' => 'Podešavanje prolaza više ne može da se menja nakon završetka grupne faze.',
            ]);
        }

        $validated = $request->validate([
            'direct_qualifiers_per_group' => ['required', 'integer', 'min:0', 'max:16'],
            'repechage_enabled' => ['required', 'boolean'],
            'repechage_participants_count' => ['nullable', 'integer', 'min:0', 'max:128'],
            'repechage_qualifiers_count' => ['nullable', 'integer', 'min:0', 'max:64'],
        ]);

        $groupsCount = $tournament->groups()->count();
        $groupSize = (int) data_get($tournament->settings ?? [], 'group_size', 0);
        $knockoutSize = (int) $tournament->knockout_size;

        $directQualifiersPerGroup = (int) $validated['direct_qualifiers_per_group'];
        $repechageEnabled = (bool) $validated['repechage_enabled'];
        $repechageParticipantsCount = $validated['repechage_participants_count'] !== null
            ? (int) $validated['repechage_participants_count']
            : 0;
        $repechageQualifiersCount = $validated['repechage_qualifiers_count'] !== null
            ? (int) $validated['repechage_qualifiers_count']
            : 0;

        if ($groupsCount < 1 || $groupSize < 2) {
            return back()->withErrors([
                'direct_qualifiers_per_group' => 'Prvo moraš da podesiš grupe i veličinu grupe.',
            ]);
        }

        if ($directQualifiersPerGroup > $groupSize) {
            return back()->withErrors([
                'direct_qualifiers_per_group' => 'Broj direktnih prolaza ne može biti veći od veličine grupe.',
            ]);
        }

        if ($repechageEnabled) {
            if ($repechageParticipantsCount < 1) {
                return back()->withErrors([
                    'repechage_participants_count' => 'Unesi koliko učesnika ide u repasaž.',
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

            if ($repechageQualifiersCount < 1) {
                return back()->withErrors([
                    'repechage_qualifiers_count' => 'Unesi koliko učesnika prolazi iz repasaža.',
                ]);
            }

            if ($repechageQualifiersCount > $repechageParticipantsCount) {
                return back()->withErrors([
                    'repechage_qualifiers_count' => 'Iz repasaža ne može proći više učesnika nego što ih učestvuje.',
                ]);
            }
        } else {
            $repechageParticipantsCount = 0;
            $repechageQualifiersCount = 0;
        }

        $configuredQualifiersCount = ($groupsCount * $directQualifiersPerGroup)
            + $repechageQualifiersCount;

        if ($knockoutSize > 0 && $configuredQualifiersCount !== $knockoutSize) {
            return back()->withErrors([
                'direct_qualifiers_per_group' => sprintf(
                    'Podešavanja trenutno daju %d učesnika za nokaut, a kostur zahteva tačno %d.',
                    $configuredQualifiersCount,
                    $knockoutSize,
                ),
            ]);
        }

        $settings = $tournament->settings ?? [];

        $settings['direct_qualifiers_per_group'] = (int) $validated['direct_qualifiers_per_group'];
        $settings['repechage_enabled'] = (bool) $validated['repechage_enabled'];
        $settings['repechage_participants_count'] = $repechageEnabled
            ? $repechageParticipantsCount
            : null;
        $settings['repechage_qualifiers_count'] = $repechageEnabled
            ? $repechageQualifiersCount
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
                'status' => 'Unos učesnika ne može da se pokrene dok grupe nisu podešene ili status turnira nije validan.',
            ]);
        }

        $tournament->update([
            'status' => TournamentStatus::GROUP_DRAW,
        ]);

        return redirect()
            ->route('venues.tournaments.show', [$venue, $tournament])
            ->with('success', 'Unos učesnika je pokrenut.');
    }

    public function markReady(Request $request, Venue $venue, Tournament $tournament): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user->canAccessVenue($venue), 403);
        abort_unless($tournament->venue_id === $venue->id, 404);

        if (! in_array($tournament->status, [TournamentStatus::DRAFT, TournamentStatus::GROUP_DRAW, TournamentStatus::READY], true)) {
            return back()->withErrors([
                'status' => 'Unos učesnika više ne može da se završava u trenutnoj fazi turnira.',
            ]);
        }

        if ($tournament
            ->matches()
            ->where('stage', MatchStage::GROUP->value)
            ->exists()
        ) {
            return back()->withErrors([
                'status' => 'Grupni mečevi su već generisani.',
            ]);
        }

        $rosterValidationError = $this->groupRosterValidationError($tournament);

        if ($rosterValidationError !== null) {
            return back()->withErrors([
                'status' => $rosterValidationError,
            ]);
        }

        $tournament->update([
            'status' => TournamentStatus::READY,
        ]);

        return redirect()
            ->route('venues.tournaments.show', [$venue, $tournament])
            ->with('success', 'Unos učesnika je završen. Turnir je spreman za generisanje grupnih mečeva.');
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

        if (! $this->canGenerateGroupMatches($tournament)) {
            return back()->withErrors([
                'matches' => 'Grupni mečevi ne mogu da se generišu. Turnir mora biti spreman, raspodela učesnika po grupama validna i grupni mečevi ne smeju već postojati.',
            ]);
        }

        $createdMatches = $generator->generate($tournament);

        $tournament->update([
            'status' => TournamentStatus::GROUP_STAGE,
        ]);

        return redirect()
            ->route('venues.tournaments.show', [$venue, $tournament])
            ->with('success', 'Generisano grupnih mečeva: '.$createdMatches.'.');
    }

    public function completeGroupStage(Request $request, Venue $venue, Tournament $tournament): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user->canAccessVenue($venue), 403);
        abort_unless($tournament->venue_id === $venue->id, 404);

        if (! $this->canCompleteGroupStage($tournament)) {
            return back()->withErrors([
                'group_stage' => 'Grupna faza ne može biti završena dok svi grupni mečevi nisu završeni ili anulirani.',
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
            'name' => [
                'required',
                'string',
                'max:255',
            ],
            'tournament_date' => [
                'nullable',
                'date_format:Y-m-d',
            ],
            'game_type' => [
                'required',
                Rule::in(array_column(GameType::cases(), 'value')),
            ],
            'match_mode' => [
                'required',
                Rule::in(array_column(MatchMode::cases(), 'value')),
            ],
            'group_rounds' => [
                'required',
                Rule::in(array_column(GroupRounds::cases(), 'value')),
            ],
            'knockout_size' => [
                'nullable',
                'integer',
                Rule::in([8, 12, 16, 20, 24, 32]),
            ],
            'public_enabled' => [
                'required',
                'boolean',
            ],
            'resource_ids' => [
                'array',
            ],
            'resource_ids.*' => [
                'integer',
            ],

            /*
            * Ova polja su privremeno nullable da postojeća
            * Create forma nastavi da radi dok je ne proširimo.
            */
            'group_count' => [
                'nullable',
                'integer',
                'min:1',
                'max:32',
            ],
            'group_size' => [
                'nullable',
                'integer',
                'min:2',
                'max:16',
            ],
            'direct_qualifiers_per_group' => [
                'nullable',
                'integer',
                'min:0',
                'max:16',
            ],
            'repechage_enabled' => [
                'nullable',
                'boolean',
            ],
            'repechage_participants_count' => [
                'nullable',
                'integer',
                'min:0',
                'max:128',
            ],
            'repechage_qualifiers_count' => [
                'nullable',
                'integer',
                'min:0',
                'max:64',
            ],
        ]);

        $groupCount = isset($validated['group_count'])
            ? (int) $validated['group_count']
            : null;

        $groupSize = isset($validated['group_size'])
            ? (int) $validated['group_size']
            : null;

        $directQualifiersPerGroup =
            isset($validated['direct_qualifiers_per_group'])
            ? (int) $validated['direct_qualifiers_per_group']
            : null;

        $repechageEnabled = (bool) (
            $validated['repechage_enabled'] ?? false
        );

        $repechageParticipantsCount =
            isset($validated['repechage_participants_count'])
            ? (int) $validated['repechage_participants_count']
            : null;

        $repechageQualifiersCount =
            isset($validated['repechage_qualifiers_count'])
            ? (int) $validated['repechage_qualifiers_count']
            : null;

        $knockoutSize = isset($validated['knockout_size'])
            ? (int) $validated['knockout_size']
            : null;

        /*
        * Broj grupa i veličina grupe moraju uvek da budu
        * poslati zajedno.
        */
        if (
            ($groupCount === null && $groupSize !== null)
            || ($groupCount !== null && $groupSize === null)
        ) {
            return back()
                ->withErrors([
                    'group_count' => 'Broj grupa i veličina grupe moraju biti zajedno podešeni.',
                ])
                ->withInput();
        }

        /*
        * Kada su grupe podešene, moramo znati koliko
        * učesnika ide direktno iz svake grupe.
        */
        if (
            $groupCount !== null
            && $directQualifiersPerGroup === null
        ) {
            return back()
                ->withErrors([
                    'direct_qualifiers_per_group' => 'Unesi broj direktnih prolaza po grupi.',
                ])
                ->withInput();
        }

        if (
            $groupSize !== null
            && $directQualifiersPerGroup !== null
            && $directQualifiersPerGroup > $groupSize
        ) {
            return back()
                ->withErrors([
                    'direct_qualifiers_per_group' => 'Broj direktnih prolaza ne može biti veći od veličine grupe.',
                ])
                ->withInput();
        }

        if ($repechageEnabled) {
            if ($groupCount === null || $groupSize === null) {
                return back()
                    ->withErrors([
                        'repechage_enabled' => 'Grupe moraju biti podešene pre uključivanja repasaža.',
                    ])
                    ->withInput();
            }

            if (
                $repechageParticipantsCount === null
                || $repechageParticipantsCount < 1
            ) {
                return back()
                    ->withErrors([
                        'repechage_participants_count' => 'Unesi broj učesnika koji ulaze u repasaž.',
                    ])
                    ->withInput();
            }

            if (
                $repechageQualifiersCount === null
                || $repechageQualifiersCount < 1
            ) {
                return back()
                    ->withErrors([
                        'repechage_qualifiers_count' => 'Unesi broj učesnika koji prolaze iz repasaža.',
                    ])
                    ->withInput();
            }

            if (
                $repechageParticipantsCount % $groupCount !== 0
            ) {
                return back()
                    ->withErrors([
                        'repechage_participants_count' => 'Ukupan broj učesnika u repasažu mora biti deljiv sa brojem grupa.',
                    ])
                    ->withInput();
            }

            $repechagePerGroup = (int) (
                $repechageParticipantsCount / $groupCount
            );

            $availableRepechageSlotsPerGroup = max(
                0,
                $groupSize - ($directQualifiersPerGroup ?? 0),
            );

            if (
                $repechagePerGroup
                > $availableRepechageSlotsPerGroup
            ) {
                return back()
                    ->withErrors([
                        'repechage_participants_count' => 'Previše učesnika za repasaž. Po grupi nema dovoljno učesnika posle direktnog prolaza.',
                    ])
                    ->withInput();
            }

            if (
                $repechageQualifiersCount
                > $repechageParticipantsCount
            ) {
                return back()
                    ->withErrors([
                        'repechage_qualifiers_count' => 'Broj prolaza iz repasaža ne može biti veći od broja učesnika u repasažu.',
                    ])
                    ->withInput();
            }
        } else {
            $repechageParticipantsCount = null;
            $repechageQualifiersCount = null;
        }

        /*
        * Kada je kompletno podešavanje poslato, proveravamo
        * da broj kvalifikovanih odgovara veličini nokauta.
        */
        if (
            $groupCount !== null
            && $directQualifiersPerGroup !== null
            && $knockoutSize !== null
        ) {
            $directQualifiersCount =
                $groupCount * $directQualifiersPerGroup;

            $totalKnockoutQualifiers =
                $directQualifiersCount
                + (
                    $repechageEnabled
                    ? ($repechageQualifiersCount ?? 0)
                    : 0
                );

            if ($totalKnockoutQualifiers !== $knockoutSize) {
                return back()
                    ->withErrors([
                        'knockout_size' => sprintf(
                            'Ukupan broj učesnika koji prolaze dalje je %d, a veličina nokauta je %d.',
                            $totalKnockoutQualifiers,
                            $knockoutSize,
                        ),
                    ])
                    ->withInput();
            }
        }

        $resourceIds = collect(
            $validated['resource_ids'] ?? [],
        )
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
                    'resource_ids' => 'Neka od izabrane opreme ne pripada ovom lokalu ili nije aktivna.',
                ])
                ->withInput();
        }

        $tournament = DB::transaction(function () use (
            $validated,
            $venue,
            $venueResources,
            $user,
            $groupCount,
            $groupSize,
            $directQualifiersPerGroup,
            $repechageEnabled,
            $repechageParticipantsCount,
            $repechageQualifiersCount,
            $knockoutSize,
        ): Tournament {
            $gameType = GameType::from(
                $validated['game_type'],
            );

            $tournament = Tournament::create([
                'venue_id' => $venue->id,
                'name' => $validated['name'],
                'tournament_date' => $validated['tournament_date'] ?? now()->toDateString(),
                'slug' => $this->uniqueSlug(
                    $venue,
                    $validated['name'],
                ),
                'public_code' => $this->uniquePublicCode(),
                'game_type' => $gameType,
                'match_mode' => MatchMode::from(
                    $validated['match_mode'],
                ),
                'status' => TournamentStatus::DRAFT,
                'group_rounds' => GroupRounds::from(
                    $validated['group_rounds'],
                ),
                'scoring_mode' => $this->defaultScoringMode(
                    $gameType,
                ),
                'knockout_size' => $knockoutSize,
                'public_enabled' => $validated['public_enabled'],
                'settings' => [
                    'group_count' => $groupCount,
                    'group_size' => $groupSize,
                    'direct_qualifiers_per_group' => $directQualifiersPerGroup,
                    'best_position_qualifiers' => [],
                    'repechage_enabled' => $repechageEnabled,
                    'repechage_participants_count' => $repechageParticipantsCount,
                    'repechage_qualifiers_count' => $repechageQualifiersCount,
                    'avoid_same_group_rematch' => true,
                ],
                'created_by_user_id' => $user->id,
            ]);

            /*
            * Kada Create forma pošalje podešavanje grupa,
            * grupe A, B, C... nastaju odmah.
            */
            if ($groupCount !== null) {
                foreach (range(1, $groupCount) as $index) {
                    TournamentGroup::create([
                        'tournament_id' => $tournament->id,
                        'name' => $this->groupNameFromIndex(
                            $index,
                        ),
                        'sort_order' => $index,
                    ]);
                }
            }

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

            return $tournament;
        });

        return redirect()
            ->route(
                'venues.tournaments.group_draw.show',
                [$venue, $tournament],
            )
            ->with(
                'success',
                'Turnir je kreiran. Dodaj prvog učesnika.',
            );
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
            $slug = $baseSlug.'-'.$counter;
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
            TournamentStatus::DRAFT => 'Priprema',
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

            $name = chr(65 + ($index % 26)).$name;
            $index = intdiv($index, 26);
        }

        return $name;
    }

    private function participantDisplayName(TournamentParticipant $participant): string
    {
        if ($participant->player) {
            $name = trim($participant->player->first_name.' '.$participant->player->last_name);

            if ($participant->player->nickname) {
                return $name.' ('.$participant->player->nickname.')';
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
            'score' => $winnerWins.':'.$loserWins,
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

    private function canMarkReady(Tournament $tournament): bool
    {
        if (! in_array($tournament->status, [TournamentStatus::DRAFT, TournamentStatus::GROUP_DRAW, TournamentStatus::READY], true)) {
            return false;
        }

        if ($tournament
            ->matches()
            ->where('stage', MatchStage::GROUP->value)
            ->exists()
        ) {
            return false;
        }

        return $this->groupRosterValidationError($tournament) === null;
    }

    private function canGenerateGroupMatches(Tournament $tournament): bool
    {
        if ($tournament->status !== TournamentStatus::READY) {
            return false;
        }

        if ($this->groupRosterValidationError($tournament) !== null) {
            return false;
        }

        return ! $tournament->matches()
            ->where('stage', MatchStage::GROUP->value)
            ->exists();
    }

    private function groupRosterValidationError(
        Tournament $tournament
    ): ?string {
        $groups = $tournament->groups()
            ->withCount([
                'participants as active_participants_count' => fn ($query) => $query->where(
                    'status',
                    ParticipantStatus::ACTIVE->value,
                ),
            ])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        if ($groups->isEmpty()) {
            return 'Turnir nema podešene grupe.';
        }

        $participantCounts = $groups
            ->pluck('active_participants_count')
            ->map(fn ($count) => (int) $count);

        $minimumParticipantsCount = (int) (
            $participantCounts->min() ?? 0
        );

        $maximumParticipantsCount = (int) (
            $participantCounts->max() ?? 0
        );

        if ($minimumParticipantsCount < 2) {
            return 'Svaka grupa mora imati najmanje dva učesnika.';
        }

        if (
            $maximumParticipantsCount
            - $minimumParticipantsCount
            > 1
        ) {
            return 'Grupe nisu ravnomerno popunjene. Razlika između najveće i najmanje grupe može biti najviše jedan učesnik.';
        }

        $settings = $tournament->settings ?? [];

        $directQualifiersPerGroup = data_get(
            $settings,
            'direct_qualifiers_per_group',
        );

        if ($directQualifiersPerGroup === null) {
            return 'Podesi broj direktnih prolaza po grupi.';
        }

        $directQualifiersPerGroup =
            (int) $directQualifiersPerGroup;

        if (
            $directQualifiersPerGroup
            > $minimumParticipantsCount
        ) {
            return 'Broj direktnih prolaza po grupi ne može biti veći od broja učesnika u najmanjoj grupi.';
        }

        $groupsCount = $groups->count();

        $repechageEnabled = (bool) data_get(
            $settings,
            'repechage_enabled',
            false,
        );

        $repechageQualifiersCount = 0;

        if ($repechageEnabled) {
            $repechageParticipantsCount = (int) data_get(
                $settings,
                'repechage_participants_count',
                0,
            );

            $repechageQualifiersCount = (int) data_get(
                $settings,
                'repechage_qualifiers_count',
                0,
            );

            if ($repechageParticipantsCount < 1) {
                return 'Podesi broj učesnika koji ulaze u repasaž.';
            }

            if ($repechageQualifiersCount < 1) {
                return 'Podesi broj učesnika koji prolaze iz repasaža.';
            }

            if (
                $repechageParticipantsCount
                % $groupsCount
                !== 0
            ) {
                return 'Ukupan broj učesnika u repasažu mora biti deljiv sa brojem grupa.';
            }

            $repechagePerGroup = (int) (
                $repechageParticipantsCount
                / $groupsCount
            );

            $availableRepechagePlacesPerGroup = max(
                0,
                $minimumParticipantsCount
                    - $directQualifiersPerGroup,
            );

            if (
                $repechagePerGroup
                > $availableRepechagePlacesPerGroup
            ) {
                return 'U najmanjoj grupi nema dovoljno učesnika za podešeni broj direktnih prolaza i mesta u repasažu.';
            }

            if (
                $repechageQualifiersCount
                > $repechageParticipantsCount
            ) {
                return 'Broj prolaza iz repasaža ne može biti veći od broja učesnika u repasažu.';
            }
        }

        if ($tournament->knockout_size !== null) {
            $totalKnockoutParticipants =
                (
                    $groupsCount
                    * $directQualifiersPerGroup
                )
                + $repechageQualifiersCount;

            if (
                $totalKnockoutParticipants
                !== (int) $tournament->knockout_size
            ) {
                return sprintf(
                    'Podešavanja trenutno daju %d učesnika u nokautu, dok je izabran Top %d.',
                    $totalKnockoutParticipants,
                    (int) $tournament->knockout_size,
                );
            }
        }

        return null;
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
            ->whereNotIn('status', [
                MatchStatus::FINISHED->value,
                MatchStatus::VOIDED->value,
                MatchStatus::CANCELLED->value,
            ])
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
