<?php

namespace App\Models;

use App\Enums\MatchStage;
use App\Enums\MatchStatus;
use App\Enums\WinReason;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

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

            /*
             * Grupni "ghost" meč nije javni meč čak ni ako je kod starog
             * podatka is_hidden ostao pogrešno postavljen na false.
             * Nokaut mečevi bez učesnika ostaju vidljivi kao kostur žreba.
             */
            $builder->where(function (Builder $query) use ($builder): void {
                $stageColumn = $builder->getModel()->qualifyColumn('stage');
                $participantAColumn = $builder->getModel()->qualifyColumn('participant_a_id');
                $participantBColumn = $builder->getModel()->qualifyColumn('participant_b_id');

                $query
                    ->where($stageColumn, '!=', MatchStage::GROUP->value)
                    ->orWhere(function (Builder $groupMatchQuery) use (
                        $participantAColumn,
                        $participantBColumn,
                    ): void {
                        $groupMatchQuery
                            ->whereNotNull($participantAColumn)
                            ->whereNotNull($participantBColumn);
                    });
            });

            /*
             * Pre uvođenja is_hidden zastavice, neiskorišćena partija već
             * odlučene nokaut serije ostajala je kao vidljivo "Anulirano".
             * Takvi stari redovi su i dalje interni rezervni legovi.
             */
            $builder->where(function (Builder $query) use ($builder): void {
                $stageColumn = $builder->getModel()->qualifyColumn('stage');
                $statusColumn = $builder->getModel()->qualifyColumn('status');

                $query
                    ->whereNotIn($stageColumn, [
                        MatchStage::KNOCKOUT->value,
                        MatchStage::THIRD_PLACE->value,
                        MatchStage::FINAL->value,
                    ])
                    ->orWhere($statusColumn, '!=', MatchStatus::VOIDED->value);
            });
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
