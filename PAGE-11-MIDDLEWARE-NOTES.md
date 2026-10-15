# Page 11 — Middleware & Security Hardening

## Added
- `SecurityHeaders` attached to the `web` group: nosniff, DENY framing, referrer policy, restricted browser capabilities, production HTTPS HSTS.
- `PreventSensitiveCaching` attached to the `web` group: private `no-store` response on all web pages (login, reset token pages, dashboard and profile included). Does not interfere with static assets served directly from `public/`.
- Named rate limiters: `auth-login`, `password-email`, `password-reset`, `password-change`, `ai-generation`, `account-mutations`.
- Tests covering security headers, private response cache, guest redirection, login throttling.

## Do not over-cache
No full-page or browser response caching of authenticated content. Existing `CACHE_STORE` remains for application data and throttling; use Redis as cache and queue backend in production with explicit TTL and per-user keys. For production, ensure all web replicas share one central cache store.

## Follow-ups
- Move imported Stitch CDN scripts and inline scripts into Vite before enforcing CSP. Enabling a strict CSP now would break the current UI.
- Configure trusted proxies for Cloudflare/Nginx only if their addresses are trusted; otherwise client IP based throttling can be ineffective or spoofable.
- Configure `SESSION_SECURE_COOKIE=true`, `SESSION_HTTP_ONLY=true`, `SESSION_SAME_SITE=lax` under HTTPS; ensure APP_DEBUG=false.
- API OAuth callbacks and future webhook endpoints must be designed separately; do not blindly apply CSRF bypass or cache middleware to external callback endpoints.
- Route/controller ownership scoping and authorization must continue to be audited across multi-user data.

## Verify
`php artisan test --filter=MiddlewareSecurityTest`
`php artisan test`
`php artisan route:list`
`php artisan optimize:clear`
