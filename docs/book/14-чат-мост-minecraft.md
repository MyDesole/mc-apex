## Реальное время: чат, каналы и мост к Minecraft-серверу

Транспорт — Laravel Reverb (протокол Pusher). Внутри доменов нет ни одной очереди на рассылку: все события реализуют `ShouldBroadcastNow`, поэтому HTTP-запрос отправки сообщения не завершается, пока событие не отдано в Reverb. Ниже — только то, что есть в коде; где чего-то нет, это сказано прямо.

### 17.1. Что в проекте называется «бриджем»

Первое, что нужно зафиксировать, потому что название обманывает: `backend/app/Domains/Bridge/` (26 файлов) — это не сетевой мост к игровым серверам. Это домен **бридж-тестов**: каталог видов бриджа (speedbridge, godbridge, telly), заявки игроков с видео, проверка бридж-тестером, звания бриджера и очки в топ.

Мост «сайт ↔ Minecraft-сервер» — это два других места:

| Что | Где | Роль |
|---|---|---|
| Приём запросов от плагина сервера | `backend/app/Domains/Minecraft/` + `EnsureMinecraftServer` | единственный вход с игрового сервера |
| Общий секрет сервера | `config/services.php` → `minecraft.server_key`, заголовок `X-Minecraft-Server-Key` | аутентификация плагина |
| Статус сервера (онлайн, игроки, версия) | `MinecraftStatusService` (Server List Ping) | сайт сам опрашивает сервер по TCP |
| Скин игрока | `MinecraftSkinService` (Mojang API) | сайт сам ходит к Mojang |

Соответственно в домене `Bridge` **в коде отсутствует** идентификация серверов, ключи, heartbeat, синхронизация игроков и статус онлайн. Всё это — в `Minecraft`, раздел 17.14.

### 17.2. Транспорт: конфигурация Reverb и подключение фронтенда

`config/broadcasting.php` — стандартная заготовка Laravel, из неё реально используется одно соединение:

| Ключ | Значение в коде | Комментарий |
|---|---|---|
| `default` | `env('BROADCAST_CONNECTION', 'null')` | в `backend/.env.example` стоит `BROADCAST_CONNECTION=log`, в проде — `reverb` |
| `connections.reverb.driver` | `reverb` | |
| `key` / `secret` / `app_id` | `REVERB_APP_KEY` / `REVERB_APP_SECRET` / `REVERB_APP_ID` | те же значения, что в приложении Reverb |
| `options.host` | `REVERB_HOST` | |
| `options.port` | `REVERB_PORT`, по умолчанию `443` | |
| `options.scheme` / `useTLS` | `REVERB_SCHEME` (по умолчанию `https`), `useTLS = scheme === 'https'` | |

`config/reverb.php` — сервер и приложение:

| Параметр | Значение | Смысл в проекте |
|---|---|---|
| `servers.reverb.host` / `port` | `REVERB_SERVER_HOST` (0.0.0.0) / `REVERB_SERVER_PORT` (8080) | куда биндится процесс `reverb:start` |
| `servers.reverb.max_request_size` | `REVERB_MAX_REQUEST_SIZE`, 10 000 байт | лимит входящего сообщения |
| `servers.reverb.scaling.enabled` | `false` по умолчанию, Redis при включении | горизонтальное масштабирование не включено |
| `apps.provider` | `config` | одно приложение из переменных окружения |
| `apps[0].allowed_origins` | `['*']` | CORS сокета открыт |
| `apps[0].ping_interval` / `activity_timeout` | 60 / 30 секунд | |
| `apps[0].max_message_size` | `REVERB_APP_MAX_MESSAGE_SIZE`, 10 000 байт | **меньше**, чем лимит текста сообщения (5 000 символов), см. 17.11 |
| `apps[0].accept_client_events_from` | `members` | клиентские события разрешены, но фронтенд их не шлёт: `whisper` в `frontend/src` отсутствует |
| `apps[0].rate_limiting.enabled` | `false` по умолчанию | rate limit сокета выключен |

Фронтенд подключается в `frontend/src/echo.js`. Ключевое: адрес и порт задаются всегда, включая https, иначе pusher-js строит `wss://host:80`; транспорт не перечисляется вручную (иначе соединение сразу падало в `failed`):

```js
// frontend/src/echo.js, фрагмент options()
return {
  broadcaster: 'reverb',
  key: KEY,
  wsHost: host,
  wsPort: port,
  wssPort: port,          // оба порта одинаковые: иначе wss уйдёт на порт 80
  forceTLS: secure,
  authEndpoint: '/broadcasting/auth',
  activityTimeout: 30000,
}
```

Сверху — сторож соединения (`startWatchdog`, интервал 15 с): состояния `failed`, `unavailable`, `disconnected` и «залипший» `connecting` дольше 45 с приводят к `resetEcho()` — полному пересозданию Echo. Пересоздание увеличивает реактивный счётчик `echoGeneration`, на который подписаны все подписки в приложении, поэтому они восстанавливаются сами, без перезагрузки страницы. Дополнительно `resetEcho()` вызывается по `online` и по возврату вкладки из фона.

Роут `/broadcasting/auth` framework регистрирует сам (`withRouting(channels: ...)` в `bootstrap/app.php`), CSRF для него исключён явно, API работает в `statefulApi()` — авторизация канала идёт по Sanctum-cookie.

### 17.3. Каналы вебсокетов

`backend/routes/channels.php` — 21 строка, два канала. Это весь список:

```php
// backend/routes/channels.php
Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('online', function ($user) {
    return [
        'id' => (int) $user->id,
        'username' => $user->username,
    ];
});
```

| Канал | Тип | Кто может подписаться | Что приходит |
|---|---|---|---|
| `App.Models.User.{id}` | private | только сам пользователь (`(int) $user->id === (int) $id`) | все события чата и уведомлений: `message.sent`, `messages.read`, `user.typing`, `conversation.changed`, `notification.created` |
| `online` | presence | любой авторизованный | список участников канала: `{id, username}`; вход/выход видны всем подписчикам |
| `activity` | public | кто угодно, авторизация не нужна | `activity.recorded` — лента «Сейчас на APEX» |

Три следствия, важных для архитектуры:

1. **Канала на диалог нет.** Ни `conversation.{id}`, ни `chat.{id}` в `channels.php` не объявлено. Приватность обеспечивается тем, что событие копируется в личный канал каждого участника (см. 17.6), а не подпиской на общий канал диалога.
2. **`activity` в `channels.php` отсутствует** — публичные каналы не проходят авторизацию, имя задано константой `ActivityRecorded::CHANNEL = 'activity'`.
3. Канал `online` отдаёт `username` всем подписчикам — это осознанно (ник и так виден в профилях), но список онлайна фактически публичен внутри авторизованной части сайта.

### 17.4. Модель чата: три вида бесед

`Conversation` (`backend/app/Domains/Chat/Models/Conversation.php`) различает типы строкой, а не enum: миграция `2026_11_06_100000_chat_groups_gifts_voice.php` перевела `enum('direct','clan_message')` в `string(20)`, потому что значение `group` в enum не влезало.

| Тип | Как создаётся | Участники | Особенности |
|---|---|---|---|
| `direct` | `POST /api/chat/start-direct` → `ChatService::startDirect()` → `Conversation::findOrCreateDirect()` | двое, оба `ConversationParticipant` через `firstOrCreate` | пара нормализуется: `user_min_id = min(a,b)`, `user_max_id = max(a,b)`, на пару стоит `unique(['user_min_id','user_max_id'])` — второго диалога между теми же людьми не будет |
| `clan_message` | `POST /api/chat/start-clan` → `ChatService::startClan()` | автор обращения + `leader`/`officer` клана на момент создания | `unique(['clan_id','author_id'])` — одно обращение на пару «клан + автор»; состав участников фиксируется при создании и позже не пересчитывается |
| `group` | `POST /api/chat/groups` → `ChatGroupService::create()` | создатель + приглашённые друзья, максимум 20 | `owner_id`, `title` (2–48 символов после схлопывания пробелов) |

Правила групповых бесед (`ChatGroupService`) — это единственное место, где чат ограничивает социальный граф:

- приглашать можно **только принятых друзей**: `friendship.status = 'accepted'`; любой чужой id в списке → 422 «В беседу можно звать только друзей»;
- `MAX_MEMBERS = 20`, проверка и при создании (`count($userIds) + 1 > 20`), и при добавлении (`count($present) + count($new) > 20`);
- добавлять участников может любой участник беседы (в контроллере только `authorize('send')` = «я участник»);
- исключать — только `owner_id`; выйти самому может любой;
- переименовывать — только `owner_id`;
- если создатель ушёл, `owner_id` переходит к участнику с минимальным `id` (кто дольше в беседе); если ушёл последний — беседа удаляется вместе с сообщениями (`cascadeOnDelete`), контроллер возвращает `{'ok': true, 'conversation': null}`;
- каждое изменение пишет служебное сообщение `kind = 'system'` с `meta.action`: `created`, `added`, `left`, `removed`, `renamed`, `meta.target_ids`.

Личный диалог не показывается в списке, пока в нём нет ни одного сообщения: `listConversations()` фильтрует `type != 'direct' OR has('messages')`. Это лечит «Новый диалог» без единой строки у собеседника, который создавался при каждом открытии чата с игроком.

### 17.5. Путь сообщения: от нажатия до доставки

Ниже — полная цепочка для обычного текстового сообщения, с файлами и функциями.

1. **Ввод.** `frontend/src/views/chat/Messages.vue`: `body` (ref), `pendingAttachments`, `replyTo`. Ввод в поле вызывает `onBodyInput()` → `notifyTyping()` (не чаще `TYPING_SEND_INTERVAL_MS = 2500` мс) и отложенное сохранение черновика (400 мс) в `localStorage` по каждому диалогу.
2. **Отправка.** `sendMessage()` оптимистично очищает поле, обнуляет `replyTo` и `pendingAttachments`, сохраняет бэкап для отката при ошибке, вызывает `chatApi.send(id, text, replyId, attachmentIds)`.
3. **HTTP.** `POST /api/chat/conversations/{conversation}/messages`, тело `{body, reply_to_id, attachments: [id…]}`. Маршрут только под `auth:sanctum`, **без throttle**.
4. **Валидация** (`SendMessageRequest`): `body` — `nullable|string|max:5000`, `reply_to_id` — `exists:messages,id`, `attachments` — массив до 10, каждый `exists:message_attachments,id`.
5. **Право.** `$this->authorize('send', $conversation)` → `ConversationPolicy::send` → существует ли строка в `conversation_participants` для этой пары.
6. **Сервис.** `ChatService::send()` режет пробелы, оставляет только свои и ещё не привязанные вложения (`where('user_id',$me->id)->whereNull('message_id')`), падает 422 на пустое тело без вложений, проверяет, что цель ответа — из этого же диалога, и вызывает `StickerService::guard()` (нельзя отправить стикер из некупленного набора, обход через `forwarded_from_user_id` закрыт).
7. **Запись.** Одна транзакция: `insert` в `messages`, `update message_attachments.message_id`, `touchAfterWrite()` — `conversations.last_message_at = now()`, автору сразу `message_reads(read_at = now())`, `conversation_participants.last_read_at = now()`.
8. **Перечитывание.** `reload()`: `fresh()` + `MESSAGE_RELATIONS` (автор с косметикой, `reads`, `replyTo.user`, `replyTo.attachments`, `forwardedFrom`, `attachments`, `reactions`) + `ChatCardService::attach()` — ссылки на игрока/клан/питомца/лот превращаются в карточки (максимум 3 на сообщение).
9. **Ответ и рассылка.** Контроллер отдаёт `201 {message: MessageResource}` и сразу зовёт `SafeBroadcast::send(new MessageSent($message), toOthers: true)`.

```php
// backend/app/Domains/Chat/Controllers/Api/ChatController.php, 98–110
public function send(SendMessageRequest $request, Conversation $conversation): JsonResponse
{
    $this->authorize('send', $conversation);

    $message = $this->chat->send(
        conversation: $conversation,
        me: $request->user(),
        body: $request->input('body'),
        replyToId: $request->filled('reply_to_id') ? $request->integer('reply_to_id') : null,
        attachmentIds: $request->input('attachments', []),
    );

    SafeBroadcast::send(new MessageSent($message), toOthers: true);
```

```php
// backend/app/Domains/Chat/Services/ChatService.php, 297–310
$message = DB::transaction(function () use ($conversation, $me, $body, $replyToId, $attachmentIds) {
    $message = Message::create([
        'conversation_id' => $conversation->id,
        'user_id' => $me->id,
        'reply_to_id' => $replyToId,
        'body' => $body,
    ]);

    $this->attachFiles($message, $attachmentIds);

    $this->touchAfterWrite($conversation, $message, $me);

    return $message;
});
```

10. **Reverb.** `MessageSent::broadcastOn()` строит по приватному каналу на каждого участника, кроме автора. Автор своё сообщение по сокету не получает — его интерфейс обновляется ответом HTTP.
11. **Другие вкладки автора.** `toOthers()` добавляет заголовок `X-Socket-ID`, и Reverb не доставляет событие той вкладке, которая инициировала запрос; остальные вкладки того же игрока событие получают.
12. **Клиент.** `useRealtimeMessages` (один на приложение) ловит `.message.sent`, кладёт сообщение в `latestMessage`; страница через `watch(latestMessage)` вызывает `mergeMessages()`, играет звук, если автор не я, гасит «печатает», поднимает диалог наверх списка, а если это другой диалог — увеличивает `unread_count` и общий `chatUnread`.

```php
// backend/app/Domains/Chat/Events/MessageSent.php, 25–38
public function broadcastOn(): array
{
    $channels = [];

    $this->message->conversation
        ->participants()
        ->where('user_id', '!=', $this->message->user_id)
        ->pluck('user_id')
        ->each(function ($userId) use (&$channels) {
            $channels[] = new PrivateChannel('App.Models.User.' . $userId);
        });

    return $channels;
}
```

Событие — `message.sent`, payload — `{message: <MessageResource>}`. Важная деталь: `broadcastWith()` собирает payload **тем же** `MessageResource`, что и API, и принудительно добавляет `reads: []`, потому что страница ждёт список, а не отсутствие ключа (раньше массив собирался вручную и расходился с ресурсом — галочка прочтения на сообщении из сокета не вставала).

**Три пути одного сообщения.** На клиенте одно и то же сообщение может прийти ответом на отправку, по сокету и запасным опросом. Все три пути идут через `mergeMessages()` (`frontend/src/utils/chatMessages.js`): дедупликация по `id`, сортировка по возрастанию `id` только если новое вставилось не в конец, известное сообщение не перезаписывается (у него могли появиться локальные отметки прочтения).

**Запасной опрос.** `useMessagePolling` работает, пока открыт диалог: `GET /chat/conversations/{id}?after_id={newest}&limit=50&mark_read=0`. Интервал — 2 с, но при живом сокете круг пропускается, если с последнего опроса прошло меньше `LIVE_INTERVAL_MS = 15000`; проверка живости — `window.Echo.connector.pusher.connection.state === 'connected'`.

### 17.6. Четыре события чата

| Событие | Класс | Канал | Кому | Payload |
|---|---|---|---|---|
| `message.sent` | `MessageSent` | private `App.Models.User.{id}` | все участники, кроме автора | `{message}` — полный `MessageResource` + `reads` |
| `messages.read` | `MessagesRead` | private, по каналу на участника | все участники, кроме читателя | `{conversation_id, reader:{id,username,avatar_url}, read_at}` |
| `user.typing` | `UserTyping` | private, по каналу на участника | все участники, кроме печатающего | `{conversation_id, user:{id,username,avatar_url}}` |
| `conversation.changed` | `ConversationChanged` | private, по каналу на участника + `extraUserIds` | все участники и явно указанные | `{conversation_id, kind, …data}` |

`ConversationChanged.kind` — пять значений, и каждое обрабатывается отдельной веткой `switch` в `Messages.vue`: `message.updated` (заменить сообщение), `message.deleted` (убрать и подставить новый `last_message` в превью), `reactions` (перерисовать реакции), `pinned` (обновить плашку закрепа), `conversation` (новый состав/название; если меня в списке участников больше нет — убрать беседу).

`extraUserIds` нужен ровно для одного случая: исключённый участник уже удалён из `conversation_participants`, поэтому обычная выборка его не вернёт, а сообщить ему надо — иначе беседа висит в списке до перезагрузки.

**Рассылка не роняет запрос.** Все вызовы идут через `SafeBroadcast`:

```php
// backend/app/Support/Broadcasting/SafeBroadcast.php, 17–31
public static function send(object $event, bool $toOthers = false): void
{
    try {
        $pending = broadcast($event);

        if ($toOthers) {
            $pending->toOthers();
        }

        // PendingBroadcast рассылает в деструкторе — вызываем его внутри try
        unset($pending);
    } catch (Throwable $e) {
        Log::warning('Broadcast failed: '.$e->getMessage(), ['event' => $event::class]);
    }
}
```

Логика: данные уже сохранены, и если Reverb лежит, клиент всё равно получает `201`, а собеседник подтянет сообщение опросом. Без этого пользователь видел 500 и при повторной отправке плодил дубли.

**Почему `ShouldBroadcastNow`, а не очередь.** Прямое следствие в тестах `MessageBroadcastTest`: раньше рассылка уходила в `jobs`, без воркера задачи копились (в базе накопилось 348), и собеседник видел сообщение только после обновления страницы. Тест `test_sending_message_does_not_queue_a_job` проверяет, что после отправки `count(jobs) === 0`.

### 17.7. Непрочитанные и прочтения

Прочтение — отдельная таблица `message_reads` (по строке на пару «сообщение + читатель»), а не флаг на сообщении. Это позволяет считать непрочитанные для каждого участника независимо и отдавать список читателей для галочек в групповой беседе.

| Действие | Где | Механика |
|---|---|---|
| Список диалогов с непрочитанными | `ChatService::listConversations()` | один агрегирующий запрос на все диалоги: `count(*)` по сообщениям, где `user_id != me` и нет `reads` с моим `user_id`; раньше был N+1 |
| Общий счётчик | `ChatService::unreadCount()` | тот же `unreadQuery()` + `whereIn` по моим диалогам |
| Открытие диалога | `GET /chat/conversations/{id}` → `history(markRead: true)` | перед отметкой запоминается `first_unread_id` (минимум `id` среди непрочитанных) — по нему страница рисует разделитель «Новые сообщения» |
| Отметить диалог | `POST /chat/conversations/{id}/read` | `insertOrIgnore` пачкой по всем непрочитанным + `participants.last_read_at = now()` |
| Отметить одно | `POST /chat/messages/{id}/read` | `firstOrCreate`, своё сообщение игнорируется |

После любой отметки сервер шлёт `MessagesRead::forConversation()` — всем остальным участникам. Событие уходит **всем**, а не только авторам прочитанных сообщений: иначе в диалоге без сообщений от собеседника события не было бы вовсе. Клиент сам решает, что пометить, и складывает `read_at` в `lastReadAt` по паре «диалог + читатель».

Клиентская отметка отложенная: `markActiveRead()` ставит таймер 250 мс; если вкладка скрыта, отметка откладывается до `visibilitychange`. Это защита от «прочитал, проскроллив на бегу» и от лишних запросов при быстром переключении диалогов. При открытии диалога дополнительно запрашивается `GET /chat/unread-count`, чтобы бейдж в шапке совпал с реальностью.

### 17.8. Правка, удаление, пересылка, реакции, закреп

| Операция | Право | Ограничения в коде |
|---|---|---|
| Редактирование | `MessagePolicy::update` — только автор | не позже `abs(created_at->diffInHours(now())) > 24` → 422; нельзя править `kind != 'text'`; нельзя, если тело совпадает с меткой стикера `[sticker:…]` (иначе правка текста обходила бы покупку набора); пустое тело допустимо только при наличии вложений; стикер-паттерн снова проверяется `StickerService::guard`; ставится `edited_at` |
| Удаление | `MessagePolicy::delete` — автор **или** `isModerator()` (роли `moderator`/`admin`) | удаление физическое (soft deletes у модели нет); после удаления `last_message_at` пересчитывается по `max(created_at)` оставшихся, иначе диалог подскакивал наверх; собеседникам уходит `conversation.changed` с `kind: message.deleted` и новым `last_message` |
| Пересылка | `MessagePolicy::forward` — участник диалога; плюс `authorize('send', $target)` | нельзя в тот же диалог; только `kind = 'text'` (подарок и служебная отметка — события своего диалога); вложения копируются физически новыми строками `message_attachments` (файл на диске переиспользуется по тому же `path`); `forwarded_from_user_id` = исходный автор или уже существующий источник пересылки |
| Реакции | `authorize('view', $message->conversation)` — любой участник | эмодзи только из `MessageReaction::EMOJI`: ❤️ 👍 😂 😮 😢 🔥 💀 👑; повторный клик снимает; `unique(message_id, user_id, emoji)`; throttle 60/мин |
| Закреп | `authorize('send', $conversation)` — любой участник | `message_id = null` открепляет; сообщение обязано быть из этого диалога (`findOrFail` с `where conversation_id`); хранится в `conversations.pinned_message_id` |

Реакции отдаются сгруппированно и **без привязки к получателю**: `{emoji, count, mine, user_ids}`. В событие кладётся `user_ids`, а «моя ли реакция» каждый клиент считает сам — иначе одно событие на всех врало бы про `mine`.

### 17.9. Вложения и голосовые

Загрузка двухшаговая: файл сначала живёт «про запас» (`message_id = null`) и привязывается при отправке.

| Параметр | Значение | Где |
|---|---|---|
| Сколько файлов за раз | `MAX_BATCH = 10` | `UploadAttachmentRequest` |
| Размер файла | 12 МБ (`MAX_KILOBYTES = 12288`) | там же |
| Разрешённые типы | `jpg,jpeg,png,webp,gif,mp4,webm,pdf,txt,md,zip,rar,7z,json` | там же |
| Куда кладётся | диск `public`, путь `chat/YYYY/MM/…` | `ChatService::uploadAttachment()` |
| Как отдаётся | `asset('storage/' . path)` — прямой URL | `MessageAttachment::getUrlAttribute()` |
| Голосовое | до 120 с (`VOICE_MAX_SECONDS`), файл до 4 МБ, `mimetypes`: webm/ogg/mp4/m4a/aac/mpeg, путь `chat/voice/YYYY/MM/` | `ChatController::uploadVoice()`, `ChatService::uploadVoice()` |
| Проверка скачивания | `MessageAttachmentPolicy::download`: участник диалога; неотправленный файл — только загрузивший | политика применяется на `GET /chat/attachments/{id}/download` |
| Очистка | при удалении сообщения строки уходят каскадом, файлы на диске остаются | в коде нет задачи очистки |

Практическое следствие, которое видно из кода: вложения лежат на **публичном** диске и имеют прямой URL `/storage/...`, поэтому политика `MessageAttachmentPolicy` защищает только маршрут `download`; сама ссылка из `MessageResource` работает без авторизации. Это факт реализации, а не вывод.

Порядок загрузки пачки: `POST /chat/attachments` с `files[0..n]` (индексы обязательны — без них PHP собирает вложенные массивы и `files.*` не видит файлы), ответ содержит и `attachments` (весь список), и `attachment` (первый) для обратной совместимости.

### 17.10. Присутствие: два независимых источника

`PresenceService` складывает онлайн из двух источников и дополнительно помнит явно вышедших:

| Источник | Как работает | Окно |
|---|---|---|
| Presence-канал Reverb `online` | `markOnline()` при подписке, отметка живёт в кэше `presence:members` вместе с timestamp | запись считается устаревшей через `PRESENCE_TTL_SECONDS = 90` |
| `users.last_seen_at` | middleware `TouchLastSeen` пишет время не чаще раза в 20 с, без событий модели | `ONLINE_WINDOW_SECONDS = 60` |
| Явный выход | `markLeft()` убирает из `presence:members` и пишет в `presence:left` | из `presence:left` игрока исключают 60 с |

`markLeft()` существует потому, что одной активности мало: игрок, нажавший «Выйти», ещё минуту оставался онлайн, так как страница продолжала слать запросы от его имени. Теперь активность не возвращает его в список, пока запись о выходе свежая; при следующем `markOnline()` запись стирается.

Эндпоинты:

| Метод | Путь | Что делает |
|---|---|---|
| `GET` | `/api/chat/presence` | без параметров — все онлайн-ids; с `user_ids` (массив или строка через запятую) — только про запрошенных |
| `POST` | `/api/chat/presence/online` | `markOnline` + отдаёт свежий список |
| `POST` | `/api/chat/presence/offline` | `markLeft` |

На клиенте (`frontend/src/composables/chat/presence.js`) присутствие стартует на всё приложение сразу после авторизации (`startSitePresence` в `AppLayout`), а не только на странице чата — иначе собеседник на главной для канала не существовал. Ритмы: пульс `POST /presence/online` каждые 30 с (пропускается при скрытой вкладке), полный список `GET /presence` раз в 60 с, о собеседниках открытого диалога — раз в 15 с. Выход из канала (`leaving`) сразу убирает игрока из локального множества, а серверному списку про него не верят `LEFT_SUPPRESS_MS = 90_000` — ровно столько сервер в худшем случае держит отметку.

### 17.11. Права, лимиты и защита от флуда

Политики (`backend/app/Domains/Chat/Policies/`):

| Политика | Метод | Правило |
|---|---|---|
| `ConversationPolicy` | `view`, `send`, `markRead` | только участник (exists в `conversation_participants`) |
| `MessagePolicy` | `view`, `forward` | участник диалога сообщения |
| `MessagePolicy` | `update` | только автор |
| `MessagePolicy` | `delete` | автор или модератор |
| `MessageAttachmentPolicy` | `download` | участник диалога; неотправленный файл — только владелец |

Ограничения уровней валидации и сервисов:

| Что | Значение |
|---|---|
| Длина сообщения | 5 000 символов (`SendMessageRequest`, `UpdateMessageRequest`) |
| Вложений на сообщение | 10 |
| Страница истории | 40 по умолчанию, максимум 100 |
| Диалогов в списке | 50 |
| Участников в беседе | 20 |
| Название беседы | 2–48 символов |
| Голосовое | 120 с |
| Поиск по людям/кланам | от 2 символов, по 10 результатов |
| Поиск по сообщениям | от 2 символов, 40 результатов, `LIKE` с экранированием `!`, `%`, `_` |

Throttle по маршрутам (счётчик у каждого маршрута свой — `ThrottlePerRoute` подмешивает в ключ метод и URI):

| Маршрут | Лимит |
|---|---|
| `POST /chat/attachments` | 40/мин |
| `POST /chat/messages/{id}/reactions` | 60/мин |
| `POST /chat/conversations/{id}/gift` | 10/мин |
| `POST /chat/voice` | 30/мин |
| `POST /chat/groups` | 10/мин |
| `POST /chat/groups/{id}/members` | 30/мин |
| `PUT /chat/groups/{id}` | 20/мин |
| `DELETE /chat/groups/{id}/members/{user}` | без лимита |
| `POST /chat/conversations/{id}/messages` | **без лимита** |
| `PUT`/`DELETE /chat/messages/{id}` | без лимита |
| `POST /chat/conversations/{id}/typing` | без лимита на сервере; клиент шлёт не чаще 2,5 с |

То есть серверного антифлуда на отправку сообщений в коде нет: защита сводится к авторизации, лимиту длины, проверке вложений и клиентскому интервалу на «печатает». Отдельно стоит держать в голове несоответствие: `max_message_size` приложения Reverb — 10 000 байт, а текст сообщения — до 5 000 символов; для кириллицы это уже ~10 000 байт без JSON-обёртки и объекта автора. Проверки, что сериализованное событие укладывается в лимит, в коде нет — при превышении сработает только `SafeBroadcast` (запись в лог, сообщение сохранено, но не доставлено).

### 17.12. Уведомления

Хранилище — штатная таблица Laravel `notifications`: `uuid` первичный ключ, `type`, `morphs('notifiable')`, `data` (text, сериализованный массив), `read_at`, timestamps.

| Метод | Путь | Действие |
|---|---|---|
| `GET` | `/api/notifications` | `paginate(20)` + `unread_count` |
| `POST` | `/api/notifications/{id}/read` | `findOrFail` в пределах своих, `markAsRead()`, возвращает свежий `unread_count` |
| `POST` | `/api/notifications/read-all` | массовый `update(['read_at' => now()])` + `unsetRelation` для `notifications` и `unreadNotifications` |

`markAllAsRead` сделан запросом, а не коллекцией, специально: ленивая коллекция могла быть уже загружена, часть уведомлений оставалась непрочитанной, и бейдж не пропадал до перезагрузки.

Типы уведомлений (все — `via() = ['database']`, то есть только база, без почты и сокета):

| Домен | Класс | `data.type` |
|---|---|---|
| Друзья | `FriendRequestNotification` | `friend_request` |
| Друзья | `FriendAcceptedNotification` | `friend_accepted` |
| Тир-тесты | `TierTestRequestNotification` | `tier_test_request` |
| Тир-тесты | `TierTestCompletedNotification` | `tier_test_completed` |
| Тир-тесты | `TierTestAppealResolvedNotification` | `tier_test_appeal_resolved` |
| Кланы | `ClanApplicationNotification` | `clan_application` |
| Кланы | `ClanApplicationAcceptedNotification` | `clan_application_accepted` |
| Кланы | `ClanApplicationDeclinedNotification` | `clan_application_declined` |
| Кланы | `ClanWarNotification` | `clan_war_call`, `clan_war_challenge` |
| Турниры | `TournamentNotification` | `tournament_approved`, `tournament_rejected`, `tournament_won` |
| Форум | `ForumReplyNotification` | `forum_reply`, `forum_reply_to_you` |
| Бридж | `BridgeSubmissionNotification` | `bridge_submission` |
| Бридж | `BridgeTechniqueReviewedNotification` | `bridge_technique_reviewed` |
| Ачивки | `AchievementGrantedNotification` | `achievement_granted` |
| Модерация | `ModerationNotification` | `moderation_warning`, `report_resolved`, `announcement` |
| Поддержка | `SupportNotification` | `support_reply`, `support_player_reply` |
| Аккаунт | `VerifiedNotification` | `verified` |
| Почта | `VerifyEmailNotification`, `ResetPasswordNotification` | письма, не `database` |

Доставка в реальном времени: класс `NotificationCreated` (`ShouldBroadcastNow`, private `App.Models.User.{id}`, событие `notification.created`) **объявлен**, фронтенд на него подписан (`useRealtimeNotifications`, звук `notification`, инкремент счётчика), но в рабочем коде приложения событие **никто не диспатчит** — `new NotificationCreated` встречается только в `tests/Feature/BroadcastTest.php` и `tests/Feature/MessageBroadcastTest.php`. То есть в текущем коде уведомления доставляются только в базу, а счётчик в шапке обновляется запросом при загрузке страницы и ответами `unread_count`; мгновенного прихода по сокету нет.

Счётчик на клиенте один на приложение (module-level `ref` в `useRealtimeNotifications`): раньше `ref` создавался внутри функции, у шапки и страницы были разные счётчики, и «прочитать всё» на странице не сбрасывало бейдж.

### 17.13. Activity: лента «Сейчас на APEX»

Таблица `activities`: `id`, `type` (string 40, индекс), `actor_id` (nullable, `nullOnDelete`), `payload` (json), timestamps + индекс по `created_at`. `payload` — снимок на момент события: переименованный позже клан в ленте остаётся под старым именем.

| Тип | Источник события | `payload` |
|---|---|---|
| `tier.assigned` | `TierTest` created (ручной тест админа сразу `completed`) или updated с `wasChanged('status')` и `result_tier` | `{tier, score, mode}` |
| `clan.created` | `Clan` created | `{clan:{id,name,tag,color}}` |
| `clan_war.finished` | `ClanWar` updated, `status = completed` и `wasChanged('status')` | `{challenger, opponent, challenger_score, opponent_score, winner_clan_id, outcome}`, `actor = null` |
| `tournament.won` | `TournamentWin` created | `{tournament:{id,name}}` |
| `minecraft.linked` | `User` updated, `wasChanged('minecraft_linked_at')` и значение не null | `{nickname}` |
| `achievement.earned` | `AchievementService::grant()` вручную (ачивки выдаются через `attach()` без событий модели) | по домену ачивки |

```php
// backend/app/Domains/Activity/Services/ActivityFeed.php, 31–42
public function record(string $type, ?User $actor, array $payload = []): Activity
{
    $activity = Activity::create([
        'type' => $type,
        'actor_id' => $actor?->id,
        'payload' => $payload,
    ]);

    DB::afterCommit(fn () => $this->broadcast($activity));

    return $activity;
}
```

`afterCommit` — принципиально: запись идёт в транзакции действия, и если действие откатилось, зрители не должны увидеть событие, которого не было. Ошибка рассылки только логируется (`Log::warning`) — лента не бизнес-логика.

Событие `ActivityRecorded` уходит в публичный канал:

```php
// backend/app/Domains/Activity/Events/ActivityRecorded.php, 29–37
public function broadcastOn(): array
{
    return [new Channel(self::CHANNEL)];
}

public function broadcastAs(): string
{
    return 'activity.recorded';
}
```

Подписка моделей — в `AppServiceProvider::registerActivityFeed()`: `TierTest::created/updated`, `Clan::created`, `ClanWar::updated`, `TournamentWin::created`, `User::updated`. Модели о ленте не знают — удалить ленту значит удалить один метод, а не править пять моделей.

HTTP: `GET /api/activity` — публичный, `limit` 1–50 (по умолчанию 20), курсор `before` (id), новые сверху. Фронтенд (`useActivityFeed`) берёт 8 событий, держит в памяти не больше 60, новые по сокету подсвечивает на 4 секунды, при пересоздании соединения (`onEchoReset`) переподписывается и делает `catchUp()` — добирает пропущенное. Индикатор «в эфире» — не состояние подписки, а `window.Echo.connector.pusher.connection.state`, опрашивается раз в 3 с.

### 17.14. Мост к Minecraft-серверу: привязка ника и вход

Это единственная часть системы, где сайт и игровой сервер действительно разговаривают.

**Идентификация сервера.** Один общий секрет: `config/services.php` → `minecraft.server_key` ← `MINECRAFT_SERVER_KEY`; плагин присылает его в заголовке `X-Minecraft-Server-Key`. Сравнение — постоянного времени, пустой ожидаемый ключ закрывает доступ полностью:

```php
// backend/app/Http/Middleware/EnsureMinecraftServer.php, 17–27
public function handle(Request $request, Closure $next): Response
{
    $expected = (string) config('services.minecraft.server_key');
    $given = (string) $request->header('X-Minecraft-Server-Key', '');

    if ($expected === '' || ! hash_equals($expected, $given)) {
        abort(403, 'Недоступно.');
    }

    return $next($request);
}
```

Группа `Route::middleware('minecraft.server')->prefix('minecraft')` вынесена из-под сессий: ходит плагин, не браузер. Отдельных ключей на сервер, списка серверов, ротации и подписи тела запроса в коде **нет** — один секрет на всё.

**Как привязывается ник.** История решения видна в миграциях: сначала (`2026_10_08_161952`) был одноразовый код из игры в таблице `minecraft_link_codes`, но код ничего не доказывал — его вводил кто угодно, и ник занимал тот, кто успел первым. Миграция `2026_10_08_162912` удалила таблицу кодов и сделала ник ключом привязки: игрок заявляет ник в профиле под своей сессией, ник уникален на сайте (`unique('minecraft_username')`), а плагин не пускает того, чей ник в игре не совпадает с заявленным.

| Поле `users` | Тип | Смысл |
|---|---|---|
| `minecraft_username` | string(32), unique, nullable | заявленный ник, ключ привязки |
| `minecraft_uuid` | string(36), unique, nullable | UUID; доверяем только при `online-mode=true`, поэтому при заявке ника сбрасывается в null и заполняется при первом входе |
| `minecraft_linked_at` | timestamp, nullable | когда заявлен; изменение этого поля — триггер события `minecraft.linked` в ленте |

Правила ника: `^[A-Za-z0-9_]{3,16}$` (константа `NICKNAME_PATTERN`), поиск аккаунта — `LOWER(minecraft_username) = ?`, потому что в Minecraft регистр не важен. Заявка чужого ника → 422 «Этот ник уже заявлен другим аккаунтом».

**Вход на сервер.** Плагин делает два запроса: сначала `resolve` (заявлен ли ник и чей), потом `login` (ник + пароль сайта). Пароль приходит по HTTPS, сверяется с хешем и нигде не сохраняется и не логируется.

```php
// backend/app/Domains/Minecraft/Services/MinecraftLinkService.php, фрагмент login()
$key = 'minecraft-auth:' . mb_strtolower($nickname);

if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
    return ['ok' => false, 'reason' => 'throttled', 'attempts_left' => 0,
            'retry_after' => RateLimiter::availableIn($key), 'user' => null];
}

$user = $this->userByNickname($nickname);

// Хеш сверяем всегда, даже когда ник свободен: иначе по времени ответа
// можно было бы узнать, заявлен ник или нет.
$hash = $user?->password ?? $this->dummyHash();

if (! Hash::check($password, $hash) || ! $user) {
    RateLimiter::hit($key, self::DECAY_SECONDS);
    // …
}
```

Защита от подбора: `MAX_ATTEMPTS = 5`, `DECAY_SECONDS = 600`, ключ лимитера — нижний регистр ника. Защита от утечки через тайминг: при свободном нике всё равно сверяется bcrypt-хеш-заглушка (`Hash::make('apex-timing-equalizer')`, кэшируется в статике). Успешный вход очищает лимитер и, если плагин прислал `uuid` и он отличается, записывает его.

Контракт ответа `login`: `{ok, reason, attempts_left, retry_after, player?}`; `reason` ∈ `throttled` | `invalid` | `not_claimed`; HTTP 429 при блокировке, 401 при неверном пароле, 200 при успехе. `player` — `profilePayload`: `username`, `nickname`, `tier`, `tier_score`, `role`, `clan_tag`, `clan_color`, `avatar_url`, `bridge_rank{label,color}`.

**Админские операции** (тоже под секретом сервера, вызываются плагином): `admin/lookup` — на какой аккаунт заявлен ник (`account_id`, `uuid`, `claimed_at`), `admin/unlink` — освободить ник. Это нужно потому, что в offline-режиме доказать принадлежность ника нельзя и решение принимает админ.

Синхронизации игроков в обе стороны в коде **нет**: сервер не присылает список онлайн-игроков, сайт не пушит события в игру, периодической сверки ников нет. Всё общение — три запроса плагина к сайту (`resolve`, `login`, `profile`) и два админских.

Отдельно — скин. `MinecraftSkinService` не относится к плагину: сайт сам спрашивает Mojang (`api.mojang.com/users/profiles/minecraft/{nick}` → UUID, `sessionserver.mojang.com/session/minecraft/profile/{uuid}` → подписанный `textures`), скачивает PNG **только** с `textures.minecraft.net`, проверяет, что это PNG 64×64 или 64×32, и кладёт на диск `local` в `minecraft-skins/{uuid}.png`. Кэш: метаданные скина 6 ч, UUID ника 24 ч, «такого ника нет» 1 ч. Свой скин игрока (`custom-skins/{id}.png`) важнее скина из Minecraft. Отдача — `GET /api/players/{user}/skin` с заголовком `X-Skin-Model: classic|slim` (и `Access-Control-Expose-Headers`), кэш 3600 с.

### 17.15. Статус сервера: Server List Ping вместо плагина

Онлайн, число игроков и версия берутся не от плагина, а тем же запросом, который делает клиент игры в список серверов. Плагин для этого не нужен:

```php
// backend/app/Domains/Minecraft/Services/MinecraftStatusService.php, 66–72
// Handshake: версия протокола -1 (любая), адрес, порт, next state = 1 (status)
$handshake = "\x00" . $this->varInt(-1) . $this->string($host) . pack('n', $port) . $this->varInt(1);
fwrite($socket, $this->varInt(strlen($handshake)) . $handshake);

// Status request
fwrite($socket, "\x01\x00");
```

| Параметр | Значение |
|---|---|
| Таймаут соединения | `TIMEOUT = 2.0` с, `fsockopen` |
| Кэш успеха / отказа | 30 с / 15 с, ключ `minecraft:server-status` |
| Адрес в ответе | `minecraft.address` или `minecraft.host` |
| Поля ответа | `configured`, `online`, `address`, `players`, `max`, `version`, `motd` |
| MOTD | строка или дерево компонентов (`text` + `extra`), коды цвета `§x` вырезаются |
| Конфиг | `MINECRAFT_SERVER_HOST`, `MINECRAFT_SERVER_PORT` (25565), `MINECRAFT_SERVER_ADDRESS` |
| Эндпоинт | `GET /api/site/server-status`, публичный, throttle 60/мин |

Если `host` не задан — `configured: false`, `online: false`, без сетевых попыток. Список конкретных игроков онлайн сервер не отдаёт: только числа `players`/`max`.

### 17.16. Домен Bridge: заявки на виды бриджа

Раз уж название обманывает, разберём его полностью — это вторая по объёму часть «реального времени вокруг игры», хотя сокетов здесь нет вообще.

**Каталог.** `bridge_techniques` (`key` unique, `label`, `description`, `sort_order`, `is_active`) и `bridge_technique_variants` (`technique_id`, `key`, `label`, `is_special`, `sort_order`, `is_active`, `unique(technique_id,key)`). Каталог живёт в базе, а не в коде: виды добавляются куратором без выкладки. `key` генерируется из названия через `Str::slug($label,'_')` с суффиксом при коллизии.

**Заявка.** `user_bridge_techniques`: один вид — одна запись на игрока (`unique(user_id, technique_id)`), повторная подача перезаписывает её. Статусы: `declared` → `confirmed` | `rejected`. Оценки тестера: `stability`, `speed`, `difficulty` (0–100 каждая), `score` («владение видом», 0–10), `review_notes`, `reviewed_by`, `reviewed_at`, `variants` (json).

```php
// backend/app/Domains/Bridge/Services/BridgeService.php, фрагмент declare()
if ($uploadId !== null) {
    $upload = BridgeVideoUpload::query()
        ->where('uuid', $uploadId)
        ->where('user_id', $user->id)   // чужая загрузка не подойдёт
        ->first();

    if (! $upload) {
        $this->abortUnprocessable('Загрузка видео не найдена.');
    }

    if (! $upload->isCompleted()) {
        $this->abortUnprocessable('Видео ещё не догружено до конца.');
    }

    $videoPath = $upload->path;
}
```

Подтверждённую заявку нельзя ни переподать, ни отозвать: `withdraw()` и `declare()` падают 422. Отклонённую можно переподать — прошлые оценки при этом обнуляются, потому что видео новое. Набор подвидов нормализуется: остаются только id, принадлежащие этому виду (`normalizeVariants`), дубли убираются — чужой подвид из другого вида в заявку не попадёт.

**Видео.** Внешних хостингов больше нет: ролик грузится частями в закрытое хранилище `local`.

| Параметр | Значение | Где |
|---|---|---|
| Размер части | 4 МБ (`CHUNK_SIZE = 4194304`), переопределяется `BRIDGE_CHUNK_SIZE` | `BridgeVideoUpload`, `config/bridge.php` |
| Максимальный ролик | 300 МБ (`MAX_SIZE = 314572800`), `BRIDGE_MAX_VIDEO_SIZE` | там же |
| Разрешённые MIME | mp4, webm, quicktime, x-matroska, x-msvideo | `BridgeVideoService::ALLOWED_MIME` |
| Части | `bridge-chunks/{uuid}/{index}`, список принятых — в `received_chunks` (json) | повторная часть перезаписывается, это не ломает докачку |
| Сборка | потоком, `stream_copy_to_stream`, затем **сверка размера** с заявленным; при расхождении файл удаляется и 422 | `complete()` |
| Отдача | только по подписанной ссылке `URL::temporarySignedRoute('bridge.video', now()->addHours(3))`, TTL `BRIDGE_VIDEO_TTL_HOURS` | `BridgeSubmissionResource` |
| Удаление | после проверки тестером, при отзыве заявки, при удалении админом | `deleteFor()` |
| Уборка | `pruneStale()` — незавершённые загрузки старше суток | вызывается вне HTTP (команда/крон в коде не зарегистрирован) |

```php
// backend/app/Domains/Bridge/Services/BridgeVideoService.php, 161–172
for ($index = 0; $index < $upload->total_chunks; $index++) {
    $chunkPath = $this->disk()->path(self::CHUNK_DIR . '/' . $upload->uuid . '/' . $index);

    if (! is_file($chunkPath)) {
        $this->abortUnprocessable("Часть {$index} потерялась, загрузи ролик заново.");
    }

    $in = fopen($chunkPath, 'rb');

    stream_copy_to_stream($in, $out);
    fclose($in);
}
```

**Проверка.** Очередь — все заявки в статусе `declared`, свежие сверху по `updated_at`. При подтверждении обязательны все четыре оценки (`required_if:confirm,true`), тестер может поправить набор подвидов. При отклонении достаточно причины. В обоих случаях игроку уходит `BridgeTechniqueReviewedNotification`, а ролик удаляется. История проверок — `confirmed` + `rejected`, лимит 200, по `reviewed_at`.

**Звания и топ.** `bridge_ranks` (`key`, `label`, `color`, `sort_order`, `is_active`) — звание выдаёт тестер вручную, это не тир по очкам; хранится в `users.bridge_rank_id` + `bridge_rank_by` + `bridge_rank_at`, кэшируется навсегда (`bridge_ranks:by_id`, сброс по `saved`/`deleted`). Место в бридже считается запросом, без денормализации: `score = aspects_total * 1000 + confirmed_count`, где `aspects_total` — сумма трёх аспектов по подтверждённым видам, а `1000` — вес, заведомо больший любого реального числа видов. Раньше было наоборот, и игрок с 169 очками стоял выше игрока с 210. Из выборок исключаются `admin` и `moderator` (`scopeExcludeStaff`), тестеры и кураторы участвуют наравне с игроками. Очки рейтинга пересчитывает `RatingService` (в админских правках — сразу после изменения).

Флаг `users.bridge_primary` означает «бридж — основной режим игрока»: тогда на сайте вместо тира показывается звание бриджера. Его пересчитывает `RatingService` вместе с очками.

Замечание по маршрутам: метод `BridgeController::user()` (публичный профиль бриджера с подтверждёнными видами, сводкой и местом) в `routes/api.php` **не зарегистрирован** — публичного эндпоинта этого профиля в коде нет.

### 17.17. Схема таблиц и связей

```
users ──┬─< conversation_participants >── conversations ──< messages ──< message_reads
        │                                     │  │              ├──< message_attachments
        │                                     │  └─ pinned_message_id ──> messages
        │                                     └─ clan_id ──> clans
        ├─< message_reactions >── messages
        ├─< notifications (morphs notifiable)
        ├─< activities (actor_id, nullOnDelete)
        ├─< bridge_rank_id ──> bridge_ranks
        └─< user_bridge_techniques >── bridge_techniques ──< bridge_technique_variants
                                          bridge_video_uploads (uuid, user_id)
```

**conversations**

| Поле | Тип | Примечание |
|---|---|---|
| `id` | bigint PK | |
| `type` | string(20), default `direct` | было enum, расширено миграцией |
| `title` | string(64), nullable | только для `group` |
| `user_min_id`, `user_max_id` | FK users, nullable, cascade | `unique(min,max)` для `direct` |
| `clan_id`, `author_id` | FK clans/users, nullable, cascade | `unique(clan_id,author_id)` для `clan_message` |
| `owner_id` | FK users, nullable, `nullOnDelete` | создатель беседы |
| `pinned_message_id` | FK messages, nullable, `nullOnDelete` | закреп |
| `last_message_at` | timestamp, индекс | сортировка списка |

**conversation_participants**

| Поле | Тип | Примечание |
|---|---|---|
| `conversation_id`, `user_id` | FK, cascade | `unique(conversation_id,user_id)` |
| `last_read_at` | timestamp, nullable | индекс `(user_id,last_read_at)` |

**messages**

| Поле | Тип | Примечание |
|---|---|---|
| `conversation_id`, `user_id` | FK, cascade | |
| `reply_to_id` | bigint, nullable | без FK-констрейнта |
| `forwarded_from_user_id` | FK users, nullable, `nullOnDelete` | |
| `kind` | string(16), default `text` | `text` / `gift` / `system` |
| `body` | text, nullable | nullable с миграции вложений |
| `meta` | json, nullable | сумма подарка, `action`/`target_ids` у system |
| `edited_at` | timestamp, nullable | |
| индексы | `(conversation_id,created_at)`, `(conversation_id,id)`, `(user_id,created_at)` | |

**message_reads**: `message_id`, `user_id` (оба cascade), `read_at` (`useCurrent`), `unique(message_id,user_id)`, индекс `(user_id,read_at)`; `$timestamps = false`.

**message_attachments**: `message_id` (nullable — файл ещё не отправлен, cascade), `user_id` (cascade), `original_name`, `path`, `mime`, `size`, `is_image`, `is_voice`, `duration` (smallint, nullable), индексы `message_id` и `(user_id,message_id)`.

**message_reactions**: `message_id`, `user_id`, `emoji` (string 16), `unique(message_id,user_id,emoji)`.

**notifications**: `id` uuid PK, `type`, `morphs('notifiable')`, `data` text, `read_at`, timestamps.

**activities**: `type` (string 40, индекс), `actor_id` (nullable, `nullOnDelete`), `payload` json, индексы `type` и `created_at`.

**bridge_techniques**: `key` unique, `label`, `description`, `sort_order`, `is_active`.
**bridge_technique_variants**: `technique_id` (cascade), `key`, `label`, `is_special`, `sort_order`, `is_active`, `unique(technique_id,key)`.
**user_bridge_techniques**: `user_id`, `technique_id`, `status` enum(`declared`,`confirmed`,`rejected`), `video_url` (512, legacy), `video_path` (512), `variants` json, четыре оценки, `review_notes`, `reviewed_by` (`nullOnDelete`), `reviewed_at`, `unique(user_id,technique_id)`, индекс `(status,created_at)`.
**bridge_video_uploads**: `uuid` unique, `user_id` (cascade), `original_name`, `mime` (96), `size`, `total_chunks`, `received_chunks` json, `path` (512), `status` enum(`pending`,`completed`), индекс `(user_id,status)`.
**bridge_ranks**: `key` unique, `label`, `color` (default `#8b5cf6`), `sort_order`, `is_active`.

### 17.18. Все эндпоинты

**Чат** (все под `auth:sanctum`):

| Метод | Путь | Действие | Права | Лимит |
|---|---|---|---|---|
| GET | `/api/chat/conversations` | список диалогов + непрочитанные + онлайн участников | свои | — |
| GET | `/api/chat/conversations/{conversation}` | история, курсоры `before_id`/`after_id`, `limit`, `mark_read` | участник (`view`) | limit ≤ 100 |
| GET | `/api/chat/search` | поиск игроков и кланов для нового диалога | авторизован | — |
| POST | `/api/chat/start-direct` | найти/создать личный диалог | авторизован | — |
| POST | `/api/chat/start-clan` | обращение к клану (автор + лидер/офицер) | авторизован | — |
| GET | `/api/chat/unread-count` | общий счётчик непрочитанных | свои | — |
| GET | `/api/chat/conversations/{conversation}/search` | поиск по тексту сообщений диалога | участник | — |
| POST | `/api/chat/conversations/{conversation}/messages` | отправить сообщение | участник (`send`) | нет |
| PUT | `/api/chat/messages/{message}` | правка своего сообщения (≤ 24 ч) | автор | нет |
| DELETE | `/api/chat/messages/{message}` | удалить сообщение | автор или модератор | нет |
| POST | `/api/chat/messages/{message}/forward` | переслать в другой диалог | участник источника + `send` в цель | нет |
| POST | `/api/chat/messages/{message}/reactions` | поставить/снять реакцию | участник диалога | 60/мин |
| POST | `/api/chat/messages/{message}/read` | отметить одно сообщение | участник | нет |
| POST | `/api/chat/conversations/{conversation}/read` | отметить диалог | участник | нет |
| POST | `/api/chat/conversations/{conversation}/typing` | «печатает» | участник | нет |
| POST | `/api/chat/conversations/{conversation}/pin` | закрепить/открепить | участник | нет |
| POST | `/api/chat/conversations/{conversation}/gift` | подарить монеты (`1…1 000 000`, подпись ≤ 200) | участник личного диалога | 10/мин |
| POST | `/api/chat/attachments` | загрузить файл(ы) «про запас» | авторизован | 40/мин |
| GET | `/api/chat/attachments/{attachment}/download` | скачать вложение | участник диалога | — |
| POST | `/api/chat/voice` | загрузить голосовое (≤ 120 с) | авторизован | 30/мин |
| POST | `/api/chat/groups` | создать беседу (≤ 20 человек, только друзья) | авторизован | 10/мин |
| POST | `/api/chat/groups/{conversation}/members` | добавить друзей | участник | 30/мин |
| DELETE | `/api/chat/groups/{conversation}/members/{user}` | исключить / выйти | создатель или сам | — |
| PUT | `/api/chat/groups/{conversation}` | переименовать | создатель | 20/мин |
| GET | `/api/chat/presence` | кто онлайн (все или `user_ids`) | авторизован | — |
| POST | `/api/chat/presence/online` | отметка входа / пульс | авторизован | — |
| POST | `/api/chat/presence/offline` | отметка выхода | авторизован | — |

**Уведомления и лента:**

| Метод | Путь | Действие | Права |
|---|---|---|---|
| GET | `/api/notifications` | список по 20 + `unread_count` | свои |
| POST | `/api/notifications/read-all` | прочитать всё | свои |
| POST | `/api/notifications/{id}/read` | прочитать одно | свои |
| GET | `/api/activity` | лента, `limit` 1–50, курсор `before` | публичный |

**Мост к Minecraft-серверу** (заголовок `X-Minecraft-Server-Key`):

| Метод | Путь | Действие | Ответ |
|---|---|---|---|
| POST | `/api/minecraft/nickname/resolve` | заявлен ли ник и чей | `{claimed, nickname, account_username}` |
| POST | `/api/minecraft/auth/login` | вход по нику и паролю сайта | `{ok, reason, attempts_left, retry_after, player?}`, 401/429 |
| POST | `/api/minecraft/profile` | данные игрока для игры | `{claimed, player}` или 404 |
| POST | `/api/minecraft/admin/lookup` | на какой аккаунт заявлен ник | `{claimed, nickname, account_username, account_id, uuid, claimed_at}` |
| POST | `/api/minecraft/admin/unlink` | освободить ник | `{ok, nickname}` |

**Привязка со стороны игрока** (под `auth:sanctum`):

| Метод | Путь | Действие |
|---|---|---|
| GET | `/api/players/me/minecraft/link` | состояние заявки |
| POST | `/api/players/me/minecraft/link` | заявить ник (3–16, `[A-Za-z0-9_]`) |
| DELETE | `/api/players/me/minecraft/link` | снять заявку |
| POST | `/api/players/me/skin` | свой скин PNG 64×64/64×32, ≤ 64 КБ, `model` = `classic`/`slim` (10/мин) |
| DELETE | `/api/players/me/skin` | вернуть скин из Minecraft (10/мин) |
| GET | `/api/players/{user}/skin` | PNG скина + `X-Skin-Model` (публичный, 60/мин) |
| GET | `/api/site/server-status` | статус сервера по SLP (публичный, 60/мин) |

**Bridge — игрок:**

| Метод | Путь | Действие | Права |
|---|---|---|---|
| GET | `/api/bridge/techniques` | каталог + мои заявки + сводка | авторизован |
| POST | `/api/bridge/techniques` | подать вид (`technique_id`, `upload_id`, `variants`) | авторизован |
| DELETE | `/api/bridge/submissions/{submission}` | отозвать заявку (кроме подтверждённой) | владелец |
| PUT | `/api/bridge/submissions/{submission}/variants` | включить/выключить подвиды | владелец или тестер |
| POST | `/api/bridge/uploads` | начать загрузку видео | авторизован |
| POST | `/api/bridge/uploads/{upload:uuid}/chunks` | принять часть | владелец загрузки |
| POST | `/api/bridge/uploads/{upload:uuid}/complete` | собрать файл | владелец загрузки |
| GET | `/api/bridge/videos/{submission}` | отдать видео | только подписанная ссылка (`signed`) |

**Bridge — тестер** (`role:bridge_tester,bridge_curator,admin`):

| Метод | Путь | Действие |
|---|---|---|
| GET | `/api/bridge-review/submissions` | очередь `declared` |
| POST | `/api/bridge-review/submissions/{submission}` | подтвердить (все оценки) или отклонить |
| GET | `/api/bridge-review/history` | история проверок (200) |
| GET | `/api/bridge-review/ranks` | звания |
| GET | `/api/bridge-review/players` | бриджеры с подтверждёнными видами (100) |
| POST | `/api/bridge-review/players/{user}/rank` | выдать/снять звание |

**Bridge — куратор** (`role:bridge_curator,admin`): `GET|POST /api/bridge-curator/techniques`, `PUT|DELETE /api/bridge-curator/techniques/{technique}`, `POST /api/bridge-curator/techniques/{technique}/variants`, `PUT|DELETE /api/bridge-curator/variants/{variant}`.

**Bridge — админ** (`role:admin`): `GET /api/admin/users/{user}/bridge`, `PUT /api/admin/bridge/submissions/{submission}`, `DELETE /api/admin/bridge/submissions/{submission}`. Правки пишутся в `ModerationLog` (`bridge.submission_updated`, `bridge.submission_deleted`) и пересчитывают рейтинг.

### 17.19. Чего в коде нет

- Каналов на диалог/беседу (`conversation.{id}`) — вся адресация через личные каналы.
- Подписки гостей на что-либо, кроме публичного `activity`; авторизация канала у гостя всегда провалится.
- Серверного throttle на отправку сообщений, правку и удаление.
- Rate limiting самого Reverb (по умолчанию `false`), ограничения числа соединений (`max_connections` пустой).
- Очереди для рассылки — все события `ShouldBroadcastNow`, синхронно в запросе.
- Диспатча `NotificationCreated` в рабочем коде — только тесты; realtime-уведомления фактически не доставляются.
- Клиентских событий (`whisper`): «печатает» идёт HTTP-запросом, хотя Reverb разрешает `accept_client_events_from: members`.
- Множественных Minecraft-серверов, отдельных ключей, ротации секрета, подписи тела запроса.
- Синхронизации игроков между сайтом и сервером в любую сторону; списка игроков онлайн (только числа из SLP).
- Периодической проверки, что заявленный ник всё ещё принадлежит игроку.
- E2E-шифрования, редактирования истории, мягкого удаления сообщений, восстановления удалённых.
- Задачи очистки файлов вложений с диска (строки удаляются каскадом, файлы остаются) и зарегистрированного расписания для `BridgeVideoService::pruneStale()`.
