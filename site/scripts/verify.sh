#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
./bin/wp eval-file /scripts/verify.php
