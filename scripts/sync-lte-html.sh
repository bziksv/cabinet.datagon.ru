#!/usr/bin/env bash
# Полный AdminLTE demo → public/html (git + деплой).
# https://github.com/ColorlibHQ/AdminLTE/releases
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
DEST="$ROOT/public/html"
TAG="${ADMINLTE_TAG:-v4.0.0}"
TAG_DIR="${TAG#v}"
RELEASE_ZIP="/tmp/admin-lte-${TAG}.zip"
SOURCE_TAR="/tmp/adminlte-${TAG}.tar.gz"
EXTRACT="/tmp/AdminLTE-${TAG_DIR}"
VENDOR="${1:-}"

echo "→ $DEST (AdminLTE ${TAG}, полная установка)"

if [[ -f "$DEST/index.html" && "${VENDOR}" != "--force" && "${1:-}" != "--force" ]]; then
  [[ -f "$DEST/VERSION.txt" ]] && cat "$DEST/VERSION.txt" | head -1
  echo "Повтор: ./scripts/sync-lte-html.sh --force"
  exit 0
fi

rm -rf "$DEST"
mkdir -p "$DEST"

# Официальный zip релиза (тот же dist, что на adminlte.io)
echo "1/3 Скачивание релиза ${TAG}…"
if curl -fsSL --max-time 300 -o "$RELEASE_ZIP" \
  "https://github.com/ColorlibHQ/AdminLTE/releases/download/${TAG}/admin-lte-${TAG}.zip" 2>/dev/null; then
  unzip -q "$RELEASE_ZIP" -d /tmp
  rm -f "$RELEASE_ZIP"
  cp -a "/tmp/dist/." "$DEST/"
  rm -rf /tmp/dist
  SOURCE_KIND="release zip (dist/)"
else
  echo "   zip недоступен, tarball с GitHub…"
  curl -fsSL --max-time 300 -o "$SOURCE_TAR" \
    "https://github.com/ColorlibHQ/AdminLTE/archive/refs/tags/${TAG}.tar.gz"
  tar -xzf "$SOURCE_TAR" -C /tmp
  rm -f "$SOURCE_TAR"
  if [[ -f "$EXTRACT/dist/index.html" ]]; then
    cp -a "$EXTRACT/dist/." "$DEST/"
    SOURCE_KIND="github tarball dist/"
  else
    cp -a "$EXTRACT/." "$DEST/"
    SOURCE_KIND="github tarball root/"
  fi
  rm -rf "$EXTRACT"
fi

FILE_COUNT=$(find "$DEST" -type f ! -path '*/npm/*' 2>/dev/null | wc -l | tr -d ' ')
IMG_COUNT=$(find "$DEST/assets/img" -type f 2>/dev/null | wc -l | tr -d ' ')
VERSION="$TAG_DIR"

echo "2/3 Локальные npm-зависимости (без CDN)…"
export PATH="/opt/homebrew/bin:/usr/local/bin:$PATH"
if command -v npm >/dev/null 2>&1; then
  "$ROOT/scripts/vendor-adminlte-cdn.sh" || echo "   WARN: vendor-adminlte-cdn.sh — проверьте npm" >&2
else
  echo "   npm не найден — останутся ссылки на cdn.jsdelivr.net" >&2
fi

NPM_FILES=$(find "$DEST/npm" -type f 2>/dev/null | wc -l | tr -d ' ')
NPM_FILES=${NPM_FILES:-0}

cat >"$DEST/VERSION.txt" <<EOF
AdminLTE ${VERSION}
Tag: ${TAG}
Source: https://github.com/ColorlibHQ/AdminLTE/releases/tag/${TAG}
Installed: $(date -u +"%Y-%m-%d %H:%M UTC")
Payload: ${SOURCE_KIND}
Files: ${FILE_COUNT} html+css+js+assets (+ ${NPM_FILES} vendored npm)
Images: ${IMG_COUNT} under assets/img/
Local deps: public/html/npm/ (bootstrap, icons, charts, …)
URL: http://localhost:3002/html/
EOF

echo "3/3 Проверка…"
test -f "$DEST/index.html" || { echo "Нет index.html" >&2; exit 1; }
test -f "$DEST/assets/img/AdminLTELogo.png" || { echo "Нет assets/img" >&2; exit 1; }
test -f "$DEST/css/adminlte.min.css" || test -f "$DEST/css/adminlte.css" || { echo "Нет adminlte.css" >&2; exit 1; }

echo ""
echo "AdminLTE ${VERSION}"
echo "Файлов dist: ${FILE_COUNT}, картинок: ${IMG_COUNT}, npm vendor: ${NPM_FILES}"
du -sh "$DEST" | awk '{print "Размер:", $1}'
echo "http://localhost:3002/html/"
