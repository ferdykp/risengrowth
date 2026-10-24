# RisenGrowth — Threads discovery and deployment

The website now uses **Threads via Apify** by default. The verified Actor is `openclawai/threads-scraper`, using `search_latest`, maximum 10 posts per run. Official Threads API and the optional Python public-web adapters remain selectable.

## Deploy the web workflow to Ubuntu

Keep the existing `.env`, `APP_KEY`, database, and leads. Back up the database before migrations. Use the PHP version required by the committed Composer lockfile (`composer check-platform-reqs`); do not update dependencies on the server to bypass requirements.

Set these server environment values (never commit or share the token):

```dotenv
APIFY_TOKEN=your_server_token
APIFY_PROBE_MAX_CHARGE_USD=0.05
APIFY_MONTHLY_BUDGET_USD=4.50
QUEUE_CONNECTION=database
CACHE_STORE=database
DB_QUEUE_RETRY_AFTER=150
APP_ENV=production
APP_DEBUG=false
```

Deploy the committed code with:

```bash
cd /var/www/risengrowth
bash deploy-production.sh
```

The script stops on errors, installs locked dependencies, applies additive migrations, builds assets, caches Laravel configuration and asks workers to restart. It does not install a worker service or cron. Configure the Supervisor worker and cron shown below. Apify uses Laravel HTTP directly: Python, Playwright, Redis and Horizon are not required for this workflow.

After deployment, log in as an admin:

1. Open **Kata Kunci**, add/enable a Threads keyword, for example `website`.
2. Open **Eksplorasi**. Check that the Apify panel says the token is available. New installations keep the provider and automation disabled until an admin enables them.
3. To import your already successful test, choose `website`, select **Threads via Apify**, put `Y9xMQqberk5SPO808` in **ID run Apify lama**, then click **Jalankan Pencarian**. This queues reads of the existing run and stores results; it does not start another Actor. The token must belong to an account with access to that run. Input keyword and Actor must match the dataset exactly.
4. For a new search, enable Apify, save settings, leave the existing-run field empty and click Run Search. Manual searches do not wait for the automatic interval, but reuse an active run and still enforce provider enabled/pause, daily limits and account budget. This consumes Apify credit. Start with one run per UTC day and the default six-hour automatic interval. The automatic global and per-keyword intervals can be set from 1 to 10080 minutes.
5. Status, history and totals update every four seconds while the page is visible. The remote Actor is polled by delayed database jobs every 15 seconds, for at most 20 polls/20 minutes. Worker and cron must run continuously. If the worker is absent, a queued run is not a zero-result success.
6. Qualified buyer posts become leads in **Kotak Masuk Prospek** (Apify filter). Other fetched posts are retained under **Review postingan ditemukan**, where you can qualify them manually. The review list also updates automatically, while preserving a review form you are actively editing. Zero new leads can mean posts were unsuitable, excluded own accounts, or already stored; the history separates those counts.
7. Use the existing comment generator to create/edit a suggested comment for a lead, then review and copy it manually. Comments are never published automatically.
8. Enable **Otomatis** only when ready. The scheduler checks due active keywords every minute, respecting the greater of each configured keyword interval and the global minimum, and the shared daily start allowance. A blank keyword interval inherits the global setting. A displayed due time is eligibility, not a promise of capacity. Python scans are manual via their source selector or `growth:scan`; they are no longer scheduled automatically.

The UI shows provider-reported run usage and the last account-usage check, not a live credit balance or invoice. The account budget guard is capped at USD 4.50, and each start requests a USD 0.05 maximum total charge. These are application/provider controls, not a guarantee of a free account or total monthly invoice. Set an account spending limit in Apify Console and inspect billing, including other consumers of the same account. The web daily allowance is 1–10 starts across all keywords, counted conservatively from database attempt records and shared cache; redeploying/clearing cache does not reset recorded web attempts. The separate probe CLI defaults to one start/day (maximum two).

A 402/429 pauses new starts for 24 hours. An uncertain start is never automatically repeated. Recover the actual run ID from Apify Console and import that ID with the same keyword. Do not repeatedly click new search, clear counters, or retry a start without checking Console. Importing a completed ID twice does not duplicate its data; failed imports can resume using the same ID. Stale queued/running rows become explicit failures after 30 minutes when the scheduler runs. Rollback of the tracking migration drops its settings/tracking columns; keep backups and prefer a forward fix.

## Optional Python public-web workflow

Quotes to Scrape is solely a permitted demo target, not a source of real commercial leads.

## Install (macOS/Linux)

```bash
cp .env.example .env # only on a NEW install; keep your existing .env for existing deployments
composer install
npm ci
npm run build
python3 -m venv scraper/.venv
scraper/.venv/bin/pip install -r scraper/requirements.txt
scraper/.venv/bin/python -m playwright install chromium
php artisan migrate
php artisan optimize:clear
```

Configure `.env` with your real DB and `QUEUE_CONNECTION=database`, `RISENGROWTH_PYTHON=/absolute/path/to/project/scraper/.venv/bin/python`. On existing installs do not overwrite your APP_KEY or database settings.

## Run

Start independently:
```bash
php artisan serve
php artisan queue:work database --queue=default --tries=3 --timeout=110
php artisan schedule:work
```

In a separate shell test scraper:
```bash
scraper/.venv/bin/python scraper/main.py --keyword=love
scraper/.venv/bin/python -m unittest discover -s scraper/tests -v
php artisan growth:scan --keyword=1 --now
```

Manage keywords via the existing Laravel UI, then click **Jalankan Pencarian**. The Quotes practice source is disabled; do not use demo content as leads. For real lead discovery, create source configurations only for sites that permit automated crawling and supply their real CSS selectors. Keyword matching currently filters item text on configured URLs, **not site-wide keyword search**. Browser mode is available by setting `mode=browser`; it is slower.

## Operational boundaries

- Do not add sites without permission to automate data collection. Threads public keyword discovery uses the separate Apify provider, not these Python adapters.
- `scraper/sources.json` must be controlled by server admins. Do not expose it to web edits. Avoid internal hosts, private networks, arbitrary redirect destinations and untrusted source definitions.
- Keep workers isolated with minimal network/filesystem access. Check robots.txt and source terms. Do not attempt to bypass authentication, bot challenges or access restrictions.
- Docker/deployment and live DB end-to-end tests remain environment-specific. `queue:work` and scheduler must stay running for automation.
- Rule qualification is the default; AI is optional and advisory.
- RSS/Atom feeds can be configured by admins; none is enabled by default.

## v3 continuation: qualification, sources and deployment

The Python pipeline now classifies retrieved posts with deterministic, word-boundary rules. Only qualified buyer requests become new leads. Rejected and uncertain posts remain stored with evidence in the existing analysis history; view and manually reclassify them under **Eksplorasi → Review postingan ditemukan**. Existing leads are preserved, including manual status and notes. Rule scores are evidence weights, not calibrated probabilities. Generic requests without a named supported service need manual review.

The discovery form explicitly selects Python public web/API or the existing optional authorized Threads API. Apify is the default in the UI. Threads requires a valid connected account and official keyword-search permission; no unauthorized Threads scraping is provided. Existing shared workspace permissions remain: members can review shared leads, while admins configure and launch discovery.

Supported admin-controlled source modes in `scraper/sources.json`:

- `hackernews`: official Firebase recent-item API, bounded `feeds`, `max_items` (1–120), `delay_seconds`; not historical search or a reliable standalone client acquisition channel.
- `http`: authorized public HTTPS HTML; selectors for items, text, author and links; optional `next_selector`, `max_pages` (1–5), `max_items` (1–200), `delay_seconds`.
- `rss`: authorized public RSS/Atom HTTPS feed, `url`, `max_items`. Dates and feed identities are preserved where present.
- `browser`: Playwright for authorized pages requiring JavaScript. Requests are restricted to the configured public host. Browser pagination is not implemented; assets hosted on other domains are blocked.

Only enable sources you have permission to query. The JSON file is the source allowlist and must remain writable only by server administrators. Public-address checks reject private/loopback destinations; redirects are refused. Restrict worker outbound access at the host/firewall level as additional protection against DNS changes. The practice quote source remains disabled. No RSS feed is enabled by default: choose an authorized feed relevant to your market. Adapter failures appear in run history and do not silently become successful scans.

The optional Python command `growth:scan` checks enabled keywords for their configured interval when invoked manually. Omitted intervals default to 60 minutes. Shared cache locks prevent overlapping execution of a keyword, and unique jobs suppress duplicate queued scans. Set `CACHE_STORE=database` for shared locks. Queue retries are bounded (three attempts, 60-second backoff); each attempt has its own run history. Python has a 90-second process timeout, the job 110 seconds, and the database queue reservation must exceed 110 seconds. If a worker is forcibly terminated, inspect any stale `running` row before retrying; automatic stale-run repair is not yet implemented.

### Ubuntu deployment and recovery

1. Back up the database and retain the existing `.env` and `APP_KEY`.
2. Install the PHP extensions required by Composer, MySQL, Node/npm and Python with venv support. Match the project's installed PHP/Laravel versions and lockfiles.
3. Run `composer install --no-dev --optimize-autoloader`, `npm ci`, `npm run build`, then create `scraper/.venv` and install `scraper/requirements.txt`.
4. Only for browser mode, run `scraper/.venv/bin/python -m playwright install --with-deps chromium` using the deployment account. HTTP/API/RSS discovery does not launch a browser.
5. Configure MySQL, `APP_ENV=production`, `APP_DEBUG=false`, `QUEUE_CONNECTION=database`, `CACHE_STORE=database`, `DB_QUEUE_RETRY_AFTER=150`, and the absolute `RISENGROWTH_PYTHON` path. Make `storage/` and `bootstrap/cache/` writable by the application account.
6. Run `php artisan migrate --force`, `php artisan optimize`, and optionally `php artisan growth:doctor` for Python installations. The Python doctor checks Python prerequisites; it is not required for Apify. This is non-destructive preflight; it does not prove external-source permissions or worker availability.
7. Supervise a database worker independently of PHP-FPM and cron. Example Supervisor stanza (adjust project/user paths):

```ini
[program:risengrowth-queue]
command=/usr/bin/php /var/www/risengrowth/artisan queue:work database --queue=default --tries=3 --timeout=110 --sleep=3 --max-time=3600
directory=/var/www/risengrowth
user=www-data
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
stopwaitsecs=130
redirect_stderr=true
stdout_logfile=/var/www/risengrowth/storage/logs/queue.log
```

8. Add cron as the application account: `* * * * * cd /var/www/risengrowth && /usr/bin/php artisan schedule:run >> /var/www/risengrowth/storage/logs/scheduler.log 2>&1`. Use only one scheduler on a single-server deployment. Configure log rotation for application, queue and scheduler logs.
9. Check `/up`, `php artisan growth:doctor`, `php artisan schedule:list`, worker process status and the Discovery run history. A healthy HTTP endpoint alone does not prove the queue is working.
10. After deployment run `php artisan queue:restart`. For failures, inspect `php artisan queue:failed` and run history, repair source access/configuration, then use `php artisan queue:retry <id>` only for a selected Python job. For Apify, import the existing remote run ID through the website; do not restart a charged Actor. Do not flush queues or delete leads to recover. Clear stale scheduler locks with `schedule:clear-cache` only after confirming no command remains active.

For local development use `php artisan queue:work database --tries=3 --timeout=110` and `php artisan schedule:work` as separate processes. Horizon/Redis is not required for this workflow.

### Optional local AI and free drafting

Install and manage Ollama separately only if desired. Set `OLLAMA_ENABLED=true`, `OLLAMA_URL=http://127.0.0.1:11434`, and `OLLAMA_MODEL` to an installed model. Start with a small quantized model appropriate to your hardware, then evaluate Indonesian/English quality against representative fixtures before relying on its suggestions. Model downloads, RAM requirements and latency depend on the selected model. This application does not download models automatically.

The integration follows [Ollama's structured-output API](https://docs.ollama.com/capabilities/structured-outputs): non-streamed JSON schema responses with application-side validation, a 20-second timeout, bounded context/output and a short keep-alive. Only loopback endpoints are accepted. Local qualification is requested separately from discovery, stores advisory results, and never automatically promotes or rejects a lead. Review the evidence manually. Local draft errors fall back to the free template; manual editing and copy remain available. Drafts are never published automatically. Groq remains optional for existing installations; no paid AI is mandatory.

### Verification boundaries

- `php artisan test --compact` uses the isolated SQLite test database, not production MySQL.
- `scraper/.venv/bin/python -m unittest discover -s scraper/tests -v` uses deterministic fixtures/mocked HTTP.
- `PythonDiscoveryPipelineTest::test_real_python_worker_fixture_reaches_the_lead_inbox` starts the actual Python interpreter, uses real extraction/JSON/qualification/persistence and renders the inbox; only its upstream HTTP is replaced with fixtures. It skips if the local venv is missing.
- Live source access, live Ollama inference, MySQL concurrency, browser rendering and Ubuntu process supervision require environment-specific validation. Never treat mocked results as retrieved live leads.

## Optional Apify command-line probe

The `openclawai/threads-scraper` adapter is an experimental probe based on the publisher's documented `search_latest` input and post output. The adapter has returned ten public posts from other accounts in the verified `website` run. Web discovery now connects it to the database queue, scheduler, post review and Lead Inbox. The probe command below remains read-only with respect to posts/leads.

Set these values in the environment of the machine executing the probe. Do not paste tokens into chat, command-line arguments, screenshots or source control:

```dotenv
APIFY_TOKEN=your_private_token
APIFY_PROBE_DAILY_RUNS=1
APIFY_PROBE_MAX_CHARGE_USD=0.05
APIFY_MONTHLY_BUDGET_USD=4.50
THREADS_EXCLUDED_USERNAMES=risencode.dev,risendev
CACHE_STORE=database
```

The token's account must be permitted to run this Actor and read its run metadata and datasets. The probe sends a maximum total charge of USD 0.05, an Actor timeout of 120 seconds, and a limit of 10 posts. Before starting, it reads Apify's reported account-wide monthly usage and account limit; missing/unverifiable usage fails closed. It refuses a run that would reach the lower of the configured budget and the account limit. These guards do not confirm your account plan or exact remaining promotional credit, reserve credit against other external consumers, or replace checking Apify Billing. Set the actual account spending limit in Apify Console as well.

The run-start attempt is reserved under a shared cache lock before the POST. An uncertain connection failure counts toward the daily limit, because the remote run may already exist. Do not clear that counter to repeat an uncertain request: first inspect Apify Console for the run. The default is one attempted start per UTC day; configuration is capped at two. Reads of an existing run do not start another Actor. A 402/429 pauses new probes for 24 hours without automatic retry. After resolving the account issue, an administrator can remove only the `apify-probe-paused` cache key using the configured cache store; do not flush the whole cache.

After deploying these files and running normal Composer autoload/Artisan cache steps:

```bash
php artisan config:clear
php artisan growth:apify-probe --start --keyword=website
```

This command may consume Apify credit. It starts exactly one asynchronous Actor and immediately prints its run ID. If still running, wait about 15–30 seconds and inspect the SAME run, using the run ID printed by the command:

```bash
php artisan growth:apify-probe --run=YOUR_RUN_ID --keyword=website
```

There is no unbounded polling loop, automatic restart, fallback Actor or retry of a run-start POST. If the POST times out before an ID is received, recover the run ID from Apify Console rather than starting again. The per-run reported usage is displayed when returned; it must not be treated as a complete account invoice.

On success the probe prints the original validated Threads post links, author usernames and counts of excluded own posts and duplicate post IDs. All usernames from existing Threads social accounts and the configured exclusion list are filtered case-insensitively. Error/unknown hint rows, mismatched Actor/query, invalid data or more than ten post rows fail the probe. The exact Free Plan information notice observed in the live run is counted separately; ten posts plus this one notice are accepted. A successful empty dataset or a dataset containing only own posts does NOT pass feasibility. No post or lead is inserted and no comment is published.

Verify before enabling integration:

1. The run is `SUCCEEDED`, and the input was `search_latest` for `website`.
2. Returned original links are public, relevant and authored by accounts other than RisenDev.
3. Actual billing and available credits are acceptable. Actor marketing is not proof of free unlimited access or source authorization.
4. Mapping is compatible with the actual dataset. Published examples and `Http::fake` fixtures are not live results.

Tests:

```bash
php artisan test --compact tests/Feature/ApifyThreadsProviderTest.php
php artisan test --compact
```

References: [Actor documentation](https://apify.com/openclawai/threads-scraper), [async run API](https://docs.apify.com/api/v2/actors-runs-post), [account limits/usage](https://docs.apify.com/api/v2/users-me-limits-get). No extra Composer dependency or Python package is required for the HTTP adapter. Web discovery requires the committed tracking migration.
