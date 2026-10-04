#!/bin/sh
# Выполняется внутри контейнера wpcli (см. bootstrap.sh). Идемпотентен.
set -eu

: "${WP_URL:=http://localhost:8080}"
: "${WP_ADMIN_USER:=admin}"
: "${WP_ADMIN_PASSWORD:=admin}"
: "${WP_ADMIN_EMAIL:=admin@example.test}"

ss_new_install=0
if ! wp core is-installed 2>/dev/null; then
  ss_new_install=1
  wp core install --url="$WP_URL" --title="Собери станок" \
    --admin_user="$WP_ADMIN_USER" --admin_password="$WP_ADMIN_PASSWORD" \
    --admin_email="$WP_ADMIN_EMAIL" --skip-email
fi

# --- Язык и базовые опции --------------------------------------------------
wp language core install ru_RU --activate
wp option update timezone_string 'Europe/Moscow'
wp option update date_format 'd.m.Y'
wp option update time_format 'H:i'
wp option update start_of_week 1
wp option update blogdescription 'Комплектующие для станков с ЧПУ'
wp option update blog_public 0              # индексация закрыта до запуска
wp option update default_comment_status closed
wp option update default_ping_status closed
wp option update uploads_use_yearmonth_folders 1

# --- Чистка демо-контента и лишних тем/плагинов ---------------------------
if [ "$ss_new_install" = 1 ]; then
  wp post delete 1 2 3 --force >/dev/null 2>&1 || true
fi
wp plugin delete akismet hello >/dev/null 2>&1 || true

# --- Тема -----------------------------------------------------------------
wp theme activate soberi-stanok
for t in $(wp theme list --status=inactive --field=name); do
  wp theme delete "$t" >/dev/null 2>&1 || true
done

# --- Плагины ----------------------------------------------------------------
. /scripts/plugins.sh
# shellcheck disable=SC2086
wp plugin install $SS_PLUGINS --activate
wp language plugin install --all ru_RU >/dev/null 2>&1 || true

# --- Ссылки -----------------------------------------------------------------
wp rewrite structure "/%postname%/" --hard >/dev/null

# --- Магазин (этап 2) -------------------------------------------------------
[ -f /scripts/setup-shop.sh ] && . /scripts/setup-shop.sh

[ -f /scripts/setup-pages.sh ] && . /scripts/setup-pages.sh

[ -f /scripts/setup-seo.sh ] && . /scripts/setup-seo.sh

wp rewrite flush --hard >/dev/null
wp cache flush >/dev/null
echo "Готово: $WP_URL  (админка: $WP_URL/wp-admin  $WP_ADMIN_USER; пароль из .env)"
