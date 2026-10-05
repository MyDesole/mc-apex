<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Игрок может состоять только в одном клане.
 *
 * До этого уникальность была на пару (clan_id, user_id), что запрещало
 * только дубль в одном клане. Один и тот же игрок мог оказаться в двух
 * разных кланах, если оба приняли его заявки.
 */
return new class extends Migration
{
    public function up(): void
    {
        /*
         * Чистим уже существующие дубли: оставляем самое раннее членство,
         * остальные удаляем. Без этого уникальный индекс по user_id не
         * создастся на данных, где игрок уже в нескольких кланах.
         */
        $dupes = DB::table('clan_members')
            ->select('user_id')
            ->groupBy('user_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('user_id');

        foreach ($dupes as $userId) {
            $keep = DB::table('clan_members')
                ->where('user_id', $userId)
                ->orderBy('joined_at')
                ->orderBy('id')
                ->value('id');

            DB::table('clan_members')
                ->where('user_id', $userId)
                ->where('id', '!=', $keep)
                ->delete();
        }

        Schema::table('clan_members', function (Blueprint $table) {
            $table->unique('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('clan_members', function (Blueprint $table) {
            $table->dropUnique(['user_id']);
        });
    }
};
