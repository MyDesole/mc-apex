<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * «Рубли» — вторая валюта, привязана к аккаунту и не передаётся.
 * Хранится в users.rubles; каждое движение — строка журнала.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedInteger('rubles')->default(0);
        });

        Schema::create('ruble_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->integer('amount');
            $table->unsignedInteger('balance_after');
            $table->string('source', 32);
            $table->string('description')->nullable();
            $table->string('idempotency_key', 120)->nullable()->unique();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'source', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ruble_transactions');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('rubles');
        });
    }
};
