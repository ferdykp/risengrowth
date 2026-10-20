#!/usr/bin/env bash
set -Eeuo pipefail

cd "$(dirname "$0")"
echo "=== DEPLOY START ==="
git pull --ff-only origin main
composer install --no-dev --optimize-autoloader --no-interaction
composer check-platform-reqs --no-dev
php artisan config:clear --no-interaction
php artisan migrate --force --no-interaction
npm ci
npm run build
php artisan optimize --no-interaction
php artisan queue:restart --no-interaction
echo "=== DEPLOY DONE: verify worker, scheduler and Discovery history ==="
