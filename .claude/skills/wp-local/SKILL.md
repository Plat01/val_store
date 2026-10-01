---
name: wp-local
description: Локальное окружение WordPress «собери станок» в docker — запуск, WP-CLI, почта, сброс, импорт, логи. Использовать при любых командах к локальному сайту.
---

# Локальный WordPress (docker)

Всё выполняется из `site/`.

| Что | Команда |
|---|---|
| Поднять с нуля / доустановить | `./scripts/bootstrap.sh` (идемпотентно) |
| Старт / стоп | `docker compose up -d` / `docker compose stop` |
| WP-CLI | `./bin/wp <команда>` (например `./bin/wp plugin list`) |
| PHP-код в контексте WP | `./bin/wp eval '…'` или `./bin/wp eval-file /scripts/x.php` |
| Лог PHP | `tail -f wp/wp-content/debug.log` |
| phpMyAdmin | `docker compose --profile tools up -d phpmyadmin` → http://localhost:8081 |
| Импорт каталога со старого сайта | `./scripts/import-old.sh [дамп.sql[.gz]]` (идемпотентно, отчёт URL → `data/url-map.csv`) |
| Полный сброс | `docker compose down -v && rm -rf wp && ./scripts/bootstrap.sh` |

- Сайт http://localhost:8080, админка `/wp-admin` (admin / admin).
- Почта: все письма → Mailpit http://localhost:8025 (API `/api/v1/messages`).
- Префикс таблиц `ss_`, БД `wordpress`.

## Где что лежит
- `wp/` — ядро и plugins/uploads (НЕ в git, генерируется).
- `wp-content/themes/soberi-stanok/` — наша тема (bind mount, в git).
- `wp-content/mu-plugins/soberi-stanok.php` + `soberi-stanok/*.php` — модули (каждый файл
  подключается автоматически).
- `scripts/` — смонтированы в контейнер как `/scripts` (read-only); `data/` → `/data`
  (дампы, CSV, временные файлы, не в git).
- `scripts/setup.sh` — все настройки WP через WP-CLI. Любую настройку сайта добавлять
  **сюда** (а не кликать в админке), чтобы сайт воспроизводился с нуля.
- `scripts/plugins.sh` — список плагинов.

## Правила
- Не править файлы в `wp/` — они перезаписываются.
- Ради проверки изменений темы перезапуск не нужен (bind mount).
- После изменения правил ссылок: `./bin/wp rewrite flush --hard`.

## Импорт каталога (`scripts/import/import-old.php`)
- Источник: БД `old` (дамп старого сайта, префикс `wp_`) + картинки из `$OLD_FILES` → `/old-files`
  (на старом сайте `UPLOADS = 'files'`, медиатека лежала в `public_html/files/`).
- Связь со старыми сущностями — мета `_ss_old_id` (товары, вариации, вложения, категории).
- URL товара = основная категория (`rank_math_primary_product_cat`, из Yoast старого сайта) +
  Premmerce Permalink Manager (hierarchical) → `/категория/подкатегория/товар/`, как на старом сайте.
- Cyr-To-Lat обязателен: ключи локальных атрибутов вариаций транслитерированы (`Модель` → `model`).
- Единица измерения товара — мета `_ss_unit` («м», «шт»).
