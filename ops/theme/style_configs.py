"""Generate guarded, cosmetic-only APEX config changes; keep round-trip YAML comments."""
import argparse,copy,hashlib,json,re,sys
from pathlib import Path
from ruamel.yaml import YAML

COLOR=re.compile(r'[&§][0-9a-fk-or]',re.I)
TITLE='&d&lAPEX &8· &fBEDWARS'
def recolor(s):
 if re.search(r'\{team|\{Team|\{player-color}|красн|сини[ейх]|зелён|голуб|VIP|MVP|LEGEND',s,re.I):return s
 s=s.replace('&6&l│ ','&8› ').replace('&6│ ','&8› ')
 s=s.replace('&6&l','&d&l').replace('&6','&d').replace('§6','§d')
 s=re.sub(r'&e(?=ЛКМ|ПКМ|Shift|Нажми|Нажать|Клик)','&d',s)
 return s.replace('&e','&f').replace('&b','&f')
def walk(obj,path=()):
 if isinstance(obj,dict):
  for key,value in list(obj.items()):
   if any(k in str(key).lower() for k in ('permission','command','material','durability','slot','uuid','cost','price','sound')):continue
   if any(k in str(key).lower() for k in ('permission','command','material','durability','item','slot','team-displays','color','sound','click','npc')):
    if not isinstance(value,(dict,list)):continue
    if str(key) in ('team-displays',):continue
   if isinstance(value,str):
    if not re.search(r'[&§][0-9a-f]',value,re.I):continue
    if key=='title':
     plain=COLOR.sub('',value)
     branded='&8APEX &5/ &8'+plain
     obj[key]=branded if len(branded)<=32 else recolor(value)
    else:obj[key]=recolor(value)
   else:walk(value,path+(str(key),))
 elif isinstance(obj,list):
  for i,value in enumerate(obj):
   if isinstance(value,str):obj[i]=recolor(value)
   else:walk(value,path+(str(i),))

def leaves(obj,path=()):
 if isinstance(obj,dict):
  for k,v in obj.items():yield from leaves(v,path+(str(k),))
 elif isinstance(obj,list):
  for i,v in enumerate(obj):yield from leaves(v,path+(i,))
 else:yield path,obj

def apply(name,data):
 if name.endswith('CleanMOTD/config.yml'):
  data['motd']['motds']=['&d&lAPEX &8· &fСеть PvP &8[&71.8/26.3&8]\n&7Дуэли &8/ &7BedWars &8/ &7FastBridge']
  return
 if name.endswith('TAB/config.yml'):
  layout=data['header-footer']['designs']['default']
  layout['header']=['','&d&lA P E X','&7Дуэли &8/ &7BedWars &8/ &7FastBridge','']
  layout['footer']=['','&7Играть &fapexpvp.pro &8· &7Сайт &fmc-apex.ru','']
  return
 if name.endswith('TAB/animations.yml') or name.endswith('BedWarsLobbyMenu/config.yml'):return
 walk(data)
 if name.endswith('Alley/providers/scoreboard.yml'):
  board=data['scoreboard'];board['title']['text']='&d&lAPEX &8· &fДУЭЛИ';board['title']['animated']=False
  board['title']['animation']=['&d&lAPEX &8· &fДУЭЛИ']
  board['sidebar-format']='&8&m------------------'
  board['footer-addition']=['','&8mc-apex.ru']
 if '/BedWars1058/Languages/' in name:
  russian=name.endswith('messages_ru.yml')
  data['prefix']='&dAPEX &8› &r'
  for group,modes in data.get('sidebar',{}).items():
   if not isinstance(modes,dict):continue
   for phase,variants in modes.items():
    if not isinstance(variants,dict):continue
    for role,old in list(variants.items()):
     if not isinstance(old,list):continue
     if phase in ('waiting','starting'):
      status=('&7До старта &d{time} сек' if russian else '&7Starting in &d{time}s') if phase=='starting' else ('&dОжидание игроков' if russian else '&dWaiting for players')
      rows=[TITLE,'','&8'+('КАРТА' if russian else 'MAP'),'&f{map}','','&7'+('Игроки' if russian else 'Players')+' &f{on}&8/&f{max}','&7'+('Режим' if russian else 'Mode')+' &f{group}','',status,'','&8mc-apex.ru']
      if role=='spectator':rows.insert(2,'&7'+('Наблюдение' if russian else 'Spectating'))
     elif phase=='playing':
      team_count=sum(1 for line in old if line=='{team}')
      rows=[TITLE,'&7{nextEvent} &8· &f{time}','']+['{team}']*team_count+['','&8mc-apex.ru']
      if role=='spectator':rows.insert(1,'&7'+('Наблюдение' if russian else 'Spectating')+' &f{spectatorTarget}')
      assert len(rows)-1<=15,(group,phase,role,'too many scoreboard rows')
     else:
      rows=list(old)
      if rows:rows[0]=TITLE
     variants[role]=rows
  for k in ('arena-selector-gui-name','spectator-menu-gui-name','shop-gui-name','team-upgrades-gui-name'):
   if k in data and isinstance(data[k],str):
    plain=COLOR.sub('',data[k]);value='&8APEX &5/ &8'+plain
    if len(value)<=32:data[k]=value

def main():
 parser=argparse.ArgumentParser();parser.add_argument('original');parser.add_argument('output');args=parser.parse_args()
 original=Path(args.original);output=Path(args.output);yaml=YAML();yaml.preserve_quotes=True;yaml.width=130
 manifest=[]
 for src in sorted(original.rglob('*.yml')):
  name=src.relative_to(original).as_posix();data=yaml.load(src);before=copy.deepcopy(data);apply(name,data)
  if data==before:continue
  # Business values, commands, item ids, permissions and slot coordinates must be identical.
  old=dict(leaves(before));new=dict(leaves(data));changes=[]
  for path,value in old.items():
   if path not in new:continue
   updated=new[path]
   if value==updated:continue
   if isinstance(value,(int,float,bool)):assert isinstance(value,bool) and path==('scoreboard','title','animated'),path
   protected=any(token in str(path[-1]).lower() for token in ('command','permission','slot','durability','material','price','cost','uuid'))
   assert not protected,path
   if not any(k in path for k in ('sidebar','header-footer','title','animation','footer-addition')):
    assert sorted(re.findall(r'\{[^{}]+\}',str(value)))==sorted(re.findall(r'\{[^{}]+\}',str(updated))),path
   changes.append({'path':list(path),'before':value,'after':updated})
  dst=output/name;dst.parent.mkdir(parents=True,exist_ok=True)
  with dst.open('w',encoding='utf8') as f:yaml.dump(data,f)
  assert yaml.load(dst)==data
  manifest.append({'path':name,'before_sha256':hashlib.sha256(src.read_bytes()).hexdigest(),'after_sha256':hashlib.sha256(dst.read_bytes()).hexdigest(),'changed_values':len(changes)})
 output.mkdir(parents=True,exist_ok=True);(output/'manifest.json').write_text(json.dumps(manifest,ensure_ascii=False,indent=2),encoding='utf8')
 print(json.dumps(manifest,ensure_ascii=False,indent=2))
if __name__=='__main__':main()
