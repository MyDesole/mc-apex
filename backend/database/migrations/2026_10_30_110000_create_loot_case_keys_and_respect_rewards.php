<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ключи сундуков — бесплатное открытие платного кейса (награды и подарки).
 * Награды недели по респектам: одна запись на место, чтобы не выдать дважды.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loot_case_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('case_slug', 32);
            $table->unsignedInteger('count')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'case_slug']);
        });

        Schema::create('respect_week_rewards', function (Blueprint $table) {
            $table->id();
            $table->date('week_start');
            $table->unsignedTinyInteger('place');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('respects');
            $table->unsignedInteger('coins');
            $table->string('case_slug', 32)->nullable();
            $table->timestamps();

            $table->unique(['week_start', 'place']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('respect_week_rewards');
        Schema::dropIfExists('loot_case_keys');
    }
};
