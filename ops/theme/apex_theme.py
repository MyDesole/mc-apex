"""APEX Violet: transform presentation constants, never bytecode or game identifiers."""
import re,struct,zipfile,json,hashlib,argparse

TITLES={
 '§8Выбор лобби':'§8APEX §5/ §8Режимы',
 '§6§lМеню практики':'§8APEX §5/ §8Дуэли',
 '§0Донат-меню':'§8APEX §5/ §8Профиль',
 '§8Fastbridge: карта':'§8APEX §5/ §8Дистанция',
 '§8Fastbridge: реплеи':'§8APEX §5/ §8Реплеи',
 '§8Fastbridge: настройки':'§8APEX §5/ §8Настройки',
 '§8Fastbridge: история':'§8APEX §5/ §8История',
 '§8Стиль боя':'§8APEX §5/ §8Стиль боя',
 '§8Тир-тесты':'§8APEX §5/ §8Тир-тесты',
 '§8Настройка теста':'§8APEX §5/ §8Настройка теста',
 '§8Выбор команды':'§8APEX §5/ §8Команды',
 '§6§lБой с ботом':'§8APEX §5/ §8Тренировка',
 '§6§lБот: уровень':'§8APEX §5/ §8Сложность',
 '§8КВ: где играем':'§8APEX §5/ §8Клановые войны',
 '§8КВ: выбор дуэлей':'§8APEX §5/ §8Наборы КВ',
}
HEADERS={
 '§6§lAPEX':'§d§lAPEX',
 '§6§lAPEX §f§lПРАКТИКА':'§d§lAPEX §8· §fДУЭЛИ',
 '§6§lBEDWARS':'§d§lAPEX §8· §fBEDWARS',
 '§6§lFASTBRIDGE':'§d§lAPEX §8· §fFASTBRIDGE',
 '§6mc-apex.ru':'§8mc-apex.ru', '§emc-apex.ru':'§8mc-apex.ru',
}
PROTECT=re.compile(r'VIP|MVP|LEGEND|\{p\}|\[Герой\]|\[Легенда\]|\[Мастер\]|\[Король|\[Дракон',re.I)

def text(value):
 if re.match(r'^&[0-9a-fk-or]',value,re.I):
  normalized=re.sub(r'&([0-9a-fk-or])',r'§\1',value,flags=re.I)
  return text(normalized).replace('§','&')
 if value in HEADERS:return HEADERS[value]
 for before,after in TITLES.items():
  if value==before or value.startswith(before+' §8· '):return value.replace(before,after,1)
 if '§' not in value or PROTECT.search(value) or len(value)>4096:return value
 plain=re.sub('§[0-9a-fk-or]','',value,flags=re.I)
 if len(plain.strip())<2 and value!='§6§l':return value
 if re.search(r'красн|сини[ейх]|зелён|желт|голуб|{team|{Team|{player-color}',value,re.I):return value
 value=re.sub('§e(?=ЛКМ|ПКМ|Shift|Нажми|Нажать|Клик)','§d',value)
 return value.replace('§6','§d').replace('§e','§f').replace('§b','§f')

SIZES={3:4,4:4,5:8,6:8,7:2,8:2,9:4,10:4,11:4,12:4,15:3,16:2,17:4,18:4,19:2,20:2}
def class_file(data):
 assert data[:4]==b'\xca\xfe\xba\xbe'
 count=struct.unpack_from('>H',data,8)[0];out=bytearray(data[:10]);offset=10;i=1;changes=0
 while i<count:
  tag=data[offset];offset+=1;out.append(tag)
  if tag==1:
   size=struct.unpack_from('>H',data,offset)[0];offset+=2;raw=data[offset:offset+size];offset+=size
   try:value=raw.decode('utf8','surrogatepass');updated=text(value).encode('utf8','surrogatepass')
   except UnicodeDecodeError:updated=raw # Modified-UTF8 NULs remain untouched.
   assert len(updated)<65536
   changes+=updated!=raw;out.extend(struct.pack('>H',len(updated)));out.extend(updated)
  else:
   size=SIZES[tag];out.extend(data[offset:offset+size]);offset+=size
   if tag in (5,6):i+=1
  i+=1
 out.extend(data[offset:]);return bytes(out),changes

def jar(source,destination):
 changes={}
 with zipfile.ZipFile(source) as original,zipfile.ZipFile(destination,'w',zipfile.ZIP_DEFLATED) as output:
  for entry in original.infolist():
   data=original.read(entry.filename)
   if entry.filename.startswith(('ru/mcapex/','local/')) and entry.filename.endswith('.class') and not entry.filename.split('/')[-1].startswith(('ApexUi','UiTheme')):
    data,n=class_file(data)
    if n:changes[entry.filename]=n
   output.writestr(entry,data)
 return changes

if __name__=='__main__':
 parser=argparse.ArgumentParser();parser.add_argument('source');parser.add_argument('destination');args=parser.parse_args()
 print(json.dumps(jar(args.source,args.destination),ensure_ascii=False))
