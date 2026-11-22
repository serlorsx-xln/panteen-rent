#!/bin/sh
# Rebuild the test database and serve it on http://127.0.0.1:8089 (for the browser tests).
cd "$(dirname "$0")/.."
PANTEEN_DB_NAME=panteen_test php cli/install.php >/dev/null && echo "panteen_test rebuilt"
PANTEEN_DB_NAME=panteen_test PANTEEN_MAIL_DRIVER=log exec php -S 127.0.0.1:8089 -t public public/index.php
