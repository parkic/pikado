<?php

namespace App\Models;

use App\Enums\GameType;
use App\Enums\GroupRounds;
use App\Enums\MatchMode;
use App\Enums\ScoringMode;
use App\Enums\TournamentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;


class Tournament extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'venue_id',
        'name',
        'slug',
        'public_code',
        'game_type',
        'match_mode',
        'status',
        'group_rounds',
        'scoring_mode',
        'knockout_size',
        'public_enabled',
        'settings',
        'started_at',
        'finished_at',
        'created_by_user_id',
    ];

    protected $casts = [
        'game_type' => GameType::class,
        'match_mode' => MatchMode::class,
        'status' => TournamentStatus::class,
        'group_rounds' => GroupRounds::class,
        'scoring_mode' => ScoringMode::class,
        'knockout_size' => 'integer',
        'public_enabled' => 'boolean',
        'settings' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function resources(): HasMany
    {
        return $this->hasMany(TournamentResource::class);
    }

    public function groups(): HasMany
    {
        return $this->hasMany(TournamentGroup::class);
    }

    public function participants(): HasMany
    {
        return $this->hasMany(TournamentParticipant::class);
    }
}
