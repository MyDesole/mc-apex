#!/usr/bin/env bash
# Preserve every non-FastBridge class/resource from the exact installed baseline.
set -euo pipefail
export PATH="$HOME/.local/temurin-jdk/bin:$PATH"
cd "$(dirname "$0")"
LIBS=${LIBS:-$HOME/work/alley}
BASE_JAR=${BASE_JAR:-$LIBS/ApexTierTests-base.jar}
test -f "$BASE_JAR" || { echo 'Set BASE_JAR to the installed ApexTierTests JAR' >&2; exit 1; }
mkdir -p target/fastbridge-classes
javac --release 8 -proc:none -nowarn -encoding UTF-8 -sourcepath /nonexistent \
  -cp "$BASE_JAR:$LIBS/Spigot.jar:$LIBS/Alley.jar" -d target/fastbridge-classes \
  src/main/java/ru/mcapex/tiertests/{FastBridge,Replay,FbPerks}.java
export BASE_JAR
python3 - <<'PY'
from pathlib import Path
import hashlib,json,os,zipfile
baseline=Path(os.environ['BASE_JAR'])
classes=Path('target/fastbridge-classes')
overlay={str(p.relative_to(classes)):p.read_bytes() for p in classes.rglob('*.class')}
manifest={'baseline_sha256':hashlib.sha256(baseline.read_bytes()).hexdigest(),'scope':'FastBridge, Replay, FbPerks'}
overlay['META-INF/apex-fastbridge-release.json']=json.dumps(manifest).encode()
with zipfile.ZipFile(baseline) as source, zipfile.ZipFile('target/ApexTierTests.jar','w',zipfile.ZIP_DEFLATED) as output:
 for entry in source.infolist():
  if entry.filename not in overlay:output.writestr(entry,source.read(entry.filename))
 for name,data in overlay.items():output.writestr(name,data)
print('OK target/ApexTierTests.jar; baseline SHA256='+manifest['baseline_sha256'])
PY
