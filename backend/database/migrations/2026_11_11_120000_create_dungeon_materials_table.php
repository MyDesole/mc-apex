<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Материалы подземелий: что игрок накопал для крафта экипировки.
 * Один строка на пару (пользователь, материал), количество — счётчиком.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dungeon_materials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('material', 24);
            $table->unsignedInteger('quantity')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'material']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dungeon_materials');
    }
};
