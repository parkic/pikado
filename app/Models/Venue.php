<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name',
    'slug',
    'logo_path',
    'description',
    'instagram_url',
    'is_active',
])]
class Venue extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function venueUsers(): HasMany
    {
        return $this->hasMany(VenueUser::class);
    }

    public function venueResources(): HasMany
    {
        return $this->hasMany(VenueResource::class);
    }

    public function players(): HasMany
    {
        return $this->hasMany(Player::class);
    }

    public function teams(): HasMany
    {
        return $this->hasMany(Team::class);
    }

    public function tournaments(): HasMany
    {
        return $this->hasMany(Tournament::class);
    }
}
