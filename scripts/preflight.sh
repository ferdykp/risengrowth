#!/usr/bin/env bash
set -Eeuo pipefail
cd "$(dirname "$0")/.."
fail=0
ok() { printf 'OK   %s\n' "$1"; }
bad() { printf 'FAIL %s\n' "$1"; fail=1; }
warn() { printf 'WARN %s\n' "$1"; }
command -v php >/dev/null && ok 'PHP exists' || bad 'PHP missing'
command -v composer >/dev/null && ok 'Composer exists' || bad 'Composer missing'
command -v npm >/dev/null && ok 'npm exists' || bad 'npm missing'
[[ -f .env ]] && ok '.env exists' || bad 'Missing .env (copy .env.example and configure)'
[[ -f vendor/autoload.php ]] && ok 'Composer dependencies' || bad 'Run composer install'
[[ -f public/build/manifest.json ]] && ok 'Vite build' || bad 'Run npm ci && npm run build'
[[ -f resources/css/app.css && -f resources/js/app.js ]] && ok 'Vite entrypoints' || bad 'Missing Vite entrypoints'
py="${RISENGROWTH_PYTHON:-scraper/.venv/bin/python}"
if [[ -f .env && -z "${RISENGROWTH_PYTHON:-}" ]]; then
   conf=$(grep -E '^RISENGROWTH_PYTHON=' .env | tail -1 | cut -d= -f2- || true)
   [[ -n "$conf" ]] && py="$conf"
fi
if [[ -x "$py" ]]; then
  if "$py" -c 'import httpx,bs4,playwright' >/dev/null 2>&1; then ok 'Python worker packages'; else bad 'Missing Python packages'; fi
  if "$py" -m unittest discover -s scraper/tests -v; then ok 'Python unit tests'; else bad 'Python unit tests'; fi
else bad "Python worker missing: $py"; fi
if [[ -f vendor/autoload.php && -f .env ]]; then
  if php artisan about --only=environment >/dev/null 2>&1; then ok 'Laravel bootstrap'; else bad 'Laravel bootstrap'; fi
  if php artisan migrate:status >/dev/null 2>&1; then ok 'Database connection and migration table'; else bad 'Database connection/migrations'; fi
  if php artisan list --raw | grep -q '^growth:scan '; then ok 'Discovery Artisan command'; else bad 'Missing growth:scan'; fi
fi
[[ $fail -eq 0 ]] && { echo 'PREFLIGHT PASSED (network/lead pipeline not tested)'; exit 0; }
echo 'PREFLIGHT FAILED: fix items above'; exit 1
