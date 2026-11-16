import os,pathlib,socket,struct,subprocess,threading,time,sys,shutil,json
ROOT=pathlib.Path(sys.argv[1]).resolve()
ROOT.mkdir(parents=True,exist_ok=True)
def varint(v):
 b=bytearray()
 while True:
  p=v&127;v>>=7;b.append(p|(128 if v else 0))
  if not v:return bytes(b)
def string(s):
 b=s.encode();return varint(len(b))+b
def send(s,data):s.sendall(varint(len(data))+data)
def readvar(s):
 value=0
 for i in range(5):
  b=s.recv(1)
  if not b:raise EOFError()
  value|=(b[0]&127)<<(7*i)
  if not b[0]&128:return value
 raise ValueError('varint')
CAPTIONS=[]
def packet_var(data,pos):
 value=0
 for i in range(5):
  b=data[pos];pos+=1;value|=(b&127)<<(7*i)
  if not b&128:return value,pos
 raise ValueError('packet varint')
def client(name):
 try:
  s=socket.create_connection(('127.0.0.1',25595),timeout=10);s.settimeout(2)
  send(s,b'\x00'+varint(47)+string('localhost')+struct.pack('>H',25595)+varint(2))
  send(s,b'\x00'+string(name));playing=False
  while True:
   try:n=readvar(s)
   except socket.timeout:
    if playing:send(s,b'\x03\x01')
    continue
   data=bytearray()
   while len(data)<n:
    chunk=s.recv(n-len(data))
    if not chunk:raise EOFError()
    data.extend(chunk)
   if not playing and data[0]==2:
    playing=True
    pass
   elif playing and data[0]==0:send(s,bytes(data))
   elif playing and data[0]==0x2d:
    pos=2
    n,pos=packet_var(data,pos);pos+=n
    n,pos=packet_var(data,pos)
    title=json.loads(data[pos:pos+n].decode('utf8'))
    CAPTIONS.append(title if isinstance(title,str) else title.get('text',''))
 except (EOFError,OSError):pass
log=(ROOT/'test-console.log').open('w')
java=shutil.which('java')
p=subprocess.Popen([java,'-Xms256M','-Xmx1024M','-Dfile.encoding=UTF-8','-jar','server.jar','nogui'],cwd=ROOT,stdin=subprocess.PIPE,stdout=log,stderr=subprocess.STDOUT,text=True)
try:
 started=False
 for i in range(180):
  time.sleep(1)
  text=(ROOT/'test-console.log').read_text(errors='replace')
  if 'FB_TEST_FAIL' in text:raise RuntimeError('Integration test failed; see test-console.log')
  if not started and 'Done (' in text:
   started=True
   for name in ('FBTestOwner','FBTestOther'):threading.Thread(target=client,args=(name,),daemon=True).start()
  if 'FB_TEST_PASS' in text:
   print(next(line for line in text.splitlines() if 'FB_TEST_PASS' in line))
   assert any('APEX /' in x.replace('§8','').replace('§5','') for x in CAPTIONS), 'No themed window packet received'
   print('CLIENT_CAPTION_TEST_PASS',len(CAPTIONS),'actual OPEN_WINDOW captions received')
   break
  if p.poll() is not None:raise RuntimeError('Test server exited early')
 else:raise RuntimeError('Test server timeout')
finally:
 if p.poll() is None:
  p.stdin.write('stop\n');p.stdin.flush()
  try:p.wait(timeout=40)
  except subprocess.TimeoutExpired:p.terminate();p.wait(timeout=10)
 log.close()
