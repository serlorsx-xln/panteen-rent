#!/bin/sh
# Wait for the database, then create the schema + demo data on the very first start only.
set -e
cd /var/www/panteen
mkdir -p storage/cache storage/condition storage/logs storage/slips
chown -R www-data:www-data storage public/media

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
