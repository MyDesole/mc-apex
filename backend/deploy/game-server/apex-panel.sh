#!/usr/bin/env bash
# Панель управления APEX.
# Команды: status | players | plugins | console N | start | stop | restart | cmd ...
#          get-start | set-start | files | read PATH | write PATH | archives
#          apply-zip NAME | rm-archive NAME | backups | restore NAME
set -uo pipefail

DIR=/opt/minecraft/server
UPD=/opt/minecraft/updates
BK=/opt/minecraft/backups

pid() { pgrep -f 'java .*WindSpigot' | head -1; }

# Идентификатор screen-сессии, в которой живёт java.
# Имя сессии ненадёжно: после нескольких запусков на сервере остаются
# висячие сессии с тем же именем, и `screen -S minecraft` путается.
sid() {
    local jp sp
    jp=$(pid)
    [ -z "$jp" ] && return 1
    sp=$(ps -o ppid= -p "$jp" 2>/dev/null | tr -d ' ')
    [ -z "$sp" ] && return 1
    ps -o comm= -p "$sp" 2>/dev/null | grep -q screen || return 1
    screen -ls 2>/dev/null | awk -v p="$sp" '$1 ~ "^" p "\\." { print $1; exit }'
}

# Убрать висячие сессии minecraft, в которых нет java
cleanup_sessions() {
    local id
    for id in $(screen -ls 2>/dev/null | awk '/\.minecraft/ {print $1}'); do
        if [ -z "$(pid)" ] || [ "$id" != "$(sid)" ]; then
            screen -S "$id" -X quit 2>/dev/null || true
        fi
    done
    screen -wipe 2>/dev/null || true
}

log_players() {
    LOG="$DIR/logs/latest.log"
    if [ ! -f "$LOG" ]; then echo 0; return; fi
    J=$(grep -c 'joined the game' "$LOG" 2>/dev/null || true)
    L=$(grep -c 'left the game' "$LOG" 2>/dev/null || true)
    N=$(( ${J:-0} - ${L:-0} ))
    [ "$N" -lt 0 ] && N=0
    echo "$N"
}

check_path() {
    local rel="$1"
    case "$rel" in
        *..*|/*) echo "bad-path"; return 1 ;;
    esac
    case "$rel" in
        *.properties|*.yml|*.yaml|*.json|*.txt|*.conf|*.sh) ;;
        *) echo "bad-ext"; return 1 ;;
    esac
    [ -f "$DIR/$rel" ] || { echo "not-found"; return 1; }
    echo "$rel"
    return 0
}

case "${1:-status}" in
  status)
    P=$(pid)
    if [ -n "$P" ]; then
      echo "running=1"
      echo "pid=$P"
      echo "uptime=$(ps -o etime= -p "$P" | tr -d ' ')"
      echo "jar=$(ps -o args= -p "$P" | grep -o '[^ ]*\.jar' | head -1)"
    else
      echo "running=0"
    fi
    echo "mem_total=$(free -m | awk '/Mem:/{print $2}')"
    echo "mem_used=$(free -m | awk '/Mem:/{print $3}')"
    echo "disk_used=$(df -h / | awk 'NR==2{print $3}')"
    echo "disk_total=$(df -h / | awk 'NR==2{print $2}')"
    echo "load=$(awk '{print $1}' /proc/loadavg)"
    echo "players=$(log_players)"
    LOG="$DIR/logs/latest.log"
    if [ -f "$LOG" ]; then
      echo "size=$(du -h "$LOG" | cut -f1)"
      echo "updated=$(date -r "$LOG" '+%Y-%m-%d %H:%M:%S')"
    fi
    echo "dir_size=$(du -sh "$DIR" 2>/dev/null | cut -f1)"
    echo "session=$(sid || echo none)"
    ;;
  players)
    if [ -z "$(pid)" ]; then echo "online=-1"; echo "max=0"; exit 0; fi
    S=$(sid) || { echo "online=-1"; echo "max=0"; exit 0; }
    screen -S "$S" -p 0 -X stuff "list$(printf '\r')" 2>/dev/null || true
    sleep 2
    LOG="$DIR/logs/latest.log"
    LINE=$(tail -n 150 "$LOG" 2>/dev/null | grep -aE 'There are [0-9]+' | tail -1)
    if [ -n "$LINE" ]; then
      ONLINE=$(printf '%s' "$LINE" | sed -nE 's#.*There are ([0-9]+).*#\1#p')
      MAX=$(printf '%s' "$LINE" | sed -nE 's#.*[0-9]+/([0-9]+) players online.*#\1#p')
      [ -z "$MAX" ] && MAX=$(printf '%s' "$LINE" | sed -nE 's#.*a max of ([0-9]+) players online.*#\1#p')
      echo "online=${ONLINE:-0}"
      echo "max=${MAX:-0}"
    else
      echo "online=-1"
      echo "max=0"
    fi
    ;;
  plugins)
    find "$DIR/plugins" -maxdepth 1 -name '*.jar' -printf '%f\t%k KB\n' 2>/dev/null | sort
    ;;
  files)
    {
      find "$DIR" -maxdepth 1 -type f \( -name '*.properties' -o -name '*.yml' -o -name '*.yaml' \) -printf '%P\t%k KB\n' 2>/dev/null
      find "$DIR/plugins" -maxdepth 2 -type f \( -name '*.yml' -o -name '*.yaml' -o -name '*.json' -o -name '*.properties' \) -printf 'plugins/%P\t%k KB\n' 2>/dev/null
    } | sort
    ;;
  read)
    REL=$(check_path "${2:-}") || { echo "$REL"; exit 1; }
    head -c 512000 "$DIR/$REL"
    ;;
  write)
    REL=$(check_path "${2:-}") || { echo "$REL"; exit 1; }
    cat > "$DIR/$REL"
    echo "saved"
    ;;
  archives)
    if ls -1 "$UPD"/*.zip >/dev/null 2>&1; then
      ls -1 "$UPD"/*.zip | while read -r f; do
        printf '%s\t%s\t%s\n' "$(basename "$f")" "$(du -h "$f" | cut -f1)" "$(date -r "$f" '+%Y-%m-%d %H:%M')"
      done
    fi
    ;;
  apply-zip)
    NAME="${2:-}"
    case "$NAME" in ''|*..*|*/*) echo "bad-name"; exit 1 ;; esac
    ZIP="$UPD/$NAME"
    [ -f "$ZIP" ] || { echo "not-found"; exit 1; }

    bash "$0" stop >/dev/null 2>&1 || true

    mkdir -p "$BK"
    TS=$(date +%Y%m%d-%H%M%S)
    tar czf "$BK/server-$TS.tar.gz" -C "$DIR" . 2>/dev/null || { echo "backup-failed"; bash "$0" start >/dev/null 2>&1; exit 1; }

    if ! python3 -m zipfile -e "$ZIP" "$DIR" 2>/dev/null; then
      echo "unzip-failed"
      bash "$0" start >/dev/null 2>&1
      exit 1
    fi

    chmod +x "$DIR/start.sh" 2>/dev/null || true
    bash "$0" start >/dev/null 2>&1

    echo "applied"
    echo "backup=server-$TS.tar.gz"
    ;;
  rm-archive)
    NAME="${2:-}"
    case "$NAME" in ''|*..*|*/*) echo "bad-name"; exit 1 ;; esac
    rm -f "$UPD/$NAME" && echo "deleted"
    ;;
  backups)
    if ls -1 "$BK"/*.tar.gz >/dev/null 2>&1; then
      ls -1t "$BK"/*.tar.gz | head -20 | while read -r f; do
        printf '%s\t%s\t%s\n' "$(basename "$f")" "$(du -h "$f" | cut -f1)" "$(date -r "$f" '+%Y-%m-%d %H:%M')"
      done
    fi
    ;;
  restore)
    NAME="${2:-}"
    case "$NAME" in ''|*..*|*/*) echo "bad-name"; exit 1 ;; esac
    [ -f "$BK/$NAME" ] || { echo "not-found"; exit 1; }
    bash "$0" stop >/dev/null 2>&1 || true
    tar xzf "$BK/$NAME" -C "$DIR" 2>/dev/null || { echo "restore-failed"; bash "$0" start >/dev/null 2>&1; exit 1; }
    bash "$0" start >/dev/null 2>&1
    echo "restored"
    ;;
  console)
    tail -n "${2:-150}" "$DIR/logs/latest.log" 2>/dev/null || echo "(лог не найден)"
    ;;
  start)
    if [ -n "$(pid)" ]; then echo "already running"; exit 0; fi
    cleanup_sessions
    cd "$DIR" || exit 1
    screen -dmS minecraft bash -c "cd $DIR && exec ./start.sh"
    echo "started"
    ;;
  stop)
    if [ -z "$(pid)" ]; then cleanup_sessions; echo "not running"; exit 0; fi
    S=$(sid) || true
    if [ -n "${S:-}" ]; then
      screen -S "$S" -p 0 -X stuff "stop$(printf '\r')" 2>/dev/null || kill "$(pid)"
    else
      kill "$(pid)"
    fi
    for i in $(seq 1 90); do
      if [ -z "$(pid)" ]; then cleanup_sessions; echo "stopped"; exit 0; fi
      sleep 1
    done
    echo "timeout"; exit 1
    ;;
  restart)
    bash "$0" stop
    sleep 2
    bash "$0" start
    ;;
  cmd)
    shift
    [ -z "$(pid)" ] && { echo "not-running"; exit 1; }
    S=$(sid) || { echo "no-session"; exit 1; }
    if screen -S "$S" -p 0 -X stuff "$*$(printf '\r')" 2>/dev/null; then
      echo "sent"
    else
      echo "no-session"
      exit 1
    fi
    ;;
  get-start)
    cat "$DIR/start.sh" 2>/dev/null || echo "(нет)"
    ;;
  set-start)
    cat > "$DIR/start.sh"
    chmod +x "$DIR/start.sh"
    echo "saved"
    ;;
  *)
    echo "unknown command"; exit 2
    ;;
esac
