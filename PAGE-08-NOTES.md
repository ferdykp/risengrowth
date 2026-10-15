# Page 08 — Activity & Engagement

## Implemented
- `GET /activity`, protected by Laravel `auth` middleware.
- Summary metrics: decisions, attempts, verified published attempts, failed/unknown attempts.
- Independent paginated views for review decisions, engagement attempts, and internal events; tab/status filters.
- Per-user scoping: decisions by reviewer, attempts by triggering user, logs by actor.
- Safe status presentation: `approved` is **not** `published`; even `status=published` requires `confirmed_at` for display as *Terbit terkonfirmasi*.
- Lead links and responsive empty states, consistent with Page 07 styling.
- No publishing API, no fabricated activity, no new migrations.
- Added a navigation link in eligible existing Blade views that already contain the review menu.

## Main changes
- `app/Http/Controllers/ActivityController.php`
- `app/Models/EngagementAttempt.php`
- `resources/views/activity/index.blade.php`
- `routes/web.php`
- `tests/Feature/ActivityTest.php`

## Run locally
```bash
composer install
php artisan migrate
php artisan optimize:clear
php artisan test --filter=ActivityTest
php artisan test
php artisan serve
```
Then open `/activity` after login.

## Limitations
- Does not actually transmit comments or contact Threads API.
- Attempts table is prepared for future publishing or manual-confirmation workflows; it may be empty until those modules are implemented.
- Activity logs are filtered by `actor_id`, so existing system events with no actor do not appear for an individual user.
- The activity page is test-authenticated, but older read-only pages from prior phases still require an authentication/privacy hardening pass before production.
- PHP syntax was checked where available; full Laravel tests and browser rendering must be run in the user's environment.
