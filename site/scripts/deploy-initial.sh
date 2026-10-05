#!/usr/bin/env bash
# Только первый перенос локального сайта; повторно существующую БД не заменяет.
set -euo pipefail
cd "$(dirname "$0")/.."
ss_host=${SS_SSH_HOST:-root@178.253.45.218}
ssh "$ss_host" 'test ! -e /etc/soberi-stanok/wordpress-initialized'
install -d -m 700 data/deploy
./bin/wp db export /data/deploy/database.sql --add-drop-table
chmod 600 data/deploy/database.sql
gzip -f data/deploy/database.sql
rsync -az --exclude wp-config-docker.php --exclude wp-config.php --exclude .htaccess --exclude wp-content/debug.log --exclude wp-content/cache/ --exclude wp-content/mu-plugins/ --exclude wp-content/themes/ wp/ "$ss_host":/var/www/soberi-stanok/public/
rsync -az wp-content/themes/soberi-stanok "$ss_host":/var/www/soberi-stanok/public/wp-content/themes/
rsync -az wp-content/mu-plugins/ "$ss_host":/var/www/soberi-stanok/public/wp-content/mu-plugins/
rsync -az server scripts "$ss_host":/opt/soberi-stanok/
scp data/deploy/database.sql.gz "$ss_host":/root/soberi-stanok/import.sql.gz
ssh "$ss_host" 'gzip -dc /root/soberi-stanok/import.sql.gz | mariadb wordpress'
ssh "$ss_host" 'bash /opt/soberi-stanok/scripts/setup-server-wordpress.sh && bash /opt/soberi-stanok/scripts/setup-server-cron.sh && bash /opt/soberi-stanok/scripts/setup-server-backups.sh'
echo 'Первый перенос выполнен. Локальный дамп: site/data/deploy/ (не Git).'
