#!/usr/bin/env bash
# Снимок состояния кабинета → /tmp/cabinet-dev.log
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
LOG="/tmp/cabinet-dev.log"

{
  echo "======== diagnose $(date) ========"
  echo "--- ports ---"
  for p in 3002 13002 13003 13004; do
    echo -n ":$p "
    lsof -i :"$p" -sTCP:LISTEN 2>/dev/null | awk 'NR==2{print $1,$2,$9}' || echo "free"
  done
  echo "--- curl ---"
  for url in "http://127.0.0.1:3002/login" "http://127.0.0.1:13002/login" "http://127.0.0.1:13003/login" "http://127.0.0.1:13004/login"; do
    curl -sS -o /dev/null -w "$url → %{http_code} %{time_total}s\n" --max-time 8 "$url" 2>&1 || true
  done
  echo "--- .env (без паролей) ---"
  grep -E '^(APP_ENV|APP_URL|DB_HOST|LOG_CHANNEL|SKIP_|HTTP_HEADERS)' "$ROOT/.env" 2>/dev/null || echo "no .env"
  echo "--- php :3002 (очередь) ---"
  pid=$(lsof -ti :3002 -sTCP:LISTEN 2>/dev/null | head -1)
  if [[ -n "$pid" ]]; then
    n=$(lsof -p "$pid" 2>/dev/null | grep -c 'localhost:exlm-agent->localhost' || echo 0)
    echo "слушает PID $pid, висящих локальных соединений: $n"
    lsof -p "$pid" 2>/dev/null | grep mysql | head -3 || echo "(нет активного mysql)"
  fi
  echo "--- laravel tail ---"
  tail -8 "$ROOT/storage/logs/laravel-$(date +%Y-%m-%d).log" 2>/dev/null || tail -8 "$ROOT/storage/logs/laravel.log" 2>/dev/null || echo "(нет)"
  echo "--- worker 13004 tail (часто зависает) ---"
  tail -6 /tmp/cabinet-worker-13004.log 2>/dev/null || echo "(нет)"
  echo "================================"
} | tee -a "$LOG"

echo "Записано в $LOG"
