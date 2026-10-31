<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Купленные походы в подземелья: сколько докуплено на день.
 *
 * Как и с боями на арене: бесплатных три, дальше цена растёт с каждой
 * покупкой. Считаем по (игрок, день), поэтому лимит обнуляется сам.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dungeon_extra_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('day');
            $table->unsignedInteger('bought')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'day']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dungeon_extra_runs');
    }
};
