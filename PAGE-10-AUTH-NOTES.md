# Page 10 — Complete Internal Authentication & Route Hardening

## Implemented
- Login (`/login`) with remember me, rate limiting, session regeneration, logout with session invalidation.
- Guest-only reset request (`/forgot-password`) and reset form (`/reset-password/{token}`), backed by Laravel password broker and existing `password_reset_tokens` migration.
- Reset email enumeration protection (same visible message whether email exists or not).
- Change password on Settings via `PUT /account/password`, requiring current password and new confirmed password of 12+ characters, mixed case and digits. Logs out current session on success.
- All nine main dashboard pages and state-changing routes are behind `auth` middleware.
- Internal-only accounts; no public registration.
- Automated feature tests for guest redirects, login/logout, reset link, reset token, password change and disabled registration. Adjusted pre-existing overview/lead/discovery feature tests to authenticate their requests.

## Important limits
- OAuth login, MFA/2FA, invitations, admin user management, email verification, sessions management across devices, and dynamic role permissions are NOT implemented. Do not describe these as delivered.
- External reset emails require `MAIL_*` in `.env` to point to a working SMTP service. `MAIL_MAILER=log` writes reset links to logs in development only.
- Existing historical pages/models were written as an internal single-workspace design; before allowing multiple independent tenants/users, audit and scope all lead/discovery/activity queries by workspace. `auth` alone is not sufficient multi-tenant authorization.
- UI keeps the Stitch-inspired palette; full design polish / browser review still required.
- No public deployment before environment safety checks, HTTPS and MAIL config.

## Local instructions
1. Back up `.env` and local source changes. Unzip and merge into your project, keeping `.env` and existing database.
2. `composer install` and `php artisan migrate` (password reset token table already belongs to Laravel default migration).
3. Configure `MAIL_MAILER=smtp`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS` and queue worker if mail is queued. For local-only tests use `MAIL_MAILER=log`.
4. `php artisan optimize:clear`
5. `php artisan test --filter=AuthenticationTest` then `php artisan test`.
6. `php artisan serve`, then log in at `/login`.
7. If you have no user account, create one securely via Tinker using a unique email and long password. Never commit passwords or credentials.

## Verification
PHP lint performed on all PHP source files (`app`, `routes`, `tests`). Laravel integration tests were NOT run here: `vendor` is absent in the source ZIP. Test completion must be confirmed on your machine.
