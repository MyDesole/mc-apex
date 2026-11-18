<?php

namespace App\Domains\Players\Services;

use App\Domains\Minecraft\Models\MinecraftDuel;
use App\Domains\Minecraft\Models\MinecraftGrant;
use App\Domains\Minecraft\Services\ServerRankService;
use App\Domains\Users\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DonorShowcaseService
{
    public const MODES = ['duels', 'bedwars', 'fastbridge'];

    public const THEMES = ['classic', 'obsidian', 'aurora', 'ember'];

    public function rank(User $user): ?string
    {
        $ranks = ['legend', 'apex', 'mvp_plus', 'mvp', 'vip'];
        $active = DB::table('server_rank_grants')->where('user_id', $user->id)
            ->whereIn('rank', $ranks)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->pluck('rank')->all();
        foreach ($ranks as $rank) {
            if (in_array($rank, $active, true)) {
                return $rank;
            }
        }
        // Existing Lavka VIP orders issue commands directly, without a server_rank_grants row.
        $orders = DB::table('privilege_orders as o')->join('minecraft_grants as g', 'g.id', '=', 'o.grant_id')
            ->where('o.user_id', $user->id)->where('g.status', MinecraftGrant::DONE)
            ->whereIn('o.item_key', ['vip_7', 'vip_30', 'vip_90'])
            ->get(['o.item_key', 'o.created_at', 'g.delivered_at']);
        foreach ($orders as $order) {
            $days = ['vip_7' => 7, 'vip_30' => 30, 'vip_90' => 90][$order->item_key];
            if (CarbonImmutable::parse($order->delivered_at ?? $order->created_at)->addDays($days)->isFuture()) {
                return 'vip';
            }
        }

        return null;
    }

    public function show(User $user, ?User $viewer = null): array
    {
        $rank = $this->rank($user);
        $own = $viewer?->id === $user->id;
        $settings = $user->donor_showcase;
        $visible = $rank !== null && ($settings['enabled'] ?? false);
        $duel = $visible && ! empty($settings['duel_id']) ? $this->duels($user)->find($settings['duel_id']) : null;
        $achievement = $visible && ! empty($settings['achievement_id'])
            ? $user->achievements()->where('achievements.id', $settings['achievement_id'])->first() : null;

        return [
            'eligible' => $rank !== null,
            'rank' => $rank ? ServerRankService::RANKS[$rank]['name'] : null,
            'visible' => $visible,
            'settings' => $own || $visible ? $settings : null,
            'duel' => $duel ? $this->duelCard($duel, $user) : null,
            'achievement' => $achievement ? ['id' => $achievement->id, 'name' => $achievement->name] : null,
            'options' => $own ? [
                'duels' => $this->duels($user)->orderByDesc('played_at')->orderByDesc('id')->limit(30)->get()->map(fn ($d) => $this->duelCard($d, $user))->all(),
                'achievements' => $user->achievements()->select('achievements.id', 'achievements.name')->get()->map(fn ($a) => ['id' => $a->id, 'name' => $a->name])->all(),
                'modes' => self::MODES, 'themes' => self::THEMES,
            ] : null,
        ];
    }

    public function save(User $user, array $data): array
    {
        abort_unless($this->rank($user) !== null, 403, 'Витрина доступна с действующим VIP и выше.');
        if (! empty($data['duel_id']) && ! $this->duels($user)->whereKey($data['duel_id'])->exists()) {
            throw ValidationException::withMessages(['duel_id' => 'Можно закрепить только собственный матч.']);
        }
        if (! empty($data['achievement_id']) && ! $user->achievements()->where('achievements.id', $data['achievement_id'])->exists()) {
            throw ValidationException::withMessages(['achievement_id' => 'Сначала получите это достижение.']);
        }
        $settings = [
            'enabled' => (bool) $data['enabled'], 'mode' => $data['mode'], 'theme' => $data['theme'],
            'title' => trim(strip_tags($data['title'] ?? '')),
            'duel_id' => $data['duel_id'] ?? null, 'achievement_id' => $data['achievement_id'] ?? null,
        ];
        $user->forceFill(['donor_showcase' => $settings])->save();

        return $this->show($user, $user);
    }

    private function duels(User $user): Builder
    {
        return MinecraftDuel::query()->where(fn ($q) => $q->where('winner_user_id', $user->id)->orWhere('loser_user_id', $user->id));
    }

    private function duelCard(MinecraftDuel $duel, User $user): array
    {
        $won = $duel->winner_user_id === $user->id;

        return ['id' => $duel->id, 'kit' => $duel->kit, 'won' => $won,
            'opponent' => $won ? $duel->loser_name : $duel->winner_name,
            'duration_seconds' => $duel->duration_seconds, 'played_at' => $duel->played_at?->toIso8601String()];
    }
}
