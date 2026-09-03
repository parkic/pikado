<?php

namespace App\Http\Controllers;

use App\Models\Venue;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\File;
use Inertia\Inertia;
use Inertia\Response;

class VenueSettingsController extends Controller
{
    public function edit(Request $request, Venue $venue): Response
    {
        abort_unless($request->user()->canAccessVenue($venue), 403);

        return Inertia::render('Venues/Settings/Edit', [
            'venue' => [
                'id' => $venue->id,
                'name' => $venue->name,
                'slug' => $venue->slug,
                'description' => $venue->description,
                'address' => $venue->address,
                'phone' => $venue->phone,
                'website_url' => $venue->website_url,
                'instagram_url' => $venue->instagram_url,
                'public_theme' => $venue->public_theme ?: 'dark',
                'logo_url' => $this->logoUrl($venue),
            ],
        ]);
    }

    public function update(Request $request, Venue $venue): RedirectResponse
    {
        abort_unless($request->user()->canAccessVenue($venue), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'website_url' => ['nullable', 'url:http,https', 'max:2048'],
            'instagram_url' => ['nullable', 'url:http,https', 'max:2048'],
            'public_theme' => ['required', 'string', 'in:dark,light'],
            'logo' => [
                'nullable',
                File::image()
                    ->types(['jpg', 'jpeg', 'png', 'webp'])
                    ->max(5 * 1024),
            ],
            'remove_logo' => ['sometimes', 'boolean'],
        ]);

        $logoPath = $venue->logo_path;

        if (($validated['remove_logo'] ?? false) && $logoPath) {
            Storage::disk('public')->delete($logoPath);
            $logoPath = null;
        }

        if ($request->hasFile('logo')) {
            if ($logoPath) {
                Storage::disk('public')->delete($logoPath);
            }

            $logoPath = $request->file('logo')->store(
                "venues/{$venue->id}",
                'public',
            );
        }

        $venue->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'address' => $validated['address'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'website_url' => $validated['website_url'] ?? null,
            'instagram_url' => $validated['instagram_url'] ?? null,
            'public_theme' => $validated['public_theme'],
            'logo_path' => $logoPath,
        ]);

        return redirect()
            ->route('venues.settings.edit', $venue)
            ->with('success', 'Podešavanja lokala su sačuvana.');
    }

    private function logoUrl(Venue $venue): ?string
    {
        if (! $venue->logo_path) {
            return null;
        }

        return Storage::disk('public')->url($venue->logo_path);
    }
}
