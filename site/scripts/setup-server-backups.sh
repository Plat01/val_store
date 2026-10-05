#!/usr/bin/env bash
set -euo pipefail
[[ $EUID = 0 ]] || exit 1
ss_project=$(cd "$(dirname "$0")/.." && pwd)
install -m 644 "$ss_project/server/backup.service" /etc/systemd/system/soberi-stanok-backup.service
install -m 644 "$ss_project/server/backup.timer" /etc/systemd/system/soberi-stanok-backup.timer
systemctl daemon-reload
systemctl enable --now soberi-stanok-backup.timer
systemctl start soberi-stanok-backup.service
