#!/usr/bin/env bash
set -euo pipefail
ROOT=$(cd "$(dirname "$0")" && pwd)
JAVA_HOME=${JAVA_HOME:-/home/nikita/dev/tools/jdk/17}
SERVER_JAR=${SERVER_JAR:-/home/nikita/work/alley/Spigot.jar}
ALLEY_JAR=${ALLEY_JAR:-$ROOT/../work/Alley.jar}
BEDWARS_JAR=${BEDWARS_JAR:-$ROOT/../work/BedWars.jar}
mkdir -p "$ROOT/target/classes"
find "$ROOT/src/main/java" -name '*.java' -print > "$ROOT/target/sources.txt"
"$JAVA_HOME/bin/javac" --release 8 -encoding UTF-8 -cp "$SERVER_JAR:$ALLEY_JAR:$BEDWARS_JAR" -d "$ROOT/target/classes" @"$ROOT/target/sources.txt"
cp "$ROOT/src/main/resources/plugin.yml" "$ROOT/target/classes/plugin.yml"
"$JAVA_HOME/bin/jar" cf "$ROOT/target/ApexSocial.jar" -C "$ROOT/target/classes" .
printf 'Built %s\n' "$ROOT/target/ApexSocial.jar"
