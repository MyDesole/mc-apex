<?php

namespace App\Providers;

use App\Domains\Clan\Models\Clan;
use App\Domains\Clan\Models\ClanForumTopic;
use App\Domains\Clan\Models\ClanResource;
use App\Domains\Chat\Models\Conversation;
use App\Domains\Forum\Models\ForumReply;
use App\Domains\Forum\Models\ForumTopic;
use App\Domains\Chat\Models\Message;
use App\Domains\Chat\Models\MessageAttachment;
use App\Domains\Clan\Policies\ClanPolicy;
use App\Domains\Clan\Policies\ClanForumTopicPolicy;
use App\Domains\Clan\Policies\ClanResourcePolicy;
use App\Domains\Chat\Policies\ConversationPolicy;
use App\Domains\Forum\Policies\ForumReplyPolicy;
use App\Domains\Forum\Policies\ForumTopicPolicy;
use App\Domains\Chat\Policies\MessageAttachmentPolicy;
use App\Domains\Chat\Policies\MessagePolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

/**
 * Регистрация политик.
 *
 * Laravel умеет находить политики по имени (Model -> ModelPolicy), но здесь
 * они названы явно: Conversation -> ConversationPolicy, ForumTopic ->
 * ForumTopicPolicy, поэтому связь очевидна и не зависит от догадок.
 */
class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        Conversation::class => ConversationPolicy::class,
        Message::class => MessagePolicy::class,
        MessageAttachment::class => MessageAttachmentPolicy::class,
        ForumTopic::class => ForumTopicPolicy::class,
        ForumReply::class => ForumReplyPolicy::class,
        ClanResource::class => ClanResourcePolicy::class,
        ClanForumTopic::class => ClanForumTopicPolicy::class,
        Clan::class => ClanPolicy::class,
    ];

    public function boot(): void
    {
        //
    }
}
