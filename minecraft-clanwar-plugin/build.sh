#!/bin/bash
# Сборка без Maven. Нужны ~/work/alley/{Spigot,Alley}.jar и ~/work/bw1058/bw.jar
# (Spigot и Alley - с сервера, BedWars1058 - со SpigotMC).
set -e
export PATH=$HOME/.local/temurin-jdk/bin:$PATH
cd "$(dirname "$0")"
A=${LIBS:-$HOME/work/alley}
B=${BW:-$HOME/work/bw1058/bw.jar}
rm -rf target && mkdir -p target/classes
javac -encoding UTF-8 --release 8 -nowarn -cp "$A/Spigot.jar:$A/Alley.jar:$B" -d target/classes $(find src/main/java -name '*.java')
cp -r src/main/resources/* target/classes/
jar cf target/ApexClanWars.jar -C target/classes .
echo "OK target/ApexClanWars.jar"
