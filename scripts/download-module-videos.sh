#!/usr/bin/env bash
# Скачать обучающие ролики с YouTube в public/media/module-videos/ (не в git).
# Требует: yt-dlp (brew install yt-dlp  или  pip install yt-dlp)
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
OUT="$ROOT/public/media/module-videos"
MANIFEST="$ROOT/scripts/module-videos-manifest.json"

if ! command -v yt-dlp >/dev/null 2>&1; then
  echo "Установите yt-dlp: brew install yt-dlp"
  exit 1
fi

if ! command -v ffmpeg >/dev/null 2>&1; then
  echo "Нужен ffmpeg для mp4 со звуком: brew install ffmpeg"
  exit 1
fi

mkdir -p "$OUT"

if ! command -v jq >/dev/null 2>&1; then
  echo "Нужен jq: brew install jq"
  exit 1
fi

IDS=()
while IFS= read -r line; do
  IDS+=("$line")
done < <(jq -r '.videos[]' "$MANIFEST")

echo "Каталог: $OUT"
echo "Роликов в манифесте: ${#IDS[@]}"
echo ""

for id in "${IDS[@]}"; do
  mp4="$OUT/${id}.mp4"
  jpg="$OUT/${id}.jpg"
  url="https://www.youtube.com/watch?v=${id}"

  if [[ -f "$mp4" && -f "$jpg" ]]; then
    echo "[skip] $id — уже есть mp4+jpg"
    continue
  fi

  echo "[download] $id …"
  rm -f "$OUT/${id}.f"[0-9]*.* 2>/dev/null || true
  yt-dlp -f 'bv*[height<=720]+ba/b[height<=720]/b' \
    --merge-output-format mp4 \
    --no-playlist \
    --no-overwrites \
    -o "$OUT/${id}.%(ext)s" \
    "$url" || {
      echo "[warn] не удалось скачать $id" >&2
      continue
    }

  rm -f "$OUT/${id}.f"[0-9]*.* 2>/dev/null || true

  if [[ ! -f "$jpg" ]]; then
    yt-dlp --skip-download --write-thumbnail --convert-thumbnails jpg \
      -o "$OUT/${id}" "$url" 2>/dev/null || true
    for ext in jpg jpeg webp png; do
      if [[ -f "$OUT/${id}.${ext}" ]]; then
        [[ "$ext" != jpg ]] && mv -f "$OUT/${id}.${ext}" "$jpg"
        break
      fi
    done
  fi

  echo "[done] $id"
done

echo ""
echo "Готово. Залейте на VPS папку: public/media/module-videos/"
