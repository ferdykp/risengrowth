# Page 09 — Settings & Integrations

Implemented on top of Page 08. Includes `/settings` with editable account profile, persisted discovery interval and notification preferences, per-user social account connection status, and Groq API key availability indicator. No tokens are exposed. No OAuth, API connectivity test, active scheduler, or automated publishing is claimed or implemented.

## Files
- `app/Http/Controllers/SettingsController.php`
- `app/Models/UserGrowthSetting.php`
- `resources/views/settings/index.blade.php`
- `database/migrations/2026_10_14_120000_create_user_growth_settings_table.php`
- `tests/Feature/SettingsTest.php`
- Updated `routes/web.php` and Activity navigation.

## Run
```bash
composer install
php artisan migrate
php artisan optimize:clear
php artisan test --filter=SettingsTest
php artisan test
php artisan serve
```

The deployment must protect all workspace routes (Overview, Inbox, Detail and Discovery) before public exposure. OAuth, discovery scheduling, external token refresh, real notifications and password management remain future work. Settings intentionally keep `discovery_enabled = false` until a verified discovery engine exists.
