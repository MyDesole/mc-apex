<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('achievements', function (Blueprint $table) {
            // Награда в ApexCoin за ачивку. Если null — считается из points
            // по формуле из config('apex.coins.achievement').
            $table->unsignedInteger('coin_reward')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('achievements', function (Blueprint $table) {
            $table->dropColumn('coin_reward');
        });
    }
};
