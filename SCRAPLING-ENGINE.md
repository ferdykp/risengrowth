# Scrapling engine for Threads keyword discovery (experimental)

Scrapling is a second, optional engine for Threads keyword discovery. **Apify stays the default.** Both engines write to the same tables (`discovery_runs`, `discovered_posts`, `discovery_run_posts`, `ai_analyses`, `leads`), so everything downstream (qualification, the lead inbox, post review, comment drafts) works the same for both.

## How the existing Apify engine works

1. An admin picks a keyword and **Threads via Apify** on **Eksplorasi**, then clicks **Jalankan Pencarian** (`DiscoveryRunController`).
2. `RunApifyDiscovery::enqueue` checks the token, provider toggle, daily limit and budget. It then creates a `discovery_runs` row (`source_type=apify`) and queues `RunApifyDiscoveryJob`.
3. The job starts the `openclawai/threads-scraper` Actor (`ApifyThreadsProvider`), polls it every 15 seconds and reads its dataset.
4. `PostNormalizer::fromApify` validates each row. `RunApifyDiscovery::persist` stores posts as `platform=threads`, runs rule qualification (`RecordOpportunityAssessment`) and creates leads for qualified buyer posts.

## How Scrapling plugs in

Scrapling reuses the existing Python worker pipeline instead of adding a new one:

```
Eksplorasi form (source=scrapling)
  → RunPythonDiscoveryJob(keywordId, engine: 'scrapling')
  → RunPythonDiscovery::execute($keyword, 'scrapling')
      runs: scraper/.venv/bin/python scraper/threads_scrapling.py --keyword … --mode … --timeout … --max-posts …
      validates the JSON (permalinks must be https://www.threads.com/@user/post/CODE)
      drops your own accounts (THREADS_EXCLUDED_USERNAMES + connected Threads accounts)
      stores posts as platform=threads, run source_type=scrapling, qualification source=scrapling
```

`scraper/threads_scrapling.py`:

- Fetches `https://www.threads.com/search?q=<keyword>&serp_type=recent` with Scrapling's lightweight `Fetcher` (HTTP, no browser) by default.
- Parses the JSON that Threads embeds in `<script type="application/json">` and reads every post object it finds: post ID (`pk`), shortcode, username, text, `taken_at`, likes, replies, reposts and quotes when present.
- Deduplicates by the numeric Threads post ID. Apify reports the same numeric ID, so a post found by both engines is stored once.
- Reports problems per keyword without crashing: `LOGIN_WALL`, `HTTP_403`, `HTTP_429`, other `HTTP_xxx`, `NETWORK_ERROR`, `UNEXPECTED_*`. Partial results are kept. If every keyword fails, the run is marked failed and the reason appears in run history.
- `--mode browser` uses Scrapling's `StealthyFetcher` to render JavaScript. Scrapling 0.5 no longer has `DynamicFetcher`, so `StealthyFetcher` is the only browser option. Cloudflare solving stays off, and no login, cookies or private APIs are used.
- In browser mode the search page is scrolled `SCRAPLING_SCROLLS` times (default 5, max 15) with 1.5–3 s pauses. Posts that load while scrolling are read from the JSON responses the page itself receives, so more than the first batch comes back. HTTP mode ignores this setting and only sees the first batch.

## Switching engines

- Per run: choose **Threads via Scrapling (eksperimen)** or **Threads via Apify** in the **Sumber discovery** dropdown.
- Default selection: set `SCRAPER_ENGINE` in `.env`, then run `php artisan config:clear`.

Apify (default):

```dotenv
SCRAPER_ENGINE=apify
APIFY_TOKEN=your_server_token
```

Scrapling (no Apify token needed):

```dotenv
SCRAPER_ENGINE=scrapling
RISENGROWTH_PYTHON=/absolute/path/to/project/scraper/.venv/bin/python   # optional; this is the default path
SCRAPLING_MODE=http        # or browser
SCRAPLING_TIMEOUT=20       # seconds per request
SCRAPLING_MAX_POSTS=20     # per keyword (max 100); raise it with browser scrolling
SCRAPLING_SCROLLS=5        # browser mode only: scrolls per search (0-15)
SCRAPLING_MAX_POST_AGE_DAYS=7  # default date range: today minus N days to today (WIB)
THREADS_EXCLUDED_USERNAMES=risencode.dev,risendev
```

Keywords come from the existing **Kata Kunci** page. Each run searches one keyword.

### Automatic delays

- Scrapling jobs from the web UI run one after another. Each job waits at least `SCRAPLING_COOLDOWN_SECONDS` (default 60) after the previous one, plus up to 50% random jitter. A job that arrives early goes back to the queue until its slot opens, so clicking several keywords in a row is safe.
- After a `LOGIN_WALL`, `HTTP_403` or `HTTP_429`, every Scrapling job pauses for `SCRAPLING_BLOCK_BACKOFF_SECONDS` (default 900 = 15 minutes).
- The CLI waits `--delay` seconds (default 15) plus up to 50% jitter between keywords.
- `growth:scan --engine=scrapling --now` skips the queue spacing but still sets the block pause.
- Delays lower the chance of a login wall; they don't prevent it. Threads doesn't publish its limits.

### Post date range

- The form has **Tanggal posting dari / sampai** fields (WIB dates, used only for Scrapling). They default to the last `SCRAPLING_MAX_POST_AGE_DAYS` days (default `7`) up to today.
- Posts dated outside the range are not stored. Posts with no date are dropped too.
- Run history shows the range, how many posts fell outside it, and how many were your own accounts.
- The dates filter what one search page returns. They are not sent to Threads. Threads search only returns a small batch of recent posts, so an old range will usually come back empty.
- `growth:scan --engine=scrapling` always uses the default range.

## Install

Scrapling needs **Python 3.10+**.

```bash
python3.12 -m venv scraper/.venv
scraper/.venv/bin/pip install -r scraper/requirements.txt
# Only for SCRAPLING_MODE=browser (downloads a browser):
scraper/.venv/bin/scrapling install
```

## Run locally

```bash
# Worker only, prints JSON (repeat --keyword for several; --delay sets seconds between keywords)
scraper/.venv/bin/python scraper/threads_scrapling.py --keyword website --keyword "jasa aplikasi" --delay 15

# Through Laravel, synchronously for one keyword ID
php artisan growth:scan --engine=scrapling --keyword=1 --now

# Or the normal web flow
php artisan queue:work database --queue=default --tries=3 --timeout=110
```

## Tests

```bash
scraper/.venv/bin/python -m unittest discover -s scraper/tests -v      # parsing, dedup, login wall, HTTP/network errors
php artisan test --compact tests/Feature/ScraplingDiscoveryTest.php   # engine selection, persistence, failure reporting
php artisan test --compact                                            # full suite, including the Apify tests

# Optional live check against public Threads (never runs by default):
RISENGROWTH_LIVE_THREADS=1 RISENGROWTH_LIVE_KEYWORD=website \
  scraper/.venv/bin/python -m unittest scraper/tests/test_threads_scrapling.py -k Live -v
```

## Limitations

- Threads search often needs a login when you are signed out. In that case the run fails with `LOGIN_WALL`. This is expected, and the engine does not try to get around it.
- Threads may answer automated clients with 403/429, and it changes its embedded JSON without notice. If the post shape changes, results come back empty or as `LOGIN_WALL` until the parser is updated.
- Stealth or browser mode does not guarantee success. Signed-out Threads often shows a login prompt after a few scrolls; the posts loaded before that are still kept.
- Browser mode needs `scrapling install` and the browser's system libraries on the server. Each search takes about 20–60 s instead of a few seconds.
- The parser was written against fixtures of Threads' known embedded post shape. It has not been checked against live Threads output yet. Run the live test above from a machine with network access before relying on it.
- An empty result can't be told apart from a silently changed page layout. Compare with an Apify run when in doubt.
- Check Threads' terms before using this in production. Only public data is accessed.
