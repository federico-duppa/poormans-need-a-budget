# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What it is

A **zero-based** family budget app (envelope method): you assign every peso to a
category, record transactions, and track the "available" month to month. Multi-currency
(ARS base + USD), Google login restricted by whitelist, single shared family budget.
Production on Laravel Cloud (PostgreSQL); development on SQLite.

> **The experience focus is mobile-first.** Every new UI is designed first for
> the phone: narrow layout (`max-w-2xl`), bottom navigation (bottom-nav), floating
> quick-add button, large touch targets. The app is an installable PWA.
> Check how it looks in a small viewport before considering anything done.

## Commands

```bash
composer setup                 # installs deps, .env, key, migrate, build (first run)
composer dev                   # server + queue + logs (pail) + vite, all together
composer test                  # config:clear + php artisan test
./vendor/bin/pest              # run the whole suite
./vendor/bin/pest --filter="text of the it()"   # a single test / group
php artisan migrate            # migrations (dev, SQLite)
npm run build                  # compile assets (Tailwind v4 / Vite)
```

Tests use SQLite `:memory:` with `RefreshDatabase` (see `phpunit.xml`, `tests/Pest.php`).
There is a global helper `loginFamilyUser(email?)` in `tests/Pest.php` that provisions and
authenticates a family budget user — use it as the base for almost all tests.

## Stack

Laravel 13 (PHP 8.4) · **Livewire 4** + Alpine + Blade · Tailwind v4 (Vite) ·
Socialite (Google) · Pest. Reports with CSS bars (no chart JS).

## Critical convention: Livewire 4 components (single-file)

Livewire 4 uses **single-file components**: class + view in ONE file with the `⚡` prefix
under `resources/views/components/<group>/⚡<name>.blade.php`. Generate them with
`php artisan make:livewire Group/Name`. They are referenced by their dotted name
(`accounts.index`, `budget.dashboard`).

- Full-page pages are mounted **embedded** in a wrapper view that applies the
  layout: `resources/views/app/*.blade.php` contains `<x-layouts.app heading="..."><livewire:budget.dashboard/></x-layouts.app>`, and `routes/web.php` routes with `Route::view(...)`.
- The layout is an anonymous component in `resources/views/components/layouts/app.blade.php`
  (`<x-layouts.app>`), which includes the bottom-nav and the quick-add FAB.
- In tests, `Livewire::test('budget.dashboard')` resolves by the same dotted name.
- **Do not inject dependencies in lifecycle hooks** (`updated`, `mount` except simple
  cases): resolve services with `app(BudgetService::class)`.

## Domain architecture (the "why")

**Money in cents.** All amounts are integers (minor units) to avoid floats.
`App\Support\Money` formats (es-AR) and parses input. Never use floats for money.

**Multi-currency.** Each `Account` has a `currency` (ARS/USD). Each `Transaction` stores
`amount` (in the account's currency), `exchange_rate`, and `amount_base` (cache of the amount
in base currency, computed in `Transaction::saving`). **All budget math
operates on `amount_base`** to consolidate to the budget's base currency.

**`App\Services\BudgetService` is the heart.** Do not duplicate its logic in components.
It maintains this invariant (verified by tests, do not break it):

```
balance of on-budget cash/bank accounts = money to assign + Σ available of categories
```

- `assigned/activity/available` are per category and month; `available` is **cumulative**
  (carryover month to month: Σ of months ≤ the given one).
- `readyToAssign(budget)` = balance of non-card accounts − Σ available of all categories.
- Months are normalized to `YYYY-MM-01`; comparisons use `whereDate` (the
  `month` field may carry a time component).

**Credit cards (fund reservation).** When creating an `Account` of type `credit_card`,
an observer (`AccountObserver`) creates its payment category in the system group "Pagos de
tarjeta" (`Category::linked_account_id` → account). In the engine:
- Cards are **excluded from the cash side** of Ready-to-Assign (spending on credit does not
  change the RTA; it reduces the available of the expense's category).
- The payment category accumulates the funds from credit purchases (`creditFunded`) minus
  the payments made (`paymentActivity`). `payCreditCard()` records the payment as a
  two-legged transfer (cash outflow categorized to the payment + inflow to the card).
- System categories (with `linked_account_id`) and their groups are not editable by the user.

**Categories.** `CategoryGroup::categories()` returns only the **active** ones (hides
`archived_at`); `allCategories()` includes archived ones. "Removing" a category with history
**archives** it (preserves reports), without history it deletes it. Reports read transactions,
not the relation, so archived categories with history keep appearing in reports.

**Auth and family budget.** Login only with Google (`GoogleController` + Socialite).
`FamilyBudgetProvisioner` validates the email against `config('budget.allowed_emails')`
(env `ALLOWED_EMAILS`, comma-separated; the **first** email is admin), creates/updates the
user and associates them with the **single** family budget (seeding default categories the
first time). The `whitelisted` middleware revokes access if the email leaves the list.
Access to the current user's budget: `auth()->user()->currentBudget()`.

## Production notes

- `ALLOWED_EMAILS` and other env vars are frozen with `config:cache` on deploy: if they change,
  you have to redeploy. See `DEPLOY.md`.
- `GOOGLE_REDIRECT_URI` must match the Google console exactly and point to
  `/auth/google/callback`. `trustProxies('*')` is active for HTTPS behind the proxy.

## Git

Develop on the branch `claude/laravel-family-budget-app-cnk8so`. **Whenever you
work on a branch and finish the work, open a PR to `main`** (with the tests
green). Avoid mentioning trademarks (e.g. "YNAB") in code, docs,
comments, or commit messages — use generic terms (zero-based, money to assign,
age of money).
</content>
