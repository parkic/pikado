<?php

use App\Enums\GameType;
use App\Enums\GroupRounds;
use App\Enums\MatchMode;
use App\Enums\ScoringMode;
use App\Enums\TournamentStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tournaments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('venue_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('name');
            $table->string('slug');
            $table->string('public_code')->unique();

            $table->string('game_type')->default(GameType::DART_301->value);
            $table->string('match_mode')->default(MatchMode::SINGLES->value);
            $table->string('status')->default(TournamentStatus::DRAFT->value);
            $table->string('group_rounds')->default(GroupRounds::SINGLE->value);
            $table->string('scoring_mode')->default(ScoringMode::POINTS_DIFFERENCE->value);

            $table->unsignedSmallInteger('knockout_size')->nullable();

            $table->boolean('public_enabled')->default(true);
            $table->json('settings')->nullable();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();

            $table->foreignId('created_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['venue_id', 'slug']);

            $table->index('venue_id');
            $table->index('game_type');
            $table->index('status');
            $table->index(['venue_id', 'status']);
            $table->index('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tournaments');
    }
};
