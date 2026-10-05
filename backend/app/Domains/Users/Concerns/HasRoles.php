<?php

namespace App\Domains\Users\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * Роли проекта и их правила.
 *
 * Ключевой смысл вынесен сюда, чтобы списки ролей не дублировались
 * по контроллерам: раньше «исключить персонал» было скопировано
 * в нескольких местах и любая новая роль ломала эти выборки.
 */
trait HasRoles
{
    /**
     * Роли, которые не участвуют в рейтингах и топах.
     *
     * Бридж-тестер здесь по той же причине, что и обычный тестер:
     * проверяющий не должен соревноваться с теми, кого проверяет.
     */
    public const STAFF_ROLES = ['admin', 'moderator', 'tester', 'bridge_tester', 'bridge_curator'];

    /** Роли, которым доступна админка. */
    public const ADMIN_PANEL_ROLES = ['moderator', 'admin'];

    /** Роли, которые могут проводить тир-тесты. */
    public const TESTER_ROLES = ['tester', 'admin'];

    /** Роли, которые проверяют бридж-заявки. */
    public const BRIDGE_TESTER_ROLES = ['bridge_tester', 'admin'];

    /** Роли, которые ведут каталог видов и подвидов бриджа. */
    public const BRIDGE_CURATOR_ROLES = ['bridge_curator', 'admin'];

    /** Человеческие названия ролей. */
    public const ROLE_LABELS = [
        'user' => 'Игрок',
        'media' => 'Медийка',
        'tester' => 'Тестер',
        'bridge_tester' => 'Бридж-тестер',
        'bridge_curator' => 'Куратор бридж-теста',
        'moderator' => 'Модератор',
        'admin' => 'Администратор',
    ];

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isModerator(): bool
    {
        return in_array($this->role, self::ADMIN_PANEL_ROLES, true);
    }

    public function isTester(): bool
    {
        return in_array($this->role, self::TESTER_ROLES, true);
    }

    /**
     * Медийка: ютубер, стример и т.д.
     * Участвует в топах наравне с игроками, но имеет уникальный вид профиля.
     */
    public function isMedia(): bool
    {
        return $this->role === 'media';
    }

    /** Бридж-тестер: проверяет заявки на виды бриджа. */
    public function isBridgeTester(): bool
    {
        return in_array($this->role, self::BRIDGE_TESTER_ROLES, true);
    }

    /** Куратор бриджа: ведёт каталог видов и подвидов. */
    public function isBridgeCurator(): bool
    {
        return in_array($this->role, self::BRIDGE_CURATOR_ROLES, true);
    }

    /**
     * Персонал: не попадает в рейтинги, топы и статистику игроков.
     * Медийка персоналом НЕ считается — она участвует в топах.
     */
    public function isStaff(): bool
    {
        return in_array($this->role, self::STAFF_ROLES, true);
    }

    public function hasRole(string ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    public function roleLabel(): string
    {
        return self::ROLE_LABELS[$this->role] ?? $this->role;
    }

    /**
     * Исключить персонал из выборки (для рейтингов и топов).
     */
    public function scopeExcludeStaff(Builder $query): Builder
    {
        return $query->whereNotIn('role', self::STAFF_ROLES);
    }

    /**
     * Отображаемое имя роли для интерфейса (null — обычный игрок).
     */
    public function getRoleBadgeAttribute(): ?string
    {
        return $this->role === 'user' ? null : $this->roleLabel();
    }
}
