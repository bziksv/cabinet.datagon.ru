#!/usr/bin/env bash
# Деплой cabinet.titlo.ru на s3 (155.212.171.103).
# Включает git pull, composer, кэши и ОБЯЗАТЕЛЬНЫЙ перезапуск queue workers.
set -euo pipefail

SSH_HOST="${DEPLOY_SSH:-root@155.212.171.103}"
BRANCH="${DEPLOY_BRANCH:-main}"

echo "Deploy cabinet.titlo.ru → ${SSH_HOST} (branch ${BRANCH})"

ssh -o BatchMode=yes "$SSH_HOST" bash -s "$BRANCH" <<'REMOTE'
set -euo pipefail
BRANCH="$1"
APP=/var/www/cabinet_titl_usr/data/www/cabinet.titlo.ru
PHP=/opt/php85/bin/php
USER=cabinet_titl_usr

cd "$APP"
git fetch origin
git checkout "$BRANCH"
git reset --hard "origin/${BRANCH}"

COMPOSER_ALLOW_SUPERUSER=1 "$PHP" "$(which composer)" install --no-dev --optimize-autoloader --no-interaction --ignore-platform-reqs

if git diff --name-only HEAD@{1} HEAD 2>/dev/null | grep -qE '^(package(-lock)?\.json|resources/(js|sass)/)'; then
  NODE_OPTIONS=--openssl-legacy-provider npm run production
fi

chown -R "$USER:$USER" "$APP"

sudo -u "$USER" "$PHP" artisan migrate --force --no-interaction
sudo -u "$USER" "$PHP" artisan config:clear
sudo -u "$USER" "$PHP" artisan config:cache
sudo -u "$USER" "$PHP" artisan route:clear
sudo -u "$USER" "$PHP" artisan view:cache

# Workers держат старый PHP-код в памяти — без рестарта jobs идут со старым кодом.
sudo -u "$USER" "$PHP" artisan queue:restart

SUPERVISOR_GROUPS=(
  cabinet-titlo-default
  cabinet-titlo-db-optimize
  cabinet-titlo-cluster-child
  cabinet-titlo-cluster-main
  cabinet-titlo-cluster-wait
  cabinet-titlo-position
  cabinet-titlo-relevance
  cabinet-titlo-relevance-medium
  cabinet-titlo-monitoring-helper
  cabinet-titlo-monitoring-change-dates
  cabinet-titlo-monitoring-wait
  cabinet-titlo-monitoring-competitors-stat
  cabinet-titlo-competitor-analyse
  cabinet-titlo-ai-generation
  cabinet-titlo-site-audit
  cabinet-titlo-site-audit-aggregate
  cabinet-titlo-wordstat
  cabinet-titlo-phrase-commerce
)

# Рестарт в фоне, отвязанно от SSH: relevance ждёт текущие задачи до 11 минут (stopwaitsecs=660),
# а SSH-сессию сервер закрывает через 10 минут — синхронный restart обрывался и оставлял группу остановленной.
RESTARTED=()
for group in "${SUPERVISOR_GROUPS[@]}"; do
  if supervisorctl status "${group}:" 2>/dev/null | grep -qE 'RUNNING|STARTING'; then
    echo "restart ${group} (в фоне)"
    setsid nohup bash -c "supervisorctl restart '${group}:*'; sleep 5; supervisorctl start '${group}:*'" \
      > "/tmp/deploy-restart-${group}.log" 2>&1 < /dev/null &
    RESTARTED+=("$group")
  fi
done

not_running() {
  local group
  for group in "${RESTARTED[@]}"; do
    supervisorctl status "${group}:" 2>/dev/null | grep -v RUNNING || true
  done
}

sleep 15
DEADLINE=$((SECONDS + 420))
while [ -n "$(not_running)" ] && [ "$SECONDS" -lt "$DEADLINE" ]; do
  sleep 10
done

PENDING="$(not_running)"
if [ -n "$PENDING" ]; then
  echo "ВНИМАНИЕ: ещё не поднялись (рестарт продолжается в фоне, проверьте supervisorctl status через несколько минут):"
  echo "$PENDING"
else
  echo "Все группы воркеров RUNNING: ${#RESTARTED[@]}"
fi

curl -sI https://cabinet.titlo.ru/login | head -3
echo "Deploy OK"
REMOTE
