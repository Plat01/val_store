#!/usr/bin/env bash
# Импорт каталога со старого сайта.
#   ./scripts/import-old.sh                      — из уже загруженной БД `old`
#   ./scripts/import-old.sh path/to/dump.sql[.gz] — сначала (пере)загрузить дамп в БД `old`
# Картинки берутся из $OLD_FILES (см. .env), по умолчанию ../old_site/public_html/files.
set -euo pipefail
cd "$(dirname "$0")/.."
set -a; [ -f .env ] && . <(grep -vE "^(UID|GID)=" .env); set +a
OLD_DB=${OLD_DB:-old}
[ "$OLD_DB" != "${DB_NAME:-wordpress}" ] || { echo "База источника не должна совпадать с рабочей" >&2; exit 1; }
[[ "$OLD_DB" =~ ^[a-zA-Z0-9_]+$ ]] || { echo "Недопустимое имя OLD_DB" >&2; exit 1; }
if [ $# -ge 1 ]; then
  [ -r "$1" ] || { echo "Дамп недоступен: $1" >&2; exit 1; }
fi

if [ $# -ge 1 ]; then
  echo "Загружаю дамп $1 в БД $OLD_DB…"
  docker compose exec -T db mariadb -uroot -p"${DB_ROOT_PASSWORD:-root}" -e \
    "DROP DATABASE IF EXISTS \`$OLD_DB\`; CREATE DATABASE \`$OLD_DB\` CHARACTER SET utf8mb4;
     GRANT ALL ON \`$OLD_DB\`.* TO '${DB_USER:-wordpress}'@'%';"
  if [[ "$1" == *.gz ]]; then gunzip -c "$1"; else cat "$1"; fi \
    | docker compose exec -T db mariadb -uroot -p"${DB_ROOT_PASSWORD:-root}" "$OLD_DB"
fi

./bin/wp eval-file /scripts/import/import-old.php

# SEO/alt и WebP также после повторного импорта свежего дампа.
./bin/wp eval-file /scripts/setup-seo.php
./bin/wp webp-converter regenerate --force
