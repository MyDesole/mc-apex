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
        Schema::create('tournament_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->constrained()->cascadeOnDelete();

            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('clan_id')->nullable()->constrained()->cascadeOnDelete();

            $table->unsignedInteger('seed')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected', 'withdrawn'])
                ->default('pending');
            $table->timestamps();

            $table->unique(['tournament_id', 'user_id']);
            $table->unique(['tournament_id', 'clan_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tournament_participants');
    }
};
