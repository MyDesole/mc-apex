<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Мини-игра «Шахтёр»: забег — сетка блоков с рудами, которую знает
 * только сервер, и открытые игроком клетки.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('miner_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->json('grid');
            $table->json('opened');
            $table->unsignedSmallInteger('energy');
            $table->unsignedInteger('coins')->default(0);
            // active | finished
            $table->string('status', 12)->default('active');
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('miner_runs');
    }
};
