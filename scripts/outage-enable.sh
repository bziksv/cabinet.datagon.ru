#!/usr/bin/env bash
# Install nginx outage snippet (once) + enable static downtime page.
# Usage: sudo ./scripts/outage-enable.sh [--install-nginx-only]
set -euo pipefail

APP="${CABINET_APP:-/var/www/cabinet_titl_usr/data/www/cabinet.titlo.ru}"
FLAG="$APP/storage/app/outage/ENABLED"
INCLUDES="/etc/nginx/fastpanel2-sites/cabinet_titl_usr/cabinet.titlo.ru.includes"
SNIPPET_SRC="$APP/scripts/nginx-cabinet-outage.inc"
BEGIN="# BEGIN CABINET-TITLO-DB-OUTAGE"
END="# END CABINET-TITLO-DB-OUTAGE"
INSTALL_ONLY=0
[[ "${1:-}" == "--install-nginx-only" ]] && INSTALL_ONLY=1

if [[ ! -f "$SNIPPET_SRC" ]]; then
  echo "Missing snippet: $SNIPPET_SRC" >&2
  exit 1
fi

mkdir -p "$(dirname "$FLAG")"

install_nginx_snippet() {
  local tmp
  tmp="$(mktemp)"
  if [[ -f "$INCLUDES" ]] && grep -qF "$BEGIN" "$INCLUDES"; then
    awk -v begin="$BEGIN" -v end="$END" '
      $0==begin {print; skip=1; next}
      $0==end {skip=0; while ((getline line < "'"$SNIPPET_SRC"'") > 0) print line; close("'"$SNIPPET_SRC"'"); print; next}
      !skip {print}
    ' "$INCLUDES" > "$tmp"
  else
    {
      [[ -f "$INCLUDES" ]] && cat "$INCLUDES"
      echo
      echo "$BEGIN"
      cat "$SNIPPET_SRC"
      echo "$END"
    } > "$tmp"
  fi
  cp "$tmp" "$INCLUDES"
  rm -f "$tmp"
  nginx -t
  systemctl reload nginx
  echo "nginx outage snippet installed/updated"
}

install_nginx_snippet

if [[ "$INSTALL_ONLY" -eq 1 ]]; then
  exit 0
fi

date -u +%Y-%m-%dT%H:%M:%SZ > "$FLAG"
chown cabinet_titl_usr:cabinet_titl_usr "$FLAG" 2>/dev/null || true
echo "OUTAGE ENABLED → $FLAG"
echo "Check: curl -sI https://cabinet.titlo.ru/ | head -5"
