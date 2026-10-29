<?php

namespace Tests\Feature;

use App\Domains\Clan\Models\Clan;
use App\Domains\Clan\Models\ClanMember;
use App\Domains\Shop\Models\ShopItem;
use App\Domains\Shop\Models\UserInventory;
use App\Domains\Shop\Services\ShopService;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Бюджет SQL-запросов на горячих эндпоинтах: число запросов не должно
 * расти вместе с числом питомцев, лотов и игроков (N+1).
 */
class QueryBudgetTest extends TestCase
{
    use RefreshDatabase;

    private User $me;

    protected function setUp(): void
    {
        parent::setUp();

        $species = collect(['fox', 'cat', 'dog'])->map(fn ($s) => ShopItem::create([
            'slug' => "pet-{$s}", 'name' => "Питомец {$s}", 'type' => 'profile_pet', 'effect_value' => $s,
            'price' => 1000, 'is_active' => true, 'rarity' => 'rare',
        ]));

        $users = User::factory()->count(20)->create(['apex_coins' => 5000]);
        $this->me = $users->first();

        foreach ($users as $i => $user) {
            foreach ([0, 1] as $n) {
                $row = UserInventory::create(['user_id' => $user->id, 'shop_item_id' => $species[($i + $n) % 3]->id, 'quantity' => 1]);
                $row->forceFill(['pet_xp' => $i * 10 + $n, 'pet_wins' => $n, 'pet_rating' => 1000 + $i, 'equipped_at' => now()])->save();
                DB::table('pet_interactions')->insert([
                    'owner_id' => $user->id, 'actor_id' => $user->id, 'inventory_id' => $row->id,
                    'action' => 'feed', 'day' => today()->toDateString(), 'created_at' => now(),
                ]);

                // Второй питомец каждого игрока (кроме меня) — на аукционе
                if ($n === 1 && $i > 0 && $i <= 15) {
                    $auction = DB::table('pet_auctions')->insertGetId([
                        'seller_id' => $user->id, 'inventory_id' => $row->id, 'start_price' => 100,
                        'current_price' => 150, 'top_bidder_id' => $users[($i + 1) % 20]->id, 'bids_count' => 1,
                        'ends_at' => now()->addDay(), 'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
                    ]);
                    DB::table('pet_auction_messages')->insert([
                        'auction_id' => $auction, 'user_id' => $user->id, 'body' => 'Торгуемся', 'created_at' => now(),
                    ]);
                }
            }
            ShopService::syncPets($user);
        }

        // У меня питомцев больше — видно, растут ли запросы вместе с ними
        foreach (range(1, 10) as $n) {
            UserInventory::create(['user_id' => $this->me->id, 'shop_item_id' => $species[$n % 3]->id, 'quantity' => 1]);
        }

        foreach ($users->take(5) as $i => $leader) {
            $clan = Clan::create(['name' => "Клан {$i}", 'tag' => "CL{$i}", 'leader_id' => $leader->id]);
            ClanMember::create(['clan_id' => $clan->id, 'user_id' => $leader->id, 'role' => 'leader']);
        }
    }

    private function queries(callable $request): int
    {
        // Сброс статических кэшей между запросами — как в отдельном HTTP-запросе
        $this->app['auth']->forgetGuards();
        $queries = 0;
        DB::listen(function ($q) use (&$queries) {
            $queries++;
            if (getenv('QUERY_SQL')) {
                fwrite(STDERR, '  ' . $q->sql . "\n");
            }
        });
        $request();

        return $queries;
    }

    public function test_query_budgets(): void
    {
        $me = $this->me;
        $budgets = [
            '/api/home' => [fn () => $this->getJson('/api/home')->assertOk(), 25],
            '/api/home (кэш)' => [fn () => $this->getJson('/api/home')->assertOk(), 9],
            '/api/home/me' => [fn () => $this->actingAs($me)->getJson('/api/home/me')->assertOk(), 22],
            '/api/auctions' => [fn () => $this->getJson('/api/auctions')->assertOk()->assertJsonCount(15, 'lots'), 10],
            '/api/pets' => [fn () => $this->actingAs($me)->getJson('/api/pets')->assertOk()->assertJsonCount(12, 'pets'), 18],
            '/api/pets/top?by=level' => [fn () => $this->getJson('/api/pets/top?by=level')->assertOk()->assertJsonCount(20, 'pets'), 8],
            '/api/pets/top?by=rare' => [fn () => $this->getJson('/api/pets/top?by=rare')->assertOk()->assertJsonCount(20, 'pets'), 8],
            // Второй заход — из кэша
            '/api/pets/top?by=level (кэш)' => [fn () => $this->getJson('/api/pets/top?by=level')->assertOk(), 0],
            '/api/pets/top?by=rating' => [fn () => $this->getJson('/api/pets/top?by=rating')->assertOk(), 8],
            '/api/shop/inventory' => [fn () => $this->actingAs($me)->getJson('/api/shop/inventory')->assertOk(), 20],
            '/api/shop' => [fn () => $this->actingAs($me)->getJson('/api/shop')->assertOk(), 25],
            '/api/ranking' => [fn () => $this->getJson('/api/ranking')->assertOk(), 15],
            '/api/players/{id}' => [fn () => $this->getJson("/api/players/{$me->id}")->assertOk(), 40],
        ];

        $report = [];
        $over = [];
        foreach ($budgets as $name => [$request, $budget]) {
            if (getenv('QUERY_SQL')) {
                fwrite(STDERR, "== {$name}\n");
            }
            $n = $this->queries($request);
            $report[] = sprintf('%-26s %3d (бюджет %d)', $name, $n, $budget);
            if ($n > $budget) {
                $over[] = "{$name}: {$n} > {$budget}";
            }
        }

        if (getenv('QUERY_REPORT')) {
            fwrite(STDERR, "\n" . implode("\n", $report) . "\n");
        }

        $this->assertSame([], $over, implode("\n", $report));
    }
}
