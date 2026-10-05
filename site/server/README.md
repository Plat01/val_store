# VPS FirstByte: этап 7

Debian 12, 178.253.45.218, 3 ГБ RAM, 67 ГБ основной диск.
Домен `soberi-stanok.ru`, DNS Reg.ru: A @ и A www → IP VPS; AAAA нет.
Сайт работает как **staging**: витрина открыта всем, nginx без пароля;
индексация закрыта `blog_public=0` и
`X-Robots-Tag: noindex,nofollow,noarchive`. Запуск и SMTP — этап 8.

## Доступ

- Сайт: https://soberi-stanok.ru/.
- Админка: https://soberi-stanok.ru/wp-admin/ — обычный браузер, HTTPS.
- WordPress запрашивает выданные владельцем данные. Пароля nginx нет.
  Локальный `admin/admin` не работает; неавторизованный `/wp-admin/` ведёт на вход.
- Локальные данные входа: `site/data/server-access.json` / `server-access.txt`,
  права 600; каталог `data/` не попадает в Git. Не публиковать эти файлы.
- На сервере: `/etc/soberi-stanok/secrets.env`, root:root, 600. Случайные отдельные
  пароли для БД и Redis. Пароль WordPress хранится в БД как хеш;
  исходный для первого переноса — в защищённом файле секретов.
- SSH: `ssh root@178.253.45.218`, только ключ. Пароль root заблокирован,
  PasswordAuthentication и KbdInteractiveAuthentication отключены.
- Вход WordPress: Limit Login Attempts Reloaded, 4 ошибки → 20 минут блокировки,
  4 блокировки → 24 часа. Новая регистрация отключена. XML-RPC закрыт.
- Служебных панелей, FTP, Docker, phpMyAdmin, Mailpit и почтового сервера нет.
  MariaDB/Redis/PHP-FPM доступны только через локальные Unix-сокеты.
  `mariadb.socket` отключён и masked: его `ListenStream=3306` обходил
  `skip-networking` при загрузке systemd. После перезагрузки TCP-портов БД нет.

Открыты только TCP 22, 80 и 443 (IPv4/IPv6). HTTP перенаправляет на HTTPS,
кроме ACME challenge. SMTP на этапе 8 будет внешним исходящим подключением:
входящие SMTP-порты для этого открывать не нужно. UFW запрещает остальные
входящие соединения; fail2ban защищает SSH; вход WordPress защищает Limit Login Attempts.

## Повторная установка и перенос

Сначала скопировать `site/server` и `site/scripts` в `/opt/soberi-stanok/`.
От root:

```sh
bash /opt/soberi-stanok/scripts/setup-server.sh
bash /opt/soberi-stanok/scripts/setup-server-site.sh
```

`setup-server.sh` ставит PHP 8.3 из подписанного репозитория Sury (штатный PHP
Debian 12 — 8.2), nginx, MariaDB, Redis, firewall, fail2ban, автообновления ОС/PHP.
Также устанавливает актуальный пакет ядра; новое ядро применяется перезагрузкой.
`setup-server-site.sh` настраивает сокеты и HTTPS Let's Encrypt с автообновлением.
Сертификат выпускается только после настройки DNS. При первичной установке
учётные данные владельца можно передать root-only файлом
`/root/soberi-stanok/admin.env` с shell-quoted `WP_ADMIN_USER` и `WP_ADMIN_PASSWORD`.
Не помещать пароли в аргументы команд или tracked файлы.

Первый перенос **из локального проекта**:

```sh
site/scripts/deploy-initial.sh
```

Экспорт БД через локальный WP-CLI, rsync ядра/плагинов/языка/uploads/WebP и нашего
кода, импорт MariaDB, точный `search-replace` `localhost:8080` → HTTPS домена
(сериализованные данные сохраняются, GUID не изменяются). Новый wp-config
создаётся над document root с новыми salts, без Docker/Mailpit/debug.
Первый перенос очищает локальные сессии, application passwords и только тестовые
заказы Playwright. Администратор ID=1 получает заданные владельцем реквизиты.
Повторный запуск не перезаписывает действующую БД.

Отдельные воспроизводимые настройки:

```sh
bash /opt/soberi-stanok/scripts/setup-server-wordpress.sh
bash /opt/soberi-stanok/scripts/setup-server-cron.sh
bash /opt/soberi-stanok/scripts/setup-server-backups.sh
```

Повторный `setup-server-wordpress.sh` применяет HTTPS/noindex/Redis/ограничения
входа, обновляет salts (потребуется войти заново), но не возвращает пароль
первого импорта поверх изменённого владельцем пароля.
Код принадлежит root, PHP-процесс может записывать только uploads/WebP;
редактирование и установка файлов из веб-админки отключены. Плагины/ядро обновлять
через WP-CLI по SSH после резервной копии. Единственный сайт-процесс
`soberi-stanok`, пул PHP `ondemand`, 6 процессов по 256 МБ, OPcache PHP,
MariaDB buffer 384 МБ, Redis maxmemory 128 МБ. Swap 1,5 ГБ оставлен от FirstByte.

Следующая выкладка темы/mu-plugins (из локального проекта):

```sh
site/scripts/deploy-code.sh
```

Перед выкладкой создаётся резервная копия; БД/uploads не заменяются.
После выкладки очищаются Redis и nginx cache, перечитывается PHP.
Изменения серверных конфигов отдельно применяются соответствующим setup-скриптом.

## Кэш и фоновые задачи

nginx FastCGI cache: 256 МБ, TTL 5 минут. Только гостевые GET/HEAD без query
и сессионных cookies. Корзина, оформление, аккаунт, вход, wp-admin, wp-json,
wc-api, sitemap, robots, POST и ответы Set-Cookie не кэшируются. Диагностика: `X-FastCGI-Cache: HIT/BYPASS`.
Изображения отдаются как WebP при `Accept: image/webp`, оригинал остаётся fallback.
После изменения товаров из админки обновление гостевого кэша — до 5 минут;
немедленная очистка через SSH: `find /var/cache/nginx/soberi-stanok -type f -delete`.

`DISABLE_WP_CRON=true`: `soberi-stanok-cron.timer` каждые 5 минут запускает
WP-CLI под пользователем сайта. Фоновые задачи работают независимо от посещений сайта.
Почта на staging явно отключена mu-plugin `staging.php`, SMTP на этапе 8.
Переход к production требует сменить `WP_ENVIRONMENT_TYPE` после настройки SMTP.
При запуске надо убрать X-Robots-Tag также из nginx snippets
и image location; одного `blog_public=1` недостаточно.

## Резервные копии

Отдельного бэкап-диска нет (подтверждено владельцем). Каждый день около 03:15
по Москве `soberi-stanok-backup.timer` создаёт root-only копии в
`/var/backups/soberi-stanok/YYYY-MM-DD_HHMMSS/`, хранение 14 календарных дней:
БД (`--single-transaction`), весь сайт/медиатека, конфиги, сертификаты и секреты,
контрольные суммы SHA-256. Ошибка не оставляет завершённую неполную копию.
Копии на том же VPS не спасают от потери основного диска. Первая полная копия
также скачана в локальный `site/data/server-backups/` (не Git, права 700).

```sh
# Создать копию немедленно:
systemctl start soberi-stanok-backup.service
# Проверка восстановления в отдельную БД и временный каталог:
/opt/soberi-stanok/scripts/server/verify-backup.sh /var/backups/soberi-stanok/КАТАЛОГ
```

Проверка сверяет SHA-256, импортирует SQL в временную БД, извлекает сайт и
сверяет байты uploads. Рабочая БД не изменяется. При проверке после изменения
медиатеки использовать свежую копию. В будущем для отдельного диска задать
`SS_BACKUP_DIR` в `/etc/soberi-stanok/backup.env`; сначала проверить, что
хранилище действительно смонтировано и доступно root.

Для восстановления рабочего сайта сначала закрыть запросы и остановить cron,
сохранить текущие файлы/БД, проверить SHA-256, затем распаковать `site.tar.gz`
в `/var/www`, импортировать `database.sql.gz` в `wordpress`, восстановить
нужные конфиги из `config.tar.gz`, проверить права, nginx/PHP и очистить кэши.
Не распаковывать конфиги поверх другого сервера вслепую. Файлы с секретами —
только root/site group, конфиги MariaDB без паролей должны читаться mysql (644).

## Проверки

Локально: `npm test`, `npm run check:mcp`, `site/scripts/verify.sh`.
VPS: `node site/scripts/verify-server.cjs` — реальные HTTPS, публичную витрину и защиту wp-admin,
вход с заданным паролем, cookies Secure/HttpOnly,
HIT/BYPASS, WebP, закрытые служебные файлы, каталог 375/768/1440 и тестовый заказ.
Отрицательная проверка входа: `SS_TEST_WRONG_LOGIN=1 node site/scripts/verify-server.cjs`.
Её не повторять многократно: 4 ошибки блокируют также проверяющего на 20 минут.
В этой сессии проверены отказ `admin/admin` и фактическое срабатывание блокировки.
После теста удалить **только** записанный в `data/server-test-order.json`
тестовый заказ, восстановив его остаток через WooCommerce:
`SS_TEST_ORDER=<id> wp --allow-root --path=/var/www/soberi-stanok/public eval-file /opt/soberi-stanok/scripts/server/cleanup-test-order.php`.
Для повторной проверки без заказа: `SS_SKIP_CHECKOUT=1 node site/scripts/verify-server.cjs`.
Отчёты текущей сессии в `site/data/stage7-*` (не Git).

На сервере проверены: Unix-сокеты, UFW, SSH по ключу, fail2ban, Redis Connected,
nginx/PHP lint, ядро WordPress по checksum, загрузка на новом ядре
`6.1.0-53-amd64` (повторно проверены 22/80/443 и отсутствие TCP БД), таймеры cron/backup/certbot/APT,
`certbot renew --dry-run --no-random-sleep-on-renew`, изолированное восстановление.

Источники конфигурации:
[репозиторий PHP Sury](https://packages.sury.org/php/README.txt),
[nginx для WordPress](https://developer.wordpress.org/advanced-administration/server/web-server/nginx/),
[исключения кэша WooCommerce](https://developer.woocommerce.com/docs/best-practices/performance/configuring-caching-plugins),
[обновления Debian](https://manpages.debian.org/bookworm/unattended-upgrades/unattended-upgrades.8.en.html).
