#!/usr/bin/env bash
# Disable static downtime page (remove ENABLED flag).
# Usage: sudo ./scripts/outage-disable.sh
set -euo pipefail

APP="${CABINET_APP:-/var/www/cabinet_titl_usr/data/www/cabinet.titlo.ru}"
FLAG="$APP/storage/app/outage/ENABLED"

rm -f "$FLAG"
echo "OUTAGE DISABLED (flag removed)"
echo "Check: curl -sI https://cabinet.titlo.ru/login | head -5"
