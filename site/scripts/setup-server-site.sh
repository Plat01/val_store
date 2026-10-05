#!/usr/bin/env bash
# После setup-server.sh, из доставленного каталога /opt/soberi-stanok/scripts.
set -euo pipefail
[[ $EUID = 0 ]] || exit 1
ss_project=$(cd "$(dirname "$0")/.." && pwd)
ss_domain=${SS_DOMAIN:-soberi-stanok.ru}
[[ $ss_domain =~ ^[a-z0-9.-]+$ ]] || exit 1
umask 077
ss_secrets=/etc/soberi-stanok/secrets.env
if [[ ! -f $ss_secrets ]]; then
  {
    printf 'SS_DOMAIN=%q\n' "$ss_domain"
    printf 'DB_PASSWORD=%q\n' "$(openssl rand -hex 32)"
    printf 'REDIS_PASSWORD=%q\n' "$(openssl rand -hex 32)"
    printf 'WP_ADMIN_USER=%q\n' 'store_manager'
    printf 'WP_ADMIN_PASSWORD=%q\n' "$(openssl rand -hex 24)"
  } > "$ss_secrets"
fi
. "$ss_secrets"
if [[ -f /root/soberi-stanok/admin.env ]]; then
  . /root/soberi-stanok/admin.env
  # В shell-escaped виде; пароль не появляется в аргументах или логах.
  sed -i '/^WP_ADMIN_USER=/d; /^WP_ADMIN_PASSWORD=/d' "$ss_secrets"
  printf 'WP_ADMIN_USER=%q\nWP_ADMIN_PASSWORD=%q\n' "$WP_ADMIN_USER" "$WP_ADMIN_PASSWORD" >> "$ss_secrets"
fi
chmod 600 "$ss_secrets"
# Root БД использует unix_socket (пароль root не работает в сети).
cat > /etc/mysql/mariadb.conf.d/60-soberi-stanok.cnf <<'SQLCONF'
[mysqld]
skip-networking
innodb_buffer_pool_size = 384M
max_connections = 40
local_infile = 0
SQLCONF
chmod 644 /etc/mysql/mariadb.conf.d/60-soberi-stanok.cnf
# Новые пакеты Debian включают mariadb.socket с ListenStream=3306.
# skip-networking не закрывает унаследованный от systemd сокет.
systemctl disable --now mariadb.socket
systemctl mask mariadb.socket
systemctl restart mariadb
mariadb <<SQL
CREATE DATABASE IF NOT EXISTS wordpress CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'soberi_stanok'@'localhost' IDENTIFIED BY '$DB_PASSWORD';
ALTER USER 'soberi_stanok'@'localhost' IDENTIFIED BY '$DB_PASSWORD';
GRANT ALL PRIVILEGES ON wordpress.* TO 'soberi_stanok'@'localhost';
DROP DATABASE IF EXISTS test;
DELETE FROM mysql.global_priv WHERE User='';
FLUSH PRIVILEGES;
SQL
# Redis: только Unix-сокет и отдельный случайный пароль.
cp -n /etc/redis/redis.conf /root/soberi-stanok/redis.original.conf || true
sed -i -E '/^(port |unixsocket |unixsocketperm |requirepass |maxmemory |maxmemory-policy |save |appendonly )/d' /etc/redis/redis.conf
cat >> /etc/redis/redis.conf <<REDIS
port 0
unixsocket /run/redis/redis-server.sock
unixsocketperm 770
requirepass $REDIS_PASSWORD
maxmemory 128mb
maxmemory-policy allkeys-lru
save ""
appendonly no
REDIS
systemctl restart redis-server
install -m 644 "$ss_project/server/php-pool.conf" /etc/php/8.3/fpm/pool.d/soberi-stanok.conf
rm -f /etc/php/8.3/fpm/pool.d/www.conf
touch /var/log/php-soberi-stanok.log
chown soberi-stanok:soberi-stanok /var/log/php-soberi-stanok.log
chmod 640 /var/log/php-soberi-stanok.log
install -m 644 "$ss_project/server/php-logrotate.conf" /etc/logrotate.d/soberi-stanok-php
php-fpm8.3 -t
systemctl restart php8.3-fpm
# Root пароль блокируется, SSH по выданным ключам сохраняется.
passwd -l root >/dev/null
install -m 644 "$ss_project/server/nginx-cache.conf" /etc/nginx/conf.d/soberi-stanok-cache.conf
install -m 644 "$ss_project/server/nginx-fastcgi.conf" /etc/nginx/snippets/ss-fastcgi.conf
# HTTP на время выпуска сертификата: сайт ещё не отдаётся.
if [[ ! -f /etc/letsencrypt/live/$ss_domain/fullchain.pem ]]; then
  rm -f /etc/nginx/sites-enabled/default
  cat > /etc/nginx/sites-available/soberi-stanok <<HTTP
server {
  listen 80 default_server;
  listen [::]:80 default_server;
  server_name $ss_domain www.$ss_domain;
  location ^~ /.well-known/acme-challenge/ { root /var/www/acme; }
  location / { return 503; }
}
HTTP
  ln -sfn /etc/nginx/sites-available/soberi-stanok /etc/nginx/sites-enabled/soberi-stanok
  nginx -t
  systemctl reload nginx
  certbot certonly --webroot -w /var/www/acme -d "$ss_domain" -d "www.$ss_domain" --non-interactive --agree-tos --register-unsafely-without-email
fi
sed "s/__DOMAIN__/$ss_domain/g" "$ss_project/server/nginx-site.conf" > /etc/nginx/sites-available/soberi-stanok
rm -f /etc/nginx/sites-enabled/default
ln -sfn /etc/nginx/sites-available/soberi-stanok /etc/nginx/sites-enabled/soberi-stanok
install -d /etc/letsencrypt/renewal-hooks/deploy
printf '#!/bin/sh\nnginx -t && systemctl reload nginx\n' > /etc/letsencrypt/renewal-hooks/deploy/nginx
chmod 755 /etc/letsencrypt/renewal-hooks/deploy/nginx
nginx -t
systemctl reload nginx
systemctl enable --now certbot.timer
echo 'Сервисы, staging и HTTPS настроены; секреты в /etc/soberi-stanok/secrets.env (600).'
