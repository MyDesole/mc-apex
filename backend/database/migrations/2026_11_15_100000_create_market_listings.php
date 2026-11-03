<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Рынок игроков: материалы и снаряжение подземелий за ApexCoin.
 * Лот держит товар в эскроу: материалы списаны, предмет убран из
 * инвентаря, пока лот не продан, снят или не истёк.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('market_listings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_id')->constrained('users')->cascadeOnDelete();
            $table->string('kind', 12);
            // Ключ материала или slug снаряжения
            $table->string('ref', 40);
            $table->unsignedSmallInteger('qty')->default(1);
            // Для снаряжения: shop_item_id, редкость и характеристики экземпляра
            $table->json('payload')->nullable();
            $table->unsignedInteger('price');
            $table->unsignedInteger('fee')->default(0);
            $table->string('status', 12)->default('active');
            $table->unsignedBigInteger('buyer_id')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('sold_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'kind', 'created_at']);
            $table->index(['seller_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('market_listings');
    }
};
