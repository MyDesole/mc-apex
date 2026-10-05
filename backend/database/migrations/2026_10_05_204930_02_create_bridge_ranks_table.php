<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ранги бриджера: Bridge Master, Bridge Новичок и прочие ступени.
 *
 * Это не тир по очкам, а звание: его выдаёт бридж-тестер вручную.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bridge_ranks', function (Blueprint $table) {
            $table->id();
            $table->string('key', 64)->unique();
            $table->string('label', 96);
            $table->string('color', 16)->default('#8b5cf6');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bridge_ranks');
    }
};
