<?php

namespace App\Providers;

use App\Domains\Achievements\Models\Achievement;
use App\Domains\Achievements\Services\AchievementService;
use App\Domains\Activity\Observers\ActivityObserver;
use App\Domains\Bridge\Models\UserBridgeTechnique;
use App\Domains\Players\Models\ProfileRecommendation;
use App\Domains\Achievements\Models\UserAchievement;
use App\Domains\Chat\Models\Conversation;
use App\Domains\Chat\Models\Message;
use App\Domains\Clan\Models\Clan;
use App\Domains\Clan\Models\ClanApplication;
use App\Domains\Clan\Models\ClanEvent;
use App\Domains\Clan\Models\ClanResource;
use App\Domains\Clan\Models\ClanWar;
use App\Domains\Forum\Models\ForumAttachment;
use App\Domains\Forum\Models\ForumCategory;
use App\Domains\Forum\Models\ForumReply;
use App\Domains\Forum\Models\ForumTopic;
use App\Domains\Friends\Models\Friendship;
use App\Domains\News\Models\News;
use App\Domains\Shop\Models\ShopItem;
use App\Domains\Shop\Models\ShopSetting;
use App\Domains\Shop\Models\UserInventory;
use App\Domains\Tiers\Models\TierTest;
use App\Domains\Tournaments\Models\Tournament;
use App\Domains\Tournaments\Models\TournamentMatch;
use App\Domains\Tournaments\Models\TournamentParticipant;
use App\Domains\Tournaments\Models\TournamentWin;
use App\Domains\Users\Models\User;
use App\Domains\Wallet\Models\CoinTransaction;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        /*
         * Короткие имена для полиморфных связей.
         *
         * Поля вроде coin_transactions.reference_type раньше хранили полное
         * имя класса (App\Domains\Shop\Models\ShopItem). Теперь модели живут в доменах,
         * и такие строки пришлось бы переписывать при каждом переносе.
         * Псевдонимы отвязывают данные от пространства имён.
         *
         * Без enforceMorphMap: модели вне списка продолжают работать.
         */
        Relation::morphMap([
            'user' => User::class,
            'clan' => Clan::class,
            'clan_application' => ClanApplication::class,
            'clan_event' => ClanEvent::class,
            'clan_resource' => ClanResource::class,
            'clan_war' => ClanWar::class,
            'forum_topic' => ForumTopic::class,
            'forum_reply' => ForumReply::class,
            'forum_category' => ForumCategory::class,
            'forum_attachment' => ForumAttachment::class,
            'message' => Message::class,
            'conversation' => Conversation::class,
            'shop_item' => ShopItem::class,
            'shop_setting' => ShopSetting::class,
            'user_inventory' => UserInventory::class,
            'achievement' => Achievement::class,
            'user_achievement' => UserAchievement::class,
            'tier_test' => TierTest::class,
            'tournament' => Tournament::class,
            'tournament_match' => TournamentMatch::class,
            'tournament_participant' => TournamentParticipant::class,
            'news' => News::class,
            'friendship' => Friendship::class,
            'coin_transaction' => CoinTransaction::class,
        ]);

        $this->registerActivityFeed();
        $this->registerAchievementTriggers();
    }

    /**
     * Ачивки, условия которых меняются вне сервисов с явной проверкой:
     * форум, чат, магазин, турниры, рекомендации, рефералы, бридж.
     *
     * Каждое событие проверяет только свою группу условий — сообщение
     * в чате не должно пересчитывать клан и рейтинг.
     */
    private function registerAchievementTriggers(): void
    {
        $check = function (?int $userId, array $groups): void {
            $user = $userId ? User::find($userId) : null;

            if ($user) {
                AchievementService::check($user, $groups);
            }
        };

        ForumTopic::created(fn (ForumTopic $topic) => $check($topic->author_id, ['forum']));
        ForumReply::created(fn (ForumReply $reply) => $check($reply->author_id, ['forum']));
        Message::created(fn (Message $message) => $check($message->user_id, ['chat']));

        UserInventory::created(function (UserInventory $item) use ($check) {
            $check($item->user_id, ['shop']);
            $check($item->gifted_by, ['shop']);
        });

        TournamentParticipant::created(fn (TournamentParticipant $p) => $check($p->user_id, ['tournaments']));
        TournamentWin::created(fn (TournamentWin $win) => $check($win->user_id, ['tournaments']));
        ProfileRecommendation::created(fn (ProfileRecommendation $r) => $check($r->target_id, ['social']));

        UserBridgeTechnique::updated(function (UserBridgeTechnique $technique) use ($check) {
            if ($technique->wasChanged('status')) {
                $check($technique->user_id, ['bridge']);
            }
        });

        User::created(fn (User $user) => $check($user->referred_by, ['social']));

        User::updated(function (User $user) {
            $groups = array_keys(array_filter([
                'minecraft' => $user->wasChanged('minecraft_linked_at'),
                'bridge' => $user->wasChanged('bridge_rank_id'),
                'shop' => $user->wasChanged('apex_coins_spent'),
            ]));

            if ($groups) {
                AchievementService::check($user, $groups);
            }
        });
    }

    /**
     * Лента «Сейчас на APEX» слушает модели других доменов.
     *
     * Подписка собрана здесь, чтобы модели не знали о ленте: удалить
     * ленту — значит удалить этот метод, а не править пять моделей.
     */
    private function registerActivityFeed(): void
    {
        $observer = fn () => $this->app->make(ActivityObserver::class);

        TierTest::created(fn (TierTest $test) => $observer()->tierTestCreated($test));
        TierTest::updated(fn (TierTest $test) => $observer()->tierTestUpdated($test));
        Clan::created(fn (Clan $clan) => $observer()->clanCreated($clan));
        ClanWar::updated(fn (ClanWar $war) => $observer()->clanWarUpdated($war));
        TournamentWin::created(fn (TournamentWin $win) => $observer()->tournamentWinCreated($win));
        User::updated(fn (User $user) => $observer()->userUpdated($user));
    }
}
