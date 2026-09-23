<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('clan_wars', function (Blueprint $table) {
            $table->id();
            $table->foreignId('challenger_clan_id')->constrained('clans')->cascadeOnDelete();
            $table->foreignId('opponent_clan_id')->constrained('clans')->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->enum('status', ['pending', 'accepted', 'declined', 'completed', 'cancelled'])->default('pending');
            $table->dateTime('scheduled_at')->nullable();
            $table->unsignedTinyInteger('challenger_score')->nullable();
            $table->unsignedTinyInteger('opponent_score')->nullable();
            $table->foreignId('winner_clan_id')->nullable()->constrained('clans')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clan_wars');
    }
};
