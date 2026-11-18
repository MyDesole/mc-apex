from pathlib import Path
import subprocess,zipfile,shutil,os,argparse
parser=argparse.ArgumentParser(description='Prepare a local native integration fixture; never contacts production')
parser.add_argument('--assets',type=Path,required=True)
parser.add_argument('--server-jar',type=Path,default=Path(os.environ.get('SERVER_JAR','/home/nikita/work/alley/Spigot.jar')))
args=parser.parse_args();assets=args.assets.resolve()
r=Path(__file__).resolve().parents[2];s=r/'work/social-server';plugins=s/'plugins';plugins.mkdir(parents=True,exist_ok=True)
for name in ['FastAsyncWorldEdit.jar','WorldEdit.jar','Alley.jar','BedWars1058.jar']:
 shutil.copy2(assets/name,plugins/name)
shutil.copy2(args.server_jar,s/'server.jar')
for folder in ['Alley/database','Alley/storage','BedWars1058/Arenas','BedWars1058/Cache']:(plugins/folder).mkdir(parents=True,exist_ok=True)
(plugins/'Alley/database/database.yml').write_text("mongo:\n  uri: 'mongodb://127.0.0.1:27019'\n  database: 'ApexSocialIsolatedTests'\n")
with zipfile.ZipFile(assets/'Alley.jar') as z:
 (plugins/'Alley/settings.yml').write_text(z.read('settings.yml').decode().replace('auto-update: true','auto-update: false').replace('spawn: world:10.0:100.0:100.0:0.0:0.0','spawn: world:0.5:65.0:0.5:0.0:0.0'))
shutil.copy2(assets/'kits.yml',plugins/'Alley/storage/kits.yml')
shutil.copy2(assets/'bw_amazon.yml',plugins/'BedWars1058/Arenas/bw_amazon.yml')
shutil.copy2(assets/'bw_amazon.zip',plugins/'BedWars1058/Cache/bw_amazon.zip')
world=s/'bw_amazon'
if not world.exists():
 world.mkdir()
 with zipfile.ZipFile(plugins/'BedWars1058/Cache/bw_amazon.zip') as z:
  for entry in z.infolist():
   target=(world/entry.filename).resolve()
   assert target==world.resolve() or world.resolve() in target.parents
  z.extractall(world)
(s/'eula.txt').write_text('eula=true\n')
(s/'server.properties').write_text('server-ip=127.0.0.1\nserver-port=25596\nonline-mode=false\nnetwork-compression-threshold=-1\nspawn-protection=0\nlevel-type=FLAT\ngenerate-structures=false\nview-distance=3\nmax-players=6\nallow-flight=true\n')
src=r/'work/social-stubs';src.mkdir(exist_ok=True)
(src/'AuthStub.java').write_text('''package ru.mcapex.social.test;
public final class AuthStub extends org.bukkit.plugin.java.JavaPlugin {
 public final Manager manager=new Manager();
 public static final class Manager { public boolean isAuthenticated(org.bukkit.entity.Player p){return true;}public boolean bypasses(org.bukkit.entity.Player p){return false;} }
}''')
(src/'TierStub.java').write_text('''package ru.mcapex.social.test;
public final class TierStub extends org.bukkit.plugin.java.JavaPlugin {
 public static class Hub {public org.bukkit.Location hub,bedwars;}
 public static class Stats {public org.bukkit.configuration.file.YamlConfiguration yml=new org.bukkit.configuration.file.YamlConfiguration();}
 public static class Perks {public org.bukkit.configuration.file.YamlConfiguration data=new org.bukkit.configuration.file.YamlConfiguration();}
 public static class Bridge {public org.bukkit.World world;public java.util.Map<java.util.UUID,Object> sessions=new java.util.HashMap<>(),watches=new java.util.HashMap<>();public Stats stats=new Stats(),islandStats=new Stats(),legacyStats=new Stats();public Perks perks=new Perks();}
 public static class Api {public com.google.gson.JsonObject post(String path,com.google.gson.JsonObject data){return new com.google.gson.JsonObject();}}
 final Bridge bridge=new Bridge();public Hub hub(){return null;}public Bridge fastBridge(){return bridge;}public Api api(){return new Api();}
 public void onEnable(){getDataFolder().mkdirs();org.bukkit.configuration.file.YamlConfiguration y=new org.bukkit.configuration.file.YamlConfiguration();String[] ranks={"vip","mvp","mvp_plus","apex","legend"};for(String r:ranks){y.set("limits."+r+".presets",2);y.set("limits."+r+".replays",80);y.set("limits."+r+".days",30);}try{y.save(new java.io.File(getDataFolder(),"fastbridge-perks.yml"));}catch(Exception e){throw new RuntimeException(e);}}
}''')
(src/'TestBoot.java').write_text('''package ru.mcapex.social.test;
public final class TestBoot extends org.bukkit.plugin.java.JavaPlugin {
 public void onEnable(){org.bukkit.Bukkit.getScheduler().runTaskLater(this,()->{try{org.bukkit.plugin.Plugin p=org.bukkit.Bukkit.getPluginManager().getPlugin("ApexSocial");Class.forName("ru.mcapex.social.SocialServerChecks",true,p.getClass().getClassLoader()).getMethod("start",org.bukkit.plugin.java.JavaPlugin.class).invoke(null,this);}catch(Throwable e){getLogger().log(java.util.logging.Level.SEVERE,"SOCIAL_TEST_FAIL",e);}},40L);}
}''')
jdk=str(Path(os.environ.get('JAVA_HOME','/home/nikita/dev/tools/jdk/17'))/'bin')+'/';classes=src/'classes';classes.mkdir(exist_ok=True)
subprocess.run([jdk+'javac','--release','8','-cp',str(s/'server.jar'),'-d',str(classes),*[str(p) for p in src.glob('*.java')]],check=True)
for name,main,deps in [('ApexAuth','AuthStub',[]),('ApexTierTests','TierStub',[]),('SocialTestBoot','TestBoot',['ApexSocial'])]:
 with zipfile.ZipFile(plugins/(name+'.jar'),'w',zipfile.ZIP_DEFLATED) as z:
  for f in classes.rglob('*.class'):
   if f.stem==main or f.stem.startswith(main+'$'):z.write(f,str(f.relative_to(classes)))
  z.writestr('plugin.yml','name: '+name+'\nversion: 0.0.0-test-only\nmain: ru.mcapex.social.test.'+main+'\ndepend: '+str(deps)+'\n')
print('Isolated native server prepared; test-only auth stub, isolated Mongo on port 27019; no production secrets copied')
