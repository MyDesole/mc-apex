import math,os,shutil
S=os.path.expanduser('~/work/bw1058/stage'); shutil.rmtree(S,ignore_errors=True)
A=S+'/plugins/BedWars1058/Arenas'; os.makedirs(A)
maps={
 'frost':dict(src='x/frost/Frost',teams=[('RED',(0,72,60),(0.0,72.0,63.0)),('BLUE',(0,72,-60),(0.0,72.0,-63.0))],
   dia=[(-37.2,71.0,0.6),(-37.6,71.4,19.3),(37.2,71.0,-0.6),(38.0,71.3,-19.4)],
   em=[(0.0,76.2,13.7),(0.0,69.8,21.0),(0.0,69.8,-21.0),(0.0,76.2,-13.7)]),
 'aquarium':dict(src='x/aqua/Aquarium',teams=[('RED',(-48,87,0),(-51.0,87.0,0.0)),('BLUE',(48,87,0),(51.0,87.0,0.0)),('GREEN',(0,87,48),(0.0,87.0,51.0)),('YELLOW',(0,87,-48),(0.0,87.0,-51.0))],
   dia=[(-45.0,91.0,35.5),(-41.0,82.5,-39.0),(-35.5,91.0,-45.0),(35.5,91.0,45.0),(45.0,91.0,-35.5)],
   em=[(-11.8,105.2,-8.3),(9.8,105.2,8.3)]),
}
def loc(p,yaw=0): return "%s,%s,%s,%s,0"%(p[0],p[1],p[2],round(yaw,1))
def gen(bed,sp):
    # на 5 блоков дальше спавна от кровати (там сундуки базы)
    dx,dz=sp[0]-bed[0],sp[1-1+2]-bed[2]
    n=math.hypot(dx,dz) or 1
    return (round(bed[0]+dx/n*8,1),sp[1],round(bed[2]+dz/n*8,1))
def yaw_to_center(p):
    return math.degrees(math.atan2(p[0],-p[2])) if (p[0] or p[2]) else 0
def write(name,m,display,maxin,minp,group):
    L=["group: %s"%group,'display-name: "%s"'%display,"minPlayers: %d"%minp,"maxInTeam: %d"%maxin,"allowSpectate: true",
       "worldBorder: 400","y-kill-height: 30","max-build-y: 150","island-radius: 14"]
    t0=m['teams'][0][2]
    L+=["waiting:","  Loc: \"%s\""%loc((t0[0],t0[1]+0.5,t0[2]),yaw_to_center(t0)),"Team:"]
    for c,bed,sp in m['teams']:
        y=yaw_to_center(sp); n=c.capitalize()
        sh=(round(sp[0]+3*math.cos(math.radians(y)),1),sp[1],round(sp[2]+3*math.sin(math.radians(y)),1))
        up=(round(sp[0]-3*math.cos(math.radians(y)),1),sp[1],round(sp[2]-3*math.sin(math.radians(y)),1))
        L+=["  %s:"%n,"    Color: %s"%c,'    Spawn: "%s"'%loc(sp,y),'    Bed: "%s"'%loc(bed),'    Shop: "%s"'%loc(sh,y),'    Upgrade: "%s"'%loc(up,y),
            '    Iron: "%s"'%loc(gen(bed,sp)),'    Gold: "%s"'%loc(gen(bed,sp))]
    L.append("generator:")
    for k,v in (("Diamond",m['dia']),("Emerald",m['em'])):
        L.append("  %s:"%k)
        for p in v: L.append('    - "%s"'%loc(p))
    open(A+'/%s.yml'%name,'w',encoding='utf8').write("\n".join(L)+"\n")
    shutil.copytree(os.path.expanduser('~/work/maps/bw/'+m['src']),S+'/worlds/'+name,ignore=shutil.ignore_patterns('playerdata','stats','DIM*','WorldDownloader.txt','level.dat_old','session.lock','data'))
write('bw_frost',maps['frost'],'Frost (2 команды)',4,2,'Default')
write('bw_aquarium',maps['aquarium'],'Aquarium (4 команды)',3,2,'Default')
write('bw_cw',maps['frost'],'Клановая война',50,2,'Default')
os.makedirs(S+'/plugins/BedWars1058',exist_ok=True)
open(S+'/plugins/BedWars1058/config.yml','w').write("serverType: SHARED\n")
print(open(A+'/bw_frost.yml').read())
PY2=1