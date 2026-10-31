<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Кабинет тестера: свободные слоты, начисления за тесты, запросы выплат
 * и профиль тестера (сейчас там только блокировка календаря).
 */
return new class extends Migration
{
    public function up(): void
    {
        // Слоты: «сегодня с 18:00 до 20:00 я готов тестировать»
        Schema::create('tester_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            // open — свободен, booked — занят заявкой, closed — снят тестером
            $table->string('status', 16)->default('open');
            $table->string('note', 120)->nullable();
            $table->unsignedBigInteger('tier_test_id')->nullable()->index();
            $table->timestamps();

            $table->index(['user_id', 'starts_at']);
            $table->index(['status', 'starts_at']);
        });

        // Начисления за проведённые тесты (рубли + ApexCoin)
        Schema::create('tester_earnings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Один тест — одно начисление, повторно не начислим
            $table->unsignedBigInteger('tier_test_id')->unique();
            $table->string('tier', 4)->nullable();
            $table->unsignedInteger('rub')->default(0);
            $table->unsignedInteger('coins')->default(0);
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });

        // Запросы на выплату: тестер просит, админ отмечает «выплачено»
        Schema::create('tester_payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('rub')->default(0);
            $table->unsignedInteger('coins')->default(0);
            // pending — ждёт решения, paid — выплачено, rejected — отказано
            $table->string('status', 16)->default('pending');
            $table->unsignedBigInteger('decided_by')->nullable();
            $table->dateTime('decided_at')->nullable();
            $table->string('comment', 200)->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });

        // Профиль тестера: блокировка календаря (ушёл в афк — доступ снят)
        Schema::create('tester_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->dateTime('blocked_until')->nullable();
            $table->string('blocked_reason', 160)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tester_profiles');
        Schema::dropIfExists('tester_payouts');
        Schema::dropIfExists('tester_earnings');
        Schema::dropIfExists('tester_slots');
    }
};
