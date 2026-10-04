#!/bin/sh
# Выполняется внутри wpcli, идемпотентно. Страницы и форма этапа 5.
set -eu
wp eval-file /scripts/setup-pages.php
