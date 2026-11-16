#!/usr/bin/env python3
"""Guarded one-time UI rollout. Restores presentation files only on rollback."""
import argparse,fcntl,hashlib,json,os,re,shutil,subprocess,tarfile,time
from pathlib import Path

SERVER=Path('/opt/minecraft/server')
ROOT=Path('/opt/minecraft/ci/ui')
ALLOWED={
 'plugins/ApexTierTests-Tactics.jar','plugins/ApexClanWars.jar','plugins/BedWarsLobbyMenu.jar',
 'plugins/ModeOnlineHolograms_v3.jar','plugins/TopHolograms_v2.jar',
 'plugins/Alley/menus.yml','plugins/Alley/providers/scoreboard.yml',
 'plugins/Alley/messages/global-messages.yml','plugins/Alley/messages/game-messages.yml',
 'plugins/BedWars1058/Languages/messages_ru.yml','plugins/BedWars1058/Languages/messages_en.yml',
 'plugins/TAB/config.yml','plugins/CleanMOTD/config.yml','plugins/CleanMOTD/messages.yml'
}
def sha(path):return hashlib.sha256(path.read_bytes()).hexdigest()
def panel(action):subprocess.run(['bash',str(SERVER/'apex-panel.sh'),action],check=True)
def start_and_check():
 panel('start')
 for _ in range(90):
  try:text=(SERVER/'logs/latest.log').read_text(errors='replace')
  except FileNotFoundError:text=''
  if 'Done (' in text and 'ApexUi: APEX Violet' in text:return
  time.sleep(2)
 raise RuntimeError('New UI did not finish startup')
def replace(src,dst):
 before=dst.stat();temp=dst.with_name(dst.name+'.apex-ui-new')
 shutil.copyfile(src,temp);os.chmod(temp,before.st_mode&0o777);os.chown(temp,before.st_uid,before.st_gid);os.replace(temp,dst)

def main():
 p=argparse.ArgumentParser();p.add_argument('action',choices=['apply','rollback']);p.add_argument('release');p.add_argument('--bundle');args=p.parse_args()
 if not re.fullmatch('[0-9a-f]{40}',args.release):raise ValueError('Expected commit SHA')
 ROOT.mkdir(parents=True,exist_ok=True,mode=0o700)
 with (ROOT/'lock').open('w') as lock:
  fcntl.flock(lock,fcntl.LOCK_EX)
  release=ROOT/args.release;backup=release/'backup'
  if args.action=='apply':
   if release.exists():raise ValueError('Release exists; use rollback or a new commit')
   release.mkdir(mode=0o700);staged=release/'files';staged.mkdir()
   with tarfile.open(args.bundle,'r:gz') as tar:
    seen=set()
    for member in tar.getmembers():
     if member.name not in ALLOWED|{'manifest.json'} or not member.isfile() or member.name in seen or member.size>32*1024*1024:raise ValueError('Unexpected archive member')
     seen.add(member.name);dest=staged/member.name;dest.parent.mkdir(parents=True,exist_ok=True);dest.write_bytes(tar.extractfile(member).read())
   manifest=json.loads((staged/'manifest.json').read_text())
   assert {m['path'] for m in manifest}==seen-{'manifest.json'}
   for item in manifest:
    if item['path'] not in ALLOWED or sha(SERVER/item['path'])!=item['before_sha256']:raise ValueError('Source changed: '+item['path'])
    if sha(staged/item['path'])!=item['after_sha256']:raise ValueError('Bundle checksum mismatch')
   panel('stop');backup.mkdir(mode=0o700)
   try:
    # Save complete stopped server, plus exact changed files for non-destructive UI rollback.
    with tarfile.open(release/'server.tar.gz','w:gz') as tar:tar.add(SERVER,arcname='server')
    for item in manifest:
     src=SERVER/item['path'];dest=backup/item['path'];dest.parent.mkdir(parents=True,exist_ok=True);shutil.copy2(src,dest)
    for item in manifest:replace(staged/item['path'],SERVER/item['path'])
    (release/'manifest.json').write_text(json.dumps(manifest,indent=2))
    start_and_check()
   except Exception:
    panel('stop')
    for item in manifest:
     old=backup/item['path']
     if old.exists():replace(old,SERVER/item['path'])
    panel('start');raise
  else:
   manifest=json.loads((release/'manifest.json').read_text());panel('stop')
   try:
    for item in manifest:replace(backup/item['path'],SERVER/item['path'])
   finally:panel('start')
  print('APEX UI '+args.action+' completed: '+args.release)
if __name__=='__main__':main()
