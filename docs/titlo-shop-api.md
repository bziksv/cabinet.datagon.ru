# Titlo Shop API (интеграции магазинов)

HTTP API кабинета для внешних админок (пилот: vilmed Bitrix-модуль `titlo.relevance`).

## Выдача ключа

1. Войти в кабинет → Профиль → **API keys** (`/integration/api-keys`).
2. Создать ключ (показывается один раз, префикс `titlo_…`).
3. В магазине: `Authorization: Bearer titlo_…`.

## Base URL

`https://cabinet.titlo.ru/api/v1` (локально: `http://cabinet…/api/v1`).

## Эндпоинты

| Method | Path | Описание |
|--------|------|----------|
| POST | `/relevance/analyses` | `{url, phrase, region?, engine?, top?}` → `{analysis_id, status}` (дефолты: yandex / 213 Москва / top 20) |
| GET | `/relevance/analyses/{analysis_id}` | poll → `history_id` при `done` |
| GET | `/relevance/histories?url=&phrase=&limit=10` | список прошлых проверок посадочной (динамика баллов, `delta_points`) |
| GET | `/relevance/histories/{id}` | краткая сводка |
| GET | `/relevance/histories/{id}/missing-phrases?filter=zero\|diff\|all` | legacy: недостающие фразы |
| GET | `/relevance/histories/{id}/missing-phrases?mode=tlp` | TLP unigram: `missing` + `diff`, сортировка `tfidf_top` |
| POST | `/ai/generate` | `{type: category\|preview\|detail\|phrase, url?, name?, keywords[], note?}` |
| GET | `/ai/generate/{record_id}` | poll результата |
| POST | `/relevance/batches` | `{items:[{external_id?, url, phrase}]}` до 100 |
| GET | `/relevance/batches/{batch_id}` | прогресс пачки |

Лимиты релевантности — как у владельца ключа в кабинете. DeepSeek токен только на стороне cabinet.

## Vilmed

Модуль: `vilmed.ru/local/modules/titlo.relevance/`.  
Поля: товар `PREVIEW_TEXT` + `DETAIL_TEXT`, категория `DESCRIPTION` (IB 24).
