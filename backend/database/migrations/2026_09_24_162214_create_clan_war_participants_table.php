<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clan_war_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clan_war_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('clan_id')->constrained()->cascadeOnDelete();
            $table->timestamp('joined_at')->useCurrent();
            $table->timestamps();

            $table->unique(['clan_war_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clan_war_participants');
    }
};
