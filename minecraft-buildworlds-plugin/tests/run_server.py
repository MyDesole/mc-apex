"""Run isolated WindSpigot, real Alley/BW, and three protocol-47 players."""
import pathlib,socket,struct,subprocess,threading,time,zipfile,os
r=pathlib.Path(__file__).resolve().parents[2];root=r/'work/build-server';jdk=str(pathlib.Path(os.environ.get('JAVA_HOME','/home/nikita/dev/tools/jdk/17'))/'bin')+'/'
config=root/'plugins/BedWars1058/config.yml'
if config.exists():config.write_text(config.read_text().replace('serverType: MULTIARENA','serverType: SHARED').replace('game-restart: 45','game-restart: 8'))
# Match the existing production FAWE setting; no production configuration is changed.
fawe=root/'plugins/FastAsyncWorldEdit/config.yml'
if fawe.exists():fawe.write_text(fawe.read_text().replace('region-restrictions: true','region-restrictions: false'))
cp=':'.join(str(p) for p in [root/'server.jar',r/'work/build-assets/Alley.jar',r/'work/build-assets/BedWars1058.jar',r/'minecraft-buildworlds-plugin/target/ApexBuildWorlds.jar'])
classes=r/'minecraft-buildworlds-plugin/target/test-classes';classes.mkdir(exist_ok=True)
subprocess.run([jdk+'javac','--release','8','-encoding','UTF-8','-cp',cp,'-d',str(classes),str(pathlib.Path(__file__).with_name('BuildChecks.java'))],check=True)
with zipfile.ZipFile(r/'minecraft-buildworlds-plugin/target/ApexBuildWorlds.jar') as source,zipfile.ZipFile(root/'plugins/ApexBuildWorlds.jar','w',zipfile.ZIP_DEFLATED) as dest:
 for entry in source.infolist():dest.writestr(entry,source.read(entry.filename))
 for f in classes.rglob('*.class'):dest.write(f,str(f.relative_to(classes)))
def vi(n):
 out=bytearray()
 while True:
  b=n&127;n>>=7;out.append(b|(128 if n else 0))
  if not n:return bytes(out)
def string(v):b=v.encode();return vi(len(b))+b
def readvi(s):
 out=0
 for i in range(5):
  b=s.recv(1)
  if not b:raise EOFError()
  out|=(b[0]&127)<<(7*i)
  if b[0]<128:return out
 raise ValueError('varint')
def send(s,body):s.sendall(vi(len(body))+body)
def client(name):
 try:
  with socket.create_connection(('127.0.0.1',25596),timeout=15) as s:
   s.settimeout(1);send(s,b'\x00'+vi(47)+string('localhost')+struct.pack('>H',25596)+vi(2));send(s,b'\x00'+string(name));playing=False
   while True:
    try:length=readvi(s)
    except socket.timeout:
     if playing:send(s,b'\x03\x01')
     continue
    body=b''
    while len(body)<length:
     chunk=s.recv(length-len(body))
     if not chunk:raise EOFError()
     body+=chunk
    if not playing and body[0]==2:playing=True
    elif playing and body[0]==0:send(s,body)
    elif playing and body[0]==2 and name=='BuildAdmin':
     with (root/'test-client-chat.log').open('ab') as chat:chat.write(body[1:]+b'\n')
 except (OSError,EOFError):pass
log=(root/'test-console.log').open('w');p=subprocess.Popen([jdk+'java','-Xms256M','-Xmx1400M','-Dfile.encoding=UTF-8','-jar','server.jar','nogui'],cwd=root,stdin=subprocess.PIPE,stdout=log,stderr=subprocess.STDOUT,text=True)
try:
 joined=False
 for i in range(180):
  time.sleep(1);text=(root/'test-console.log').read_text(errors='replace')
  if 'BUILD_TEST_FAIL' in text or 'Encountered an unexpected exception' in text:raise RuntimeError('Native integration failed: '+str(root/'test-console.log'))
  if not joined and 'Done (' in text:
   joined=True
   for name in ['BuildAdmin','BuildGuest','BuildOther']:threading.Thread(target=client,args=(name,),daemon=True).start()
  if 'BUILD_TEST_PASS' in text:
   time.sleep(2)
   text=(root/'test-console.log').read_text(errors='replace')
   assert 'Encountered an unexpected exception' not in text and p.poll() is None,'Server must remain healthy after arena replacement'
   print(next(line for line in text.splitlines() if 'BUILD_TEST_PASS' in line));break
  if p.poll() is not None:raise RuntimeError('Native server exited early')
 else:raise RuntimeError('Native server timeout')
finally:
 if p.poll() is None:
  p.stdin.write('stop\n');p.stdin.flush()
  try:p.wait(timeout=35)
  except subprocess.TimeoutExpired:p.terminate();p.wait(timeout=10)
 log.close()
