#!/usr/bin/env bash
set -euo pipefail
export PATH="$HOME/.local/temurin-jdk/bin:$PATH"
cd "$(dirname "$0")"
bash build-fastbridge-patch.sh
LIBS=${LIBS:-$HOME/work/alley}
BASE_JAR=${BASE_JAR:-$LIBS/ApexTierTests-base.jar}
BW=${BW:-$HOME/work/bw1058/bw.jar}
CP="target/fastbridge-classes:$BASE_JAR:$LIBS/Spigot.jar:$LIBS/Alley.jar:$BW:${PL:-$HOME/work/protocollib.jar}"
export LIBS BW
export BASE_JAR
mkdir -p target/tests
javac --release 8 -proc:none -nowarn -encoding UTF-8 -cp "$CP" -d target/tests src/test/java/ru/mcapex/tiertests/*.java
java -ea -cp "target/tests:$CP" ru.mcapex.tiertests.FbPerksTest
if [[ "${SERVER_TEST:-0}" != 1 ]]; then
  echo 'Storage tests passed. For real server/client integration: SERVER_TEST=1 SERVER_JAR=/path/WindSpigot.jar bash test-fastbridge.sh'
  exit 0
fi
TEST_ROOT=$(mktemp -d "$PWD/target/server-test-XXXXXX")
export TEST_ROOT
cp "${SERVER_JAR:?Set SERVER_JAR to the exact server core}" "$TEST_ROOT/server.jar"
python3 - <<'PY'
from pathlib import Path
import os,zipfile
root=Path(os.environ['TEST_ROOT'])
(root/'plugins').mkdir()
import shutil
shutil.copyfile(os.environ.get('PL',str(Path.home()/'work/protocollib.jar')),root/'plugins/ProtocolLib.jar')
(root/'eula.txt').write_text('eula=true\n')
(root/'server.properties').write_text('server-ip=127.0.0.1\nserver-port=25595\nonline-mode=false\nlevel-name=test-world\nlevel-type=FLAT\ngenerator-settings=2;0;1;\nspawn-protection=0\nview-distance=2\nnetwork-compression-threshold=-1\nmax-players=4\nallow-nether=false\n')
with zipfile.ZipFile(root/'plugins/FbServerTest.jar','w') as z:
 z.writestr('plugin.yml','name: ApexTierTests\nversion: test\nsoftdepend: [ProtocolLib]\nmain: ru.mcapex.tiertests.FbServerTest\ncommands:\n  fastbridge:\n    aliases: [fb]\n  style:\n    usage: /style\n')
 with zipfile.ZipFile('target/ApexTierTests.jar') as release:
  for entry in release.infolist():
   if entry.filename.endswith('.class') and any(entry.filename.split('/')[-1].startswith(n) for n in ('FastBridge','FbPerks','FbStats','FbHud','FbMap','Replay','Ghost','BattleStyle','ApexUi')):z.writestr(entry,release.read(entry.filename))
 for pattern in ('FbServerTest*.class','BattleStyleServerChecks*.class','ApexUiServerChecks*.class'):
  for p in Path('target/tests').rglob(pattern):z.write(p,p.relative_to('target/tests'))
 for archive in (Path(os.environ['LIBS'])/'Alley.jar',Path(os.environ['BW'])):
  with zipfile.ZipFile(archive) as api:
   for entry in api.infolist():
    if entry.filename.endswith('.class') and entry.filename not in z.namelist():z.writestr(entry,api.read(entry.filename))
PY
python3 src/test/run_server_test.py "$TEST_ROOT"
