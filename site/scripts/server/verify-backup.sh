#!/usr/bin/env bash
# Изолированное восстановление: реальная БД и сайт не изменяются.
set -euo pipefail
umask 077
ss_backup=${1:?Укажите полный каталог резервной копии}
ss_tmp=$(mktemp -d /var/tmp/soberi-restore.XXXXXX)
ss_db=ss_restore_$(date +%s)_$$
ss_cleanup() { mariadb -e "DROP DATABASE IF EXISTS $ss_db"; rm -rf "$ss_tmp"; }
trap ss_cleanup EXIT
(cd "$ss_backup" && sha256sum -c SHA256SUMS)
mariadb -e "CREATE DATABASE $ss_db CHARACTER SET utf8mb4"
gzip -dc "$ss_backup/database.sql.gz" | mariadb "$ss_db"
tar -xzf "$ss_backup/site.tar.gz" -C "$ss_tmp"
ss_count=$(mariadb -N "$ss_db" -e 'SELECT COUNT(*) FROM ss_posts WHERE post_type IN ("product","product_variation");')
[[ $ss_count -gt 0 ]] || exit 1
ss_uploads="$ss_tmp/soberi-stanok/public/wp-content/uploads"
[[ -d $ss_uploads ]] || exit 1
ss_files=$(find "$ss_uploads" -type f | wc -l)
[[ $ss_files -gt 0 ]] || exit 1
# Проверить фактические байты распакованных uploads против текущей копии сайта.
diff -qr "$ss_uploads" /var/www/soberi-stanok/public/wp-content/uploads
tar -tzf "$ss_backup/config.tar.gz" > "$ss_tmp/config-files.txt"
grep -q 'etc/soberi-stanok/secrets.env' "$ss_tmp/config-files.txt"
echo "Восстановление проверено: $ss_count товаров/вариаций, $ss_files файлов uploads; контрольные суммы корректны."
