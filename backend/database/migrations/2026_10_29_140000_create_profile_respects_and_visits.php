<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Респекты и гости профиля.
 *
 * Респект — раз в день от игрока чужому профилю; из них считается
 * общий счёт и топ недели. Гости — кто заходил в профиль (одна запись
 * на гостя в день, время последнего захода обновляется).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profile_respects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->date('day');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['owner_id', 'user_id', 'day']);
            $table->index(['created_at', 'owner_id']);
        });

        Schema::create('profile_visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('visitor_id')->constrained('users')->cascadeOnDelete();
            $table->date('day');
            $table->timestamp('last_at')->useCurrent();

            $table->unique(['owner_id', 'visitor_id', 'day']);
            $table->index(['owner_id', 'last_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profile_visits');
        Schema::dropIfExists('profile_respects');
    }
};
