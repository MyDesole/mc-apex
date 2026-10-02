<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Плата за вступление в клан.
 *
 * Лидер может назначить цену за приём. Деньги списываются у заявителя
 * в момент принятия и уходят лидеру: если брать плату при подаче,
 * заявитель мог бы заморозить монеты, подав заявки во все кланы.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clans', function (Blueprint $table) {
            $table->unsignedInteger('entry_fee')->default(0)->after('is_open');
        });
    }

    public function down(): void
    {
        Schema::table('clans', function (Blueprint $table) {
            $table->dropColumn('entry_fee');
        });
    }
};
