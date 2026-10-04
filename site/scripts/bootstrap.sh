#!/usr/bin/env bash
# Поднимает локальный сайт с нуля: контейнеры → установка WP → настройки.
# Повторный запуск безопасен (шаги идемпотентны).
set -euo pipefail
cd "$(dirname "$0")/.."

[ -f .env ] || cp .env.example .env
wp_root=${WP_ROOT:-./wp}
mkdir -p "$wp_root" "${DATA_ROOT:-./data}"

docker compose up -d db wordpress mailpit

echo "Жду, пока контейнер WordPress разложит ядро…"
for _ in $(seq 1 60); do
  [ -f "$wp_root/wp-config.php" ] && [ -f "$wp_root/wp-settings.php" ] && break
  sleep 2
done

[ -f "$wp_root/wp-config.php" ] && [ -f "$wp_root/wp-settings.php" ] || { echo "Ядро WordPress не готово" >&2; exit 1; }
docker compose run --rm -T --entrypoint sh wpcli /scripts/setup.sh
