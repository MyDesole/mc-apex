<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coin_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Со знаком: плюс — начисление, минус — списание
            $table->bigInteger('amount');
            $table->bigInteger('balance_after')->nullable();

            // tier_test | achievement | daily_bonus | gift_in | gift_out | gift_tax | purchase | admin | other
            $table->string('source', 32);
            $table->string('description', 191)->nullable();

            // Полиморфная привязка к источнику (TierTest, Achievement, ShopItem, ...)
            $table->string('reference_type', 64)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();

            // Ключ идемпотентности: одна и та же награда не начислится дважды
            $table->string('idempotency_key', 191)->nullable()->unique();

            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();

            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['source', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coin_transactions');
    }
};
