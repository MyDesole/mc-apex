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
        Schema::create('tournament_matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->constrained()->cascadeOnDelete();

            $table->unsignedInteger('round');                  // 1, 2, 3... (финал = максимальный)
            $table->unsignedInteger('position');               // позиция в раунде (0, 1, 2...)
            $table->string('bracket', 8)->default('main');     // main / upper / lower / final

            // Участники
            $table->foreignId('participant1_id')->nullable()->constrained('tournament_participants')->nullOnDelete();
            $table->foreignId('participant2_id')->nullable()->constrained('tournament_participants')->nullOnDelete();
            $table->foreignId('winner_id')->nullable()->constrained('tournament_participants')->nullOnDelete();

            $table->unsignedTinyInteger('score1')->nullable();
            $table->unsignedTinyInteger('score2')->nullable();

            $table->enum('status', ['pending', 'ready', 'live', 'completed', 'cancelled'])
                ->default('pending');

            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            // Ссылка на следующий матч
            $table->foreignId('next_match_id')->nullable()->constrained('tournament_matches')->nullOnDelete();
            $table->string('next_slot', 8)->nullable();        // 'p1' / 'p2'

            $table->timestamps();

            $table->index(['tournament_id', 'round']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tournament_matches');
    }
};
