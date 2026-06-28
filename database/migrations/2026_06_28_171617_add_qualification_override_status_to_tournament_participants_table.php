<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournament_participants', function (Blueprint $table) {
            $table->string('qualification_override_status')
                ->nullable()
                ->after('status');

            $table->index('qualification_override_status');
        });
    }

    public function down(): void
    {
        Schema::table('tournament_participants', function (Blueprint $table) {
            $table->dropIndex(['qualification_override_status']);
            $table->dropColumn('qualification_override_status');
        });
    }
};
