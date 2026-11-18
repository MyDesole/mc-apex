#!/usr/bin/env bash
set -euo pipefail
# Usage: run-admission.sh SPIGOT_API_JAR WINDSPIGOT_JAR GUARDED_TIER_JAR
ROOT=$(cd "$(dirname "$0")/.." && pwd)
CP="$1:$2:$ROOT/target/ApexAuth-1.0.0.jar:$3"
OUT=$(mktemp -d)
trap 'rm -rf -- "$OUT"' EXIT
JAVAC=${JAVA_HOME:+$JAVA_HOME/bin/}javac
JAVA=${JAVA_HOME:+$JAVA_HOME/bin/}java
"$JAVAC" -encoding UTF-8 -cp "$CP" -d "$OUT" "$ROOT/tests/AdmissionChecks.java"
"$JAVA" -cp "$OUT:$CP" ru.mcapex.auth.AdmissionChecks
