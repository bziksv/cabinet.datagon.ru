#!/usr/bin/env bash
# Локальные копии npm-пакетов → public/html/npm/ + замена cdn.jsdelivr.net в HTML.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
DEST="$ROOT/public/html"
NPM_DIR="$DEST/npm"
WORK="/tmp/adminlte-vendor-$$"

if [[ ! -f "$DEST/index.html" ]]; then
  echo "Сначала: ./scripts/sync-lte-html.sh --force" >&2
  exit 1
fi

if ! command -v npm >/dev/null 2>&1; then
  echo "npm не установлен" >&2
  exit 1
fi

echo "→ $NPM_DIR"
rm -rf "$NPM_DIR" "$WORK"
mkdir -p "$NPM_DIR" "$WORK"

cat >"$WORK/package.json" <<'EOF'
{
  "private": true,
  "dependencies": {
    "@fontsource/source-sans-3": "5.0.12",
    "@popperjs/core": "2.11.8",
    "@simonwep/pickr": "1.9.1",
    "apexcharts": "3.37.1",
    "bootstrap": "5.3.7",
    "bootstrap-icons": "1.13.1",
    "chart.js": "4.5.1",
    "dropzone": "6.0.0-beta.2",
    "easymde": "2.21.0",
    "filepond": "4.32.12",
    "flatpickr": "4.6.13",
    "fullcalendar": "6.1.20",
    "glightbox": "3.3.1",
    "imask": "7.6.1",
    "jsvectormap": "1.5.3",
    "nouislider": "15.8.1",
    "overlayscrollbars": "2.11.0",
    "quill": "2.0.3",
    "sortablejs": "1.15.7",
    "tabulator-tables": "6.4.0",
    "tom-select": "2.6.1"
  }
}
EOF

echo "npm install (все зависимости демо)…"
(cd "$WORK" && npm install --omit=dev --no-audit --no-fund --silent 2>&1) || \
  (cd "$WORK" && npm install --omit=dev --no-audit --no-fund)

# Доп. версии, если упомянуты в HTML
(cd "$WORK" && npm install bootstrap@5.3.8 apexcharts@5.12.0 sortablejs@1.15.0 --omit=dev --no-audit --no-fund --silent 2>/dev/null) || true

copy_pkg() {
  local name="$1"
  local ver="$2"
  local src=""
  if [[ "$name" == @*/* ]]; then
    src="$WORK/node_modules/$name"
  elif [[ "$name" == @* ]]; then
    src="$WORK/node_modules/$name"
  else
    src="$WORK/node_modules/$name"
  fi
  if [[ ! -d "$src" ]]; then
    echo "  skip (нет в node_modules): $name@$ver" >&2
    return 0
  fi
  local dest="$NPM_DIR/${name}@${ver}"
  rm -rf "$dest"
  cp -a "$src" "$dest"
}

WORK="$WORK" NPM_DIR="$NPM_DIR" node <<'NODE'
const fs = require('fs');
const path = require('path');
const work = process.env.WORK;
const npmDir = process.env.NPM_DIR;
const lockPath = path.join(work, 'package-lock.json');
const lock = JSON.parse(fs.readFileSync(lockPath, 'utf8'));
const pkgs = lock.packages || {};
const copied = new Set();
for (const [key, meta] of Object.entries(pkgs)) {
  if (!key.startsWith('node_modules/')) continue;
  const rel = key.replace(/^node_modules\//, '');
  if (!rel || rel.includes('node_modules/')) continue;
  if (!meta.version) continue;
  const tag = `${rel}@${meta.version}`;
  if (copied.has(tag)) continue;
  const src = path.join(work, 'node_modules', rel);
  if (!fs.existsSync(src)) continue;
  const dest = path.join(npmDir, tag);
  fs.cpSync(src, dest, { recursive: true });
  copied.add(tag);
}
console.log('Скопировано пакетов:', copied.size);
NODE

rm -rf "$WORK"

echo "Перепись CDN → ./npm/ в HTML…"
find "$DEST" -name '*.html' -print0 | while IFS= read -r -d '' f; do
  perl -pi -e 's|https://cdn\.jsdelivr\.net/npm/|./npm/|g' "$f"
done

COUNT=$(find "$NPM_DIR" -type f 2>/dev/null | wc -l | tr -d ' ')
echo "Vendored: ${COUNT} файлов в public/html/npm/"
