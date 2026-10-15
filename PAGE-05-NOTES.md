# Page 05 — Keyword Management

## Implemented
- `/keywords` keyword list with search, category, status, and pagination.
- Create/edit with normalization and duplicate checks including soft-deleted records.
- Toggle enabled, archive (soft delete), restore.
- Ownership checks and authenticated-only CRUD.
- Minimal internal email/password login (`/login`), session regeneration, rate limit, logout; no public registration.
- Discovery keyword shortcut to management.
- Feature test suite `KeywordManagementTest`.

## Important
- No Threads API calls occur; enabled keywords are **configuration**, not proof of active discovery.
- Create initial internal user using Tinker, for example: `\App\Models\User::create(['name'=>'Admin','email'=>'your-email@example.com','password'=>'a-strong-password']);` (use a unique strong secret).
- Run: `php artisan test --filter=KeywordManagementTest` then `php artisan test`.
- This page retains the Stitch-inspired styling used in pages 01–04.
- Existing pages 01–04 remain publicly accessible pending the project-wide authentication hardening phase. Do not deploy publicly yet.
- Future improvement: standardize reusable layout and asset compilation rather than loading Tailwind from CDN.
