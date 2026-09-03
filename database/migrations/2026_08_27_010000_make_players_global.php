<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('players', function (Blueprint $table) {
            $table->dropForeign(['venue_id']);
            $table->dropIndex(['venue_id', 'first_name', 'last_name']);
            $table->dropIndex(['venue_id', 'is_active']);
            $table->dropIndex(['venue_id']);
            $table->dropColumn('venue_id');

            $table->index(['first_name', 'last_name']);
        });
    }

    public function down(): void
    {
        Schema::table('players', function (Blueprint $table) {
            $table->foreignId('venue_id')
                ->nullable()
                ->after('id')
                ->constrained()
                ->nullOnDelete();
            $table->index(['venue_id', 'first_name', 'last_name']);
            $table->index(['venue_id', 'is_active']);
        });
    }
};
