<?php

namespace App\Models;

use App\Enums\ResourceType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TournamentResource extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'tournament_id',
        'venue_resource_id',
        'name',
        'type',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'type' => ResourceType::class,
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function venueResource(): BelongsTo
    {
        return $this->belongsTo(VenueResource::class);
    }

    public function matches(): HasMany
    {
        return $this->hasMany(TournamentMatch::class);
    }
}
