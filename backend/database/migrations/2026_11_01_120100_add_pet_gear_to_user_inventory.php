<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Экипировка питомца: слот => строка инвентаря с предметом.
 *
 * Хранится на строке питомца, а не в отдельной таблице: питомец и так
 * строка инвентаря, и надеть на него предмет — это одно поле, а не
 * сущность. Один предмет нельзя надеть дважды: проверяется в
 * PetGearService по всем питомцам игрока.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_inventory', function (Blueprint $table) {
            $table->json('pet_gear')->nullable()->after('pet_name');
        });
    }

    public function down(): void
    {
        Schema::table('user_inventory', function (Blueprint $table) {
            $table->dropColumn('pet_gear');
        });
    }
};
