# Скрипт управления игровым сервером

`apex-panel.sh` лежит на игровом сервере в `/opt/minecraft/server/` и
вызывается панелью по SSH. Здесь он хранится, чтобы изменения были видны в
истории.

Установка после правки:

```bash
scp backend/deploy/game-server/apex-panel.sh root@168.113.181.145:/opt/minecraft/server/
ssh root@168.113.181.145 'chmod +x /opt/minecraft/server/apex-panel.sh'
```

Подкоманды: `status | players | plugins | console N | start | stop |
restart | cmd ... | get-start | set-start | files | read PATH | write PATH |
archives | apply-zip NAME | rm-archive NAME | backups | restore NAME`.

Важно: screen-сессия ищется по процессу java (`sid()`), а не по имени —
после нескольких перезапусков на сервере остаются висячие сессии
`*.minecraft`, и `screen -S minecraft` начинает путаться: команда в
консоль молча не доходит.
