#!/usr/bin/env bash
set -euo pipefail
ROOT=$(cd "$(dirname "$0")" && pwd)
JAVA_HOME=${JAVA_HOME:-/home/nikita/dev/tools/jdk/17}
SERVER_JAR=${SERVER_JAR:-/home/nikita/work/alley/Spigot.jar}
mkdir -p "$ROOT/target/classes"
"$JAVA_HOME/bin/javac" --release 8 -encoding UTF-8 -cp "$SERVER_JAR" -d "$ROOT/target/classes" "$ROOT/src/main/java/ru/mcapex/buildworlds/BuildWorlds.java"
cp "$ROOT/src/main/resources/plugin.yml" "$ROOT/target/classes/plugin.yml"
"$JAVA_HOME/bin/jar" cf "$ROOT/target/ApexBuildWorlds.jar" -C "$ROOT/target/classes" .
