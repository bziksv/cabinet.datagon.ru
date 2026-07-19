#!/usr/bin/env python3
"""Сборка config/google_geo_regions.json из https://xmlstock.com/geotargets-google.csv

Включает:
- все Active Country
- все Active City (мир)

Раньше фильтровали только RU City — для модулей с Google-регионами этого мало.
"""
import csv
import importlib.util
import json
import sys
import urllib.request
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
OUT = ROOT / 'config' / 'google_geo_regions.json'
CSV_URL = 'https://xmlstock.com/geotargets-google.csv'
ALLOWED_TYPES = {'Country', 'City'}


def load_ru_en() -> dict:
    path = ROOT / 'scripts' / 'google-geo-ru-en.py'
    spec = importlib.util.spec_from_file_location('google_geo_ru_en', path)
    mod = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(mod)
    return {v: k for k, v in mod.RU_EN.items()}


def load_csv(path: Path) -> None:
    en_to_ru = load_ru_en()
    regions = []
    seen = set()

    with path.open(encoding='utf-8') as f:
        for row in csv.DictReader(f):
            if row.get('Status') != 'Active':
                continue
            target_type = (row.get('Target Type') or '').strip()
            if target_type not in ALLOWED_TYPES:
                continue

            rid = row['Criteria ID'].strip().strip('"')
            name_en = row['Name'].strip().strip('"')
            country = (row.get('Country Code') or '').strip().upper()
            if not rid.isdigit() or not name_en or rid in seen:
                continue

            seen.add(rid)
            name_ru = en_to_ru.get(name_en, '')
            regions.append({
                'id': rid,
                'name': name_ru or name_en,
                'name_en': name_en,
                'country_code': country,
                'type': target_type,
            })

    # Страны сначала, потом города; внутри — по имени
    type_rank = {'Country': 0, 'City': 1}
    regions.sort(key=lambda item: (
        type_rank.get(item.get('type', ''), 9),
        item['name'].lower(),
        item.get('country_code') or '',
    ))
    OUT.write_text(
        json.dumps(regions, ensure_ascii=False, separators=(',', ':')),
        encoding='utf-8',
    )
    countries = sum(1 for r in regions if r.get('type') == 'Country')
    cities = sum(1 for r in regions if r.get('type') == 'City')
    print(f'{len(regions)} regions ({countries} countries, {cities} cities) -> {OUT}')


def main() -> None:
    if len(sys.argv) > 1:
        load_csv(Path(sys.argv[1]))
        return

    tmp = Path('/tmp/geotargets-google.csv')
    if not tmp.exists():
        print(f'Downloading {CSV_URL} ...')
        urllib.request.urlretrieve(CSV_URL, tmp)
    else:
        print(f'Using cached {tmp}')
    load_csv(tmp)


if __name__ == '__main__':
    main()
