#!/bin/bash
# Сборка без Maven: javac против Bukkit API (ядро сервера), Alley и BedWars1058.
# Нужны ~/work/alley/Spigot.jar, ~/work/alley/Alley.jar и ~/work/bw1058/bw.jar (скачать с сервера, см. УСТАНОВКА.md).
set -e
export PATH=$HOME/.local/temurin-jdk/bin:$PATH
cd "$(dirname "$0")"
LIBS=${LIBS:-$HOME/work/alley}
BW=${BW:-$HOME/work/bw1058/bw.jar}
OUT=target/classes
rm -rf target && mkdir -p $OUT
javac -encoding UTF-8 --release 8 -nowarn -cp "$LIBS/Spigot.jar:$LIBS/Alley.jar:$BW" -d $OUT $(find src/main/java -name '*.java')
cp -r src/main/resources/* $OUT/
jar cf target/ApexTierTests.jar -C $OUT .
echo "OK target/ApexTierTests.jar"
