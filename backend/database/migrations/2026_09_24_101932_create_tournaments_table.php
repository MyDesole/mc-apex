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
        Schema::create('tournaments', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('banner')->nullable();

            $table->enum('type', ['solo', 'clan']);            // люди / кланы
            $table->enum('format', ['single_elim', 'double_elim', 'round_robin'])->default('single_elim');

            $table->enum('status', ['draft', 'registration', 'ongoing', 'completed', 'cancelled'])
                ->default('draft');

            // Призовые
            $table->decimal('prize_pool', 12, 2)->default(0);
            $table->string('prize_currency', 8)->default('RUB');
            $table->string('prize_description')->nullable();

            // Ограничения
            $table->string('min_tier', 2)->nullable();         // S/A/B/C/D/E
            $table->string('max_tier', 2)->nullable();
            $table->unsignedInteger('max_participants')->default(16);

            // Даты
            $table->timestamp('registration_starts_at')->nullable();
            $table->timestamp('registration_ends_at')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();

            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index('status');
            $table->index('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tournaments');
    }
};
