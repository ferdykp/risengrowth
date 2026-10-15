# RisenDev Growth Assistant — Step-by-Step Implementation Plan

**Target:** Laravel 13 modular monolith, MySQL, Redis/Horizon, Blade/Tailwind/Alpine, official Threads API, AI provider abstraction.  
**Delivery rule:** build **one feature at a time**, explain the workflow and responsibilities before writing code, then test before proceeding.

## Phase 0 — Feasibility first (do not start DB/dashboard yet)
1. Create Meta Developer account and app with Threads use case.
2. Configure OAuth redirect URI and test actor; document app mode, requested scopes, expiration.
3. Review official `keyword_search` support, relevant permission (`threads_basic`, `threads_keyword_search`), rate limits, app review and data-use terms.
4. Authenticate in test environment; verify response fields `id,text,username,permalink,timestamp` when allowed.
5. Test queries with relevant Indonesian phrases; compare `RECENT`/`TOP` and record usable results, duplication, quota/errors.
6. Investigate **reply-to-third-party post** rules separately; do not assume all search results can be replied to through API.
7. Record API capability matrix: `can_search`, `can_get_permalink`, `can_reply_to_target`, `requires_review`, rate limit, fallback.
8. **Gate:** proceed with MVP if official discovery works; otherwise reassess product scope before building.

**Deliverable:** short real-world API feasibility report, redacted sample payload, capability flags. **Never commit credentials.**

## Phase 1 — Project foundation
1. Check PHP, Composer, Node/npm, MySQL and Redis versions against Laravel 13 official requirements.
2. Create Laravel project and Git repo; add `.env.example`; `.env` excluded; environments local/staging/production.
3. Configure MySQL, logging, timezone/time storage (UTC in DB, Asia/Jakarta UI), cache, sessions, queues.
4. Set up Blade, Tailwind, Alpine and a compact design-token system; no unnecessary JS SPA.
5. Add login-only auth for one internal operator; disable public registration. Choose Laravel-compatible official auth path intentionally (do not add Fortify/Livewire without approval).
6. Add lint/format (`Laravel Pint`), tests (Pest or PHPUnit), Git hooks/CI checks.
7. Define server-side auth middleware; use Policies for approval and account actions.
8. Run `php artisan migrate:fresh` only on development database.

**Gate:** login works, unauthorized requests blocked, local automated test and build pass.

## Phase 2 — Persistence schema
Use `05-DB-MIGRASI.md` as the exact design baseline. Create migrations in order:
1. `users` (Laravel default) → `social_accounts`.
2. `search_keywords`.
3. `discovery_runs`.
4. `discovered_posts` and `discovery_run_posts` pivot.
5. `leads`.
6. `ai_analyses`.
7. `comment_drafts` and `comment_approvals`.
8. `engagement_attempts` and `activity_logs`.

Build Eloquent relationships, model casts/encrypted tokens, seed sample data, factories, indexes and FK tests. **Gate:** `migrate:fresh`, seed, rollback, duplicate external post test, FK-delete tests.

## Phase 3 — Threads connection
1. Create `ThreadsClient` adapter with capability/query methods and typed exceptions.
2. OAuth start/callback with secure state, redirect allowlist, exact permissions, access token exchange.
3. Persist encrypted tokens; verify scopes; calculate expiry only from provider metadata (not assumed).
4. Connection screen shows connected/expired/permission issue and disconnect action.
5. Test unauthorized/expired/rate-limited scenarios with HTTP fakes.

**Gate:** UI does not show “connected” without verified API identity and capabilities.

## Phase 4 — Keyword discovery & ingestion
1. CRUD keyword with categories, enable flag, search type, optional interval; server-side validation.
2. Add “Run now” using queued action; record `discovery_runs` status/count/latency/errors.
3. Official API request → normalize payload → filter empty/own account → upsert by `(platform, external_post_id)`.
4. Attach post to run through pivot to preserve provenance.
5. Paginated lead inbox with filters and source permalink.
6. Handle partial paginated results, 429 backoff, timeouts, malformed payload, cancelled account.

**Gate:** repeated same search does not duplicate posts; API downtime does not break dashboard.

## Phase 5 — Async execution
1. Install Redis and configure `QUEUE_CONNECTION=redis`.
2. Install Horizon after Redis tested; restrict Horizon dashboard in production.
3. Define queues `discovery`, `analysis`, `drafts`, `publish` (publish only if authorized).
4. Use scheduler to enqueue due keywords, with lock/overlap prevention.
5. Use per-platform/account throttling, bounded exponential backoff + jitter, idempotent processing and provider quota tracking.
6. Supervisor/systemd manages Horizon; one scheduler entry on production.
7. Display run/backlog/failure health (minimum UI). Do not execute discovery synchronously from a page request.

**Gate:** process restart safe; duplicate job safe; 429 respects backoff.

## Phase 6 — AI analysis
1. Define AI provider interface, request/response DTO and strict validated JSON schema.
2. Do deterministic relevance prefilter before calling AI.
3. Analyze intent (`direct_need`, `latent_need`, `community`, `irrelevant`), service category, component scores, confidence, short rationale.
4. Store analysis with provider/model/prompt version, token/cost metadata where supplied, validity/error state; do not store hidden reasoning.
5. Upsert lead priority; enable manual override while retaining machine history.
6. Use test set of ~30–50 sample posts curated and labeled by operator; measure precision@20.

**Gate:** failed AI output cannot silently become a high-intent lead.

## Phase 7 — Draft generation & approval
1. Generate 2–3 useful, distinct tone variants; prioritize helpful replies over pitching.
2. Permit operator to select, edit, save revision, regenerate.
3. Approval is a transactional state change binding reviewer, account, target post, final exact text and content hash; cannot approve blank/oversize reply.
4. Reject, reset approval on edited revision, record audit trail.
5. Add manual engagement: “Copy comment” + “Open original post” + “Mark posted manually” with explicit operator confirmation.
6. Only if real API capabilities support permitted third-party reply: add guarded publish job with idempotency, `verification_required` state on ambiguous timeout; never retry blindly.

**Gate:** unit and feature tests prove no publication without approval and no old approval after edit.

## Phase 8 — Dashboard & UX refinement
1. Overview: actionable metrics, newest high-intent opportunities, health.
2. Discovery: keyword groups, run history and errors.
3. Inbox: fast filtering and list/detail layout.
4. Review Queue: post context and AI rationale side-by-side with comment editor; explicit approve/send distinctions.
5. Activity: manual vs API outcome; timeline; date/status filters.
6. Settings: connected account, AI budget, retention policy.
7. Keyboard navigation, empty/loading/error states, mobile usability and contrast audits.

**Gate:** operator can complete target workflow on desktop and mobile without technical jargon.

## Phase 9 — Production readiness
1. Automated tests: fixtures for API, policy, job retry, state transitions, race conditions, failed provider outputs.
2. CI: Composer validate/test/Pint, frontend build, fresh migration verification.
3. Production `.env`: `APP_DEBUG=false`, HTTPS, secrets managed securely, rate limiting, session security.
4. Nginx + PHP-FPM; deploy with safe migration strategy; Redis and Horizon managed by service supervisor.
5. Set up logs/metrics/alerts, database backups and restore test, retention pruning job.
6. Test OAuth callback on production domain and vendor app mode/scopes before enabling.
7. Start low-volume internal usage; sample-review AI and draft quality weekly.

**Gate:** restores tested, permissions and publish guards revalidated, system operational under throttling/outage.

## Suggested implementation slices (one conversation per slice)
- Slice A: Meta access validation only.
- Slice B: Laravel + auth foundation only.
- Slice C: migrations/relationships/tests only.
- Slice D: Threads connection only.
- Slice E: manual discovery and Inbox only.
- Slice F: queue and scheduler only.
- Slice G: AI analysis only.
- Slice H: draft generation only.
- Slice I: approval + manual engagement only.
- Slice J: monitoring and optional approved publish.

## Sample local commands (verify install requirements first)
```bash
composer create-project laravel/laravel risendev-growth
cd risendev-growth
php artisan --version
php artisan key:generate
php artisan migrate
npm install
npm run dev
php artisan test
```
Redis/Horizon setup after phase 5 (not required to demo phase 1):
```bash
composer require laravel/horizon
php artisan horizon:install
php artisan horizon
```
Production scheduler (single server/coordination required):
```cron
* * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
```

## Definition of Done for every slice
- User story and edge cases written.
- Model/controller/action/job responsibilities explained before code.
- Server-side validation and policy implemented.
- At least happy-path and negative-path tests pass.
- No unrelated features, packages, or migrations added.
- Migration is compatible with existing records; sensitive fields encrypted.
- Short changelog and manual QA steps documented.

## Sources
- https://www.postman.com/meta/threads/documentation/dht3nzz/threads-api
- https://laravel.com/docs/13.x/queues
- https://laravel.com/docs/13.x/horizon
- https://laravel.com/docs/13.x/scheduling
