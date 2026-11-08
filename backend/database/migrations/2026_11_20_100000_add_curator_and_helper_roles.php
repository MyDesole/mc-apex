<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Новые роли сайта: куратор (выше всех) и хелпер (помощник персонала).
 *
 * На проде колонка users.role уже varchar, там миграция ничего не делает;
 * на чистой установке MySQL с ENUM расширяет список значений. SQLite
 * не трогаем: там enum хранится как строка.
 */
return new class extends Migration
{
    private const ROLES = [
        'user', 'media', 'tester', 'helper', 'bridge_tester', 'bridge_curator',
        'moderator', 'server_admin', 'admin', 'curator',
    ];

    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $column = DB::selectOne("SHOW COLUMNS FROM users LIKE 'role'");
        $type = (string) ($column->Type ?? '');

        if (! str_starts_with($type, 'enum(') || str_contains($type, "'curator'")) {
            return;
        }

        $values = implode(', ', array_map(fn (string $role) => "'" . $role . "'", self::ROLES));

        DB::statement('ALTER TABLE users MODIFY role ENUM(' . $values . ") NOT NULL DEFAULT 'user'");
    }

    public function down(): void
    {
        // Расширение ролей не откатываем: в базе уже могут быть такие значения
    }
};
