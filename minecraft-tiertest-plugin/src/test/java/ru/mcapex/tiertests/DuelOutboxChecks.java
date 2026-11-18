package ru.mcapex.tiertests;

import com.google.gson.JsonObject;
import java.io.*;
import java.nio.file.*;
import java.util.*;
import java.util.concurrent.atomic.*;

/** Independent delivery checks; uses only temporary files and injected delivery/executor/clock. */
public final class DuelOutboxChecks {
    static int checks;
    static void check(boolean value,String message){checks++;if(!value)throw new AssertionError(message);}
    static JsonObject result(String key){JsonObject body=new JsonObject();body.addProperty("match_key",key);body.addProperty("winner","Alice");return body;}
    public static void main(String[] args)throws Exception{
        File root=Files.createTempDirectory("apex-duel-outbox-").toFile();
        try{
            AtomicLong now=new AtomicLong(100000);AtomicInteger delivered=new AtomicInteger();
            DuelOutbox first=new DuelOutbox(new File(root,"restart"),Runnable::run,body->{throw new IOException("site down");},now::get);
            check(first.offer(result("match-one")),"durable offer succeeds");check(new File(first.directory,"match-one.json").isFile(),"file exists before delivery");
            first.flush();check(new File(first.directory,"match-one.json").isFile(),"HTTP failure keeps file");check(first.failures.get("match-one.json")==1,"failed delivery tracked");
            first.flush();check(first.failures.get("match-one.json")==1,"backoff avoids tight retry");
            DuelOutbox restarted=new DuelOutbox(first.directory,Runnable::run,body->delivered.incrementAndGet(),now::get);restarted.flush();
            check(delivered.get()==1&&!new File(first.directory,"match-one.json").exists(),"restart recovers and deletes acknowledged result");restarted.flush();check(delivered.get()==1,"acknowledged file not resent");
            Queue<Runnable> work=new ArrayDeque<>();DuelOutbox parallel=new DuelOutbox(new File(root,"parallel"),work::add,body->delivered.incrementAndGet(),now::get);
            for(int i=0;i<3;i++)check(parallel.offer(result("batch-"+i)),"queued batch result "+i);
            parallel.flush();check(work.size()==2&&parallel.inFlight.size()==2,"at most two outstanding HTTP requests");parallel.flush();check(work.size()==2,"flush does not duplicate in-flight result");
            work.remove().run();parallel.flush();check(work.size()==2,"slot reused for pending third result");while(!work.isEmpty())work.remove().run();check(parallel.directory.listFiles().length==0,"all queued results delivered");
            DuelOutbox corrupt=new DuelOutbox(new File(root,"corrupt"),Runnable::run,body->delivered.incrementAndGet(),now::get);Files.write(new File(corrupt.directory,"aaa.json").toPath(),new byte[]{1});corrupt.offer(result("healthy"));int before=delivered.get();corrupt.flush();check(delivered.get()==before+1,"bad file does not starve healthy result");check(new File(corrupt.directory,"aaa.json").exists(),"bad file retained for recovery");
            File block=new File(corrupt.directory,"blocked.tmp");check(block.mkdir(),"write failure fixture created");check(!corrupt.offer(result("blocked")),"disk write failure is reported, not acknowledged");
            boolean rejected=false;try{corrupt.offer(result("../escape"));}catch(IllegalArgumentException ex){rejected=true;}check(rejected,"result key cannot escape directory");
            System.out.println(checks+" durable duel outbox checks passed");
        }finally{try(java.util.stream.Stream<Path> paths=Files.walk(root.toPath())){paths.sorted(Comparator.reverseOrder()).forEach(path->{try{Files.delete(path);}catch(IOException ex){throw new UncheckedIOException(ex);}});}}
    }
}
