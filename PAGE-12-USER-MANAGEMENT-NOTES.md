# Page 12 — Admin-managed Accounts

## Features

- Admin-only `/admin/users` with search, filter, pagination, create, edit, status toggle and reset temporary passwords.
- Role `admin` or `member`, account activation flag, mandatory initial password change.
- Administrators create a temporary password and share it outside the app over a safe channel. Never store/send plaintext in logs.
- New users sign in at `/login`, must change password via `/account/initial-password`, then login again.
- Members cannot access `/admin/*`.
- Prevent admin self-deactivation, self-demotion and removal of the last active administrator.
- A disabled user fails login, and their next request invalidates the session.
- No public `/register`. Original 9 modules remain.

## Installation

1. Back up `.env`, database, and any local changes before copying new files. Do not overwrite `.env`.
2. `composer install && php artisan migrate && php artisan optimize:clear`
3. If this is the first admin, run `php artisan risengrowth:create-admin` and follow prompts (never use a hard-coded password).
4. Login via `/login`, navigate to `/admin/users`.
5. Tests: `php artisan test --filter=UserManagementTest && php artisan test`.

## Important limitations / follow-ups

- Because this release introduces user roles into an existing installation, **existing accounts become `member`** by default. The bootstrap command can create a first admin if none exists; it does **not** silently promote an existing user. If you need to promote an existing owner, perform a deliberate, authorized database update after verifying the user's identity.
- Existing private application data queries are not yet workspace-scoped. Only the new user administration endpoints have dedicated role authorization. Perform a full ownership isolation audit before providing access to external/untrusted users.
- Password resets invalidate remember-me tokens, but existing sessions on other devices may not be instantly revoked. For stronger immediate revocation, use centralized database sessions and invalidate user sessions on account deactivation/password reset.
- If multiple admins update account roles concurrently, run last-admin checks in an appropriate serialized transaction in your target DB and re-test under concurrency.
- No 2FA and no audit history yet. Manual browser tests are recommended.
