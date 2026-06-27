<?php

namespace App\Http\Controllers;

use App\Models\Venue;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class VenueDashboardController extends Controller
{
    public function __invoke(Request $request, Venue $venue): Response
    {
        $user = $request->user();

        abort_unless($user->canAccessVenue($venue), 403);

        $venue->load([
            'venueResources' => fn ($query) => $query
                ->where('is_active', true)
                ->orderBy('sort_order'),
        ]);

        return Inertia::render('Venues/Dashboard', [
            'venue' => [
                'id' => $venue->id,
                'name' => $venue->name,
                'slug' => $venue->slug,
                'description' => $venue->description,
                'instagram_url' => $venue->instagram_url,
                'resources' => $venue->venueResources->map(fn ($resource) => [
                    'id' => $resource->id,
                    'name' => $resource->name,
                    'type' => $resource->type->value,
                    'sort_order' => $resource->sort_order,
                ]),
            ],
        ]);
    }
}
