<?php

namespace App\Http\Controllers;

use App\Models\Venue;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class VenueResourceController extends Controller
{
    public function index(Request $request, Venue $venue): Response
    {
        $user = $request->user();

        $hasVenueAccess = $user->global_role?->value === 'superadmin'
            || $user->venueUsers()
                ->where('venue_id', $venue->id)
                ->where('is_active', true)
                ->exists();

        abort_unless($hasVenueAccess, 403);

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
}
