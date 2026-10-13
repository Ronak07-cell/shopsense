#!/bin/sh
set -e
mkdir -p database storage/framework/sessions storage/framework/cache storage/framework/views storage/logs
touch database/database.sqlite
php artisan migrate --force
php artisan db:seed --force

# Train the recommender once the store is up. The recommender may be asleep
# on the free plan, so retry for a few minutes.
(
  for i in 1 2 3 4 5 6 7 8 9 10 11 12 13 14 15 16 17 18; do
    sleep 15
    if php artisan recommender:sync; then break; fi
  done
) &

exec php artisan serve --host=0.0.0.0 --port="${PORT:-8000}"
