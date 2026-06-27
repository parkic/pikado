<?php

use App\Enums\ParticipantStatus;
use App\Enums\ParticipantType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tournament_participants', function (Blueprint $table) {
            $table->id();

            $table->foreignId('tournament_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('participant_type');

            $table->foreignId('player_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->foreignId('team_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->foreignId('tournament_group_id')
                ->nullable()
                ->constrained('tournament_groups')
                ->nullOnDelete();

            $table->string('group_position')->nullable();

            $table->string('status')->default(ParticipantStatus::ACTIVE->value);

            $table->timestamp('withdrawn_at')->nullable();
            $table->string('withdrawn_stage')->nullable();
            $table->string('withdrawn_reason')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tournament_id', 'player_id']);
            $table->unique(['tournament_id', 'team_id']);
            $table->unique(['tournament_id', 'group_position']);

            $table->index('tournament_id');
            $table->index('participant_type');
            $table->index('player_id');
            $table->index('team_id');
            $table->index('tournament_group_id');
            $table->index('status');
            $table->index('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tournament_participants');
    }
};
