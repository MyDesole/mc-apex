# ApexBuildWorlds

Три закрытых строительных мира для администраторов, без подключения к матчам.

## Использование

`/buildmap stickfight`, `/buildmap fireballfight`, `/buildmap thebridge`.
`/buildmap exit` - назад, `/buildmap setspawn` - новая точка входа.
Нужны `apex.buildworlds.admin` (default: op) и успешный вход ApexAuth.
Права донатеров не меняются. Миры: build_stickfight, build_fireballfight, build_thebridge.

## Сборка

`bash minecraft-buildworlds-plugin/build.sh` с Java 17 и точным Spigot.jar 1.8.8.
Артефакт target/ApexBuildWorlds.jar содержит только основной плагин, без тестовых классов.
Установка через обычный перезапуск сервера, не /reload.
Состояние и признаки владения мирами: plugins/ApexBuildWorlds/state.yml.
Перед откатом сохраняйте этот каталог вместе с тремя мирами. Существующие миры не удаляются автоматически.

## Изолированная проверка

В каталог offline fixture assets положить Alley.jar, ApexTierTests.jar, ProtocolLib.jar,
BedWars1058.jar, WorldEdit.jar, FastAsyncWorldEdit.jar, kits.yml, bw_amazon.yml и bw_amazon.zip.
Нужен отдельный MongoDB на 127.0.0.1:27019; тестовая база ApexSocialIsolatedTests.
`python3 minecraft-buildworlds-plugin/tests/setup_server.py --assets /path/to/assets`
`python3 minecraft-buildworlds-plugin/tests/run_server.py`
Повторный запуск проверяет сохранность пользовательской постройки после полного рестарта.
Сервер слушает только 127.0.0.1:25596. API сайта заменён loopback-адресом, авторизация - тестовой заглушкой.
