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
        Schema::create('achievements', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();        // 'clan_joined', 'tier_s', ...
            $table->string('name');
            $table->text('description');
            $table->string('icon', 32);              // эмодзи или имя иконки
            $table->string('color', 16);             // #7c3aed
            $table->enum('rarity', ['common', 'rare', 'epic', 'legendary'])->default('common');
            $table->unsignedInteger('points')->default(0);   // очки за ачивку
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('achievements');
    }
};
