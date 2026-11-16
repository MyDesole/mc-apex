# FastBridge: релиз с сохранением установленного плагина

Настройки: `/fb settings`; пресеты: `/fb preset save|load|delete имя`;
избранное: `/fb favorites`, `/fb favorite last`; история: `/fb history`;
приватная тренировка от APEX: `/fb private`, общая: `/fb public`.
Лимиты и подробности — раздел 17.22 технической книги и руководство пользователя.

Репозиторий содержит не все исходники установленного ApexTierTests.
Поэтому новый релиз собирается поверх точной копии рабочего JAR, заменяя только
FastBridge, Replay, FbPerks и их вложенные классы. Остальные классы, команды,
ресурсы и существующие возможности доната сохраняются побайтно.

```bash
# LIBS содержит Spigot.jar (ядро сервера) и Alley.jar.
BASE_JAR=/path/to/ApexTierTests-Tactics.jar bash minecraft-tiertest-plugin/build-fastbridge-patch.sh
BASE_JAR=/path/to/ApexTierTests-Tactics.jar bash minecraft-tiertest-plugin/test-fastbridge.sh
# Полная интеграция на отдельном сервере и localhost-клиентах:
BASE_JAR=/path/to/ApexTierTests-Tactics.jar SERVER_TEST=1 SERVER_JAR=/path/to/WindSpigot.jar bash minecraft-tiertest-plugin/test-fastbridge.sh
```

В GitHub Actions Minecraft выбрать `source_ref=feature/fastbridge-perks`,
`plugins=tiertests`. Зависимости и базовый JAR скачиваются с сервера.
Если базовый JAR изменился после сборки, выкладка отказывается его перетирать:
нужна повторная сборка. В META-INF/apex-fastbridge-release.json записан его SHA256.
Не используйте старую полную сборку для этой поставки.

Проверки: реальные Bukkit YAML/JSON, лимиты, избранное и приватные ACL,
совместимость старых реплеев; отдельный WindSpigot и два клиента проверяют вход
на дорожки, запрет телепортации, видимость, пресеты, финиш, приватный реплей и GUI.
Тестовый плагин и миры создаются только в target/server-test-*, не на проде.
