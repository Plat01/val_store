#!/usr/bin/env bash
# Запуск от root на Debian 12: bash setup-server.sh
set -euo pipefail
[[ $EUID = 0 ]] || { echo 'Требуется root'; exit 1; }
. /etc/os-release
[[ $ID = debian && $VERSION_ID = 12 ]] || { echo 'Скрипт предназначен для Debian 12'; exit 1; }
export DEBIAN_FRONTEND=noninteractive
apt-get update
apt-get install -y ca-certificates curl lsb-release gnupg
if [[ ! -f /usr/share/keyrings/debsuryorg-archive-keyring.gpg ]]; then
  curl -fsSLo /tmp/debsuryorg-archive-keyring.deb https://packages.sury.org/debsuryorg-archive-keyring.deb
  dpkg -i /tmp/debsuryorg-archive-keyring.deb
fi
printf '%s\n' 'deb [signed-by=/usr/share/keyrings/debsuryorg-archive-keyring.gpg] https://packages.sury.org/php/ bookworm main' > /etc/apt/sources.list.d/php.list
apt-get update
apt-get -y upgrade
apt-get install -y linux-image-amd64 nginx mariadb-server redis-server php8.3-fpm php8.3-cli php8.3-mysql php8.3-curl php8.3-gd php8.3-intl php8.3-mbstring php8.3-xml php8.3-zip php8.3-redis php8.3-imagick ufw fail2ban unattended-upgrades certbot rsync unzip dnsutils ripgrep
if [[ ! -x /usr/local/bin/wp ]]; then
  curl -fsSLo /usr/local/bin/wp https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
  chmod 755 /usr/local/bin/wp
fi
# В образе FirstByte no-op rc.local не имел execute bit.
if [[ -f /etc/rc.local && "$(cat /etc/rc.local)" = $'#!/bin/bash\nexit 0' ]]; then
  chmod 755 /etc/rc.local
fi
timedatectl set-timezone Europe/Moscow
install -d -m 700 /etc/soberi-stanok /root/soberi-stanok
install -d -m 755 /var/www/soberi-stanok/public /var/www/acme /opt/soberi-stanok /var/cache/nginx/soberi-stanok
id soberi-stanok >/dev/null 2>&1 || useradd --system --home-dir /var/www/soberi-stanok --shell /usr/sbin/nologin --user-group soberi-stanok
usermod -aG redis soberi-stanok
chown soberi-stanok:soberi-stanok /var/www/soberi-stanok/public
chown www-data:www-data /var/cache/nginx/soberi-stanok
# Сначала разрешение SSH, затем включение firewall: ключ не потеряется.
ufw default deny incoming
ufw default allow outgoing
ufw allow 22/tcp comment 'SSH keys only'
ufw allow 80/tcp comment 'HTTP redirect and ACME'
ufw allow 443/tcp comment 'HTTPS shop'
ufw --force enable
[[ -s /root/.ssh/authorized_keys ]] || { echo 'Нет ключей root, настройка SSH остановлена'; exit 1; }
cat > /etc/ssh/sshd_config.d/00-soberi-stanok.conf <<'SSH'
PermitRootLogin prohibit-password
PasswordAuthentication no
KbdInteractiveAuthentication no
PubkeyAuthentication yes
PermitEmptyPasswords no
X11Forwarding no
MaxAuthTries 3
AllowTcpForwarding local
GatewayPorts no
SSH
sshd -t
systemctl reload ssh
cat > /etc/fail2ban/jail.d/soberi-stanok.local <<'JAIL'
[DEFAULT]
bantime = 1h
findtime = 10m
maxretry = 5
banaction = ufw
[sshd]
enabled = true
backend = systemd
JAIL
cat > /etc/apt/apt.conf.d/20auto-upgrades <<'APT'
APT::Periodic::Update-Package-Lists "1";
APT::Periodic::Unattended-Upgrade "1";
APT
cat > /etc/apt/apt.conf.d/52-soberi-stanok <<'APT'
Unattended-Upgrade::Origins-Pattern {
  "origin=Debian,codename=bookworm,label=Debian";
  "origin=Debian,codename=bookworm-security,label=Debian-Security";
  "origin=Debian,codename=bookworm-updates,label=Debian";
  "origin=deb.sury.org,codename=bookworm";
};
Unattended-Upgrade::Automatic-Reboot "false";
Unattended-Upgrade::Remove-Unused-Dependencies "true";
APT
systemctl enable --now fail2ban nginx mariadb redis-server php8.3-fpm apt-daily.timer apt-daily-upgrade.timer
systemctl restart fail2ban
echo 'Базовые пакеты и SSH/firewall настроены. Далее setup-server-site.sh.'
