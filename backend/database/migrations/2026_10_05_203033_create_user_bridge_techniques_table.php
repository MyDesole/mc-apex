<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Бридж-заявки игрока по видам бриджа.
 *
 * Игрок сам отмечает, что умеет, и прикладывает видео. Пока тестер не
 * подтвердил, вид в профиле показан серым. Тестер ставит оценки аспектов
 * (стабильность, скорость, сложность — по 100) и владение видом (0–10).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_bridge_techniques', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('technique_id')->constrained('bridge_techniques')->cascadeOnDelete();

            // declared — игрок заявил, confirmed — тестер подтвердил,
            // rejected — тестер отклонил
            $table->enum('status', ['declared', 'confirmed', 'rejected'])->default('declared');

            $table->string('video_url', 512)->nullable();

            // Оценки тестера
            $table->unsignedTinyInteger('stability')->nullable();   // стабильность, 0–100
            $table->unsignedTinyInteger('speed')->nullable();       // скорость, 0–100
            $table->unsignedTinyInteger('difficulty')->nullable();  // сложность, 0–100
            $table->unsignedTinyInteger('score')->nullable();       // владение видом, 0–10

            $table->text('review_notes')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();

            $table->timestamps();

            // Один вид — одна запись на игрока: повторная подача обновляет её
            $table->unique(['user_id', 'technique_id']);

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_bridge_techniques');
    }
};
