#!/usr/bin/env sh
set -e

if [ -z "$APP_KEY" ]; then
    echo "APP_KEY is missing. Set APP_KEY in Railway variables."
    exit 1
fi

php artisan migrate --force --seed
php artisan storage:link || true
php artisan config:cache
php artisan serve --host=0.0.0.0 --port="${PORT:-8000}"