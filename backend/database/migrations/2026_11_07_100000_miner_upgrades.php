<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * «Шахтёр» 2.0: состояние забега сверх сетки (ярус, комбо, динамит,
 * сундуки, жители) и профиль шахтёра — улучшения, коллекция
 * артефактов и ежедневные задания.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('miner_runs', function (Blueprint $table) {
            $table->json('meta')->nullable()->after('opened');
            $table->index(['status', 'finished_at']);
        });

        Schema::create('miner_profiles', function (Blueprint $table) {
            $table->foreignId('user_id')->primary()->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('pickaxe')->default(0);
            $table->unsignedTinyInteger('fortune')->default(0);
            $table->unsignedTinyInteger('lantern')->default(0);
            $table->unsignedTinyInteger('cart')->default(0);
            $table->unsignedTinyInteger('dynamite')->default(0);
            // id артефакта => сколько найдено
            $table->json('artifacts')->nullable();
            // {date, items: [{id, progress, claimed}]}
            $table->json('quests')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('miner_profiles');

        Schema::table('miner_runs', function (Blueprint $table) {
            $table->dropIndex(['status', 'finished_at']);
            $table->dropColumn('meta');
        });
    }
};
