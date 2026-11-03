#!/bin/bash
# Файловый менеджер и метрики для панели APEX (рядом с apex-panel.sh).
# Вызывается сайтом по SSH. Все пути относительные от DIR; любой выход за
# корень (.., абсолютный путь, симлинк наружу) отклоняется здесь же —
# проверка в PHP вторая линия обороны, а не единственная.
#
# Команды:  metrics | ls REL | cat REL | put REL (stdin) | put-new REL (stdin)
#           mkdir REL | touch REL | mv REL NEWREL | trash REL | trash-ls
#           dl REL | plugins-state
set -uo pipefail

DIR=${APEX_DIR:-/opt/minecraft/server}
PB=${APEX_PB:-/opt/minecraft/panel-backups}
TRASH=${APEX_TRASH:-/opt/minecraft/trash}
MAXTEXT=1048576
MAXDL=20971520

TEXT_EXT="properties yml yaml json txt conf cfg log toml xml sh md"

fail() { echo "$1"; exit 1; }

# Относительный путь -> абсолютный внутри DIR
resolve() {
    local rel="$1" p
    case "$rel" in
        *..*|/*|*$'\n'*|*$'\r'*) return 1 ;;
    esac
    p=$(realpath -m -- "$DIR/$rel") || return 1
    case "$p" in
        "$DIR"|"$DIR"/*) printf '%s' "$p" ;;
        *) return 1 ;;
    esac
}

is_text_name() {
    local ext="${1##*.}"
    ext=$(printf '%s' "$ext" | tr 'A-Z' 'a-z')
    for e in $TEXT_EXT; do [ "$e" = "$ext" ] && return 0; done
    return 1
}

pid() { pgrep -f 'java .*WindSpigot' | head -1; }

sid() {
    local jp sp
    jp=$(pid); [ -z "$jp" ] && return 1
    sp=$(ps -o ppid= -p "$jp" 2>/dev/null | tr -d ' ')
    [ -z "$sp" ] && return 1
    screen -ls 2>/dev/null | awk -v p="$sp" '$1 ~ "^" p "\\." { print $1; exit }'
}

# Отправить команду в консоль и вернуть то, что появилось в логе после неё
console_ask() {
    local S marker lines
    S=$(sid) || return 1
    lines=$(wc -l < "$DIR/logs/latest.log" 2>/dev/null || echo 0)
    screen -S "$S" -p 0 -X stuff "$1$(printf '\r')" 2>/dev/null || return 1
    sleep "${2:-2}"
    tail -n +"$((lines + 1))" "$DIR/logs/latest.log" 2>/dev/null | sed -E 's/\x1b\[[0-9;]*m//g; s/§.//g'
}

cpu_sample() { awk '/^cpu /{print $2+$3+$4+$7+$8, $2+$3+$4+$5+$6+$7+$8}' /proc/stat; }

case "${1:-}" in
  metrics)
    read -r b1 t1 <<< "$(cpu_sample)"
    sleep 1
    read -r b2 t2 <<< "$(cpu_sample)"
    dt=$((t2 - t1))
    [ "$dt" -gt 0 ] && echo "cpu=$(( (b2 - b1) * 100 / dt ))" || echo "cpu=0"
    echo "cores=$(nproc)"
    P=$(pid)
    if [ -n "$P" ]; then
      echo "java_rss=$(( $(ps -o rss= -p "$P" | tr -d ' ') / 1024 ))"
      OUT=$(console_ask "tps" 1; console_ask "list" 1)
      T=$(printf '%s\n' "$OUT" | grep -a 'TPS from last' | tail -1 | sed -E 's/.*: *([0-9.*]+).*/\1/')
      echo "tps=${T:-}"
      L=$(printf '%s\n' "$OUT" | grep -aE 'There are [0-9]+' | tail -1)
      N=$(printf '%s' "$L" | sed -nE 's#.*There are ([0-9]+).*#\1#p')
      echo "online=${N:-}"
      # Следующая строка после «There are» — ники через запятую
      NAMES=$(printf '%s\n' "$OUT" | grep -a -A1 'There are' | tail -1 | sed -E 's/^\[[^]]*\][^:]*: ?//' )
      printf '%s\n' "$NAMES" | grep -aq 'There are' && NAMES=""
      echo "names=${NAMES:-}"
    fi
    echo "disk_pct=$(df / | awk 'NR==2{gsub("%","",$5); print $5}')"
    ;;
  plugins-state)
    [ -z "$(pid)" ] && fail "not-running"
    console_ask "pl" 2 | grep -aE 'Plugins \([0-9]+\)' | tail -1 | sed -E 's/.*Plugins \([0-9]+\): *//'
    ;;
  ls)
    P=$(resolve "${2:-}") || fail "bad-path"
    [ -d "$P" ] || fail "not-found"
    find "$P" -mindepth 1 -maxdepth 1 -printf '%y\t%s\t%TY-%Tm-%Td %TH:%TM\t%f\n' 2>/dev/null | sort -t$'\t' -k1,1r -k4,4f | head -2000
    ;;
  cat)
    P=$(resolve "${2:-}") || fail "bad-path"
    [ -f "$P" ] || fail "not-found"
    is_text_name "$P" || fail "bad-ext"
    [ "$(stat -c %s "$P")" -le "$MAXTEXT" ] || fail "too-big"
    cat "$P"
    ;;
  put)
    P=$(resolve "${2:-}") || fail "bad-path"
    [ "$P" = "$DIR" ] && fail "bad-path"
    is_text_name "$P" || fail "bad-ext"
    [ -d "$P" ] && fail "is-dir"
    TMP=$(mktemp) || fail "tmp-failed"
    head -c $((MAXTEXT + 1)) > "$TMP"
    [ "$(stat -c %s "$TMP")" -le "$MAXTEXT" ] || { rm -f "$TMP"; fail "too-big"; }
    BK=""
    if [ -f "$P" ]; then
      BK="$PB/$(date +%Y%m%d-%H%M%S)/${2}"
      mkdir -p "$(dirname "$BK")" && cp -p "$P" "$BK" || { rm -f "$TMP"; fail "backup-failed"; }
    fi
    cat "$TMP" > "$P" || { rm -f "$TMP"; fail "write-failed"; }
    rm -f "$TMP"
    echo "saved"
    [ -n "$BK" ] && echo "backup=$BK"
    ;;
  put-new)
    # Загрузка файла: только новый путь или перезапись с бэкапом, любые байты
    P=$(resolve "${2:-}") || fail "bad-path"
    [ "$P" = "$DIR" ] && fail "bad-path"
    [ -d "$P" ] && fail "is-dir"
    mkdir -p "$(dirname "$P")" || fail "mkdir-failed"
    if [ -f "$P" ]; then
      BK="$PB/$(date +%Y%m%d-%H%M%S)/${2}"
      mkdir -p "$(dirname "$BK")" && cp -p "$P" "$BK" || fail "backup-failed"
    fi
    cat > "$P" || fail "write-failed"
    echo "saved"
    ;;
  mkdir)
    P=$(resolve "${2:-}") || fail "bad-path"
    [ "$P" = "$DIR" ] && fail "bad-path"
    [ -e "$P" ] && fail "exists"
    mkdir -p "$P" && echo "created"
    ;;
  touch)
    P=$(resolve "${2:-}") || fail "bad-path"
    is_text_name "$P" || fail "bad-ext"
    [ -e "$P" ] && fail "exists"
    mkdir -p "$(dirname "$P")" && : > "$P" && echo "created"
    ;;
  mv)
    A=$(resolve "${2:-}") || fail "bad-path"
    B=$(resolve "${3:-}") || fail "bad-path"
    [ "$A" = "$DIR" ] || [ "$B" = "$DIR" ] && fail "bad-path"
    [ -e "$A" ] || fail "not-found"
    [ -e "$B" ] && fail "exists"
    mkdir -p "$(dirname "$B")" && mv -- "$A" "$B" && echo "moved"
    ;;
  trash)
    P=$(resolve "${2:-}") || fail "bad-path"
    [ "$P" = "$DIR" ] && fail "bad-path"
    [ -e "$P" ] || fail "not-found"
    D="$TRASH/$(date +%Y%m%d-%H%M%S)/$(dirname "${2}")"
    mkdir -p "$D" && mv -- "$P" "$D/" && echo "trashed"
    echo "to=$D"
    ;;
  trash-ls)
    [ -d "$TRASH" ] || exit 0
    find "$TRASH" -mindepth 2 -type f -printf '%TY-%Tm-%Td %TH:%TM\t%k KB\t%P\n' 2>/dev/null | sort -r | head -100
    ;;
  dl)
    P=$(resolve "${2:-}") || fail "bad-path"
    [ -f "$P" ] || fail "not-found"
    [ "$(stat -c %s "$P")" -le "$MAXDL" ] || fail "too-big"
    cat "$P"
    ;;
  *)
    fail "unknown command"
    ;;
esac
