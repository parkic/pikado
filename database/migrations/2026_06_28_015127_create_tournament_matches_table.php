<?php

use App\Enums\MatchStage;
use App\Enums\MatchStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('matches', function (Blueprint $table) {
            $table->id();

            $table->foreignId('tournament_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('stage')->default(MatchStage::GROUP->value);

            $table->foreignId('tournament_group_id')
                ->nullable()
                ->constrained('tournament_groups')
                ->nullOnDelete();

            $table->string('bracket_round')->nullable();
            $table->unsignedInteger('bracket_position')->nullable();

            $table->foreignId('participant_a_id')
                ->nullable()
                ->constrained('tournament_participants')
                ->nullOnDelete();

            $table->foreignId('participant_b_id')
                ->nullable()
                ->constrained('tournament_participants')
                ->nullOnDelete();

            $table->integer('score_a')->nullable();
            $table->integer('score_b')->nullable();

            $table->foreignId('winner_participant_id')
                ->nullable()
                ->constrained('tournament_participants')
                ->nullOnDelete();

            $table->foreignId('loser_participant_id')
                ->nullable()
                ->constrained('tournament_participants')
                ->nullOnDelete();

            $table->string('status')->default(MatchStatus::SCHEDULED->value);

            $table->foreignId('tournament_resource_id')
                ->nullable()
                ->constrained('tournament_resources')
                ->nullOnDelete();

            $table->unsignedInteger('scheduled_order')->nullable();
            $table->unsignedSmallInteger('round_robin_leg')->nullable();

            $table->unsignedSmallInteger('wins_required')->nullable();
            $table->string('win_reason')->nullable();

            $table->json('meta')->nullable();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('tournament_id');
            $table->index('stage');
            $table->index('tournament_group_id');
            $table->index('status');
            $table->index('tournament_resource_id');
            $table->index('scheduled_order');
            $table->index('winner_participant_id');
            $table->index('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('matches');
    }
};
