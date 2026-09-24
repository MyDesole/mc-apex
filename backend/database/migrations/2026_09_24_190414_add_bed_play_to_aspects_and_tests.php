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
        Schema::table('player_aspects', function (Blueprint $table) {
            $table->unsignedTinyInteger('bed_play')->default(0)->after('ppl');
        });

//        Schema::table('tier_tests', function (Blueprint $table) {
//            $table->unsignedTinyInteger('bed_play')->default(0);
//        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('player_aspects', function (Blueprint $table) {
            $table->dropColumn('bed_play');
        });

        Schema::table('tier_tests', function (Blueprint $table) {
            $table->dropColumn('bed_play');
        });
    }
};
