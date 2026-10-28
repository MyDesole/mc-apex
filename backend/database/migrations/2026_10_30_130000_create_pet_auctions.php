<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Аукцион питомцев: лот — конкретный экземпляр из инвентаря продавца,
 * ставки с таймером. Монеты лидера заморожены (списаны) до конца торгов.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pet_auctions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('inventory_id')->constrained('user_inventory')->cascadeOnDelete();
            $table->unsignedInteger('start_price');
            $table->unsignedInteger('current_price')->nullable();
            $table->foreignId('top_bidder_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('bids_count')->default(0);
            $table->timestamp('ends_at');
            // active | sold | expired | cancelled
            $table->string('status', 16)->default('active');
            $table->timestamps();

            $table->index(['status', 'ends_at']);
        });

        Schema::create('pet_auction_bids', function (Blueprint $table) {
            $table->id();
            $table->foreignId('auction_id')->constrained('pet_auctions')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('amount');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pet_auction_bids');
        Schema::dropIfExists('pet_auctions');
    }
};
