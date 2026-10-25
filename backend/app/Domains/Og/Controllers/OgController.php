<?php

namespace App\Domains\Og\Controllers;

use App\Domains\Clan\Models\Clan;
use App\Domains\Og\Services\OgImageRenderer;
use App\Domains\Users\Models\User;
use App\Http\Controllers\Controller;
use App\Support\Concerns\ResolvesFromUrl;
use Illuminate\Http\Response;

/**
 * Превью ссылок для ботов Discord, Telegram, VK и т.д.
 *
 * SPA отдаёт пустой index.html, а боты JavaScript не выполняют.
 * nginx отправляет их запросы /user/* и /clan/* сюда (см. backend/deploy/nginx-og.conf):
 * отдаём HTML с og-тегами и картинку, нарисованную на сервере.
 */
class OgController extends Controller
{
    use ResolvesFromUrl;

    private const TIER_COLORS = [
        'S' => '#facc15', 'A' => '#fb923c', 'B' => '#a78bfa',
        'C' => '#22d3ee', 'D' => '#4ade80', 'E' => '#94a3b8',
    ];

    private const IMAGE_TTL = 3600;

    public function __construct(private OgImageRenderer $renderer) {}

    public function user(string $user): Response
    {
        $player = $this->player($user);
        $card = $this->userCard($player);

        return $this->page(
            title: "{$player->username} — {$card['subtitle']}",
            description: $card['description'],
            path: '/user/'.rawurlencode($player->username),
            image: url('/og/user/'.$player->id.'/image.png').'?v='.$player->updated_at?->timestamp,
        );
    }

    public function userImage(string $user): Response
    {
        $player = $this->player($user);
        $card = $this->userCard($player);

        return $this->png($this->renderer->render([
            'title' => $player->username,
            'lines' => $card['lines'],
            'badge' => $card['tier'],
            'badge_color' => self::TIER_COLORS[$card['tier']] ?? self::TIER_COLORS['E'],
            'footer' => $this->domain(),
        ]));
    }

    public function clan(string $clan): Response
    {
        $model = $this->clanModel($clan);
        $card = $this->clanCard($model);

        return $this->page(
            title: "[{$model->tag}] {$model->name} — клан APEX TIERS",
            description: $card['description'],
            path: '/clan/'.rawurlencode(str_replace(' ', '_', $model->name)),
            image: url('/og/clan/'.$model->id.'/image.png').'?v='.$model->updated_at?->timestamp,
        );
    }

    public function clanImage(string $clan): Response
    {
        $model = $this->clanModel($clan);
        $card = $this->clanCard($model);

        return $this->png($this->renderer->render([
            'title' => $model->name,
            'lines' => $card['lines'],
            'badge' => mb_strtoupper((string) $model->tag),
            'badge_color' => $model->banner_color ?: '#7c3aed',
            'footer' => $this->domain(),
        ]));
    }

    /* ----------------------------- Данные карточек ----------------------------- */

    private function userCard(User $player): array
    {
        $ranked = ! $player->isStaff() && $player->rating_points > 0;
        $tier = $ranked ? ($player->tier ?: 'E') : 'E';
        $score = (int) $player->tier_score;

        $position = $ranked
            ? User::query()->excludeStaff()->where('rating_points', '>', $player->rating_points)->count() + 1
            : null;

        $clan = $player->clanMember?->clan;

        $subtitle = $ranked ? "тир {$tier} · {$score}%" : 'игрок APEX TIERS';

        $lines = [];
        $lines[] = ['text' => $ranked ? "Тир {$tier} · {$score}% · #{$position} в рейтинге" : 'Ещё не проходил тир-тест'];
        if ($clan) {
            $lines[] = ['text' => "[{$clan->tag}] {$clan->name}", 'color' => $clan->banner_color ?: '#a78bfa'];
        }

        $description = $ranked
            ? "Тир {$tier} ({$score}%), #{$position} в рейтинге APEX TIERS".($clan ? ", клан [{$clan->tag}]" : '').'.'
            : 'Профиль игрока на APEX TIERS — рейтинге Minecraft PvP.';

        return compact('tier', 'subtitle', 'lines', 'description');
    }

    private function clanCard(Clan $clan): array
    {
        $members = $clan->members()->count();

        $lines = [
            ['text' => "Участников: {$members} · Мощь: {$clan->power}"],
            ['text' => "Побед: {$clan->wins} · Поражений: {$clan->losses}"],
        ];

        $description = trim((string) $clan->description) !== ''
            ? mb_strimwidth(trim($clan->description), 0, 180, '…')
            : "Клан [{$clan->tag}] на APEX TIERS: {$members} участников, {$clan->wins} побед.";

        return compact('lines', 'description');
    }

    /* ----------------------------- Ответы ----------------------------- */

    private function page(string $title, string $description, string $path, string $image): Response
    {
        $url = rtrim(config('app.frontend_url'), '/').$path;

        $html = view('og', [
            'title' => $title,
            'description' => $description,
            'url' => $url,
            'image' => $image,
        ])->render();

        return response($html)->header('Content-Type', 'text/html; charset=utf-8');
    }

    private function png(string $binary): Response
    {
        return response($binary)
            ->header('Content-Type', 'image/png')
            ->header('Cache-Control', 'public, max-age='.self::IMAGE_TTL);
    }

    private function domain(): string
    {
        return parse_url(config('app.frontend_url'), PHP_URL_HOST) ?: 'apex';
    }

    private function player(string $value): User
    {
        return $this->resolveFromUrl(User::class, $value, 'username', 'Игрок не найден.');
    }

    private function clanModel(string $value): Clan
    {
        $clan = $this->resolveFromUrl(Clan::class, $value, 'name', 'Клан не найден.', $this->underscoreVariants(trim(rawurldecode($value))));

        abort_if($clan->is_banned, 404);

        return $clan;
    }
}
