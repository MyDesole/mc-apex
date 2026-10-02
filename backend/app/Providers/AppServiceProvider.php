<?php

namespace App\Providers;

use App\Domains\Achievements\Models\Achievement;
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
    }
}
