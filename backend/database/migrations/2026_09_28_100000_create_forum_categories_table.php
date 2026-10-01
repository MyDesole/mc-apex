<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('forum_categories', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 64)->unique();
            $table->string('name', 96);
            $table->string('description', 255)->nullable();
            $table->string('icon', 32)->nullable();
            $table->string('color', 16)->nullable();
            $table->unsignedInteger('sort_order')->default(0);

            // Кто может создавать темы: all | verified | staff
            $table->string('post_policy', 16)->default('all');

            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('forum_categories');
    }
};
