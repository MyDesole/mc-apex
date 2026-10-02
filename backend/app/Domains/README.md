# Домены бэкенда

Код разделён по предметным областям. Домен — папка в `app/Domains/`,
внутри неё файлы разложены по типам, а подпапки повторяют прежнюю
структуру (`Api/`, `Admin/`, `Auth/` и так далее), чтобы одинаковые
имена классов из разных мест не конфликтовали.

```
app/Domains/<Домен>/
├── Controllers/     тонкие контроллеры (Api/, Api/Admin/, Api/Tester/)
├── Requests/        валидация запросов
├── Services/        вся бизнес-логика
├── Models/          Eloquent-модели
├── Policies/        права доступа
├── Resources/       преобразование ответов API
├── Notifications/   уведомления
├── Events/          события
├── Mail/            письма
├── Concerns/        трейты домена
└── Support/         вспомогательные классы домена
```

## Домены

| Домен | Что входит |
|---|---|
| `Clan` | кланы, участники, заявки, войны, события, ресурсы, клановый форум |
| `Forum` | темы, ответы, разделы, лайки, вложения |
| `Chat` | диалоги, сообщения, вложения, прочтения |
| `Shop` | товары, инвентарь, настройки магазина |
| `Achievements` | ачивки и их выдача |
| `Tiers` | тир-тесты и аспекты |
| `Tournaments` | турниры, сетки, участники, матчи |
| `Players` | профили игроков, аспекты, рейтинг, главная и топ |
| `Users` | пользователи и роли |
| `Friends` | дружба и заявки |
| `Notifications` | уведомления |
| `Wallet` | ApexCoin, транзакции, подарки, награды |
| `News` | новости |
| `Auth` | вход, регистрация, пароли, подтверждение почты |
| `Core` | настройки сайта |

## Что осталось общим

Эти классы не принадлежат одному домену и лежат вне `Domains`:

- `app/Http/Controllers/Controller.php` — базовый контроллер;
- `app/Http/Requests/BaseFormRequest.php` — базовый запрос;
- `app/Http/Responses/ApiResponse.php` — формат ответа;
- `app/Support/Concerns/` — трейты `ResolvesFromUrl`, `AbortsWithMessage`,
  `StoresUserFiles`.

Остальное разошлось по доменам: `ClanHighlight` и `ClanContext` — в `Clan`,
`ForumPresenter` и `ForumReplyTree` — в `Forum`.

## Полиморфные связи

Поля `*_type` хранят **короткие имена**, а не полные имена классов:

| Таблица | Поле | Псевдонимы |
|---|---|---|
| `coin_transactions` | `reference_type` | `shop_item`, `tier_test`, `achievement`, `user`, `clan` |
| `notifications` | `notifiable_type` | `user` |
| `personal_access_tokens` | `tokenable_type` | `user` |

Карта псевдонимов — в `AppServiceProvider::boot()` (`Relation::morphMap`).
Она нужна, чтобы перенос модели не ломал данные. `enforceMorphMap` не
включён: модели вне списка продолжают работать.

Важно: `CoinService` пишет `reference_type` напрямую, поэтому применяет
карту сам через `morphType()`. Обычная запись в поле карту не применяет.

`forum_likes.likeable_type` и `forum_attachments.attachable_type` хранят
строки `topic` и `reply` вручную и с картой не связаны.

## Правила

- Контроллер тонкий: валидация в `Requests`, права в `Policies`,
  логика в `Services`.
- В контроллерах не должно быть `$request->validate()` и `DB::transaction`.
- Ответы формируются через `Resources`, а не ручным `map()`: ручной
  `map()` уже трижды терял поля, которые читает фронтенд.
- Новое поле, которое читает шаблон, добавляется в
  `tests/Feature/ApiFieldsForTemplatesTest.php`.

## История переноса

Перенос из плоских `app/Models`, `app/Services`, `app/Http/Controllers`
сделан одним коммитом; точка возврата — тег `before-domains`.
Фабрики лежат в `database/factories/Domains/...` и задают модель явно
(`protected $model`), потому что Laravel не выводит её по имени класса
для моделей в доменах.
