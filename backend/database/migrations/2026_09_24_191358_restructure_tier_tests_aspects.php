<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tier_tests', function (Blueprint $table) {
            // PvP-специфичные
            $table->unsignedTinyInteger('aim')->default(0)->after('building');
            $table->unsignedTinyInteger('game_sense')->default(0)->after('aim');

            // BW-специфичные
            $table->unsignedTinyInteger('pvp')->default(0)->after('game_sense');
            $table->unsignedTinyInteger('teamplay')->default(0)->after('bed_play');
        });    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
