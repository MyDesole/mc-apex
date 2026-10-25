<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tier_test_appeals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tier_test_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('reason');
            $table->string('status', 16)->default('pending')->index();
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('resolution')->nullable();
            // Тест, созданный при пересмотре (если апелляцию приняли)
            $table->foreignId('revised_test_id')->nullable()->constrained('tier_tests')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            // Одна апелляция на тест
            $table->unique('tier_test_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tier_test_appeals');
    }
};
