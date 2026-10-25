<?php

namespace App\Domains\Moderation\Controllers;

use App\Domains\Auth\Services\PasswordResetService;
use App\Domains\Moderation\Models\ModerationLog;
use App\Domains\Users\Models\User;
use App\Domains\Wallet\Models\CoinTransaction;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Действия персонала с аккаунтом игрока из досье.
 *
 * Чистка профиля и заметки — модератору и админу; ник, пароль, почта,
 * сессии и Minecraft — только админу (маршруты разнесены по группам).
 * Каждое действие пишется в журнал модерации.
 */
class PlayerAdminController extends Controller
{
    /* ----------------------------- Модератор и админ ----------------------------- */

    public function removeAvatar(Request $request, User $user): JsonResponse
    {
        $this->guardTarget($request, $user);
        $this->deleteFile($user->avatar);
        $user->forceFill(['avatar' => null])->save();

        ModerationLog::record($request->user(), 'user.avatar_removed', $user->id);

        return response()->json(['ok' => true]);
    }

    public function removeCover(Request $request, User $user): JsonResponse
    {
        $this->guardTarget($request, $user);
        $this->deleteFile($user->cover_path);
        $user->forceFill(['cover_path' => null])->save();

        ModerationLog::record($request->user(), 'user.cover_removed', $user->id);

        return response()->json(['ok' => true]);
    }

    /** Стереть «о себе», статус и цитату — за оскорбления или рекламу. */
    public function clearProfile(Request $request, User $user): JsonResponse
    {
        $this->guardTarget($request, $user);
        $user->forceFill(['bio' => null, 'status' => null, 'quote' => null])->save();

        ModerationLog::record($request->user(), 'user.profile_cleared', $user->id);

        return response()->json(['ok' => true]);
    }

    /** Внутренняя заметка персонала об игроке — видна только в досье и журнале. */
    public function note(Request $request, User $user): JsonResponse
    {
        $data = $request->validate(['text' => ['required', 'string', 'max:1000']]);

        ModerationLog::record($request->user(), 'user.note', $user->id, ['note' => $data['text']]);

        return response()->json(['ok' => true]);
    }

    /* ----------------------------- Только админ ----------------------------- */

    public function rename(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'username' => [
                'required', 'string', 'min:3', 'max:32', 'regex:/^[a-zA-Z0-9_]+$/',
                Rule::unique('users', 'username')->ignore($user->id),
            ],
        ], [
            'username.regex' => 'Ник может содержать только латинские буквы, цифры и подчёркивание.',
            'username.unique' => 'Такой ник уже занят.',
        ]);

        $from = $user->username;
        $user->forceFill(['username' => $data['username']])->save();

        ModerationLog::record($request->user(), 'user.rename', $user->id, ['from' => $from, 'to' => $user->username]);

        return response()->json(['user' => ['id' => $user->id, 'username' => $user->username]]);
    }

    /** Письмо с кодом сброса — обычный путь «Забыли пароль», запущенный админом. */
    public function sendPasswordReset(Request $request, User $user, PasswordResetService $passwords): JsonResponse
    {
        abort_unless($user->email, 422, 'У игрока нет почты.');

        $passwords->sendCode($user->email);

        ModerationLog::record($request->user(), 'user.password_reset_sent', $user->id);

        return response()->json(['ok' => true]);
    }

    /**
     * Временный пароль, если до почты не добраться. Показывается админу
     * один раз, все сессии игрока завершаются.
     */
    public function temporaryPassword(Request $request, User $user): JsonResponse
    {
        $this->guardTarget($request, $user);

        $password = Str::lower(Str::random(4).'-'.Str::random(4).'-'.Str::random(4));

        $user->forceFill(['password' => Hash::make($password), 'remember_token' => null])->save();
        $this->revokeSessions($user);

        ModerationLog::record($request->user(), 'user.password_set', $user->id);

        return response()->json(['password' => $password]);
    }

    public function verifyEmail(Request $request, User $user): JsonResponse
    {
        abort_if($user->email_verified_at, 422, 'Почта уже подтверждена.');

        $user->forceFill(['email_verified_at' => now()])->save();

        ModerationLog::record($request->user(), 'user.email_verified', $user->id);

        return response()->json(['ok' => true]);
    }

    public function logoutEverywhere(Request $request, User $user): JsonResponse
    {
        $this->guardTarget($request, $user);
        $user->forceFill(['remember_token' => null])->save();
        $this->revokeSessions($user);

        ModerationLog::record($request->user(), 'user.sessions_revoked', $user->id);

        return response()->json(['ok' => true]);
    }

    public function unlinkMinecraft(Request $request, User $user): JsonResponse
    {
        abort_unless($user->minecraft_username, 422, 'Minecraft-аккаунт не привязан.');

        $from = $user->minecraft_username;
        $user->forceFill(['minecraft_uuid' => null, 'minecraft_username' => null, 'minecraft_linked_at' => null])->save();

        ModerationLog::record($request->user(), 'user.minecraft_unlinked', $user->id, ['from' => $from]);

        return response()->json(['ok' => true]);
    }

    /* ----------------------------- Рефералы ----------------------------- */

    /** Кто кого привёл: итоги, топ пригласивших и свежие приглашения. */
    public function referrals(): JsonResponse
    {
        $earned = CoinTransaction::where('source', CoinTransaction::SOURCE_REFERRAL)
            ->where('amount', '>', 0)
            ->selectRaw('user_id, SUM(amount) as total')
            ->groupBy('user_id')
            ->pluck('total', 'user_id');

        $top = User::query()
            ->whereIn('id', User::whereNotNull('referred_by')->select('referred_by'))
            ->withCount('referrals')
            ->orderByDesc('referrals_count')
            ->limit(20)
            ->get(['id', 'username', 'avatar', 'referral_code', 'is_banned'])
            ->map(fn (User $u) => [
                'id' => $u->id,
                'username' => $u->username,
                'avatar_url' => $u->avatar_url,
                'is_banned' => (bool) $u->is_banned,
                'invited' => $u->referrals_count,
                // Сколько из приглашённых реально играют — прошли тир-тест
                'invited_active' => User::where('referred_by', $u->id)->where('tier_score', '>', 0)->count(),
                'coins_earned' => (int) ($earned[$u->id] ?? 0),
            ]);

        $recent = User::query()
            ->whereNotNull('referred_by')
            ->with('referrer:id,username,avatar')
            ->latest()
            ->limit(30)
            ->get(['id', 'username', 'avatar', 'referred_by', 'created_at', 'tier', 'tier_score', 'is_banned', 'last_seen_at'])
            ->map(fn (User $u) => [
                'id' => $u->id,
                'username' => $u->username,
                'avatar_url' => $u->avatar_url,
                'created_at' => $u->created_at,
                'last_seen_at' => $u->last_seen_at,
                'tier' => $u->tier,
                'tested' => (float) $u->tier_score > 0,
                'is_banned' => (bool) $u->is_banned,
                'referrer' => $u->referrer ? ['id' => $u->referrer->id, 'username' => $u->referrer->username] : null,
            ]);

        return response()->json([
            'totals' => [
                'invited' => User::whereNotNull('referred_by')->count(),
                'invited_week' => User::whereNotNull('referred_by')->where('created_at', '>=', now()->subDays(7))->count(),
                'referrers' => User::whereNotNull('referred_by')->distinct('referred_by')->count('referred_by'),
                'coins_paid' => (int) $earned->sum(),
            ],
            'top' => $top,
            'recent' => $recent,
        ]);
    }

    /* ----------------------------- Помощники ----------------------------- */

    /** Себя и администратора так трогать нельзя; персонал — только админу. */
    private function guardTarget(Request $request, User $user): void
    {
        $actor = $request->user();

        abort_if($user->id === $actor->id && ! $actor->isAdmin(), 422, 'Это действие нельзя применить к себе.');
        abort_if($user->isAdmin() && $user->id !== $actor->id, 403, 'Нельзя менять аккаунт администратора.');
        abort_if($user->isStaff() && ! $actor->isAdmin(), 403, 'Аккаунт персонала меняет только администратор.');
    }

    private function deleteFile(?string $path): void
    {
        if ($path && ! Str::startsWith($path, ['http://', 'https://'])) {
            Storage::disk('public')->delete($path);
        }
    }

    private function revokeSessions(User $user): void
    {
        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
        }

        if (method_exists($user, 'tokens')) {
            $user->tokens()->delete();
        }
    }
}
