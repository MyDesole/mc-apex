<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Колонка users.role когда-то была ENUM из четырёх значений. С тех пор
 * появились медийка, бридж-роли и администратор сервера — на MySQL со
 * старым ENUM сохранение такой роли падало.
 *
 * На проде колонка уже varchar, поэтому там миграция ничего не делает;
 * на чистой установке расширяет enum. SQLite не трогаем — там enum
 * хранится как строка.
 */
return new class extends Migration
{
    private const ROLES = ['user', 'media', 'tester', 'bridge_tester', 'bridge_curator', 'moderator', 'server_admin', 'admin'];

    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $column = DB::selectOne("SHOW COLUMNS FROM users LIKE 'role'");
        $type = (string) ($column->Type ?? '');

        if (! str_starts_with($type, 'enum(') || str_contains($type, 'server_admin')) {
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
