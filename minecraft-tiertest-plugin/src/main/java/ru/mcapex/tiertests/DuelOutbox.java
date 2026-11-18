package ru.mcapex.tiertests;

import com.google.gson.JsonObject;
import com.google.gson.JsonParser;
import java.io.*;
import java.nio.charset.StandardCharsets;
import java.nio.file.*;
import java.util.*;
import java.util.concurrent.*;
import java.util.function.LongSupplier;

/** Durable, idempotent result delivery. Failed requests keep their file across server restarts. */
final class DuelOutbox {
    interface Delivery {void send(JsonObject body)throws Exception;}
    final File directory;final Executor executor;final Delivery delivery;final LongSupplier clock;
    final Semaphore slots=new Semaphore(2);
    final Set<String> inFlight=Collections.newSetFromMap(new ConcurrentHashMap<String,Boolean>());
    final Map<String,Long> retryAt=new ConcurrentHashMap<>();
    final Map<String,Integer> failures=new ConcurrentHashMap<>();
    DuelOutbox(File directory,Executor executor,Delivery delivery){this(directory,executor,delivery,System::currentTimeMillis);}
    DuelOutbox(File directory,Executor executor,Delivery delivery,LongSupplier clock){
        this.directory=directory;this.executor=executor;this.delivery=delivery;this.clock=clock;
        if(!directory.isDirectory()&&!directory.mkdirs())throw new IllegalStateException("Cannot create durable duel outbox");
    }
    synchronized boolean offer(JsonObject body){
        String key=body.get("match_key").getAsString();if(!key.matches("[A-Za-z0-9_-]{1,128}"))throw new IllegalArgumentException("Invalid match key");
        File target=new File(directory,key+".json"),temp=new File(directory,key+".tmp");
        if(target.isFile())return true;
        try{
            Files.write(temp.toPath(),body.toString().getBytes(StandardCharsets.UTF_8));
            try{Files.move(temp.toPath(),target.toPath(),StandardCopyOption.ATOMIC_MOVE,StandardCopyOption.REPLACE_EXISTING);}
            catch(AtomicMoveNotSupportedException ex){Files.move(temp.toPath(),target.toPath(),StandardCopyOption.REPLACE_EXISTING);}
            return true;
        }catch(IOException ex){return false;}
    }
    void flush(){
        File[] files=directory.listFiles(f->f.isFile()&&f.getName().endsWith(".json"));if(files==null)return;
        Arrays.sort(files,Comparator.comparingLong(File::lastModified).thenComparing(File::getName));
        for(File file:files){
            String name=file.getName();if(retryAt.getOrDefault(name,0L)>clock.getAsLong()||!inFlight.add(name))continue;
            if(!slots.tryAcquire()){inFlight.remove(name);break;}
            try{executor.execute(()->{
                try{
                    JsonObject body=new JsonParser().parse(new String(Files.readAllBytes(file.toPath()),StandardCharsets.UTF_8)).getAsJsonObject();
                    delivery.send(body);Files.deleteIfExists(file.toPath());retryAt.remove(name);failures.remove(name);
                }catch(Exception ex){int n=failures.merge(name,1,Integer::sum);retryAt.put(name,clock.getAsLong()+Math.min(60000L,5000L*(1L<<Math.min(n-1,4))));}
                finally{inFlight.remove(name);slots.release();}
            });}catch(RuntimeException ex){inFlight.remove(name);slots.release();}
        }
    }
}
