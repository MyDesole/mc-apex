<?php

namespace App\Domains\Shop\Controllers\Api;

use App\Domains\Moderation\Models\ModerationLog;
use App\Domains\Players\Support\PetTraits;
use App\Domains\Shop\Models\ShopItem;
use App\Domains\Shop\Models\UserInventory;
use App\Domains\Shop\Services\ShopService;
use App\Domains\Users\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Инвентарь чужого игрока для админа (досье): посмотреть, надеть или
 * снять предмет, поменять количество, забрать, перебросить черты
 * питомца. Каждое действие пишется в историю модерации.
 */
class AdminInventoryController extends Controller
{
    public function index(User $user): JsonResponse
    {
        return response()->json($this->payload($user));
    }

    public function equip(Request $request, User $user, ShopItem $shopItem): JsonResponse
    {
        return $this->act($request, $user, 'user.item_equip', $shopItem->name, fn () => ShopService::equip($user, $shopItem));
    }

    public function unequip(Request $request, User $user, ShopItem $shopItem): JsonResponse
    {
        return $this->act($request, $user, 'user.item_unequip', $shopItem->name, fn () => ShopService::unequip($user, $shopItem));
    }

    /** Поменять количество (приоритеты, монеты и прочее, что копится). */
    public function quantity(Request $request, User $user, UserInventory $row): JsonResponse
    {
        abort_unless($row->user_id === $user->id, 404);

        $data = $request->validate(['quantity' => ['required', 'integer', 'min:1', 'max:999']]);
        $before = $row->quantity;

        $row->forceFill(['quantity' => $data['quantity']])->save();

        ModerationLog::record($request->user(), 'user.item_quantity', $user->id, [
            'item' => $row->item?->name, 'from' => $before, 'to' => $data['quantity'],
        ]);

        return response()->json($this->payload($user));
    }

    /** Забрать предмет: надетый сначала снимаем, чтобы профиль не держал его. */
    public function destroy(Request $request, User $user, UserInventory $row): JsonResponse
    {
        abort_unless($row->user_id === $user->id, 404);

        $item = $row->item;

        DB::transaction(function () use ($user, $row, $item) {
            if ($item && $row->isEquipped()) {
                try {
                    ShopService::unequip($user, $item);
                } catch (RuntimeException) {
                    // Уже снят — просто удаляем
                }
            }

            $row->delete();
        });

        ModerationLog::record($request->user(), 'user.item_remove', $user->id, ['item' => $item?->name]);

        return response()->json($this->payload($user->fresh()));
    }

    /** Новые случайные черты питомца (номер экземпляра сохраняется). */
    public function rerollPet(Request $request, User $user, UserInventory $row): JsonResponse
    {
        abort_unless($row->user_id === $user->id && $row->pet_traits, 404);

        $row->forceFill(['pet_traits' => PetTraits::roll()])->save();

        if ($row->isEquipped()) {
            $user->forceFill(['pet_traits' => $row->pet_traits + ['serial' => $row->pet_serial]])->save();
        }

        ModerationLog::record($request->user(), 'user.pet_reroll', $user->id, ['item' => $row->item?->name, 'serial' => $row->pet_serial]);

        return response()->json($this->payload($user->fresh()));
    }

    private function act(Request $request, User $user, string $action, string $name, callable $fn): JsonResponse
    {
        try {
            $fn();
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        ModerationLog::record($request->user(), $action, $user->id, ['item' => $name]);

        return response()->json($this->payload($user->fresh()));
    }

    /** Только то, что реально лежит в инвентаре (у админа — без бесплатной витрины). */
    private function payload(User $user): array
    {
        $items = ShopService::inventory($user)
            ->filter(fn (array $item) => is_int($item['inventory_id']))
            ->values();

        return [
            'items' => $items,
            'counts' => ['all' => $items->count(), 'equipped' => $items->where('equipped', true)->count()],
            'owns_all_cosmetics' => ShopService::ownsAllCosmetics($user),
        ];
    }
}
