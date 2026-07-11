#!/bin/sh
set -eu

if [ "${DB_CONNECTION:-}" = "pgsql" ]; then
  attempts=0
  until php -r '
    try {
        new PDO(
            "pgsql:host=" . getenv("DB_HOST") . ";port=" . getenv("DB_PORT") . ";dbname=" . getenv("DB_DATABASE"),
            getenv("DB_USERNAME"),
            getenv("DB_PASSWORD")
        );
    } catch (Throwable $exception) {
        exit(1);
    }
  '; do
    attempts=$((attempts + 1))
    if [ "$attempts" -ge 30 ]; then
      echo "PostgreSQL did not become available in time." >&2
      exit 1
    fi
    sleep 2
  done
fi

php artisan migrate --force

if [ "${FIELDOPS_SEED_DEMO:-false}" = "true" ]; then
  php artisan db:seed --force
fi

exec "$@"
