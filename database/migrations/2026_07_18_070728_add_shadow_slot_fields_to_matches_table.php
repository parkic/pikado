<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->string('participant_a_position', 16)->nullable()->after('participant_a_id');
            $table->string('participant_b_position', 16)->nullable()->after('participant_b_id');
            $table->boolean('is_hidden')->default(false)->after('participant_b_position');

            $table->index('is_hidden');
            $table->index(
                ['tournament_id', 'stage', 'tournament_group_id', 'participant_a_position'],
                'matches_shadow_a_lookup'
            );
            $table->index(
                ['tournament_id', 'stage', 'tournament_group_id', 'participant_b_position'],
                'matches_shadow_b_lookup'
            );
        });
    }

    public function down(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->dropIndex('matches_shadow_a_lookup');
            $table->dropIndex('matches_shadow_b_lookup');
            $table->dropIndex('matches_is_hidden_index');

            $table->dropColumn([
                'participant_a_position',
                'participant_b_position',
                'is_hidden',
            ]);
        });
    }
};
