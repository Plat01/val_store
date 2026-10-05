#!/usr/bin/env bash
# Root, ежедневная полная копия. SS_BACKUP_DIR можно сменить в backup.env.
set -euo pipefail
umask 077
[[ -f /etc/soberi-stanok/backup.env ]] && . /etc/soberi-stanok/backup.env
ss_base=${SS_BACKUP_DIR:-/var/backups/soberi-stanok}
install -d -m 700 "$ss_base"
exec 9>/run/lock/soberi-stanok-backup.lock
flock -n 9 || exit 0
ss_stamp=$(date +%Y-%m-%d_%H%M%S)
ss_work=$(mktemp -d "$ss_base/.partial.XXXXXX")
trap 'rm -rf "$ss_work"' EXIT
mariadb-dump --single-transaction --quick --routines --events --triggers wordpress | gzip > "$ss_work/database.sql.gz"
tar -czf "$ss_work/site.tar.gz" -C /var/www soberi-stanok
# Доступы входят в защищённую root-only копию: нужны для восстановления.
tar -czf "$ss_work/config.tar.gz" -C / etc/soberi-stanok etc/nginx etc/php/8.3 etc/mysql/mariadb.conf.d/60-soberi-stanok.cnf etc/redis/redis.conf etc/letsencrypt opt/soberi-stanok etc/systemd/system/soberi-stanok-backup.service etc/systemd/system/soberi-stanok-backup.timer etc/systemd/system/soberi-stanok-cron.service etc/systemd/system/soberi-stanok-cron.timer etc/ssh/sshd_config.d/00-soberi-stanok.conf etc/fail2ban/jail.d/soberi-stanok.local etc/apt/apt.conf.d/20auto-upgrades etc/apt/apt.conf.d/52-soberi-stanok etc/logrotate.d/soberi-stanok-php etc/systemd/system/mariadb.socket etc/rc.local
(cd "$ss_work" && sha256sum database.sql.gz site.tar.gz config.tar.gz > SHA256SUMS)
mv "$ss_work" "$ss_base/$ss_stamp"
# 14 календарных дней; только завершённые каталоги копий.
find "$ss_base" -mindepth 1 -maxdepth 1 -type d -name '20??-??-??_*' -mtime +13 -exec rm -rf -- {} +
echo "Копия создана: $ss_base/$ss_stamp"
