#!/usr/bin/env bash
# Первый перенос: DB уже импортирована, файлы в /var/www/soberi-stanok/public.
set -euo pipefail
[[ $EUID = 0 ]] || exit 1
. /etc/soberi-stanok/secrets.env
ss_project=$(cd "$(dirname "$0")/.." && pwd)
ss_root=/var/www/soberi-stanok/public
export DB_PASSWORD REDIS_PASSWORD WP_ADMIN_USER WP_ADMIN_PASSWORD SS_DOMAIN
# Конфиг над document root, недоступен по HTTP и не меняется PHP-процессом.
php8.3 <<'PHP' > /var/www/soberi-stanok/wp-config.php
<?php
$c = [
'DB_NAME'=>'wordpress', 'DB_USER'=>'soberi_stanok', 'DB_PASSWORD'=>getenv('DB_PASSWORD'),
'DB_HOST'=>'localhost', 'DB_CHARSET'=>'utf8mb4', 'DB_COLLATE'=>'',
'FS_METHOD'=>'direct', 'WP_ENVIRONMENT_TYPE'=>'staging', 'WP_HOME'=>'https://'.getenv('SS_DOMAIN'),
'WP_SITEURL'=>'https://'.getenv('SS_DOMAIN'), 'WP_DEBUG'=>false,
'DISABLE_WP_CRON'=>true, 'WP_DEBUG_DISPLAY'=>false, 'DISALLOW_FILE_EDIT'=>true, 'FORCE_SSL_ADMIN'=>true,
'WP_POST_REVISIONS'=>10, 'WP_MEMORY_LIMIT'=>'256M', 'WP_MAX_MEMORY_LIMIT'=>'256M',
'WP_REDIS_SCHEME'=>'unix', 'WP_REDIS_PATH'=>'/run/redis/redis-server.sock',
'WP_REDIS_PASSWORD'=>getenv('REDIS_PASSWORD'), 'WP_REDIS_DATABASE'=>0,
'WP_REDIS_PREFIX'=>'soberi-stanok:', 'WP_REDIS_MAXTTL'=>86400,
];
echo "<?php\n";
foreach ($c as $k=>$v) echo 'define('.var_export($k,true).', '.var_export($v,true).");\n";
foreach (['AUTH_KEY','SECURE_AUTH_KEY','LOGGED_IN_KEY','NONCE_KEY','AUTH_SALT','SECURE_AUTH_SALT','LOGGED_IN_SALT','NONCE_SALT'] as $k)
 echo 'define('.var_export($k,true).', '.var_export(bin2hex(random_bytes(48)),true).");\n";
echo "define('DISALLOW_FILE_MODS', !(defined('WP_CLI') && WP_CLI));\n";
echo '$table_prefix = '.var_export('ss_',true).";\n";
echo "if (!defined('ABSPATH')) define('ABSPATH', __DIR__ . '/public/');\nrequire_once ABSPATH . 'wp-settings.php';\n";
PHP
chmod 640 /var/www/soberi-stanok/wp-config.php
chown root:soberi-stanok /var/www/soberi-stanok/wp-config.php
rm -f "$ss_root/wp-config.php" "$ss_root/wp-content/debug.log" "$ss_root/wp-config-docker.php"
wp() { /usr/local/bin/wp --allow-root --path="$ss_root" "$@"; }
wp core is-installed
if [[ ! -f /etc/soberi-stanok/wordpress-initialized ]]; then
  wp search-replace 'http://localhost:8080' "https://$SS_DOMAIN" --all-tables-with-prefix --precise --skip-columns=guid
  wp eval-file "$ss_project/scripts/server/secure-wordpress.php"
  touch /etc/soberi-stanok/wordpress-initialized
fi
wp option update home "https://$SS_DOMAIN"
wp option update siteurl "https://$SS_DOMAIN"
wp option update blog_public 0
wp option update users_can_register 0
wp option update default_role subscriber
wp eval 'if (!is_array(get_option("limit_login_lockouts", []))) delete_option("limit_login_lockouts");'
wp option update limit_login_allowed_retries 4
wp option update limit_login_lockout_duration 1200
wp option update limit_login_valid_duration 86400
wp option update limit_login_allowed_lockouts 4
wp option update limit_login_long_duration 86400
wp plugin activate limit-login-attempts-reloaded
wp plugin is-installed redis-cache || wp plugin install redis-cache
wp plugin activate redis-cache
wp redis enable
wp rewrite flush
wp cache flush
# Программный код не записывается PHP; загруженные файлы доступны для записи.
chown -R root:soberi-stanok "$ss_root"
find "$ss_root" -type d -exec chmod 755 {} +
find "$ss_root" -type f -exec chmod 644 {} +
for ss_dir in uploads uploads-webpc; do
  install -d -o soberi-stanok -g soberi-stanok "$ss_root/wp-content/$ss_dir"
  chown -R soberi-stanok:soberi-stanok "$ss_root/wp-content/$ss_dir"
done
find /var/cache/nginx/soberi-stanok -type f -delete
# Удалённые неиспользуемые темы/плагины и локальный Mailpit не переносятся.
echo 'WordPress: HTTPS, noindex, новый администратор и Redis настроены.'
