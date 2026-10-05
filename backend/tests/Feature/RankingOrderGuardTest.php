<?php

namespace Tests\Feature;

use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Страж: рейтинг не должен сортироваться по несуществующей колонке.
 *
 * Зачем отдельный тест. SQLite молча принимает неизвестную колонку в
 * ORDER BY (считает её строковой константой), поэтому данные приходят в
 * порядке вставки и проверки порядка проходят. MySQL в той же ситуации
 * падает с 500 — так баг с rating_sort доехал до продакшена.
 *
 * Здесь разбирается сам SQL: колонка в ORDER BY должна быть реальной
 * колонкой своей таблицы либо псевдонимом из SELECT.
 */
class RankingOrderGuardTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<int, string> */
    private function captureQueries(callable $action): array
    {
        $queries = [];

        DB::listen(function ($query) use (&$queries) {
            $queries[] = $query->sql;
        });

        $action();

        return $queries;
    }

    /**
     * Разбирает ORDER BY и проверяет каждую колонку.
     *
     * Понимает и `users.id`, и `"users"."id"`, и простой псевдоним.
     */
    private function assertOrderColumnsExist(array $queries, string $context): void
    {
        $checked = 0;

        foreach ($queries as $sql) {
            if (! preg_match('/order by (.+?)(?:\s+limit|\s+offset|$)/i', $sql, $m)) {
                continue;
            }

            foreach (explode(',', $m[1]) as $part) {
                // Отбрасываем направление сортировки
                $token = trim(explode(' ', trim($part))[0]);

                if ($token === '') {
                    continue;
                }

                $token = str_replace(['"', '`'], '', $token);

                // Разделяем таблицу и колонку
                $table = null;
                $column = $token;

                if (str_contains($token, '.')) {
                    [$table, $column] = explode('.', $token, 2);
                }

                $exists = $table
                    ? Schema::hasColumn($table, $column)
                    : (
                        Schema::hasColumn('users', $column)
                        || preg_match('/\bas\s+"?' . preg_quote($column, '/') . '"?\b/i', $sql)
                    );

                $this->assertTrue(
                    (bool) $exists,
                    "{$context}: сортировка по несуществующей колонке «{$token}». "
                    . 'На SQLite это проходит молча, на MySQL падает с 500. SQL: '
                    . mb_substr($sql, 0, 260),
                );

                $checked++;
            }
        }

        $this->assertGreaterThan(0, $checked, "{$context}: ни одного ORDER BY — страж бесполезен");
    }

    private function playerWithScores(int $i): void
    {
        $user = User::factory()->create(['username' => "guard{$i}"]);

        $user->aspectPvp()->create([
            'block_placing' => $i, 'rotka' => $i, 'movement' => $i,
            'aim' => $i, 'game_sense' => $i,
        ]);

        $user->aspectBedwars()->create([
            'pvp' => $i, 'game_sense' => $i, 'bed_play' => $i,
            'teamplay' => $i, 'building' => $i,
        ]);
    }

    public function test_general_ranking_orders_by_existing_column(): void
    {
        foreach ([1, 2, 3] as $i) {
            $this->playerWithScores($i);
        }

        Sanctum::actingAs(User::factory()->create());

        $queries = $this->captureQueries(function () {
            $this->getJson('/api/ranking')->assertOk();
        });

        $this->assertOrderColumnsExist($queries, 'общий рейтинг');
    }

    public function test_all_ranking_modes_order_by_existing_column(): void
    {
        foreach ([1, 2, 3] as $i) {
            $this->playerWithScores($i);
        }

        Sanctum::actingAs(User::factory()->create());

        foreach (['overall', 'pvp', 'bedwars', 'bridge'] as $mode) {
            $queries = $this->captureQueries(function () use ($mode) {
                $this->getJson("/api/ranking?mode={$mode}")->assertOk();
            });

            $this->assertOrderColumnsExist($queries, "режим {$mode}");
        }
    }

    public function test_cursor_pagination_also_orders_correctly(): void
    {
        foreach (range(1, 7) as $i) {
            $this->playerWithScores($i);
        }

        Sanctum::actingAs(User::factory()->create());

        $first = $this->getJson('/api/ranking?limit=3')->assertOk();
        $cursor = $first->json('next_cursor');

        $queries = $this->captureQueries(function () use ($cursor) {
            $this->getJson(
                "/api/ranking?limit=3&cursor_score={$cursor['score']}&cursor_id={$cursor['id']}&offset={$cursor['offset']}"
            )->assertOk();
        });

        $this->assertOrderColumnsExist($queries, 'вторая страница');
    }
}
