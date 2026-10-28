<?php

namespace App\Domains\Players\Services;

use App\Domains\Friends\Models\Friendship;
use App\Domains\Players\Models\PlayerAspectBedwars;
use App\Domains\Players\Models\PlayerAspectPvp;
use App\Domains\Players\Services\RatingService;
use App\Domains\Players\Models\ProfileRecommendation;
use App\Domains\Users\Models\User;
use App\Support\Concerns\StoresUserFiles;
use App\Domains\Players\Resources\PlayerProfileResource;
use App\Domains\Achievements\Services\AchievementService;
use App\Domains\Bridge\Resources\BridgeSubmissionResource;
use App\Domains\Bridge\Services\BridgeService;

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
            'bridgeRank',
        ]);

        [$position, $total] = $this->rankOf($player);

        return [
            'user' => (new PlayerProfileResource($player))->resolve(),

            // Режим профиля выбирает сам игрок: от него зависит порядок блоков
            // и звание бриджера вместо тира
            'profile_mode' => $player->profile_mode ?? 'pvp',

            // Звание бриджера: в бридж-профиле это главный ранг
            'bridge_rank' => $player->bridgeRank
                ? [
                    'id' => $player->bridgeRank->id,
                    'key' => $player->bridgeRank->key,
                    'label' => $player->bridgeRank->label,
                    'color' => $player->bridgeRank->color,
                ]
                : null,
            'friendship' => $this->friendshipPayload($player, $me),
            'rank' => [
                'position' => $position,
                'total' => $total,
            ],
            // Из чего сложились очки рейтинга — для блока в профиле
            'rating' => $player->isStaff() ? null : app(RatingService::class)->breakdown($player),
            'bridge' => $this->bridgePayload($player),
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
     * Место в рейтинге по tier_score.
     *
     * Места нет в двух случаях: счёт нулевой и игрок состоит в персонале.
     * Персонал не попадает в сам рейтинг, поэтому и место в профиле ему
     * показывать нечего — иначе значок места был бы, а в списке его нет.
     *
     * @return array{0: ?int, 1: int}
     */
    public function rankOf(User $player): array
    {
        if ((int) $player->rating_points <= 0 || $player->isStaff()) {
            return [null, 0];
        }

        return [
            /*
             * Считаем только тех, кто есть в рейтинге: без персонала.
             * Иначе место смещалось бы на число персонала с большим счётом.
             * При равных очках выше тот, у кого больше id — как в списке рейтинга.
             */
            User::query()
                ->excludeStaff()
                ->where(fn ($q) => $q
                    ->where('rating_points', '>', $player->rating_points)
                    ->orWhere(fn ($q) => $q->where('rating_points', $player->rating_points)->where('id', '>', $player->id)))
                ->count() + 1,

            User::query()
                ->excludeStaff()
                ->where('rating_points', '>', 0)
                ->count(),
        ];
    }

    /**
     * Бридж игрока: подтверждённые виды и место в бридж-топе.
     *
     * Отдаём только подтверждённое: неподтверждённые виды видит сам игрок
     * в своей форме, другим они ни к чему.
     */
    private function bridgePayload(User $player): array
    {
        $bridge = app(BridgeService::class);

        [$position, $total] = $bridge->rankOf($player);

        return [
            'techniques' => BridgeSubmissionResource::collection(
                // technique.variants нужен для пиллов: иначе список подвидов пуст
                $bridge->confirmedForUser($player)->load('technique.variants')
            )->resolve(),
            'summary' => $bridge->summary($player),
            'rank' => [
                'position' => $position,
                'total' => $total,
            ],
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
            ->with('author:id,username,avatar,tier,bridge_primary,bridge_rank_id,profile_mode,name_effect,chat_bubble,profile_title,name_icon,is_verified,accent_color,banner_color')
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
            $data['avatar'] = $this->storeAvatar($user, $files['avatar']);
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

    /** Сторона аватара после сжатия: шапка рисует его не крупнее ~130px. */
    private const AVATAR_SIDE = 512;

    /**
     * Аватар до 10 МБ: статичную картинку уменьшаем до 512px и сохраняем
     * в WebP — профиль не тянет мегабайты. GIF не трогаем (анимация), без
     * GD на сервере кладём файл как есть.
     */
    private function storeAvatar(User $user, \Illuminate\Http\UploadedFile $file): string
    {
        $dir = "users/{$user->id}";
        $mime = $file->getMimeType();

        if ($mime === 'image/gif' || ! function_exists('imagewebp')) {
            return $file->store($dir, 'public');
        }

        $source = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($file->getRealPath()),
            'image/png' => @imagecreatefrompng($file->getRealPath()),
            'image/webp' => @imagecreatefromwebp($file->getRealPath()),
            default => false,
        };

        if (! $source) {
            return $file->store($dir, 'public');
        }

        $width = imagesx($source);
        $height = imagesy($source);
        $scale = min(1, self::AVATAR_SIDE / max($width, $height));
        $w = max(1, (int) round($width * $scale));
        $h = max(1, (int) round($height * $scale));

        $target = imagecreatetruecolor($w, $h);
        imagealphablending($target, false);
        imagesavealpha($target, true);
        imagecopyresampled($target, $source, 0, 0, 0, 0, $w, $h, $width, $height);

        ob_start();
        imagewebp($target, null, 86);
        $binary = ob_get_clean();

        imagedestroy($source);
        imagedestroy($target);

        $path = $dir . '/' . \Illuminate\Support\Str::random(40) . '.webp';
        \Illuminate\Support\Facades\Storage::disk('public')->put($path, $binary);

        return $path;
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
