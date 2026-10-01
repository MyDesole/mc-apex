<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_inventory', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shop_item_id')->constrained('shop_items')->cascadeOnDelete();

            // Для расходников: сколько осталось применений
            $table->unsignedInteger('quantity')->default(1);

            $table->timestamp('equipped_at')->nullable();
            $table->timestamp('acquired_at')->nullable();

            $table->foreignId('gifted_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->unique(['user_id', 'shop_item_id']);
            $table->index(['user_id', 'equipped_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_inventory');
    }
};
