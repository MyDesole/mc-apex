<?php

namespace Tests\Feature;

use App\Models\CoinTransaction;
use App\Models\Friendship;
use App\Models\User;
use App\Services\CoinService;
use App\Services\ShopSettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Подарки ApexCoin друзьям: комиссия, лимиты, правила владельца получателя.
 */
class GiftTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: User, 1: User} [отправитель, получатель-друг]
     */
    private function friends(int $senderCoins = 5000, int $friendCoins = 0): array
    {
        $sender = User::factory()->create(['apex_coins' => $senderCoins]);
        $friend = User::factory()->create(['apex_coins' => $friendCoins]);

        Friendship::create([
            'user_id' => $sender->id,
            'friend_id' => $friend->id,
            'status' => 'accepted',
        ]);

        return [$sender, $friend];
    }

    public function test_gift_endpoints_require_authentication(): void
    {
        $target = User::factory()->create();

        $this->getJson('/api/gifts/limits')->assertUnauthorized();
        $this->postJson("/api/gifts/{$target->id}", ['amount' => 100])->assertUnauthorized();
    }

    public function test_gift_limits_endpoint_returns_balance_and_rules(): void
    {
        $user = User::factory()->create(['apex_coins' => 777]);

        $response = $this->actingAs($user)->getJson('/api/gifts/limits')->assertOk();

        $response->assertJsonStructure(['balance', 'gifted_today', 'limits' => ['enabled', 'min', 'max', 'daily_limit', 'fee_percent']]);

        $this->assertSame(777, $response->json('balance'));
        $this->assertSame(0, $response->json('gifted_today'));
        $this->assertSame(config('apex.coins.gift'), $response->json('limits'));
    }

    public function test_gift_moves_coins_with_fee_and_writes_two_ledger_rows(): void
    {
        [$sender, $friend] = $this->friends(5000, 0);

        $response = $this->actingAs($sender)
            ->postJson("/api/gifts/{$friend->id}", ['amount' => 1000])
            ->assertCreated();

        $fee = (int) floor(1000 * config('apex.coins.gift.fee_percent') / 100);

        $this->assertSame(50, $fee);
        $this->assertSame(950, $response->json('received'));
        $this->assertSame($fee, $response->json('fee'));
        $this->assertSame(4000, $response->json('balance'));
        $this->assertSame(1000, $response->json('gifted_today'));
        $this->assertSame(
            "Подарено 950 ApexCoin игроку {$friend->username} (комиссия {$fee}).",
            $response->json('message')
        );

        $this->assertSame(4000, $sender->fresh()->apex_coins);
        $this->assertSame(1000, $sender->fresh()->apex_coins_spent);
        $this->assertSame(950, $friend->fresh()->apex_coins);

        $out = CoinTransaction::where('user_id', $sender->id)->sole();
        $this->assertSame(-1000, $out->amount);
        $this->assertSame(4000, $out->balance_after);
        $this->assertSame('gift_out', $out->source);
        $this->assertSame($sender->id, $out->actor_id);
        $this->assertSame(['to_user_id' => $friend->id, 'fee' => $fee], $out->meta);

        $in = CoinTransaction::where('user_id', $friend->id)->sole();
        $this->assertSame(950, $in->amount);
        $this->assertSame(950, $in->balance_after);
        $this->assertSame('gift_in', $in->source);
        $this->assertSame($sender->id, $in->actor_id);
        $this->assertSame(['from_user_id' => $sender->id, 'fee' => $fee], $in->meta);
    }

    public function test_gift_fee_is_rounded_down(): void
    {
        // fee_percent = 5: 19 -> 0.95 -> 0, 21 -> 1.05 -> 1
        [$sender, $friend] = $this->friends(5000, 0);

        $small = $this->actingAs($sender)->postJson("/api/gifts/{$friend->id}", ['amount' => 19])->assertCreated();
        $this->assertSame(0, $small->json('fee'));
        $this->assertSame(19, $small->json('received'));
        $this->assertSame(4981, $sender->fresh()->apex_coins);
        $this->assertSame(19, $friend->fresh()->apex_coins);

        $rounded = $this->actingAs($sender)->postJson("/api/gifts/{$friend->id}", ['amount' => 21])->assertCreated();
        $this->assertSame(1, $rounded->json('fee'));
        $this->assertSame(20, $rounded->json('received'));
        $this->assertSame(39, $friend->fresh()->apex_coins);
    }

    public function test_cannot_gift_to_self(): void
    {
        $user = User::factory()->create(['apex_coins' => 5000]);

        $response = $this->actingAs($user)
            ->postJson("/api/gifts/{$user->id}", ['amount' => 100])
            ->assertStatus(422);

        $this->assertSame('Нельзя подарить монеты самому себе.', $response->json('message'));
        $this->assertSame(5000, $user->fresh()->apex_coins);
        $this->assertSame(0, CoinTransaction::count());
    }

    public function test_cannot_gift_to_a_non_friend(): void
    {
        $sender = User::factory()->create(['apex_coins' => 5000]);
        $stranger = User::factory()->create(['apex_coins' => 0]);

        $response = $this->actingAs($sender)
            ->postJson("/api/gifts/{$stranger->id}", ['amount' => 100])
            ->assertStatus(422);

        $this->assertSame('Дарить ApexCoin можно только друзьям.', $response->json('message'));
        $this->assertSame(5000, $sender->fresh()->apex_coins);
        $this->assertSame(0, $stranger->fresh()->apex_coins);
        $this->assertSame(0, CoinTransaction::count());
    }

    public function test_pending_incoming_friendship_does_not_allow_gifting(): void
    {
        $sender = User::factory()->create(['apex_coins' => 5000]);
        $friend = User::factory()->create(['apex_coins' => 0]);

        // Заявка в друзья от получателя к отправителю — ещё не дружба
        Friendship::create(['user_id' => $friend->id, 'friend_id' => $sender->id, 'status' => 'pending']);

        $response = $this->actingAs($sender)
            ->postJson("/api/gifts/{$friend->id}", ['amount' => 100])
            ->assertStatus(422);

        $this->assertSame('Дарить ApexCoin можно только друзьям.', $response->json('message'));
        $this->assertSame(0, CoinTransaction::count());
    }

    /**
     * Исправлено: isFriendsWith() собирал запрос без группировки, из-за чего
     * в прямом направлении статус дружбы не проверялся и подарок уходил
     * неподтверждённой заявке.
     */
    public function test_pending_outgoing_friendship_does_not_allow_gifting(): void
    {
        $sender = User::factory()->create(['apex_coins' => 5000]);
        $pending = User::factory()->create(['apex_coins' => 0]);

        Friendship::create(['user_id' => $sender->id, 'friend_id' => $pending->id, 'status' => 'pending']);

        $this->actingAs($sender)
            ->postJson("/api/gifts/{$pending->id}", ['amount' => 100])
            ->assertStatus(422);

        $this->assertSame(0, $pending->fresh()->apex_coins);
        $this->assertSame(5000, $sender->fresh()->apex_coins);
    }

    public function test_blocked_outgoing_friendship_does_not_allow_gifting(): void
    {
        $sender = User::factory()->create(['apex_coins' => 5000]);
        $blocked = User::factory()->create(['apex_coins' => 0]);

        Friendship::create(['user_id' => $sender->id, 'friend_id' => $blocked->id, 'status' => 'blocked']);

        $this->actingAs($sender)
            ->postJson("/api/gifts/{$blocked->id}", ['amount' => 100])
            ->assertStatus(422);

        $this->assertSame(0, $blocked->fresh()->apex_coins);
    }

    public function test_friendship_in_reverse_direction_also_allows_gifting(): void
    {
        $sender = User::factory()->create(['apex_coins' => 5000]);
        $friend = User::factory()->create(['apex_coins' => 0]);

        // Заявку отправлял будущий получатель — связь всё равно считается дружбой
        Friendship::create(['user_id' => $friend->id, 'friend_id' => $sender->id, 'status' => 'accepted']);

        $this->actingAs($sender)->postJson("/api/gifts/{$friend->id}", ['amount' => 100])->assertCreated();

        $this->assertSame(95, $friend->fresh()->apex_coins);
    }

    public function test_gift_amount_must_be_within_configured_bounds(): void
    {
        [$sender, $friend] = $this->friends(1000000, 0);

        $below = $this->actingAs($sender)->postJson("/api/gifts/{$friend->id}", ['amount' => 9])->assertStatus(422);
        $this->assertSame(
            'Сумма подарка должна быть от ' . config('apex.coins.gift.min') . ' до ' . config('apex.coins.gift.max') . ' ApexCoin.',
            $below->json('message')
        );

        $above = $this->actingAs($sender)->postJson("/api/gifts/{$friend->id}", ['amount' => 100001])->assertStatus(422);
        $this->assertStringContainsString('Сумма подарка должна быть', $above->json('message'));

        // Больше жёсткого лимита валидатора — уже ошибка валидации, а не бизнес-правило
        $this->actingAs($sender)
            ->postJson("/api/gifts/{$friend->id}", ['amount' => 1000001])
            ->assertStatus(422)
            ->assertJsonValidationErrors('amount');

        $this->actingAs($sender)
            ->postJson("/api/gifts/{$friend->id}", ['amount' => 0])
            ->assertStatus(422)
            ->assertJsonValidationErrors('amount');

        $this->assertSame(0, CoinTransaction::count());
        $this->assertSame(1000000, $sender->fresh()->apex_coins);
    }

    public function test_daily_gift_limit_is_enforced_including_boundary(): void
    {
        config(['apex.coins.gift.daily_limit' => 5000]);

        [$sender, $friend] = $this->friends(100000, 0);

        // Ровно лимит — можно
        $this->actingAs($sender)->postJson("/api/gifts/{$friend->id}", ['amount' => 5000])->assertCreated();

        $this->assertSame(5000, CoinService::giftedToday($sender->fresh()));

        // Плюс один ApexCoin сверх лимита — уже нельзя
        $response = $this->actingAs($sender)
            ->postJson("/api/gifts/{$friend->id}", ['amount' => 10])
            ->assertStatus(422);

        $this->assertSame('Дневной лимит подарков исчерпан. Сегодня можно подарить ещё 0 ApexCoin.', $response->json('message'));

        $this->assertSame(95000, $sender->fresh()->apex_coins);
        $this->assertSame(2, CoinTransaction::count(), 'В леджере только успешный подарок');
    }

    public function test_daily_gift_limit_counts_only_current_day(): void
    {
        config(['apex.coins.gift.daily_limit' => 5000]);

        [$sender, $friend] = $this->friends(100000, 0);

        $this->travel(-2)->days();
        $this->actingAs($sender)->postJson("/api/gifts/{$friend->id}", ['amount' => 5000])->assertCreated();
        $this->travelBack();

        // Вчерашний подарок не занимает сегодняшний лимит
        $this->assertSame(0, CoinService::giftedToday($sender->fresh()));

        $this->actingAs($sender)->postJson("/api/gifts/{$friend->id}", ['amount' => 5000])->assertCreated();

        $this->assertSame(5000, $this->actingAs($sender)->getJson('/api/gifts/limits')->json('gifted_today'));
    }

    public function test_gift_with_insufficient_funds_changes_nothing(): void
    {
        [$sender, $friend] = $this->friends(50, 0);

        $response = $this->actingAs($sender)
            ->postJson("/api/gifts/{$friend->id}", ['amount' => 100])
            ->assertStatus(422);

        $this->assertSame('Недостаточно ApexCoin на балансе.', $response->json('message'));

        // Оба перевода внутри одной транзакции: откатывается всё
        $this->assertSame(50, $sender->fresh()->apex_coins);
        $this->assertSame(0, $sender->fresh()->apex_coins_spent);
        $this->assertSame(0, $friend->fresh()->apex_coins);
        $this->assertSame(0, CoinTransaction::count());
        $this->assertSame(0, CoinService::giftedToday($sender->fresh()));
    }

    public function test_gifts_can_be_disabled_by_config(): void
    {
        config(['apex.coins.gift.enabled' => false]);

        [$sender, $friend] = $this->friends(5000, 0);

        $response = $this->actingAs($sender)
            ->postJson("/api/gifts/{$friend->id}", ['amount' => 100])
            ->assertStatus(422);

        $this->assertSame('Подарки временно отключены.', $response->json('message'));
        $this->assertSame(5000, $sender->fresh()->apex_coins);
        $this->assertSame(0, CoinTransaction::count());
    }

    public function test_gift_settings_from_shop_settings_are_applied(): void
    {
        // Комиссия 50% и дневной лимит 50 (минимум подарка — 10)
        ShopSettingService::put('gift.fee_percent', 50);
        ShopSettingService::put('gift.daily_limit', 50);

        [$sender, $friend] = $this->friends(5000, 0);

        // Первый подарок проходит: 100 в лимит 50... не проходит,
        // поэтому дарим 40 — комиссия 20, доходит 20.
        $response = $this->actingAs($sender)
            ->postJson("/api/gifts/{$friend->id}", ['amount' => 40])
            ->assertCreated();

        // Исправлено: правила читаются из shop_settings, поэтому комиссия
        // и дневной лимит из админки действительно применяются.
        $this->assertSame(20, $response->json('fee'));
        $this->assertSame(20, $response->json('received'));
        $this->assertSame(20, $friend->fresh()->apex_coins);

        // Второй подарок того же дня упирается в лимит из настроек
        $this->actingAs($sender)
            ->postJson("/api/gifts/{$friend->id}", ['amount' => 40])
            ->assertStatus(422);
    }
}
