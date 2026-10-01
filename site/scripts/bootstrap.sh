#!/usr/bin/env bash
# Поднимает локальный сайт с нуля: контейнеры → установка WP → настройки.
# Повторный запуск безопасен (шаги идемпотентны).
set -euo pipefail
cd "$(dirname "$0")/.."

[ -f .env ] || cp .env.example .env
mkdir -p wp data

docker compose up -d db wordpress mailpit

echo "Жду, пока контейнер WordPress разложит ядро…"
for _ in $(seq 1 60); do
  [ -f wp/wp-config.php ] && [ -f wp/wp-settings.php ] && break
  sleep 2
done

docker compose run --rm --entrypoint sh wpcli /scripts/setup.sh
