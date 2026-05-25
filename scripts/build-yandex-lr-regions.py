#!/usr/bin/env python3
"""Сборка config/yandex_lr_regions.json из scripts/yandex-lr-regions-paste.txt"""
import json
import re
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
PASTE = ROOT / 'scripts' / 'yandex-lr-regions-paste.txt'
OUT = ROOT / 'config' / 'yandex_lr_regions.json'

SKIP = (
    '8-800',
    'По любым вопросам',
    'admin@1ps',
    'Полный список параметров',
    'пн-пт с',
)


def main() -> None:
    text = PASTE.read_text(encoding='utf-8')
    regions = []
    seen = set()

    for line in text.splitlines():
        line = line.strip()
        if not line or any(s in line for s in SKIP):
            continue
        m = re.match(r'^(\d+)\s+(.+)$', line)
        if not m:
            continue
        rid, name = m.group(1), m.group(2).strip()
        if rid in seen:
            continue
        seen.add(rid)
        regions.append({'id': rid, 'name': name})

    OUT.write_text(
        json.dumps(regions, ensure_ascii=False, separators=(',', ':')),
        encoding='utf-8',
    )
    print(f'{len(regions)} regions -> {OUT}')


if __name__ == '__main__':
    main()
