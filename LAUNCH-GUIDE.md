# RisenGrowth — Self-hosted launch guide

## Status and limitations

This package contains the existing Laravel application, self-hosted Python extraction, an **enabled Hacker News official public API adapter**, and a **disabled HTML demo adapter**. It **does not** perform Threads keyword search, and it **does not** automatically turn every keyword match into a qualified sales lead. Live production operation still requires setup and acceptance tests on your own host.

## Local setup (macOS/Linux)

1. Unzip into an isolated directory. **Back up the old database and `.env`**; this archive intentionally does not contain secrets or the current DB.
2. Copy your existing `.env` to the project root. If there is none, copy `.env.example`, set your app key with `php artisan key:generate`, and configure the DB credentials. Do not regenerate an existing key.
3. Set `QUEUE_CONNECTION=database` and `RISENGROWTH_PYTHON=/ABSOLUTE/PATH/risenGrowth/scraper/.venv/bin/python` in `.env`.
4. Run `bash scripts/install-local.sh`. The script installs packages, builds assets, creates Python venv, installs Chromium, migrates DB, and runs the preflight checks.
5. Run in separate terminals: `php artisan serve`, `php artisan queue:work database --queue=default --tries=1 --timeout=110`, and `php artisan schedule:work`. For long-lived Linux deployment, supervise worker and schedule through systemd/cron (examples below).
6. Create an enabled keyword using the UI. Use `php artisan growth:scan --keyword=ID --now` to verify the synchronous end-to-end path, then verify `discovery_runs`, `discovered_posts`, and `leads` in your DB.
7. `scraper/.venv/bin/python scraper/main.py --keyword=website` tests ingestion independently. If no matches, the response is still valid; the API scans only a bounded set of recent HN items.

## Server setup

Serve **only** `/public` as document root behind HTTPS; never expose the repository or `.env`. Set `APP_ENV=production`, `APP_DEBUG=false`, configure `APP_URL`, mail, session and rate-limiting, and run `php artisan optimize` after env changes. Install composer dependencies with `composer install --no-dev --optimize-autoloader`; install/build Node dependencies during deployment; install Python requirements + Chromium on the server. Give web and worker users access to `storage` and `bootstrap/cache` and restrict other files.

Queue worker supervised (e.g. systemd): `php /srv/risenGrowth/artisan queue:work database --sleep=3 --tries=1 --timeout=110 --max-time=3600`. Restart after each deployment with `php artisan queue:restart`.

Scheduler cron (once per minute): `* * * * * cd /srv/risenGrowth && /usr/bin/php artisan schedule:run >> /dev/null 2>&1` (use your actual PHP binary path). Do **not** also run `schedule:work` when cron is enabled.

## Safety and monitoring

The active source is the official Hacker News API; it is not Threads. All other HTML/browser source adapters must be explicitly configured for permitted targets. Never scrape private data, bypass authentication, blocks, or CAPTCHA. Do not run automation against Threads without permission. Use a backup, review migration results, protect credentials, and verify production logs and DB counts. The `relevant_count=0` field indicates lead classification remains unimplemented for this new source.

Inspect failures: `php artisan queue:failed`, `tail -n 100 storage/logs/laravel.log`, `php artisan schedule:list`, and `bash scripts/preflight.sh`. A scan returning zero matches is not necessarily a failure.

## Acceptance checklist

- [ ] `bash scripts/preflight.sh` passes on target host
- [ ] Local Python unit tests pass
- [ ] Live Python fetch returns JSON with `posts`, `errors`, `sources_configured`
- [ ] Manual Laravel scan completes, with a recorded `discovery_runs` row
- [ ] Repeat scan creates no duplicate posts/leads
- [ ] Dashboard shows expected records for the signed-in account
- [ ] Queue worker receives a dispatched scan
- [ ] Scheduler triggers in supervised environment
- [ ] HTTPS, DB backup, secrets, application debug settings, and error monitoring reviewed

**Do not call this Threads automation or verified production-ready before completing the checklist.**
