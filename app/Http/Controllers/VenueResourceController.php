<?php

namespace App\Http\Controllers;

use App\Models\Venue;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

use App\Enums\ResourceType;
use App\Models\VenueResource;

class VenueResourceController extends Controller
{
    public function index(Request $request, Venue $venue): Response
    {
        $user = $request->user();

        abort_unless($user->canAccessVenue($venue), 403);

        $resources = $venue->venueResources()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn ($resource) => [
                'id' => $resource->id,
                'name' => $resource->name,
                'type' => $resource->type->value,
                'sort_order' => $resource->sort_order,
                'is_active' => $resource->is_active,
            ]);

        return Inertia::render('Venues/Resources/Index', [
            'venue' => [
                'id' => $venue->id,
                'name' => $venue->name,
                'slug' => $venue->slug,
            ],
            'resources' => $resources,
        ]);
    }

    public function create(Request $request, Venue $venue): Response
    {
        $user = $request->user();

        abort_unless($user->canAccessVenue($venue), 403);

        return Inertia::render('Venues/Resources/Create', [
            'venue' => [
                'id' => $venue->id,
                'name' => $venue->name,
                'slug' => $venue->slug,
            ],
            'resourceTypes' => collect(ResourceType::cases())
                ->map(fn ($type) => [
                    'label' => $type->value,
                    'value' => $type->value,
                ]),
        ]);
    }

    public function store(Request $request, Venue $venue)
    {
        $user = $request->user();

        abort_unless($user->canAccessVenue($venue), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'in:dart_board,beer_pong_table,other'],
            'sort_order' => ['required', 'integer', 'min:0'],
            'is_active' => ['required', 'boolean'],
        ]);

        $venue->venueResources()->create([
            'name' => $validated['name'],
            'type' => ResourceType::from($validated['type']),
            'sort_order' => $validated['sort_order'],
            'is_active' => $validated['is_active'],
        ]);

        return redirect()
            ->route('venues.resources.index', ['venue' => $venue->slug])
            ->with('success', 'Resource je uspešno dodat.');
    }

    public function edit(Request $request, Venue $venue, VenueResource $resource): Response
    {
        $user = $request->user();

        abort_unless($user->canAccessVenue($venue), 403);
        abort_unless($resource->venue_id === $venue->id, 404);

        return Inertia::render('Venues/Resources/Edit', [
            'venue' => [
                'id' => $venue->id,
                'name' => $venue->name,
                'slug' => $venue->slug,
            ],
            'resource' => [
                'id' => $resource->id,
                'name' => $resource->name,
                'type' => $resource->type->value,
                'sort_order' => $resource->sort_order,
                'is_active' => $resource->is_active,
            ],
            'resourceTypes' => collect(ResourceType::cases())
                ->map(fn ($type) => [
                    'label' => $type->value,
                    'value' => $type->value,
                ]),
        ]);
    }

    public function update(Request $request, Venue $venue, VenueResource $resource)
    {
        $user = $request->user();

        abort_unless($user->canAccessVenue($venue), 403);
        abort_unless($resource->venue_id === $venue->id, 404);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'in:dart_board,beer_pong_table,other'],
            'sort_order' => ['required', 'integer', 'min:0'],
            'is_active' => ['required', 'boolean'],
        ]);

        $resource->update([
            'name' => $validated['name'],
            'type' => ResourceType::from($validated['type']),
            'sort_order' => $validated['sort_order'],
            'is_active' => $validated['is_active'],
        ]);

        return redirect()
            ->route('venues.resources.index', ['venue' => $venue->slug])
            ->with('success', 'Resource je uspešno izmenjen.');
    }

    public function destroy(Request $request, Venue $venue, VenueResource $resource)
    {
        $user = $request->user();

        abort_unless($user->canAccessVenue($venue), 403);
        abort_unless($resource->venue_id === $venue->id, 404);

        $resource->delete();

        return redirect()
            ->route('venues.resources.index', ['venue' => $venue->slug])
            ->with('success', 'Resource je uspešno obrisan.');
    }
}
