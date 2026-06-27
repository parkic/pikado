<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tournament_groups', function (Blueprint $table) {
            $table->id();

            $table->foreignId('tournament_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('name');
            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tournament_id', 'name']);

            $table->index('tournament_id');
            $table->index('sort_order');
            $table->index('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tournament_groups');
    }
};
