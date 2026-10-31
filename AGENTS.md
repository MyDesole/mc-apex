# APEX

Игровая веб-платформа: сайт `mc-apex.ru` на Laravel 13 + Vue 3.

## Документация

Вся документация проекта живёт в `docs/` и собирается в PDF:

| Документ | Источник | Для кого |
|---|---|---|
| Техническая книга | `docs/book/*.md` | разработчик |
| Руководство пользователя | `docs/guide/Руководство-пользователя.md` | игрок |
| Памятки | `docs/guide/Памятки.md` | быстрый справочник |

**Если менялись механики, константы, лимиты, награды или роуты — документация
обновляется в том же коммите.** Правила и порядок действий:
[`docs/skills/apex-docs/SKILL.md`](docs/skills/apex-docs/SKILL.md).

Сборка: `node docs/tools/build.mjs`. Устройство сборки:
[`docs/tools/README.md`](docs/tools/README.md).

## Структура

```
backend/    Laravel: app/Domains/<Домен>/{Controllers,Models,Services,...}
frontend/    Vue: views/, components/ (.vue + сестринский .css), services/, stores/
docs/        книга, руководство, памятки, сборка, скилл
```

Правила кода — в [`backend/AGENTS.md`](backend/AGENTS.md).
