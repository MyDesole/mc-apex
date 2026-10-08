<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Привязка игрока майнкрафта к аккаунту на сайте.
 *
 * Привязка идёт через одноразовый код: игрок заходит на сервер, получает
 * код в чате и вводит его на сайте. Пароль при привязке не участвует —
 * иначе плагин видел бы пароль ещё до того, как игрок подтвердил, что
 * это его аккаунт.
 *
 * Коды живут отдельной таблицей с коротким сроком и одноразовые.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // UUID доверяем только при online-mode=true на сервере
            $table->string('minecraft_uuid', 36)->nullable()->unique();
            $table->string('minecraft_username', 32)->nullable();
            $table->timestamp('minecraft_linked_at')->nullable();
        });

        Schema::create('minecraft_link_codes', function (Blueprint $table) {
            $table->id();

            // Один активный код на игрока: новый затирает старый
            $table->string('uuid', 36)->unique();
            $table->string('username', 32);
            $table->string('code', 16)->index();
            $table->timestamp('expires_at');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('minecraft_link_codes');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['minecraft_uuid', 'minecraft_username', 'minecraft_linked_at']);
        });
    }
};
