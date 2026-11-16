# APEX Violet

Система оформления для стандартного Minecraft 1.8 без обязательного ресурспака.
Палитра: `§5/§d` — акцент, `§f` — значения, `§7/§8` — подписи и разделители;
тёмные стеклянные рамки (data 15) и фиолетовые углы (data 10).
Цвета рангов, команд и состояний остаются смысловыми.

## Состав

- `ApexUi` — оформление пустых ячеек виртуальных меню, защита декоративных элементов;
- `ApexUiPackets` — клиентские заголовки через ProtocolLib без переименования сторонних серверных меню;
- `apex_theme.py` — идемпотентная замена UI-констант собственных плагинов;
- `style_configs.py` — round-trip YAML для отображаемых строк и скорбордов;
- `install_theme.py` — проверка SHA256, полный бэкап остановленного сервера и установка с восстановлением;
- справочник: раздел 17.24 технической книги.

Для генерации YAML нужен `ruamel.yaml>=0.18,<0.20`.
Исходные/готовые серверные конфиги и бинарники остаются вне Git; в архиве поставки
есть `manifest.json` с путями и хэшами каждого файла. Генератор не трогает команды,
права, цены, материалы, слоты и числовые настройки.

```bash
python3 ops/theme/style_configs.py /path/original-configs /path/themed-configs
python3 ops/theme/apex_theme.py original-owned-plugin.jar themed-owned-plugin.jar
python3 -m unittest discover -s ops/theme -p 'test_*.py' -v
```

После подготовки архива на сервере:

```bash
python3 install_theme.py apply <полный-SHA-коммита> --bundle /path/release.tar.gz
python3 install_theme.py rollback <полный-SHA-коммита>
```

Полный бэкап: `/opt/minecraft/ci/ui/<SHA>/server.tar.gz`.
Rollback восстанавливает только изменённые UI-файлы, не миры/прогресс.
Основной JAR последующих обновлений сохраняет тему через `build-fastbridge-patch.sh`.
В GitHub Actions выбирайте `source_ref=feature/apex-ui`, `plugins=tiertests`.

Проверено на отдельном WindSpigot с настоящим ProtocolLib и двумя локальными
клиентами: оформление OPEN_WINDOW, защита рамок, сохранение кнопок, магазинов,
редакторов, физического сундука, рангов и прежних функций FastBridge/BattleStyle.
