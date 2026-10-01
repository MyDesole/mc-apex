<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Роль «Медийка» — для ютуберов, стримеров и других медиа-лиц проекта.
 *
 * Колонка была enum('user','tester','moderator','admin'). Переводим её в строку:
 * на SQLite enum и так хранится как varchar с CHECK-ограничением, поэтому
 * расширить список значений проще через смену типа. Такая схема не требует
 * правок при добавлении новых ролей в будущем.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 32)->default('user')->change();
        });

        // На случай, если СУБД оставила CHECK-ограничение прежним
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            DB::statement('PRAGMA ignore_check_constraints = 0');
        }
    }

    public function down(): void
    {
        // Возвращаем прежние роли на 'user', чтобы enum не сломался
        DB::table('users')->where('role', 'media')->update(['role' => 'user']);

        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 32)->default('user')->change();
        });
    }
};
