# RisenGrowth — Real-source discovery (free, self-hosted)

This build keeps the existing Laravel -> Python -> MySQL ingestion workflow and adds `hackernews-official`, using the **official public Hacker News API**, not a paid scraper provider. The old `quotes-demo` source is disabled in `scraper/sources.json`.

## What it does and does not do

- Fetches a limited sample of the most recent Ask HN and new-story items, and filters titles/body with the configured keyword (case-insensitive substring).
- Uses stable item IDs for deduplication across runs. Content is stored as `web` posts and unclassified leads in the existing tables.
- Does **not** search Threads, all Hacker News history, comments, or the entire internet. Matching posts are **not yet qualified sales leads**; manual review is mandatory.
- You own the code and worker; the external content is still provided by Hacker News under its own conditions.

## Install / run

From the existing RisenGrowth Laravel root:

```bash
composer install
npm ci
npm run build
python3 -m venv scraper/.venv
scraper/.venv/bin/pip install -r scraper/requirements.txt
scraper/.venv/bin/python -m playwright install chromium
php artisan migrate
php artisan optimize:clear
```

Keep your existing `.env`, DB credentials and APP_KEY. Configure `RISENGROWTH_PYTHON=/absolute/path/to/risenGrowth/scraper/.venv/bin/python` and `QUEUE_CONNECTION=database`.

Run worker directly (prints one JSON object on stdout):

```bash
scraper/.venv/bin/python scraper/main.py --keyword=website
scraper/.venv/bin/python -m unittest discover -s scraper/tests -v
```

Empty `posts` is valid: the last 60 sampled HN items may not mention the exact phrase.

Create a **new** enabled keyword in RisenGrowth (prefer English phrases such as `website`, `looking for developer`, `need a developer`, `build a website`). Use its actual database ID:

```bash
php artisan growth:scan --keyword=2 --now
php artisan tinker --execute='dump(DB::table("discovery_runs")->latest("id")->first());'
```

To run asynchronously, start:

```bash
php artisan queue:work database --queue=default --tries=1 --timeout=110
php artisan schedule:work
```

The scheduled command only scans keywords due by their configured interval. Do not run multiple scheduler instances.

## Configuration

Edit `scraper/sources.json`. For Hacker News, mode `hackernews` accepts `feeds` (one or more of `askstories`, `newstories`, `jobstories`, `showstories`) and `max_items` (1..120). Increase cautiously: the official API requires one request per item. Use normal HTTP/browser sources only on websites that allow automated collection, with target selectors explicitly configured.

## Notes

- The system currently adds a `Lead` even if the discovered post is not a genuine buying-intent signal. Review leads manually before outreach; automatic qualification is future work.
- `relevant_count` remains 0 until the AI/scoring workflow is connected. Do not interpret it as a scraping failure.
- No bypass of authorization, rate-limiting or CAPTCHA is implemented.
- For a production system, use standard secret management, keep debug off, and maintain source-specific usage approvals.
