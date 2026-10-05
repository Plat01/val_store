#!/usr/bin/env bash
# Последующие выкладки кода; DB/uploads сохраняются.
set -euo pipefail
cd "$(dirname "$0")/.."
ss_host=${SS_SSH_HOST:-root@178.253.45.218}
ssh "$ss_host" 'test -e /etc/soberi-stanok/wordpress-initialized && /opt/soberi-stanok/scripts/server/backup.sh'
rsync -az --delete wp-content/themes/soberi-stanok/ "$ss_host":/var/www/soberi-stanok/public/wp-content/themes/soberi-stanok/
rsync -az --delete wp-content/mu-plugins/ "$ss_host":/var/www/soberi-stanok/public/wp-content/mu-plugins/
rsync -az server scripts "$ss_host":/opt/soberi-stanok/
ssh "$ss_host" 'chown -R root:soberi-stanok /var/www/soberi-stanok/public/wp-content/themes /var/www/soberi-stanok/public/wp-content/mu-plugins; wp --allow-root --path=/var/www/soberi-stanok/public cache flush; find /var/cache/nginx/soberi-stanok -type f -delete; systemctl reload php8.3-fpm'
echo 'Код выложен; конфиги nginx требуют отдельного setup-server-site.sh.'
