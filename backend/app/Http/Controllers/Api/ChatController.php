<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Clan;
use App\Models\ClanMember;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Friendship;
use App\Models\Message;
use App\Models\MessageRead;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ChatController extends Controller
{
    /**
     * Список диалогов с последним сообщением и количеством непрочитанных.
     */
    public function index(Request $request): JsonResponse
    {
        $me = $request->user();

        $conversations = Conversation::query()
            ->whereHas('participants', fn ($q) => $q->where('user_id', $me->id))
            ->with([
                'users:id,username,avatar,tier,is_verified',
                'clan:id,name,tag,banner_color,avatar',
                'author:id,username,avatar,tier,is_verified',
                'lastMessage.user:id,username,avatar',
            ])
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        $conversations->each(function (Conversation $c) use ($me) {
            // Считаем непрочитанные: сообщения не от меня, по которым нет моего message_read
            $c->unread_count = Message::where('conversation_id', $c->id)
                ->where('user_id', '!=', $me->id)
                ->whereDoesntHave('reads', fn ($q) => $q->where('user_id', $me->id))
                ->count();

            // Сколько всего участников, кроме меня — для галочек прочтения
            $c->other_participants_count = $c->participants()
                ->where('user_id', '!=', $me->id)
                ->count();
        });

        return response()->json(['conversations' => $conversations]);
    }

    /**
     * История сообщений + пометка всех входящих как прочитанных.
     */
    public function show(Request $request, Conversation $conversation): JsonResponse
    {
        $me = $request->user();
        $this->authorizeParticipant($conversation, $me->id);

        // 1) Помечаем все входящие сообщения прочитанными
        $unreadIds = Message::where('conversation_id', $conversation->id)
            ->where('user_id', '!=', $me->id)
            ->whereDoesntHave('reads', fn ($q) => $q->where('user_id', $me->id))
            ->pluck('id');

        if ($unreadIds->isNotEmpty()) {
            $now = now();
            $rows = $unreadIds->map(fn ($id) => [
                'message_id' => $id,
                'user_id' => $me->id,
                'read_at' => $now,
            ])->all();

            MessageRead::insertOrIgnore($rows);
        }

        // 2) Обновляем last_read_at в participant (используется для unread count в index)
        ConversationParticipant::where('conversation_id', $conversation->id)
            ->where('user_id', $me->id)
            ->update(['last_read_at' => now()]);

        // 3) Грузим сообщения с reads
        $messages = $conversation->messages()
            ->with([
                'user:id,username,avatar,tier,is_verified',
                'reads:id,message_id,user_id',
                'replyTo.user:id,username,avatar',
                'forwardedFrom',
            ])
            ->orderBy('created_at')
            ->limit(200)
            ->get();

        return response()->json([
            'conversation' => $conversation->load([
                'users:id,username,avatar,tier,is_verified',
                'clan:id,name,tag,banner_color,avatar',
                'author:id,username,avatar,tier,is_verified',
            ]),
            'messages' => $messages,
            'other_participants_count' => $conversation->participants()
                ->where('user_id', '!=', $me->id)
                ->count(),
        ]);
    }

    /**
     * Переслать сообщение в другой диалог.
     */
    public function forward(Request $request, Message $message): JsonResponse
    {
        $me = $request->user();

        $this->authorizeParticipant($message->conversation, $me->id);

        $validated = $request->validate([
            'conversation_id' => ['required', 'integer', 'exists:conversations,id'],
        ]);

        $target = Conversation::findOrFail($validated['conversation_id']);

        $this->authorizeParticipant($target, $me->id);

        if ($target->id === $message->conversation_id) {
            throw ValidationException::withMessages([
                'conversation_id' => ['Нельзя переслать сообщение в тот же диалог.'],
            ]);
        }

        $forwarded = DB::transaction(function () use ($target, $me, $message) {
            $m = Message::create([
                'conversation_id' => $target->id,
                'user_id' => $me->id,
                'forwarded_from_user_id' => $message->user_id,
                'body' => $message->body,
            ]);

            $target->update(['last_message_at' => now()]);

            MessageRead::firstOrCreate([
                'message_id' => $m->id,
                'user_id' => $me->id,
            ], [
                'read_at' => now(),
            ]);

            ConversationParticipant::where('conversation_id', $target->id)
                ->where('user_id', $me->id)
                ->update(['last_read_at' => now()]);

            return $m;
        });

        broadcast(new \App\Events\MessageSent($forwarded))->toOthers();

        return response()->json([
            'message' => $forwarded->load([
                'user:id,username,avatar,tier,is_verified',
                'reads:id,message_id,user_id',
                'forwardedFrom',
            ]),
        ], 201);
    }

    /**
     * Поиск: пустой запрос — 10 друзей, 2+ символа — по юзерам и кланам.
     */
    public function search(Request $request): JsonResponse
    {
        $me = $request->user();
        $q = trim((string) $request->query('q', ''));

        if ($q === '') {
            $friendIds = Friendship::where('status', 'accepted')
                ->where(function ($sub) use ($me) {
                    $sub->where('user_id', $me->id)
                        ->orWhere('friend_id', $me->id);
                })
                ->get()
                ->map(fn ($f) => $f->user_id === $me->id ? $f->friend_id : $f->user_id)
                ->unique()
                ->take(10)
                ->values();

            $users = User::query()
                ->whereIn('id', $friendIds)
                ->select('id', 'username', 'avatar', 'tier', 'is_verified')
                ->orderByDesc('tier_score')
                ->get();

            return response()->json(['users' => $users, 'clans' => []]);
        }

        if (mb_strlen($q) < 2) {
            return response()->json(['users' => [], 'clans' => []]);
        }

        $users = User::query()
            ->where('id', '!=', $me->id)
            ->where('username', 'like', "%{$q}%")
            ->select('id', 'username', 'avatar', 'tier', 'is_verified')
            ->orderByDesc('tier_score')
            ->limit(10)
            ->get();

        $clans = Clan::query()
            ->where(function ($sub) use ($q) {
                $sub->where('name', 'like', "%{$q}%")
                    ->orWhere('tag', 'like', "%{$q}%");
            })
            ->select('id', 'name', 'tag', 'banner_color', 'avatar')
            ->limit(10)
            ->get();

        return response()->json([
            'users' => $users,
            'clans' => $clans,
        ]);
    }

    /**
     * Создать/получить direct-диалог.
     */
    public function startDirect(Request $request): JsonResponse
    {
        $me = $request->user();

        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        if ((int) $validated['user_id'] === $me->id) {
            throw ValidationException::withMessages([
                'user_id' => ['Нельзя начать диалог с самим собой.'],
            ]);
        }

        $conversation = DB::transaction(function () use ($me, $validated) {
            $c = Conversation::findOrCreateDirect($me->id, (int) $validated['user_id']);

            ConversationParticipant::firstOrCreate([
                'conversation_id' => $c->id,
                'user_id' => $me->id,
            ]);

            ConversationParticipant::firstOrCreate([
                'conversation_id' => $c->id,
                'user_id' => (int) $validated['user_id'],
            ]);

            return $c;
        });

        return response()->json([
            'conversation' => $conversation->load([
                'users:id,username,avatar,tier,is_verified',
            ]),
        ], 201);
    }

    /**
     * Создать/получить clan_message (обращение к лидеру и офицерам).
     */
    public function startClan(Request $request): JsonResponse
    {
        $me = $request->user();

        $validated = $request->validate([
            'clan_id' => ['required', 'integer', 'exists:clans,id'],
        ]);

        $clan = Clan::findOrFail($validated['clan_id']);

        $conversation = DB::transaction(function () use ($me, $clan) {
            $c = Conversation::firstOrCreate(
                [
                    'type' => 'clan_message',
                    'clan_id' => $clan->id,
                    'author_id' => $me->id,
                ],
                [
                    'last_message_at' => now(),
                ]
            );

            $participantIds = [$me->id];

            $staffIds = ClanMember::where('clan_id', $clan->id)
                ->whereIn('role', ['leader', 'officer'])
                ->pluck('user_id')
                ->all();

            $participantIds = array_unique(array_merge($participantIds, $staffIds));

            foreach ($participantIds as $uid) {
                ConversationParticipant::firstOrCreate([
                    'conversation_id' => $c->id,
                    'user_id' => $uid,
                ]);
            }

            return $c;
        });

        return response()->json([
            'conversation' => $conversation->load([
                'clan:id,name,tag,banner_color,avatar',
                'author:id,username,avatar,tier,is_verified',
            ]),
        ], 201);
    }

    /**
     * Отправить сообщение.
     */
    public function send(Request $request, Conversation $conversation): JsonResponse
    {
        $me = $request->user();

        $this->authorizeParticipant($conversation, $me->id);

        $validated = $request->validate([
            'body' => ['nullable', 'string', 'max:2000'],
            'reply_to_id' => ['nullable', 'integer', 'exists:messages,id'],
            'forwarded_from_user_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        if (empty($validated['body']) && empty($validated['forwarded_from_user_id'])) {
            throw ValidationException::withMessages([
                'body' => ['Сообщение не может быть пустым.'],
            ]);
        }

        // Проверяем, что оригинал из того же диалога
        if (!empty($validated['reply_to_id'])) {
            $original = Message::find($validated['reply_to_id']);

            if (!$original || $original->conversation_id !== $conversation->id) {
                throw ValidationException::withMessages([
                    'reply_to_id' => ['Нельзя ответить на сообщение из другого диалога.'],
                ]);
            }
        }

        $message = DB::transaction(function () use ($conversation, $me, $validated) {
            $m = Message::create([
                'conversation_id' => $conversation->id,
                'user_id' => $me->id,
                'reply_to_id' => $validated['reply_to_id'] ?? null,
                'forwarded_from_user_id' => $validated['forwarded_from_user_id'] ?? null,
                'body' => trim($validated['body'] ?? ''),
            ]);

            $conversation->update(['last_message_at' => now()]);

            MessageRead::firstOrCreate([
                'message_id' => $m->id,
                'user_id' => $me->id,
            ], [
                'read_at' => now(),
            ]);

            ConversationParticipant::where('conversation_id', $conversation->id)
                ->where('user_id', $me->id)
                ->update(['last_read_at' => now()]);

            return $m;
        });

        broadcast(new \App\Events\MessageSent($message))->toOthers();

        return response()->json([
            'message' => $message->load([
                'user:id,username,avatar,tier,is_verified',
                'reads:id,message_id,user_id',
                'replyTo.user:id,username,avatar',
                'forwardedFrom',
            ]),
        ], 201);
    }

    /**
     * Пометить конкретное сообщение прочитанным (для realtime).
     */
    public function markRead(Request $request, Message $message): JsonResponse
    {
        $me = $request->user();

        $this->authorizeParticipant($message->conversation, $me->id);

        if ($message->user_id === $me->id) {
            return response()->json(['ok' => true]);
        }

        MessageRead::firstOrCreate([
            'message_id' => $message->id,
            'user_id' => $me->id,
        ], [
            'read_at' => now(),
        ]);

        return response()->json(['ok' => true]);
    }

    /**
     * Общий счётчик непрочитанных.
     */
    public function unreadCount(Request $request): JsonResponse
    {
        $me = $request->user();

        $conversationIds = ConversationParticipant::where('user_id', $me->id)
            ->pluck('conversation_id');

        $unread = Message::whereIn('conversation_id', $conversationIds)
            ->where('user_id', '!=', $me->id)
            ->whereDoesntHave('reads', fn ($q) => $q->where('user_id', $me->id))
            ->count();

        return response()->json(['unread_count' => $unread]);
    }

    /**
     * Проверка, что юзер — участник диалога.
     */
    private function authorizeParticipant(Conversation $conversation, int $userId): void
    {
        $exists = ConversationParticipant::where('conversation_id', $conversation->id)
            ->where('user_id', $userId)
            ->exists();

        abort_unless($exists, 403, 'Нет доступа к этому диалогу.');
    }
}
