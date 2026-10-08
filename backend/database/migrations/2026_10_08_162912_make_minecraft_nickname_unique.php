<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ник становится ключом привязки, вместо кода из игры.
 *
 * Почему так лучше. Код в чате ничего не доказывал: его вводит кто угодно,
 * и ник занимал тот, кто успел первым. Теперь игрок заявляет свой ник в
 * профиле под своей сессией, ник уникален на сайте, и плагин не пускает
 * того, чей ник не совпадает с заявленным.
 *
 * Коды привязки больше не нужны — таблицу убираем.
 */
return new class extends Migration
{
    public function up(): void
    {
        // На случай повторных привязок: оставляем по одному нику на игрока
        $duplicates = \Illuminate\Support\Facades\DB::table('users')
            ->select('minecraft_username')
            ->whereNotNull('minecraft_username')
            ->groupBy('minecraft_username')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('minecraft_username');

        foreach ($duplicates as $nickname) {
            $ids = \Illuminate\Support\Facades\DB::table('users')
                ->where('minecraft_username', $nickname)
                ->orderBy('id')
                ->pluck('id')
                ->all();

            // Оставляем самую раннюю привязку, остальным ник снимаем
            array_shift($ids);

            \Illuminate\Support\Facades\DB::table('users')
                ->whereIn('id', $ids)
                ->update(['minecraft_username' => null, 'minecraft_uuid' => null, 'minecraft_linked_at' => null]);
        }

        Schema::table('users', function (Blueprint $table) {
            // Ник уникален: два аккаунта не могут заявить один и тот же
            $table->unique('minecraft_username');
        });

        Schema::dropIfExists('minecraft_link_codes');
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['minecraft_username']);
        });

        Schema::create('minecraft_link_codes', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->string('username', 32);
            $table->string('code', 16)->index();
            $table->timestamp('expires_at');
            $table->timestamps();
        });
    }
};
