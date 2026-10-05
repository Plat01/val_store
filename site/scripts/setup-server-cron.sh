#!/usr/bin/env bash
set -euo pipefail
[[ $EUID = 0 ]] || exit 1
ss_project=$(cd "$(dirname "$0")/.." && pwd)
install -m 644 "$ss_project/server/cron.service" /etc/systemd/system/soberi-stanok-cron.service
install -m 644 "$ss_project/server/cron.timer" /etc/systemd/system/soberi-stanok-cron.timer
systemctl daemon-reload
systemctl enable --now soberi-stanok-cron.timer
systemctl start soberi-stanok-cron.service
