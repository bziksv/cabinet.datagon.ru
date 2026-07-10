#!/usr/bin/env bash
# LanguageTool server for Esenin text check (local / prod sidecar).
set -euo pipefail

NAME="${LANGUAGETOOL_CONTAINER:-esenin-languagetool}"
PORT="${LANGUAGETOOL_PORT:-8010}"
IMAGE="${LANGUAGETOOL_IMAGE:-silviof/docker-languagetool:latest}"

cmd="${1:-status}"

case "$cmd" in
  start)
    if docker ps -a --format '{{.Names}}' | grep -qx "$NAME"; then
      docker start "$NAME" >/dev/null 2>&1 || true
    else
      docker run -d --name "$NAME" -p "${PORT}:8010" \
        -e Java_Xms=512m -e Java_Xmx=1024m \
        "$IMAGE"
    fi
    echo "LanguageTool: http://127.0.0.1:${PORT}/v2/languages"
    ;;
  stop)
    docker stop "$NAME" >/dev/null 2>&1 || true
    echo "Stopped $NAME"
    ;;
  restart)
    "$0" stop
    "$0" start
    ;;
  status)
    docker ps -a --filter "name=^/${NAME}$" --format 'table {{.Names}}\t{{.Status}}\t{{.Ports}}' || true
    ;;
  *)
    echo "Usage: $0 {start|stop|restart|status}"
    exit 1
    ;;
esac
