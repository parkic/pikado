<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->date('tournament_date')->nullable()->after('name');
            $table->index('tournament_date');
        });

        DB::table('tournaments')
            ->whereNull('tournament_date')
            ->update(['tournament_date' => DB::raw('DATE(created_at)')]);
    }

    public function down(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->dropIndex(['tournament_date']);
            $table->dropColumn('tournament_date');
        });
    }
};
