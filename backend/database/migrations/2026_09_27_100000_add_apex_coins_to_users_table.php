<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Баланс ApexCoin. Хранится денормализованно ради быстрых выборок;
            // истина — сумма ledger-а coin_transactions (см. App\Domains\Wallet\Services\CoinService).
            $table->unsignedBigInteger('apex_coins')->default(0);
            $table->unsignedBigInteger('apex_coins_spent')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['apex_coins', 'apex_coins_spent']);
        });
    }
};
