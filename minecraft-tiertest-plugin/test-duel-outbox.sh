#!/usr/bin/env bash
set -euo pipefail
ROOT=$(cd "$(dirname "$0")" && pwd)
JAVA_HOME=${JAVA_HOME:-/home/nikita/dev/tools/jdk/17}
SERVER_JAR=${SERVER_JAR:-/home/nikita/work/alley/Spigot.jar}
OUT=$(mktemp -d)
trap 'rm -rf -- "$OUT"' EXIT
"$JAVA_HOME/bin/javac" --release 8 -encoding UTF-8 -cp "$SERVER_JAR" -d "$OUT" \
  "$ROOT/src/main/java/ru/mcapex/tiertests/DuelOutbox.java" \
  "$ROOT/src/test/java/ru/mcapex/tiertests/DuelOutboxChecks.java"
"$JAVA_HOME/bin/java" -cp "$OUT:$SERVER_JAR" ru.mcapex.tiertests.DuelOutboxChecks
