<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('matches')
            ->select('tournament_id')
            ->distinct()
            ->orderBy('tournament_id')
            ->pluck('tournament_id')
            ->each(function (int $tournamentId): void {
                DB::table('matches')
                    ->where('tournament_id', $tournamentId)
                    ->orderBy('scheduled_order')
                    ->orderBy('id')
                    ->pluck('id')
                    ->each(function (int $matchId, int $index): void {
                        DB::table('matches')
                            ->where('id', $matchId)
                            ->update(['scheduled_order' => $index + 1]);
                    });
            });
    }

    public function down(): void
    {
        // Normalizacija samo uklanja duplikate; prethodni redosled nije bezbedno vratiti.
    }
};
