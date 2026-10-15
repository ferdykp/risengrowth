# Page 07 — Review Queue

## Implemented

- `GET /reviews`: authenticated, owner-scoped, paginated draft queue with status filters and genuine database counts.
- `PATCH /reviews/{draft}`: edit only `draft` or `pending_review`; editing resets state to `draft`, changes SHA-256, and requires re-submission.
- `POST /reviews/{draft}/submit`: `draft` → `pending_review`.
- `POST /reviews/{draft}/decision`: `pending_review` → `approved` or `rejected`, transactionally and once only.
- Approval inserts immutable content/hash/target/platform snapshot into existing `comment_approvals` table.
- History panel, missing-post and empty states, error feedback and responsive layout.
- UI maintains the neutral Stitch-inspired RisenGrowth visual direction.

## Security/invariants

- All routes require authenticated user; mutations are owner-scoped.
- Double submissions are protected by database row locks plus state guards.
- Approval requires source post platform and external ID; **approval does not publish or send**.
- Once approved/rejected, drafts are no longer editable. To revise, create a new draft through the generator.
- For a pending draft, **save changes before clicking Approve**; approval applies to saved body, not unsubmitted text in textarea.
- No schema migrations added because `comment_approvals` already exists.

## Run locally

```bash
composer install
php artisan migrate
php artisan optimize:clear
php artisan test --filter=ReviewQueueTest
php artisan test
php artisan serve
```

Visit `/reviews` after login. AI provider and social platform integration remain separate capabilities.

## Verification

PHP lint has been performed on new controllers, model and routes. Full Laravel tests and browser rendering require local composer vendor dependencies and DB. No test outcomes are claimed until actually executed.
