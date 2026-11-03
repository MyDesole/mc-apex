#!/bin/bash
# Сборка без Maven: javac против Bukkit API (ядро сервера) и Alley.
# Нужны ~/work/alley/Spigot.jar и ~/work/alley/Alley.jar (скачать с сервера, см. УСТАНОВКА.md).
set -e
export PATH=$HOME/.local/temurin-jdk/bin:$PATH
cd "$(dirname "$0")"
LIBS=${LIBS:-$HOME/work/alley}
OUT=target/classes
rm -rf target && mkdir -p $OUT
javac -encoding UTF-8 --release 8 -nowarn -cp "$LIBS/Spigot.jar:$LIBS/Alley.jar" -d $OUT $(find src/main/java -name '*.java')
cp -r src/main/resources/* $OUT/
jar cf target/ApexTierTests.jar -C $OUT .
echo "OK target/ApexTierTests.jar"
