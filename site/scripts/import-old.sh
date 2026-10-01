#!/usr/bin/env bash
# Импорт каталога со старого сайта.
#   ./scripts/import-old.sh                      — из уже загруженной БД `old`
#   ./scripts/import-old.sh path/to/dump.sql[.gz] — сначала (пере)загрузить дамп в БД `old`
# Картинки берутся из $OLD_FILES (см. .env), по умолчанию ../old_site/public_html/files.
set -euo pipefail
cd "$(dirname "$0")/.."
set -a; [ -f .env ] && . <(grep -vE "^(UID|GID)=" .env); set +a
OLD_DB=${OLD_DB:-old}

if [ $# -ge 1 ]; then
  echo "Загружаю дамп $1 в БД $OLD_DB…"
  docker compose exec -T db mariadb -uroot -p"${DB_ROOT_PASSWORD:-root}" -e \
    "DROP DATABASE IF EXISTS \`$OLD_DB\`; CREATE DATABASE \`$OLD_DB\` CHARACTER SET utf8mb4;
     GRANT ALL ON \`$OLD_DB\`.* TO '${DB_USER:-wordpress}'@'%';"
  if [[ "$1" == *.gz ]]; then gunzip -c "$1"; else cat "$1"; fi \
    | docker compose exec -T db mariadb -uroot -p"${DB_ROOT_PASSWORD:-root}" "$OLD_DB"
fi

./bin/wp eval-file /scripts/import/import-old.php
