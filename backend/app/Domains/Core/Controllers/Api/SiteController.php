<?php

namespace App\Domains\Core\Controllers\Api;

use App\Domains\Core\Models\SiteSetting;
use App\Domains\Minecraft\Services\MinecraftStatusService;
use App\Domains\Tiers\Models\TierTest;
use App\Domains\Users\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

/**
 * Общие данные сайта: подвал, статус Minecraft-сервера, команда проекта.
 */
class SiteController extends Controller
{
    /** Роли команды и подписи к ним, в порядке показа. */
    private const TEAM_ROLES = [
        'admin' => 'Администратор',
        'moderator' => 'Модератор',
        'tester' => 'Тестер',
        'bridge_curator' => 'Куратор бриджа',
        'bridge_tester' => 'Тестер бриджа',
    ];

    public function __construct(
        private readonly MinecraftStatusService $minecraft,
    ) {
    }

    /** Подвал: соцсети, текст и адрес сервера — один лёгкий запрос на любую страницу. */
    public function index(): JsonResponse
    {
        $data = Cache::remember('site:footer', 300, fn () => [
            'socials' => array_filter(SiteSetting::group('socials')),
            'footer_text' => SiteSetting::group('footer')['footer_text'] ?? null,
        ]);

        return response()->json([
            ...$data,
            'server_address' => config('services.minecraft.address') ?: config('services.minecraft.host'),
            // Очки за турниры и войны — для страницы правил (настраиваются в админке)
            'rating' => app(\App\Domains\Players\Services\RatingService::class)->weights(),
            // «Пульт»: баннер на весь сайт, выключенные функции, событие xN
            'control' => \App\Domains\Control\Services\ControlService::publicState(),
        ]);
    }

    public function serverStatus(): JsonResponse
    {
        return response()->json($this->minecraft->status());
    }

    /** Команда: персонал по ролям и сколько тир-тестов провёл каждый. */
    public function team(): JsonResponse
    {
        $members = User::query()
            ->whereIn('role', array_keys(self::TEAM_ROLES))
            ->get(['id', 'username', 'avatar', 'role', 'minecraft_username', 'created_at']);

        $tests = TierTest::query()
            ->where('status', 'completed')
            ->whereIn('tester_id', $members->pluck('id'))
            ->selectRaw('tester_id, COUNT(*) as total')
            ->groupBy('tester_id')
            ->pluck('total', 'tester_id');

        $order = array_flip(array_keys(self::TEAM_ROLES));

        return response()->json([
            'team' => $members
                ->map(fn (User $user) => [
                    'id' => $user->id,
                    'username' => $user->username,
                    'avatar_url' => $user->avatar_url,
                    'role' => $user->role,
                    'role_label' => self::TEAM_ROLES[$user->role],
                    'tests' => (int) ($tests[$user->id] ?? 0),
                    'since' => $user->created_at?->toDateString(),
                ])
                ->sortBy([fn ($a, $b) => $order[$a['role']] <=> $order[$b['role']], ['tests', 'desc']])
                ->values(),
        ]);
    }
}
