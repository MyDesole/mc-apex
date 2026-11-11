<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Чат тестируемого с тестером внутри заявки на тир-тест.
 *
 * Переписка привязана к заявке и живёт, пока заявка активна; после
 * завершения или отмены история остаётся для чтения.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tier_test_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tier_test_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('body', 1000);
            // Когда сообщение прочитал собеседник (автор своё не «читает»)
            $table->timestamp('read_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['tier_test_id', 'id']);
            $table->index(['tier_test_id', 'user_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tier_test_messages');
    }
};
