package ru.mcapex.tiertests;
import java.lang.reflect.*;import java.nio.file.*;import java.io.*;import java.util.*;
import dev.revere.alley.feature.match.internal.types.DefaultMatch;
public final class IntegrationHistoryChecks {
 static void check(boolean ok,String why){if(!ok)throw new AssertionError(why);}
 public static int run(DefaultMatch match,Runnable ordinary)throws Exception{
  Class<?> u=Class.forName("sun.misc.Unsafe");Field f=u.getDeclaredField("theUnsafe");f.setAccessible(true);
  Integrations integration=(Integrations)u.getMethod("allocateInstance",Class.class).invoke(f.get(null),Integrations.class);
  Field tracked=Integrations.class.getDeclaredField("tracked");tracked.setAccessible(true);Map<Object,Object> map=new HashMap<>();tracked.set(integration,map);
  Path dir=Files.createTempDirectory("apex-history-native-");DuelOutbox out=new DuelOutbox(dir.toFile(),Runnable::run,b->{});
  Field queue=Integrations.class.getDeclaredField("outbox");queue.setAccessible(true);queue.set(integration,out);
  Method scan=Integrations.class.getDeclaredMethod("scanMatches");scan.setAccessible(true);
  try{
   scan.invoke(integration);check(map.isEmpty(),"explicit private duel must never be tracked");
   ordinary.run();scan.invoke(integration);check(map.size()==1,"ordinary invitation with affectStatistics=false must be tracked");
   match.setWinner(match.getParticipantA());match.setLoser(match.getParticipantB());match.setStartTime(System.currentTimeMillis()-4000);match.setEndTime(System.currentTimeMillis());match.setState(dev.revere.alley.feature.match.MatchState.ENDING_ROUND);
   scan.invoke(integration);check(dir.toFile().listFiles().length==0,"intermediate round winner is not a match result");
   match.setWinner(match.getParticipantB());match.setLoser(match.getParticipantA());match.setState(dev.revere.alley.feature.match.MatchState.ENDING_MATCH);match.endMatch();
   scan.invoke(integration);File[] files=dir.toFile().listFiles();check(files.length==1,"winner set between polls must survive native match removal");
   String text=new String(Files.readAllBytes(files[0].toPath()),java.nio.charset.StandardCharsets.UTF_8);check(text.contains("\"winner\":\"SocialGuest\"")&&text.contains("\"loser\":\"SocialOwner\""),"final winner replaces intermediate round winner");
   scan.invoke(integration);check(dir.toFile().listFiles().length==1,"no duplicate result on subsequent scan");return 6;
  }finally{for(File file:dir.toFile().listFiles())Files.delete(file.toPath());Files.delete(dir);}
 }
}
