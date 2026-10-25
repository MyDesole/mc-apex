<?php

namespace App\Domains\Clan\Services;

use App\Domains\Clan\Models\Clan;
use App\Domains\Clan\Models\ClanTreasuryTransaction;
use App\Domains\Users\Models\User;
use App\Domains\Wallet\Models\CoinTransaction;
use App\Domains\Wallet\Services\CoinService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Казна клана: участники скидываются ApexCoin, лидер тратит их
 * на клановые предметы. Каждое движение пишется в историю казны.
 */
class ClanTreasuryService
{
    public const MAX_DONATION = 100000;

    /** Взнос участника: монеты уходят с его баланса в казну. */
    public function donate(User $user, int $amount): Clan
    {
        $clan = $user->clanMember?->clan;

        if (! $clan) {
            throw new RuntimeException('Скидываться в казну могут только участники клана.');
        }

        if ($amount < 1 || $amount > self::MAX_DONATION) {
            throw new RuntimeException('Взнос — от 1 до ' . self::MAX_DONATION . ' ApexCoin.');
        }

        if (! CoinService::canAfford($user, $amount)) {
            throw new RuntimeException('Недостаточно ApexCoin. Не хватает ' . ($amount - (int) $user->apex_coins) . '.');
        }

        return DB::transaction(function () use ($user, $clan, $amount) {
            CoinService::debit($user, $amount, CoinTransaction::SOURCE_CLAN_TREASURY, "Взнос в казну клана [{$clan->tag}]", [
                'reference_type' => Clan::class,
                'reference_id' => $clan->id,
            ]);

            return $this->move($clan, $user, $amount, ClanTreasuryTransaction::TYPE_DONATION, 'Взнос');
        });
    }

    /** Списать из казны: вызывается внутри транзакции покупки. */
    public function spend(Clan $clan, User $leader, int $amount, string $description): Clan
    {
        return $this->move($clan, $leader, -abs($amount), ClanTreasuryTransaction::TYPE_PURCHASE, $description);
    }

    private function move(Clan $clan, User $user, int $amount, string $type, string $description): Clan
    {
        /** @var Clan $locked */
        $locked = Clan::whereKey($clan->id)->lockForUpdate()->firstOrFail();
        $balance = (int) $locked->treasury + $amount;

        if ($balance < 0) {
            throw new RuntimeException('В казне клана недостаточно ApexCoin. Не хватает ' . abs($balance) . '.');
        }

        $locked->forceFill(['treasury' => $balance])->save();

        ClanTreasuryTransaction::create([
            'clan_id' => $locked->id,
            'user_id' => $user->id,
            'amount' => $amount,
            'balance_after' => $balance,
            'type' => $type,
            'description' => $description,
        ]);

        $clan->setAttribute('treasury', $balance);

        return $locked;
    }

    /** Баланс, главные вкладчики и последние движения. */
    public function overview(Clan $clan): array
    {
        $donors = ClanTreasuryTransaction::query()
            ->where('clan_id', $clan->id)
            ->where('type', ClanTreasuryTransaction::TYPE_DONATION)
            ->whereNotNull('user_id')
            ->groupBy('user_id')
            ->selectRaw('user_id, SUM(amount) as total')
            ->orderByDesc('total')
            ->limit(10)
            ->with('user:id,username,avatar')
            ->get();

        $history = ClanTreasuryTransaction::query()
            ->where('clan_id', $clan->id)
            ->with('user:id,username,avatar')
            ->latest('id')
            ->limit(30)
            ->get();

        return [
            'balance' => (int) $clan->fresh()->treasury,
            'max_donation' => self::MAX_DONATION,
            'donors' => $donors->map(fn ($row) => [
                'user' => $row->user ? ['id' => $row->user->id, 'username' => $row->user->username, 'avatar_url' => $row->user->avatar_url] : null,
                'total' => (int) $row->total,
            ])->values(),
            'history' => $history->map(fn (ClanTreasuryTransaction $row) => [
                'id' => $row->id,
                'amount' => $row->amount,
                'type' => $row->type,
                'description' => $row->description,
                'user' => $row->user ? ['id' => $row->user->id, 'username' => $row->user->username] : null,
                'created_at' => $row->created_at?->toIso8601String(),
            ])->values(),
        ];
    }
}
