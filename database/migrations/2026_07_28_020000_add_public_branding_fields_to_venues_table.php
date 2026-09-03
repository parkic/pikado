<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('venues', function (Blueprint $table) {
            $table->string('address')->nullable()->after('description');
            $table->string('phone', 50)->nullable()->after('address');
            $table->string('website_url', 2048)->nullable()->after('phone');
            $table->string('public_theme', 20)->default('dark')->after('instagram_url');
        });
    }

    public function down(): void
    {
        Schema::table('venues', function (Blueprint $table) {
            $table->dropColumn([
                'address',
                'phone',
                'website_url',
                'public_theme',
            ]);
        });
    }
};
