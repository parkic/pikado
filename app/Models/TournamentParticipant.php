<?php

namespace App\Models;

use App\Enums\ParticipantStatus;
use App\Enums\ParticipantType;
use App\Enums\QualificationStatus;
use App\Enums\RepechageOutcomeStatus;
use App\Enums\WithdrawalPolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TournamentParticipant extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'tournament_id',
        'participant_type',
        'player_id',
        'team_id',
        'tournament_group_id',
        'group_position',
        'status',
        'qualification_override_status',
        'withdrawn_at',
        'withdrawn_stage',
        'withdrawn_reason',
        'withdrawal_policy',
        'repechage_outcome_status',
    ];

    protected $casts = [
        'participant_type' => ParticipantType::class,
        'status' => ParticipantStatus::class,
        'qualification_override_status' => QualificationStatus::class,
        'repechage_outcome_status' => RepechageOutcomeStatus::class,
        'withdrawn_at' => 'datetime',
        'withdrawal_policy' => WithdrawalPolicy::class,
    ];

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(TournamentGroup::class, 'tournament_group_id');
    }

    public function matchesAsParticipantA(): HasMany
    {
        return $this->hasMany(TournamentMatch::class, 'participant_a_id');
    }

    public function matchesAsParticipantB(): HasMany
    {
        return $this->hasMany(TournamentMatch::class, 'participant_b_id');
    }

    public function wonMatches(): HasMany
    {
        return $this->hasMany(TournamentMatch::class, 'winner_participant_id');
    }
}
