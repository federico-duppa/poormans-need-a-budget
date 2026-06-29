# 💸 Poorman's Budget

Family budget application using the **envelope / zero-based method** to
control the household's spending. Zero-based budgeting ("give every peso a
job"), multi-currency **ARS + USD**, Google login restricted by
whitelist, and designed **mobile-first** (PWA).

## Stack

- **Laravel 13** (PHP 8.4)
- **Livewire 4** + **Alpine.js** + **Blade**
- **Tailwind CSS v4** (Vite)
- **SQLite** in development · **PostgreSQL** in production (Laravel Cloud)
- **Laravel Socialite** (Google OAuth)
- **Pest** for tests
- Reports with CSS bars (no JS dependencies)

## Zero-based budgeting methodology

1. **Give every peso a job** — zero-based budget (money to assign + monthly assignment).
2. **Embrace your true expenses** — per-category goals/targets (post-MVP).
3. **Roll with the punches** — carryover of available balances month to month.
4. **Age of money** — metric of how many days your pesos live before being spent.

Plus the handling of **credit cards with fund reservation** (move money to the
payment category when spending) and **author** attribution per transaction within
the shared family budget.

## Getting started (development)

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate
npm run build   # or: npm run dev
php artisan serve
```

### Relevant environment variables

| Variable | Description |
|---|---|
| `ALLOWED_EMAILS` | Emails (comma-separated) allowed to log in. The first one is admin. |
| `BUDGET_BASE_CURRENCY` | The budget's base currency (default `ARS`). |
| `BUDGET_SECONDARY_CURRENCY` | Supported secondary currency (default `USD`). |
| `GOOGLE_CLIENT_ID` / `GOOGLE_CLIENT_SECRET` / `GOOGLE_REDIRECT_URI` | Google OAuth credentials. |

## Tests

```bash
./vendor/bin/pest
```

## Conventions

- Amounts are stored as **integers in cents** (minor units) to avoid
  floating-point rounding errors in the financial logic.

## Development status

- [x] Phase 0 — Base scaffold (Laravel 13 + Livewire + Tailwind + mobile layout)
- [x] Phase 1 — Google auth + whitelist
- [x] Phase 2 — Accounts and transactions
- [x] Phase 3 — Zero-based budget engine
- [x] Phase 4 — Credit cards (fund reservation)
- [x] Phase 5 — Reports + PWA
- [x] Phase 6 — Laravel Cloud deploy (see [DEPLOY.md](DEPLOY.md))

### Next steps (post-MVP)

- Per-category goals/targets (save $X by a given date, spend up to $Y/month).
- Import transactions via CSV and recurring transactions.
- Splits in the entry UI, editing/deleting transactions.
- Account reconciliation.
</content>
</invoke>
