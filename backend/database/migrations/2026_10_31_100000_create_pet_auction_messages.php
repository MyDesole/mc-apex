<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Чат лота на аукционе: у каждого лота своя переписка.
 * Писать может любой вошедший игрок, читать — все.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pet_auction_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('auction_id')->constrained('pet_auctions')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('body', 300);
            $table->timestamp('created_at')->nullable();

            $table->index(['auction_id', 'id']);
        });

        Schema::table('pet_auctions', function (Blueprint $table) {
            // Неустойка продавца, если он снял лот со ставками
            $table->unsignedInteger('penalty')->nullable()->after('bids_count');
        });
    }

    public function down(): void
    {
        Schema::table('pet_auctions', fn (Blueprint $table) => $table->dropColumn('penalty'));
        Schema::dropIfExists('pet_auction_messages');
    }
};
