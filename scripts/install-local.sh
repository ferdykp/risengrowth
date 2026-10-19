#!/usr/bin/env bash
set -Eeuo pipefail
cd "$(dirname "$0")/.."
command -v composer >/dev/null || { echo 'Composer required'; exit 1; }
command -v npm >/dev/null || { echo 'npm required'; exit 1; }
command -v python3 >/dev/null || { echo 'python3 required'; exit 1; }
[[ -f .env ]] || { cp .env.example .env; echo 'Edit .env (DB_*, APP_URL, APP_KEY and QUEUE_CONNECTION) then rerun.'; exit 1; }
composer install --no-interaction
npm ci
npm run build
python3 -m venv scraper/.venv
scraper/.venv/bin/python -m pip install -r scraper/requirements.txt
scraper/.venv/bin/python -m playwright install chromium
scraper/.venv/bin/python -m unittest discover -s scraper/tests -v
php artisan migrate --force
php artisan optimize:clear
bash scripts/preflight.sh
printf '\nStart these separate terminals:\n  php artisan serve\n  php artisan queue:work database --queue=default --tries=1 --timeout=110\n  php artisan schedule:work\n'
