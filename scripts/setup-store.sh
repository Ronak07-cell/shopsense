#!/usr/bin/env bash
# Turns store/ (our Laravel files only) into a complete Laravel app:
# create a fresh Laravel skeleton, lay our files on top, migrate and seed.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

if [ -f store/artisan ]; then
  echo "store/ is already a full Laravel app. Nothing to do."
  exit 0
fi

command -v composer >/dev/null 2>&1 || { echo "Composer is required: https://getcomposer.org"; exit 1; }
command -v php >/dev/null 2>&1 || { echo "PHP 8.2+ is required"; exit 1; }

TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT

echo "Creating a fresh Laravel skeleton..."
composer create-project laravel/laravel "$TMP/app" --no-interaction --prefer-dist

echo "Adding the ShopSense files on top..."
cp -R store/. "$TMP/app/"
rm -rf store
mv "$TMP/app" store

cd store
for envfile in .env.example .env; do
  if [ -f "$envfile" ] && ! grep -q '^RECOMMENDER_URL=' "$envfile"; then
    printf '\nRECOMMENDER_URL=http://127.0.0.1:8001\n' >> "$envfile"
  fi
done

php artisan migrate --seed --force

cat <<'MSG'

Store is ready. Start everything:

  1. Recommender:  cd recommender && uvicorn app.main:app --port 8001
  2. Train it:     cd store && php artisan recommender:sync
  3. Store:        cd store && php artisan serve

Open http://127.0.0.1:8000  (admin@shopsense.test / password)
MSG
