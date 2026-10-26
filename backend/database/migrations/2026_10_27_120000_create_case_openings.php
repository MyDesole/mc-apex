<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Открытия кейсов с косметикой: история, раз в день бесплатный кейс
 * и гарантия эпика в платных.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('case_openings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('case_slug', 40);
            $table->foreignId('shop_item_id')->nullable()->constrained('shop_items')->nullOnDelete();
            // Исход: nothing, coins или редкость выпавшего предмета
            $table->string('outcome', 20);
            $table->unsignedInteger('price')->default(0);
            // Выигранные монеты: исход «монеты» или повтор предмета
            $table->unsignedInteger('coins')->default(0);
            $table->boolean('duplicate')->default(false);
            $table->timestamps();

            $table->index(['user_id', 'case_slug', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('case_openings');
    }
};
