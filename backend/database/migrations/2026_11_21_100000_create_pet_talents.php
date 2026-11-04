<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Таланты питомцев: ранги выученных талантов по строке инвентаря. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pet_talents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_id')->constrained('user_inventory')->cascadeOnDelete();
            $table->string('talent', 24);
            $table->unsignedTinyInteger('rank')->default(1);
            $table->timestamps();

            $table->unique(['inventory_id', 'talent']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pet_talents');
    }
};
