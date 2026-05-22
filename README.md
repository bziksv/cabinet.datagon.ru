# cabinet.datagon.ru (Laravel / кабинет)

Локальная папка для workspace рядом с **datagon.ru**.

**Git:** [github.com/bziksv/cabinet.datagon.ru](https://github.com/bziksv/cabinet.datagon.ru). Инструкция: [datagon.ru/docs/cabinet-git.md](../datagon.ru/docs/cabinet-git.md).

## Деплой и серверы (актуально)

**Не дублировать здесь** — каноническая заметка в маркетинг-репозитории:

**[datagon.ru/docs/cabinet-servers.md](../datagon.ru/docs/cabinet-servers.md)** · деплой VPS: **[cabinet-deploy.md](../datagon.ru/docs/cabinet-deploy.md)**

Кратко (май 2026):

| | Старый | Новый |
|---|--------|--------|
| IP | `178.250.157.140` | `155.212.171.103` |
| Домен | lk.redbox.su | cabinet.datagon.ru |
| Порт | (старый VPS) | **3002** (nginx → `127.0.0.1:3002`; datagon.ru — **3001**) |
| Путь | `/var/www/redbox.su/data/www/lk.redbox.su` | `/var/www/cabinet_data_usr/data/www/cabinet.datagon.ru` |
| БД | **здесь** | подключение к старому серверу, пока БД не перенесли |

Прод для пользователей пока **lk.redbox.su**; файлы на новом VPS уже скопированы.

## Локальный запуск (Mac)

1. `.env` с VPS (имя файла именно **`.env`**, не `env`).
2. PHP **7.4**: `brew install shivammathur/php/php@7.4`, в PATH: `/opt/homebrew/opt/php@7.4/bin`.
3. `composer install --no-dev`
4. `./scripts/dev-serve.sh` → http://localhost:3002 (3 PHP-воркера + прокси; остановка: `./scripts/dev-parallel.sh stop`)

В `.env` для Mac: **`DB_HOST=178.250.157.140`** (не `127.0.0.1`). Подробнее: [datagon.ru/docs/cabinet-git.md](../datagon.ru/docs/cabinet-git.md).

## Эталон UI (обязательно для вёрстки)

**cabinet.datagon.ru** (local **:3002**, прод **cabinet.datagon.ru**) — все UI-элементы для Blade-страниц **берём из шаблона AdminLTE** в `public/html/`:

- **URL:** http://localhost:3002/html/ (AdminLTE 4.0.0, см. `public/html/VERSION.txt`)
- **Как:** открыть подходящую демо-страницу (`forms/`, `tables/`, `widgets/`, `layout/`, …) → подобрать блок → перенести разметку в `resources/views/`
- **Обновление шаблона:** `./scripts/sync-lte-html.sh --force`

Полная инструкция: **[datagon.ru/docs/cabinet-reference.md](../datagon.ru/docs/cabinet-reference.md)** (раздел «Эталон UI кабинета»). Рабочий `layouts/app.blade.php` пока AdminLTE 3 — при переносе из v4 см. [migration guide](https://adminlte.io/themes/v4/docs/migration.html).
