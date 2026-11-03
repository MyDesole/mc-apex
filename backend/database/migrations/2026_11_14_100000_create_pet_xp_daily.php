<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Опыт питомца за сутки из экспедиций, боёв и подземелий — для суточного
 * предела (PetService::dailyXpCap): уровень 100 за месяцы, а не за день.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pet_xp_daily', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('inventory_id');
            $table->date('day');
            $table->unsignedInteger('xp')->default(0);
            $table->timestamps();

            $table->unique(['inventory_id', 'day']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pet_xp_daily');
    }
};
