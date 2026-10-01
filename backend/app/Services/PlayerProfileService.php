<?php

namespace App\Services;

use App\Models\Friendship;
use App\Models\PlayerAspectBedwars;
use App\Models\PlayerAspectPvp;
use App\Models\ProfileRecommendation;
use App\Models\User;
use App\Services\Concerns\StoresUserFiles;
use App\Http\Resources\PlayerProfileResource;

/**
 * Профиль игрока: просмотр и редактирование.
 *
 * Контроллер только принимает запрос; сборка ответа, работа с файлами
 * и пересчёт тира — здесь.
 */
class PlayerProfileService
{
    use StoresUserFiles;

    /**
     * Полный профиль для страницы игрока.
     */
    public function view(User $player, ?User $me): array
    {
        $player->load([
            'aspectPvp',
            'aspectBedwars',
            'tierTests' => fn ($q) => $q->latest()->limit(10),
            'clanMember.clan',
            'achievements',
            'friendsList',
            'friendsOf',
        ]);

        [$position, $total] = $this->rankOf($player);

        return [
            'user' => (new PlayerProfileResource($player))->resolve(),
            'friendship' => $this->friendshipPayload($player, $me),
            'rank' => [
                'position' => $position,
                'total' => $total,
            ],
            'recommendations' => $this->recommendations($player),
            'my_recommendation' => $me
                ? ProfileRecommendation::where('target_id', $player->id)
                    ->where('author_id', $me->id)
                    ->first()
                : null,
            'can_recommend' => $me
                ? ($me->id !== $player->id && $me->isFriendsWith($player->id))
                : false,
        ];
    }

    /**
     * Место в рейтинге по tier_score. Нулевой счёт места не имеет.
     *
     * @return array{0: ?int, 1: int}
     */
    public function rankOf(User $player): array
    {
        if ((int) $player->tier_score <= 0) {
            return [null, 0];
        }

        return [
            User::where('tier_score', '>', $player->tier_score)->count() + 1,
            User::where('tier_score', '>', 0)->count(),
        ];
    }

    private function friendshipPayload(User $player, ?User $me): ?array
    {
        // Профиль открыт и гостям, поэтому $me может быть null
        if (! $me) {
            return null;
        }

        $friendship = Friendship::where(function ($q) use ($me, $player) {
            $q->where('user_id', $me->id)->where('friend_id', $player->id);
        })->orWhere(function ($q) use ($me, $player) {
            $q->where('user_id', $player->id)->where('friend_id', $me->id);
        })->first();

        if (! $friendship) {
            return null;
        }

        return [
            'status' => $friendship->status,
            'initiated_by_me' => $friendship->user_id === $me->id,
        ];
    }

    private function recommendations(User $player): mixed
    {
        return ProfileRecommendation::where('target_id', $player->id)
            ->where('is_hidden', false)
            ->with('author:id,username,avatar,tier,is_verified,accent_color,banner_color')
            ->latest()
            ->limit(20)
            ->get();
    }

    /* ----------------------------- Редактирование ----------------------------- */

    /**
     * Базовые поля профиля: био, баннер, аватар, обложка, соцсети.
     */
    public function updateMe(User $user, array $data, array $files = []): User
    {
        if (isset($files['avatar'])) {
            $this->deleteStoredFile($user->avatar);
            $data['avatar'] = $files['avatar']->store("users/{$user->id}", 'public');
        }

        if (isset($files['cover'])) {
            $this->deleteStoredFile($user->cover_path);
            $data['cover_path'] = $files['cover']->store("users/{$user->id}/covers", 'public');
        }

        unset($data['cover']);

        $user->update($data);

        AchievementService::check($user);

        return $user->fresh();
    }

    /**
     * Расширенный профиль: статус, цитата, цвета, фон карточки.
     */
    public function updateProfile(User $user, array $data, array $files = []): User
    {
        if (isset($files['card_background'])) {
            $this->deleteStoredFile($user->card_background);

            $data['card_background'] = $files['card_background']
                ->store("users/{$user->id}/backgrounds", 'public');
        }

        $user->update($data);

        return $user->fresh();
    }

    public function removeAvatar(User $user): User
    {
        if ($user->avatar) {
            $this->deleteStoredFile($user->avatar);
            $user->update(['avatar' => null]);
        }

        return $user->fresh();
    }

    public function removeCover(User $user): User
    {
        if ($user->cover_path) {
            $this->deleteStoredFile($user->cover_path);
            $user->update(['cover_path' => null]);
        }

        return $user->fresh();
    }

    public function removeCardBackground(User $user): User
    {
        if ($user->card_background) {
            $this->deleteStoredFile($user->card_background);
            $user->update(['card_background' => null]);
        }

        return $user->fresh();
    }

    /* ----------------------------- Аспекты ----------------------------- */

    /**
     * Сохранить аспекты режима и пересчитать тир.
     *
     * @return array{aspect: PlayerAspectPvp|PlayerAspectBedwars, user: User}
     */
    public function updateAspects(User $user, string $mode, array $values): array
    {
        if ($mode === 'pvp') {
            $aspect = PlayerAspectPvp::updateOrCreate(['user_id' => $user->id], $values);
        } else {
            $aspect = PlayerAspectBedwars::updateOrCreate(['user_id' => $user->id], $values);
        }

        $user->recalcTierFromAspects();

        AchievementService::check($user);

        return ['aspect' => $aspect, 'user' => $user->fresh()];
    }
}
