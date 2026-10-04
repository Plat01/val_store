#!/bin/sh
# Подключается из setup.sh внутри wpcli.
set -eu
wp eval-file /scripts/setup-seo.php
# Бесплатная WebP-конвертация средствами установленного Converter for Media.
wp webp-converter regenerate --force
