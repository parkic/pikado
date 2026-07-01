<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournament_participants', function (Blueprint $table) {
            $table->string('repechage_outcome_status')
                ->nullable()
                ->after('qualification_override_status');

            $table->index('repechage_outcome_status');
        });
    }

    public function down(): void
    {
        Schema::table('tournament_participants', function (Blueprint $table) {
            $table->dropIndex(['repechage_outcome_status']);
            $table->dropColumn('repechage_outcome_status');
        });
    }
};
