<?php

namespace Tests\Feature;

use App\Models\Clan;
use App\Models\ClanApplication;
use App\Models\ClanMember;
use App\Models\CoinTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Плата за вступление в клан.
 *
 * Лидер назначает цену приёма. Деньги списываются у заявителя в момент
 * принятия и уходят лидеру: брать плату при подаче нельзя, иначе
 * заявитель заморозил бы монеты, разослав заявки во все кланы.
 */
class ClanEntryFeeTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: Clan, 1: User}
     */
    private function clan(int $fee = 0, int $leaderCoins = 0): array
    {
        $leader = User::factory()->create(['apex_coins' => $leaderCoins]);

        $clan = Clan::create([
            'name' => 'Платный клан',
            'tag' => 'FEE',
            'leader_id' => $leader->id,
            'entry_fee' => $fee,
        ]);

        ClanMember::create([
            'clan_id' => $clan->id,
            'user_id' => $leader->id,
            'role' => 'leader',
        ]);

        return [$clan, $leader];
    }

    /**
     * Заявитель вместе с заявкой: в роуте {application} — id заявки,
     * а не игрока.
     *
     * @return array{0: User, 1: ClanApplication}
     */
    private function applicant(Clan $clan, int $coins): array
    {
        $user = User::factory()->create(['apex_coins' => $coins]);

        $application = ClanApplication::create([
            'clan_id' => $clan->id,
            'user_id' => $user->id,
            'status' => 'pending',
        ]);

        return [$user, $application];
    }

    /* ---------------------- Успешное вступление ---------------------- */

    public function test_fee_is_charged_on_accept_and_goes_to_leader(): void
    {
        [$clan, $leader] = $this->clan(fee: 500);
        [$applicant, $application] = $this->applicant($clan, 800);

        $this->actingAs($leader)
            ->postJson("/api/clans/{$clan->id}/applications/{$application->id}/accept")
            ->assertOk();

        $this->assertSame(300, $applicant->fresh()->apex_coins, 'Плата списана у заявителя');
        $this->assertSame(500, $leader->fresh()->apex_coins, 'Плата ушла лидеру');

        $this->assertDatabaseHas('clan_members', [
            'clan_id' => $clan->id,
            'user_id' => $applicant->id,
            'role' => 'member',
        ]);

        // Обе операции записаны как clan_fee
        $this->assertSame(2, CoinTransaction::where('source', CoinTransaction::SOURCE_CLAN_FEE)->count());
    }

    public function test_zero_fee_means_free_entry(): void
    {
        [$clan, $leader] = $this->clan(fee: 0);
        [$applicant, $application] = $this->applicant($clan, 0);

        $this->actingAs($leader)
            ->postJson("/api/clans/{$clan->id}/applications/{$application->id}/accept")
            ->assertOk();

        $this->assertSame(0, $applicant->fresh()->apex_coins);
        $this->assertSame(0, CoinTransaction::count(), 'Без платы транзакций нет');
    }

    /* ---------------------- Не хватает монет ---------------------- */

    public function test_not_enough_coins_blocks_acceptance(): void
    {
        [$clan, $leader] = $this->clan(fee: 500);
        [$applicant, $application] = $this->applicant($clan, 100);

        $this->actingAs($leader)
            ->postJson("/api/clans/{$clan->id}/applications/{$application->id}/accept")
            ->assertStatus(422);

        // Ничего не изменилось
        $this->assertSame(100, $applicant->fresh()->apex_coins);
        $this->assertSame(0, $leader->fresh()->apex_coins);
        $this->assertDatabaseMissing('clan_members', [
            'clan_id' => $clan->id,
            'user_id' => $applicant->id,
        ]);
        $this->assertSame(0, CoinTransaction::count());
    }

    public function test_application_stays_pending_after_failed_accept(): void
    {
        [$clan, $leader] = $this->clan(fee: 500);
        [$applicant, $application] = $this->applicant($clan, 100);

        $this->actingAs($leader)
            ->postJson("/api/clans/{$clan->id}/applications/{$application->id}/accept")
            ->assertStatus(422);

        $this->assertDatabaseHas('clan_applications', [
            'clan_id' => $clan->id,
            'user_id' => $applicant->id,
            'status' => 'pending',
        ]);
    }

    public function test_exact_amount_is_enough(): void
    {
        [$clan, $leader] = $this->clan(fee: 500);
        [$applicant, $application] = $this->applicant($clan, 500);

        $this->actingAs($leader)
            ->postJson("/api/clans/{$clan->id}/applications/{$application->id}/accept")
            ->assertOk();

        $this->assertSame(0, $applicant->fresh()->apex_coins);
        $this->assertSame(500, $leader->fresh()->apex_coins);
    }

    /* ---------------------- Настройка цены ---------------------- */

    public function test_leader_can_set_entry_fee(): void
    {
        [$clan, $leader] = $this->clan();

        $this->actingAs($leader)
            ->putJson("/api/clans/{$clan->id}", [
                'name' => $clan->name,
                'entry_fee' => 750,
            ])
            ->assertOk();

        $this->assertSame(750, $clan->fresh()->entry_fee);
    }

    public function test_negative_fee_is_rejected(): void
    {
        [$clan, $leader] = $this->clan();

        $this->actingAs($leader)
            ->putJson("/api/clans/{$clan->id}", [
                'name' => $clan->name,
                'entry_fee' => -100,
            ])
            ->assertStatus(422);

        $this->assertSame(0, $clan->fresh()->entry_fee);
    }

    public function test_excessive_fee_is_rejected(): void
    {
        [$clan, $leader] = $this->clan();

        $this->actingAs($leader)
            ->putJson("/api/clans/{$clan->id}", [
                'name' => $clan->name,
                'entry_fee' => 1_000_000,
            ])
            ->assertStatus(422);
    }

    /* ---------------------- Заявитель видит цену ---------------------- */

    public function test_fee_is_visible_in_clan_page(): void
    {
        [$clan] = $this->clan(fee: 300);

        $response = $this->getJson("/api/clans/{$clan->id}")->assertOk();

        $this->assertSame(300, $response->json('clan.entry_fee'));
    }

    public function test_fee_is_visible_in_clan_list(): void
    {
        [$clan] = $this->clan(fee: 250);

        $response = $this->getJson('/api/clans')->assertOk();

        $found = collect($response->json('data'))->firstWhere('id', $clan->id);

        $this->assertNotNull($found);
        $this->assertSame(250, $found['entry_fee']);
    }

    /* ---------------------- Отказ возвращает заявку ---------------------- */

    public function test_declined_application_charges_nothing(): void
    {
        [$clan, $leader] = $this->clan(fee: 500);
        [$applicant, $application] = $this->applicant($clan, 800);

        $this->actingAs($leader)
            ->postJson("/api/clans/{$clan->id}/applications/{$application->id}/decline")
            ->assertOk();

        $this->assertSame(800, $applicant->fresh()->apex_coins, 'При отказе плата не берётся');
        $this->assertSame(0, $leader->fresh()->apex_coins);
        $this->assertSame(0, CoinTransaction::count());
    }
}
