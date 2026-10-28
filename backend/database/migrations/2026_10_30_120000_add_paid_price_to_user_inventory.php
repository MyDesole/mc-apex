<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Сколько игрок заплатил за предмет: купленное продаётся магазину за
 * половину цены, выпавшее из сундуков и подаренное — дешевле.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_inventory', function (Blueprint $table) {
            $table->unsignedInteger('paid_price')->nullable()->after('quantity');
        });
    }

    public function down(): void
    {
        Schema::table('user_inventory', function (Blueprint $table) {
            $table->dropColumn('paid_price');
        });
    }
};
