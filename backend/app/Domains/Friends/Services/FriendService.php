<?php

namespace App\Domains\Friends\Services;

use App\Domains\Friends\Models\Friendship;
use App\Domains\Users\Models\User;
use App\Domains\Friends\Notifications\FriendAcceptedNotification;
use App\Domains\Friends\Notifications\FriendRequestNotification;
use App\Support\Concerns\AbortsWithMessage;
use Illuminate\Support\Collection;
use App\Domains\Achievements\Services\AchievementService;

/**
 * Друзья: список, заявки, принятие, удаление.
 *
 * Раньше контроллер сам собирал три списка и трижды повторял сборку
 * payload игрока.
 */
class FriendService
{
    use AbortsWithMessage;

    /**
     * Списки друзей и заявок в обе стороны.
     *
     * @return array{friends: Collection, incoming: Collection, outgoing: Collection}
     */
    public function overview(User $me): array
    {
        $friends = Friendship::where('status', 'accepted')
            ->where(fn ($q) => $q->where('user_id', $me->id)->orWhere('friend_id', $me->id))
            ->with(['user', 'friend'])
            ->get()
            ->map(fn (Friendship $f) => $f->user_id === $me->id ? $f->friend : $f->user)
            ->filter()
            ->values();

        $incoming = Friendship::where('friend_id', $me->id)
            ->where('status', 'pending')
            ->with('user')
            ->latest()
            ->get();

        $outgoing = Friendship::where('user_id', $me->id)
            ->where('status', 'pending')
            ->with('friend')
            ->latest()
            ->get();

        return [
            'friends' => $friends,
            'incoming' => $incoming,
            'outgoing' => $outgoing,
        ];
    }

    /**
     * Отправить заявку в друзья.
     */
    public function request(User $me, User $target): Friendship
    {
        if ($target->id === $me->id) {
            $this->abortUnprocessable('Нельзя добавить себя.');
        }

        $existing = $this->between($me, $target)->first();

        if ($existing) {
            $this->abortUnprocessable('Заявка уже существует.');
        }

        $friendship = Friendship::create([
            'user_id' => $me->id,
            'friend_id' => $target->id,
            'status' => 'pending',
        ]);

        $target->notify(new FriendRequestNotification($me));

        return $friendship;
    }

    /**
     * Принять входящую заявку. Принять может только получатель.
     */
    public function accept(User $me, User $sender): Friendship
    {
        $friendship = Friendship::where('user_id', $sender->id)
            ->where('friend_id', $me->id)
            ->where('status', 'pending')
            ->firstOrFail();

        $friendship->update(['status' => 'accepted']);

        AchievementService::check($me);
        AchievementService::check($sender);

        $sender->notify(new FriendAcceptedNotification($me));

        return $friendship;
    }

    /**
     * Удалить связь в любую сторону.
     */
    public function remove(User $me, User $target): void
    {
        $this->between($me, $target)->delete();
    }

    /**
     * Связь между двумя игроками независимо от направления.
     */
    private function between(User $a, User $b)
    {
        return Friendship::where(function ($q) use ($a, $b) {
            $q->where('user_id', $a->id)->where('friend_id', $b->id);
        })->orWhere(function ($q) use ($a, $b) {
            $q->where('user_id', $b->id)->where('friend_id', $a->id);
        });
    }
}
