<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('players')
            ->where('first_name', 'Ðorðe')
            ->update(['first_name' => 'Đorđe']);
    }

    public function down(): void
    {
        // Ispravno ime ne vraćamo u pogrešno kodiran oblik.
    }
};
