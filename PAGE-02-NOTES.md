# RisenGrowth — Page 02: Lead Inbox

## Implemented
- `GET /leads`, validated GET filters (`q`, `priority`, `status`, `platform`, `sort`).
- Real Eloquent data, `with(post)`, stable sorting, server-side pagination preserving query string.
- Matching Stitch styling, filter bar, KPI counts, source link, responsive table, empty and no-match states.
- Overview sidebar links to Lead Inbox, count uses actual `new` leads.
- Laravel filesystem directory placeholders (`bootstrap/cache`, `storage/framework/*`) restored.
- Feature tests for empty state, search, filters, and invalid sort.

## Not implemented yet
- Lead Detail, discovery/Threads API, authentication, AI scoring, publishing. This page is read-only.
- Existing Overview design uses Tailwind CDN and some static inactive controls inherited from Page 01. Production should migrate to compiled Vite CSS and protect routes with authentication before exposure.

## Local commands
```sh
composer install
npm install
php artisan migrate
php artisan test --filter=LeadInboxTest
php artisan serve
```

Ensure `.env` points at your existing database, and back up data before migrating. Do not overwrite an existing `.env`.
