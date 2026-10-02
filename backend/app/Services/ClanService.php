<?php

namespace App\Services;

use App\Models\Clan;
use App\Models\ClanApplication;
use App\Models\ClanMember;
use App\Models\ClanWar;
use App\Models\CoinTransaction;
use App\Models\User;
use App\Notifications\ClanApplicationAcceptedNotification;
use App\Notifications\ClanApplicationDeclinedNotification;
use App\Notifications\ClanApplicationNotification;
use App\Services\Concerns\AbortsWithMessage;
use App\Services\Concerns\StoresUserFiles;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Кланы: создание, профиль, заявки, участники, медиа.
 *
 * Контроллер принимает запрос и проверяет права; правила, транзакции
 * и уведомления — здесь.
 */
class ClanService
{
    use StoresUserFiles;
    use AbortsWithMessage;

    public const PER_PAGE = 20;
    public const TOP_LIMIT = 10;

    /* ----------------------------- Списки ----------------------------- */

    /**
     * Список кланов по силе с числом участников.
     */
    public function list(?string $search, ?User $me = null): LengthAwarePaginator
    {
        // id своего клана: фронтенд по нему ведёт клик во вкладку «Мой клан»
        $myClanId = $me?->clanMember?->clan_id;

        $clans = Clan::query()
            ->with('leader:id,username,avatar')
            ->when($search, function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('tag', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('power')
            ->paginate(self::PER_PAGE);

        $counts = $this->memberCounts($clans->pluck('id'));

        $clans->getCollection()->transform(function (Clan $clan) use ($counts, $myClanId) {
            $clan->members_count = (int) ($counts[$clan->id] ?? 0);
            $clan->my_clan_id = $clan->id === $myClanId ? $myClanId : null;

            return $clan;
        });

        return $clans;
    }

    /**
     * Топ кланов для главной.
     */
    public function top(): Collection
    {
        $clans = Clan::query()
            ->with('leader:id,username,avatar')
            ->orderByDesc('power')
            ->limit(self::TOP_LIMIT)
            ->get();

        $counts = $this->memberCounts($clans->pluck('id'));

        return $clans->map(fn (Clan $clan) => [
            'id' => $clan->id,
            'name' => $clan->name,
            'tag' => $clan->tag,
            'avatar' => $clan->avatar,
            'banner_color' => $clan->banner_color,
            'power' => $clan->power,
            'wins' => $clan->wins,
            'losses' => $clan->losses,
            'members_count' => (int) ($counts[$clan->id] ?? 0),
            'leader' => $clan->leader,
        ]);
    }

    /**
     * Число участников одним запросом на все кланы — без N+1.
     */
    private function memberCounts(Collection $clanIds): Collection
    {
        if ($clanIds->isEmpty()) {
            return collect();
        }

        return ClanMember::query()
            ->selectRaw('clan_id, count(*) as total')
            ->whereIn('clan_id', $clanIds)
            ->groupBy('clan_id')
            ->pluck('total', 'clan_id');
    }

    /* ----------------------------- Профиль клана ----------------------------- */

    /**
     * Страница клана: состав, события, войны и статус текущего пользователя.
     */
    public function view(Clan $clan, ?User $me): array
    {
        $clan->load([
            'leader:id,username,avatar,tier',
            'members.user:id,username,avatar,tier',
            'events.author:id,username,avatar',
        ]);

        return [
            'clan' => $clan,
            'is_member' => $me ? $clan->isMember($me->id) : false,
            'my_clan_id' => $me?->clanMember?->clan_id,
            'application' => $me ? $this->pendingApplication($clan, $me) : null,
            'members_count' => $clan->members()->count(),
            'incoming_wars' => $this->wars($clan, 'opponent_clan_id'),
            'outgoing_wars' => $this->wars($clan, 'challenger_clan_id'),
        ];
    }

    private function wars(Clan $clan, string $column): Collection
    {
        return ClanWar::where($column, $clan->id)
            ->whereIn('status', ['pending', 'accepted'])
            ->with([
                'challenger:id,name,tag,power,banner_color,avatar',
                'opponent:id,name,tag,power,banner_color,avatar',
                'participants.user:id,username,avatar,tier',
                'participants.clan:id,name,tag,banner_color',
            ])
            ->latest()
            ->get();
    }

    private function pendingApplication(Clan $clan, User $me): ?ClanApplication
    {
        return ClanApplication::where('clan_id', $clan->id)
            ->where('user_id', $me->id)
            ->where('status', 'pending')
            ->first();
    }

    /* ----------------------------- Создание и правка ----------------------------- */

    public function create(User $leader, array $data): Clan
    {
        if ($leader->clanMember) {
            $this->abortUnprocessable('Вы уже в клане.');
        }

        return DB::transaction(function () use ($leader, $data) {
            $clan = Clan::create([
                ...$data,
                'leader_id' => $leader->id,
                'banner_color' => $data['banner_color'] ?? '#7c3aed',
                'is_open' => $data['is_open'] ?? true,
            ]);

            ClanMember::create([
                'clan_id' => $clan->id,
                'user_id' => $leader->id,
                'role' => 'leader',
                'joined_at' => now(),
            ]);

            $leader->update(['clan_joined_at' => now()]);

            AchievementService::check($leader);

            return $clan;
        });
    }

    /**
     * Обновление профиля клана, в том числе аватар и обложка.
     */
    public function update(Clan $clan, array $data, array $files = []): Clan
    {
        if (isset($files['avatar'])) {
            $this->deleteStoredFile($clan->avatar);
            $data['avatar'] = $files['avatar']->store("clans/{$clan->id}", 'public');
        }

        if (isset($files['cover'])) {
            $this->deleteStoredFile($clan->cover_path);
            $data['cover_path'] = $files['cover']->store("clans/{$clan->id}/covers", 'public');
        }

        // Ключа 'cover' в таблице нет — есть cover_path
        unset($data['cover']);

        $clan->update($data);

        return $clan->fresh();
    }

    public function removeAvatar(Clan $clan): Clan
    {
        if ($clan->avatar) {
            $this->deleteStoredFile($clan->avatar);
            $clan->update(['avatar' => null]);
        }

        return $clan->fresh();
    }

    public function removeCover(Clan $clan): Clan
    {
        if ($clan->cover_path) {
            $this->deleteStoredFile($clan->cover_path);
            $clan->update(['cover_path' => null]);
        }

        return $clan->fresh();
    }

    /* ----------------------------- Заявки ----------------------------- */

    /**
     * Подать заявку. Повторная заявка после отказа переиспользует запись.
     */
    public function apply(Clan $clan, User $user, ?string $message): ClanApplication
    {
        if ($user->clanMember) {
            $this->abortUnprocessable('Вы уже в клане.');
        }

        if (! $clan->is_open) {
            $this->abortUnprocessable('Клан закрыт для вступления.');
        }

        $existing = ClanApplication::where('clan_id', $clan->id)
            ->where('user_id', $user->id)
            ->first();

        if ($existing && $existing->status === 'pending') {
            $this->abortUnprocessable('Заявка уже отправлена.');
        }

        $application = ClanApplication::updateOrCreate(
            ['clan_id' => $clan->id, 'user_id' => $user->id],
            ['message' => $message, 'status' => 'pending']
        );

        $clan->leader?->notify(new ClanApplicationNotification($clan, $user));

        return $application;
    }

    /**
     * Очередь заявок клана на рассмотрение.
     */
    public function applications(Clan $clan): Collection
    {
        return ClanApplication::where('clan_id', $clan->id)
            ->where('status', 'pending')
            ->with('user:id,username,avatar,tier,tier_score')
            ->latest()
            ->get();
    }

    /**
     * Принять заявку: игрок становится участником клана.
     */
    public function acceptApplication(Clan $clan, ClanApplication $application): void
    {
        if ($clan->members()->count() >= $clan->max_members) {
            $this->abortUnprocessable('Клан заполнен.');
        }

        $applicant = $application->user;

        // Плату берём при принятии, а не при подаче: иначе заявитель мог бы
        // заморозить монеты, разослав заявки во все кланы.
        if ($clan->entry_fee > 0 && $applicant && ! CoinService::canAfford($applicant, $clan->entry_fee)) {
            $this->abortUnprocessable(
                "Для вступления нужна плата {$clan->entry_fee} ApexCoin. "
                . "У игрока на балансе {$applicant->apex_coins}."
            );
        }

        DB::transaction(function () use ($clan, $application, $applicant) {
            // Списываем у заявителя и передаём лидеру
            if ($clan->entry_fee > 0 && $applicant) {
                CoinService::debit(
                    $applicant,
                    $clan->entry_fee,
                    CoinTransaction::SOURCE_CLAN_FEE,
                    "Вступление в клан «{$clan->name}»",
                    ['reference_type' => Clan::class, 'reference_id' => $clan->id]
                );

                if ($clan->leader && $clan->leader->id !== $applicant->id) {
                    CoinService::credit(
                        $clan->leader,
                        $clan->entry_fee,
                        CoinTransaction::SOURCE_CLAN_FEE,
                        "Плата за вступление: {$applicant->username}",
                        null,
                        ['reference_type' => Clan::class, 'reference_id' => $clan->id]
                    );
                }
            }

            ClanMember::create([
                'clan_id' => $clan->id,
                'user_id' => $application->user_id,
                'role' => 'member',
                'joined_at' => now(),
            ]);

            if ($applicant) {
                AchievementService::check($applicant);
                $applicant->update(['clan_joined_at' => now()]);
            }

            $application->update(['status' => 'accepted']);
            $clan->recalculatePower();
        });

        // Заявитель мог вступить в другой клан — тогда заявки больше неактуальны
        ClanApplication::where('user_id', $application->user_id)
            ->where('id', '!=', $application->id)
            ->where('status', 'pending')
            ->update(['status' => 'declined']);

        $application->user?->notify(new ClanApplicationAcceptedNotification($clan));
    }

    public function declineApplication(ClanApplication $application): void
    {
        $application->update(['status' => 'declined']);

        $application->user?->notify(new ClanApplicationDeclinedNotification($application->clan));
    }

    /* ----------------------------- Участники ----------------------------- */

    /**
     * Выйти из клана.
     *
     * Лидер с участниками выйти не может — сначала нужно передать
     * лидерство (кнопка с короной в списке участников). Но лидер,
     * оставшийся один, распускает клан: передавать лидерство некому,
     * иначе он оказался бы заперт в клане навсегда.
     */
    public function leave(Clan $clan, User $user): void
    {
        $membership = ClanMember::where('clan_id', $clan->id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        if ($membership->role === 'leader') {
            $others = ClanMember::where('clan_id', $clan->id)
                ->where('user_id', '!=', $user->id)
                ->count();

            if ($others > 0) {
                $this->abortUnprocessable(
                    'Лидер не может покинуть клан. Передайте лидерство участнику '
                    . '— кнопка с короной в списке участников.'
                );
            }

            $this->dissolve($clan);

            return;
        }

        DB::transaction(function () use ($clan, $user, $membership) {
            $membership->delete();
            $user->update(['clan_joined_at' => null]);

            $clan->recalculatePower();
        });
    }

    /**
     * Распустить клан: удаляем его вместе со связанным содержимым.
     *
     * Большинство таблиц ссылаются на clans через cascadeOnDelete,
     * поэтому достаточно удалить клан. Но два места каскадом не покрыты:
     * favorite_clan_id у пользователей (nullOnDelete) и clan_joined_at
     * у вышедших участников — их чистим явно.
     */
    public function dissolve(Clan $clan): void
    {
        DB::transaction(function () use ($clan) {
            $memberIds = ClanMember::where('clan_id', $clan->id)->pluck('user_id');

            if ($memberIds->isNotEmpty()) {
                User::whereIn('id', $memberIds)->update(['clan_joined_at' => null]);
            }

            // Витрина профиля не должна ссылаться на удалённый клан
            User::where('favorite_clan_id', $clan->id)->update(['favorite_clan_id' => null]);

            $clan->delete();
        });
    }

    /**
     * Кикнуть участника. Если игрока в клане нет — это не ошибка.
     */
    public function kick(Clan $clan, User $target): void
    {
        if ($target->id === $clan->leader_id) {
            $this->abortUnprocessable('Нельзя кикнуть лидера.');
        }

        ClanMember::where('clan_id', $clan->id)
            ->where('user_id', $target->id)
            ->delete();

        $target->update(['clan_joined_at' => null]);

        $clan->recalculatePower();
    }

    /* ----------------------------- Роли внутри клана ----------------------------- */

    /**
     * Повысить или понизить участника. Доступно только лидеру.
     */
    public function updateMemberRole(Clan $clan, User $actor, User $target, array $data): ClanMember
    {
        if ($target->id === $actor->id) {
            $this->abortUnprocessable('Нельзя менять свою роль.');
        }

        $member = ClanMember::where('clan_id', $clan->id)
            ->where('user_id', $target->id)
            ->firstOrFail();

        $member->update([
            'role' => $data['role'],
            'permissions' => $data['permissions'] ?? null,
            'title' => $data['title'] ?? null,
            'promoted_at' => now(),
            'promoted_by' => $actor->id,
        ]);

        return $member->fresh()->load('user:id,username,avatar');
    }

    /**
     * Передать лидерство: старый лидер становится офицером.
     */
    public function transferLeadership(Clan $clan, User $actor, User $target): void
    {
        if ($target->id === $actor->id) {
            $this->abortUnprocessable('Нельзя передать лидерство самому себе.');
        }

        $newLeaderMember = ClanMember::where('clan_id', $clan->id)
            ->where('user_id', $target->id)
            ->firstOrFail();

        DB::transaction(function () use ($clan, $actor, $target, $newLeaderMember) {
            $oldLeaderMember = ClanMember::where('clan_id', $clan->id)
                ->where('user_id', $actor->id)
                ->firstOrFail();

            $oldLeaderMember->update(['role' => 'officer']);
            $newLeaderMember->update(['role' => 'leader']);
            $clan->update(['leader_id' => $target->id]);
        });
    }

    /**
     * Заявка обязана относиться к этому клану.
     */
    public function assertApplicationBelongsToClan(Clan $clan, ClanApplication $application): void
    {
        abort_if($application->clan_id !== $clan->id, 404);
    }

    /**
     * Руководство клана (лидер или офицер).
     */
    public function isStaff(Clan $clan, int $userId): bool
    {
        return ClanMember::where('clan_id', $clan->id)
            ->where('user_id', $userId)
            ->whereIn('role', ['leader', 'officer'])
            ->exists();
    }
}
