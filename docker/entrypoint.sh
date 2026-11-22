#!/bin/sh
# Start-up for the Panteen Rent container.
#   PANTEEN_DB_HOST set (docker-compose sets "db"): use that database server.
#   PANTEEN_DB_HOST unset: run MariaDB inside this container (single-container deploys, e.g. a Coolify
#   Dockerfile app). Its data lives in /var/lib/mysql; mount a volume there to keep it across redeploys.
# Then create the schema + demo data on the very first start only, run the reminders loop and start Apache.
set -e
cd /var/www/panteen
mkdir -p storage/cache storage/condition storage/logs storage/slips
chown -R www-data:www-data storage public/media

if [ -z "${PANTEEN_DB_HOST:-}" ]; then
  export PANTEEN_DB_HOST=127.0.0.1 PANTEEN_DB_PORT=3306 PANTEEN_DB_NAME="${PANTEEN_DB_NAME:-panteen_rent}"
  export PANTEEN_APP_ENV="${PANTEEN_APP_ENV:-production}"
  mkdir -p /var/lib/mysql /run/mysqld
  chown -R mysql:mysql /var/lib/mysql /run/mysqld
  if [ ! -d /var/lib/mysql/mysql ]; then
    mariadb-install-db --user=mysql --datadir=/var/lib/mysql --skip-test-db >/dev/null
  fi
  # restart the server if it ever stops; the healthcheck reports the site unhealthy meanwhile
  ( while true; do mariadbd --user=mysql --datadir=/var/lib/mysql >>/var/log/mariadb.log 2>&1 || true; sleep 3; done ) &
  i=0
  until mariadb-admin --protocol=socket ping >/dev/null 2>&1; do
    i=$((i + 1)); [ "$i" -gt 60 ] && { echo "bundled MariaDB did not start"; tail -20 /var/log/mariadb.log; exit 1; }
    sleep 1
  done
  # one random password and app key per data directory, kept next to the data
  secrets=/var/lib/mysql/panteen-secrets.env
  if [ ! -f "$secrets" ]; then
    umask 077
    printf 'PANTEEN_DB_PASS=%s\nPANTEEN_APP_KEY_GEN=%s\n' "$(head -c 24 /dev/urandom | od -An -tx1 | tr -d ' \n')" \
      "$(head -c 32 /dev/urandom | od -An -tx1 | tr -d ' \n')" > "$secrets"
  fi
  . "$secrets"
  export PANTEEN_DB_USER=panteen PANTEEN_DB_PASS
  export PANTEEN_APP_KEY="${PANTEEN_APP_KEY:-$PANTEEN_APP_KEY_GEN}"
  mariadb --protocol=socket <<SQL
CREATE DATABASE IF NOT EXISTS \`$PANTEEN_DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'panteen'@'127.0.0.1' IDENTIFIED BY '$PANTEEN_DB_PASS';
ALTER USER 'panteen'@'127.0.0.1' IDENTIFIED BY '$PANTEEN_DB_PASS';
GRANT ALL PRIVILEGES ON \`$PANTEEN_DB_NAME\`.* TO 'panteen'@'127.0.0.1';
FLUSH PRIVILEGES;
SQL
fi

export HOST="${PANTEEN_DB_HOST:-db}" PORT="${PANTEEN_DB_PORT:-3306}"
i=0
until php -r 'new PDO("mysql:host=" . getenv("HOST") . ";port=" . getenv("PORT"), getenv("PANTEEN_DB_USER") ?: "root", getenv("PANTEEN_DB_PASS") ?: "");' >/dev/null 2>&1; do
  i=$((i + 1)); [ "$i" -gt 60 ] && { echo "database not reachable at $HOST:$PORT"; exit 1; }
  sleep 2
done

if ! php -r 'require "app/bootstrap.php"; try { App\Core\DB::value("SELECT 1 FROM users LIMIT 1"); exit(0); } catch (Throwable $e) { exit(1); }'; then
  echo "first start: creating tables and demo data"
  if [ "${PANTEEN_SEED:-demo}" = "demo" ]; then php cli/install.php; else php cli/install.php --no-seed; fi
  chown -R www-data:www-data storage public/media
fi

# reminders, expiring unpaid bookings and parcel alerts every 10 minutes (the site also runs them on page views)
( while true; do su -s /bin/sh www-data -c 'php cli/cron.php' >/dev/null 2>&1 || true; sleep 600; done ) &

exec docker-php-entrypoint "$@"
