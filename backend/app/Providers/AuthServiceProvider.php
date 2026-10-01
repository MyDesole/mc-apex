<?php

namespace App\Providers;

use App\Models\Clan;
use App\Models\ClanForumTopic;
use App\Models\ClanResource;
use App\Models\Conversation;
use App\Models\ForumReply;
use App\Models\ForumTopic;
use App\Models\Message;
use App\Models\MessageAttachment;
use App\Policies\ClanPolicy;
use App\Policies\ClanForumTopicPolicy;
use App\Policies\ClanResourcePolicy;
use App\Policies\ConversationPolicy;
use App\Policies\ForumReplyPolicy;
use App\Policies\ForumTopicPolicy;
use App\Policies\MessageAttachmentPolicy;
use App\Policies\MessagePolicy;
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
