<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * «Пропуск» (bye): участник без соперника в первом раунде проходит дальше
 * автоматически. Такой матч не считается победой в рейтинге.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournament_matches', function (Blueprint $table) {
            $table->boolean('is_bye')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('tournament_matches', fn (Blueprint $table) => $table->dropColumn('is_bye'));
    }
};
