<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Исход войны кланов.
 *
 * Раньше ничья нигде не отмечалась: winner_clan_id оставался заполненным
 * оппонентом (сравнение было строго «>»), и ничья приносила победу.
 * Отдельная колонка outcome делает исход явным: win | draw.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clan_wars', function (Blueprint $table) {
            $table->string('outcome', 16)->nullable()->after('winner_clan_id');
        });
    }

    public function down(): void
    {
        Schema::table('clan_wars', function (Blueprint $table) {
            $table->dropColumn('outcome');
        });
    }
};
