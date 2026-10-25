<?php

namespace App\Domains\Control\Controllers;

use App\Domains\Control\Models\PromoCode;
use App\Domains\Control\Services\ControlService;
use App\Domains\Control\Services\GiftService;
use App\Domains\Moderation\Models\ModerationLog;
use App\Domains\Moderation\Notifications\ModerationNotification;
use App\Domains\Shop\Models\ShopItem;
use App\Domains\Users\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * «Пульт» админки: объявление, переключатели, событие xN, раздачи,
 * промокоды, кто онлайн и подарок предмета игроку.
 */
class ControlController extends Controller
{
    public function __construct(private GiftService $gifts) {}

    public function show(): JsonResponse
    {
        return response()->json([
            ...ControlService::state(),
            'feature_labels' => ControlService::FEATURES,
            'max_multiplier' => ControlService::MAX_MULTIPLIER,
            'items' => ShopItem::where('is_active', true)
                ->whereNotIn('type', [ShopItem::TYPE_COIN_BUNDLE])
                ->orderBy('type')->orderBy('sort_order')
                ->get(['id', 'name', 'type', 'rarity']),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'announcement' => ['nullable', 'array'],
            'announcement.text' => ['nullable', 'string', 'max:300'],
            'announcement.tone' => ['nullable', Rule::in(['info', 'success', 'warning', 'danger'])],
            'announcement.link' => ['nullable', 'string', 'max:255'],
            'announcement.link_label' => ['nullable', 'string', 'max:40'],
            'announcement.until' => ['nullable', 'date'],
            'features' => ['nullable', 'array'],
            'features.*' => ['boolean'],
            'multiplier' => ['nullable', 'integer', 'min:1', 'max:'.ControlService::MAX_MULTIPLIER],
            'multiplier_until' => ['nullable', 'date'],
        ]);

        if (! empty($data['announcement'])) {
            // У объявления свой id: закрытый игроком баннер не всплывёт, новый — всплывёт
            $data['announcement']['id'] = Str::random(8);
        }

        ControlService::update($data);

        ModerationLog::record($request->user(), 'control.update', null, array_filter([
            'announcement' => $data['announcement']['text'] ?? null,
            'features' => $data['features'] ?? null,
            'multiplier' => $data['multiplier'] ?? null,
        ], fn ($v) => $v !== null));

        return $this->show();
    }

    /** Раздача монет и/или рассылка уведомления; dry_run — только посчитать. */
    public function giveaway(Request $request): JsonResponse
    {
        $data = $request->validate([
            'audience' => ['required', Rule::in(GiftService::AUDIENCES)],
            'clan_id' => ['required_if:audience,clan', 'nullable', 'integer', 'exists:clans,id'],
            'tier' => ['required_if:audience,tier', 'nullable', Rule::in(['S', 'A', 'B', 'C', 'D', 'E'])],
            'coins' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'message' => ['nullable', 'string', 'max:300'],
            'dry_run' => ['boolean'],
        ]);

        $audience = $this->gifts->audience($data['audience'], $data['clan_id'] ?? null, $data['tier'] ?? null);

        if (! empty($data['dry_run'])) {
            return response()->json(['count' => (clone $audience)->count()]);
        }

        abort_if(empty($data['coins']) && empty($data['message']), 422, 'Укажите монеты или текст сообщения.');

        $count = $this->gifts->giveaway($request->user(), $audience, (int) ($data['coins'] ?? 0), $data['message'] ?? null);

        ModerationLog::record($request->user(), 'control.giveaway', null, [
            'audience' => $data['audience'],
            'count' => $count,
            'coins' => (int) ($data['coins'] ?? 0),
            'message' => $data['message'] ?? null,
        ]);

        return response()->json(['count' => $count]);
    }

    public function online(): JsonResponse
    {
        $users = User::query()
            ->where('last_seen_at', '>=', now()->subMinutes(5))
            ->orderByDesc('last_seen_at')
            ->limit(100)
            ->get(['id', 'username', 'avatar', 'role', 'tier', 'last_seen_at']);

        return response()->json(['users' => $users->map(fn (User $u) => [
            'id' => $u->id,
            'username' => $u->username,
            'avatar_url' => $u->avatar_url,
            'role' => $u->role,
            'tier' => $u->tier,
            'last_seen_at' => $u->last_seen_at,
        ])]);
    }

    public function giveItem(Request $request, User $user): JsonResponse
    {
        $data = $request->validate(['shop_item_id' => ['required', 'integer', 'exists:shop_items,id']]);
        $item = ShopItem::findOrFail($data['shop_item_id']);

        $this->gifts->giveItem($user, $item, $request->user());

        $user->notify(new ModerationNotification('announcement', "Администрация подарила вам «{$item->name}» — загляните в инвентарь."));

        ModerationLog::record($request->user(), 'user.item_gift', $user->id, ['item' => $item->name]);

        return response()->json(['ok' => true]);
    }

    /* ----------------------------- Промокоды ----------------------------- */

    public function promoIndex(): JsonResponse
    {
        return response()->json([
            'promo_codes' => PromoCode::with(['item:id,name', 'creator:id,username'])->latest()->limit(100)->get(),
        ]);
    }

    public function promoStore(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['nullable', 'string', 'min:3', 'max:32', 'regex:/^[A-Za-z0-9_-]+$/', 'unique:promo_codes,code'],
            'coins' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'shop_item_id' => ['nullable', 'integer', 'exists:shop_items,id'],
            'max_uses' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'expires_at' => ['nullable', 'date', 'after:now'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        abort_if(empty($data['coins']) && empty($data['shop_item_id']), 422, 'Промокод должен что-то давать: монеты или предмет.');

        $promo = PromoCode::create([
            ...$data,
            'code' => mb_strtoupper($data['code'] ?? 'APEX-'.Str::upper(Str::random(6))),
            'coins' => (int) ($data['coins'] ?? 0),
            'created_by' => $request->user()->id,
        ]);

        ModerationLog::record($request->user(), 'control.promo', null, ['code' => $promo->code, 'coins' => $promo->coins]);

        return response()->json(['promo_code' => $promo->load(['item:id,name', 'creator:id,username'])], 201);
    }

    public function promoToggle(PromoCode $promo): JsonResponse
    {
        $promo->update(['is_active' => ! $promo->is_active]);

        return response()->json(['promo_code' => $promo]);
    }

    public function promoDestroy(PromoCode $promo): JsonResponse
    {
        $promo->delete();

        return response()->json(['ok' => true]);
    }

    /** Игрок активирует промокод в кошельке. */
    public function redeem(Request $request): JsonResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:32']]);

        return response()->json($this->gifts->redeem($request->user(), $data['code']));
    }
}
