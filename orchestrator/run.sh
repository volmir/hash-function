#!/usr/bin/env bash
# Оркестратор заданий для Codex CLI.
#
# Идея: сам оркестратор НЕ ИИ, а обычный скрипт (ноль токенов на оркестрацию).
# Для каждого задания из orchestrator/TASKS.md он:
#   1) запускает НОВЫЙ `codex exec` с моделью и усилием рассуждения по уровню сложности (tier);
#   2) сам запускает проверку (gate) после работы агента, при провале даёт агенту один шанс исправить;
#   3) пишет итог в orchestrator/PROGRESS.md (память между заданиями), коммитит и пушит;
#   4) переходит к следующему. При ошибке останавливается и оставляет изменения непроведёнными.
#
# Запуск:   orchestrator/run.sh [--list] [--dry-run] [--from P6] [--only P6] [--no-push] [--mark-done P6]
# Окружение (все необязательные):
#   MODEL_LIGHT / MODEL_STANDARD / MODEL_HEAVY   id моделей (пусто = модель Codex по умолчанию)
#   EFFORT_LIGHT=low  EFFORT_STANDARD=medium  EFFORT_HEAVY=high
#   SANDBOX=workspace-write     (danger-full-access только в изолированной ВМ/контейнере)
#   GATE_BASE="docker compose run --rm app"   (как запускать проверки; "env" для запуска без Docker)
#   BRANCH=auto/hash-function   TASKS_FILE=orchestrator/TASKS.md   PROGRESS_FILE=orchestrator/PROGRESS.md
#   CODEX_BIN=codex   TASK_TIMEOUT=3600   NO_PUSH=1
set -euo pipefail

CODEX_BIN="${CODEX_BIN:-codex}"
TASKS_FILE="${TASKS_FILE:-orchestrator/TASKS.md}"
PROGRESS_FILE="${PROGRESS_FILE:-orchestrator/PROGRESS.md}"
BRANCH="${BRANCH:-auto/hash-function}"
SANDBOX="${SANDBOX:-workspace-write}"
GATE_BASE="${GATE_BASE:-docker compose run --rm app}"
TASK_TIMEOUT="${TASK_TIMEOUT:-3600}"
MODEL_LIGHT="${MODEL_LIGHT:-}"; MODEL_STANDARD="${MODEL_STANDARD:-}"; MODEL_HEAVY="${MODEL_HEAVY:-}"
EFFORT_LIGHT="${EFFORT_LIGHT:-low}"; EFFORT_STANDARD="${EFFORT_STANDARD:-medium}"; EFFORT_HEAVY="${EFFORT_HEAVY:-high}"
NO_PUSH="${NO_PUSH:-0}"
WORK=".orchestrator"
PROTECTED="orchestrator/run.sh $TASKS_FILE"

LIST=0; DRY=0; FROM=""; ONLY=""; MARK=""
while [ $# -gt 0 ]; do
  case "$1" in
    --list) LIST=1;;
    --dry-run) DRY=1;;
    --from) FROM="${2:?--from требует ID}"; shift;;
    --only) ONLY="${2:?--only требует ID}"; shift;;
    --no-push) NO_PUSH=1;;
    --mark-done) MARK="${2:?--mark-done требует ID}"; shift;;
    -h|--help) sed -n '2,23p' "$0"; exit 0;;
    *) echo "Неизвестный аргумент: $1" >&2; exit 2;;
  esac
  shift
done

die() { echo "ОШИБКА: $*" >&2; exit 1; }

[ -f "$TASKS_FILE" ] || die "нет файла заданий $TASKS_FILE (запускайте из корня проекта)"
git rev-parse --show-toplevel >/dev/null 2>&1 || die "не git-репозиторий"

# ---------- разбор TASKS.md ----------
rm -rf "$WORK/tasks"; mkdir -p "$WORK/tasks" "$WORK/logs"
awk -v dir="$WORK/tasks" '
  /^## P[0-9]+/ {
    if (out != "") close(out)
    match($0, /P[0-9]+/); id = substr($0, RSTART, RLENGTH)
    title = $0; sub(/^## P[0-9]+[ ]*[—:-]*[ ]*/, "", title)
    print title > (dir "/" id ".title"); close(dir "/" id ".title")
    print id >> (dir "/order.txt"); inprompt = 0; out = ""
    next
  }
  inprompt == 0 && /^tier: /    { sub(/^tier: /, "");    print > (dir "/" id ".tier");    close(dir "/" id ".tier");    next }
  inprompt == 0 && /^gate: /    { sub(/^gate: /, "");    print > (dir "/" id ".gate");    close(dir "/" id ".gate");    next }
  inprompt == 0 && /^retries: / { sub(/^retries: /, ""); print > (dir "/" id ".retries"); close(dir "/" id ".retries"); next }
  inprompt == 0 && /^```prompt[ ]*$/ { inprompt = 1; out = dir "/" id ".prompt"; printf "" > out; next }
  inprompt == 1 && /^```[ ]*$/ { inprompt = 0; close(out); out = ""; next }
  inprompt == 1 { print >> out }
' "$TASKS_FILE"
[ -s "$WORK/tasks/order.txt" ] || die "в $TASKS_FILE не найдено ни одного задания (## Pn)"
for id in $(cat "$WORK/tasks/order.txt"); do
  for f in tier prompt; do [ -s "$WORK/tasks/$id.$f" ] || die "у задания $id нет поля $f"; done
done

tier_model()  { case "$1" in light) echo "$MODEL_LIGHT";; standard) echo "$MODEL_STANDARD";; heavy) echo "$MODEL_HEAVY";; *) die "неизвестный tier: $1";; esac; }
tier_effort() { case "$1" in light) echo "$EFFORT_LIGHT";; standard) echo "$EFFORT_STANDARD";; heavy) echo "$EFFORT_HEAVY";; *) die "неизвестный tier: $1";; esac; }
is_done()     { [ -f "$PROGRESS_FILE" ] && grep -q "^- \[$1\] ✓" "$PROGRESS_FILE"; }
field()       { [ -f "$WORK/tasks/$1.$2" ] && cat "$WORK/tasks/$1.$2" || echo "${3:-}"; }

if [ "$LIST" = 1 ] || [ "$DRY" = 1 ]; then
  printf '%-5s %-9s %-4s %-26s %s\n' ID TIER DONE "MODEL/EFFORT" TITLE
  for id in $(cat "$WORK/tasks/order.txt"); do
    t=$(field "$id" tier); m=$(tier_model "$t"); e=$(tier_effort "$t")
    d="-"; is_done "$id" && d="да"
    printf '%-5s %-9s %-4s %-26s %s\n' "$id" "$t" "$d" "${m:-default}/$e" "$(field "$id" title)"
  done
  [ "$DRY" = 1 ] && echo && echo "(dry-run: ничего не запускалось)"
  exit 0
fi

# ---------- подготовка репозитория ----------
grep -qx "$WORK/" .git/info/exclude 2>/dev/null || echo "$WORK/" >> .git/info/exclude
dirty=$(git status --porcelain -- . ':(exclude)orchestrator' | head -5 || true)
[ -z "$dirty" ] || die "в рабочем дереве есть чужие изменения (закоммитьте или уберите):
$dirty"
git checkout "$BRANCH" 2>/dev/null || git checkout -b "$BRANCH"
if [ "$NO_PUSH" != 1 ]; then git remote get-url origin >/dev/null 2>&1 || die "нет remote origin (или запустите с --no-push)"; fi
[ -f "$PROGRESS_FILE" ] || { printf '# Журнал выполнения заданий (память оркестратора)\n\n' > "$PROGRESS_FILE"; }

TIMEOUT_CMD=""
if command -v timeout >/dev/null 2>&1; then TIMEOUT_CMD="timeout $TASK_TIMEOUT"; elif command -v gtimeout >/dev/null 2>&1; then TIMEOUT_CMD="gtimeout $TASK_TIMEOUT"; fi

run_codex() { # $1=exec|resume  $2=prompt  $3=out-file  $4=model  $5=effort
  local mode="$1" prompt="$2" out="$3" model="$4" effort="$5"
  local margs=(); [ -n "$model" ] && margs=(-m "$model")
  local base=(exec ${margs[@]+"${margs[@]}"} -c "model_reasoning_effort=\"$effort\"" --sandbox "$SANDBOX" -o "$out")
  if [ "$mode" = resume ]; then
    $TIMEOUT_CMD "$CODEX_BIN" "${base[@]}" resume --last "$prompt" > "$WORK/logs/$ID.$mode.log" 2>&1
  else
    $TIMEOUT_CMD "$CODEX_BIN" "${base[@]}" "$prompt" > "$WORK/logs/$ID.$mode.log" 2>&1
  fi
}

run_gate() { # $1=gate-command
  # shellcheck disable=SC2086
  $GATE_BASE sh -c "$1" > "$WORK/logs/$ID.gate.log" 2>&1
}

summary_line() { # из файла последнего сообщения агента
  local f="$1" s=""
  [ -f "$f" ] && s=$(grep -m1 '^ИТОГ:' "$f" || true)
  [ -z "$s" ] && [ -f "$f" ] && s=$(grep -m1 -v '^[[:space:]]*$' "$f" || true)
  printf '%s' "${s:-(агент не оставил итога)}" | cut -c1-300
}

PUSH_PENDING=0
push_now() {
  [ "$NO_PUSH" = 1 ] && return 0
  if git push -u origin "$BRANCH" >> "$WORK/logs/push.log" 2>&1; then PUSH_PENDING=0; else PUSH_PENDING=1; echo "  ! push не удался (см. $WORK/logs/push.log), коммиты сохранены локально"; fi
}

if [ -n "$MARK" ]; then
  [ -s "$WORK/tasks/$MARK.tier" ] || die "нет задания $MARK"
  is_done "$MARK" && die "$MARK уже отмечено выполненным"
  printf -- '- [%s] ✓ %s — %s — выполнено вручную (оркестратором отмечено по --mark-done)\n' "$MARK" "$(date +%F)" "$(field "$MARK" title)" >> "$PROGRESS_FILE"
  git add -A; git commit -q -m "$MARK: $(field "$MARK" title) (вручную)"; echo "отмечено и закоммичено: $MARK"; push_now; exit 0
fi

started=0; [ -z "$FROM" ] && started=1
for ID in $(cat "$WORK/tasks/order.txt"); do
  [ -n "$ONLY" ] && [ "$ID" != "$ONLY" ] && continue
  [ "$started" = 0 ] && [ "$ID" = "$FROM" ] && started=1
  [ "$started" = 1 ] || continue
  if is_done "$ID"; then echo "[$ID] уже выполнено, пропускаю"; continue; fi

  tier=$(field "$ID" tier); title=$(field "$ID" title); gate=$(field "$ID" gate skip); retries=$(field "$ID" retries 1)
  model=$(tier_model "$tier"); effort=$(tier_effort "$tier")
  echo "=== [$ID] $title | tier=$tier model=${model:-default} effort=$effort ==="

  prev=$(tail -n 15 "$PROGRESS_FILE" | grep '^- \[' || true)
  prompt="Ты выполняешь ОДНО задание из серии. ID задания: $ID.
Правила:
- Следуй AGENTS.md (он подключается автоматически).
- Выполни только это задание, к следующим не переходи.
- НЕ делай git commit/push и НЕ меняй каталог orchestrator/ и файл $TASKS_FILE: это делает оркестратор, он же запустит проверки.
- Если не можешь запустить проверку (например, нет Docker в песочнице), не утверждай, что она прошла: так и напиши.
- В конце выведи итог строго в формате:
ИТОГ: <1–3 предложения, что сделано>
ПРОВЕРЕНО: <что и как>
НЕ ПРОВЕРЕНО / ОТКЛОНЕНИЯ: <или «нет»>

Итоги предыдущих заданий:
${prev:-(пока нет)}

--- ЗАДАНИЕ $ID ---
$(cat "$WORK/tasks/$ID.prompt")"

  before=$(cksum $PROTECTED)
  out="$WORK/logs/$ID.last.md"; rm -f "$out"
  run_codex exec "$prompt" "$out" "$model" "$effort" || die "[$ID] codex завершился с ошибкой или по таймауту (лог: $WORK/logs/$ID.exec.log)"
  [ "$(cksum $PROTECTED)" = "$before" ] || die "[$ID] агент изменил защищённые файлы ($PROTECTED), остановка"

  attempt=0
  while :; do
    if [ "$gate" = skip ]; then echo "  проверка: пропущена по настройке задания"; break; fi
    echo "  проверка: $gate"
    if run_gate "$gate"; then echo "  проверка: OK"; break; fi
    if [ "$attempt" -ge "$retries" ]; then
      die "[$ID] проверка не прошла (лог: $WORK/logs/$ID.gate.log). Изменения оставлены в рабочем дереве, ничего не закоммичено. Разберите вручную и запустите снова."
    fi
    attempt=$((attempt+1)); echo "  проверка не прошла, даю агенту попытку исправить ($attempt/$retries)"
    fix="Проверка после твоего задания $ID не прошла. Последние строки вывода:
$(tail -n 80 "$WORK/logs/$ID.gate.log")

Найди причину и исправь её. Не меняй проверки, эталонные векторы и пороги ради прохождения. Затем выведи итог в том же формате (ИТОГ / ПРОВЕРЕНО / НЕ ПРОВЕРЕНО / ОТКЛОНЕНИЯ)."
    run_codex resume "$fix" "$out" "$model" "$effort" || die "[$ID] повторный запуск codex не удался (лог: $WORK/logs/$ID.resume.log)"
    [ "$(cksum $PROTECTED)" = "$before" ] || die "[$ID] агент изменил защищённые файлы, остановка"
  done

  [ -n "$(git status --porcelain -- . ':(exclude)orchestrator')" ] || die "[$ID] агент не изменил ни одного файла проекта, остановка"

  s=$(summary_line "$out")
  printf -- '- [%s] ✓ %s — %s — %s (tier=%s, model=%s, effort=%s)\n' "$ID" "$(date +%F)" "$title" "$s" "$tier" "${model:-default}" "$effort" >> "$PROGRESS_FILE"
  git add -A
  body=$( { grep -E '^(ИТОГ|ПРОВЕРЕНО|НЕ ПРОВЕРЕНО)' "$out" 2>/dev/null || true; } | head -n 6)
  git commit -q -m "$ID: $title" -m "${body:-Итог агента: см. $PROGRESS_FILE}"
  echo "  коммит: $(git rev-parse --short HEAD)"
  push_now
done

[ "$PUSH_PENDING" = 1 ] && push_now
echo
echo "Готово. Журнал: $PROGRESS_FILE"
[ "$PUSH_PENDING" = 1 ] && echo "ВНИМАНИЕ: последний push не прошёл, выполните: git push -u origin $BRANCH"
exit 0
