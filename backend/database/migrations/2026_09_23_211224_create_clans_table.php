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
        Schema::create('clans', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('tag', 8)->unique();        // [APEX]
            $table->text('description')->nullable();
            $table->string('avatar')->nullable();
            $table->string('banner_color', 16)->default('#7c3aed');
            $table->foreignId('leader_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedInteger('power')->default(0);       // очки силы
            $table->unsignedInteger('wins')->default(0);
            $table->unsignedInteger('losses')->default(0);
            $table->boolean('is_open')->default(true);          // открыт для вступления
            $table->unsignedInteger('max_members')->default(30);
            $table->timestamps();

            $table->index('power');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clans');
    }
};
