<?php

namespace App\Models;

use App\Enums\MatchStage;
use App\Enums\MatchStatus;
use App\Enums\WinReason;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;

class TournamentMatch extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'matches';

    protected $fillable = [
        'tournament_id',
        'stage',
        'tournament_group_id',
        'bracket_round',
        'bracket_position',
        'participant_a_id',
        'participant_a_position',
        'participant_b_id',
        'participant_b_position',
        'is_hidden',
        'score_a',
        'score_b',
        'winner_participant_id',
        'loser_participant_id',
        'status',
        'tournament_resource_id',
        'scheduled_order',
        'round_robin_leg',
        'wins_required',
        'win_reason',
        'meta',
        'started_at',
        'finished_at',

    ];

    protected $casts = [
        'stage' => MatchStage::class,
        'status' => MatchStatus::class,
        'win_reason' => WinReason::class,
        'score_a' => 'integer',
        'score_b' => 'integer',
        'bracket_position' => 'integer',
        'scheduled_order' => 'integer',
        'round_robin_leg' => 'integer',
        'wins_required' => 'integer',
        'meta' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'is_hidden' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope('visible_matches', function (Builder $builder): void {
            $builder->where(
                $builder->getModel()->qualifyColumn('is_hidden'),
                false
            );
        });
    }

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(TournamentGroup::class, 'tournament_group_id');
    }

    public function participantA(): BelongsTo
    {
        return $this->belongsTo(TournamentParticipant::class, 'participant_a_id');
    }

    public function participantB(): BelongsTo
    {
        return $this->belongsTo(TournamentParticipant::class, 'participant_b_id');
    }

    public function winner(): BelongsTo
    {
        return $this->belongsTo(TournamentParticipant::class, 'winner_participant_id');
    }

    public function loser(): BelongsTo
    {
        return $this->belongsTo(TournamentParticipant::class, 'loser_participant_id');
    }

    public function resource(): BelongsTo
    {
        return $this->belongsTo(TournamentResource::class, 'tournament_resource_id');
    }
}
