# Deploy to Laravel Cloud

This app is ready to be deployed on [Laravel Cloud](https://cloud.laravel.com).
It uses **PostgreSQL** in production and **SQLite** in development (the driver is
chosen by the `DB_CONNECTION` variable).

## 1. Create the project in Laravel Cloud

1. Connect the GitHub repo `federico-duppa/poormans-need-a-budget`.
2. Choose the branch to deploy.
3. Laravel Cloud detects Laravel automatically and runs:
   - `composer install --no-dev`
   - `npm ci && npm run build` (compiles Tailwind/Vite — `public/build` is not versioned)
   - The **deploy command** must include the migrations.

## 2. Database

Create a managed **PostgreSQL** database in Laravel Cloud and link it to the environment.
Laravel Cloud injects the credentials (`DB_*`). Make sure to:

```
DB_CONNECTION=pgsql
```

(The rest — host, port, database, user, password — is provided by Laravel Cloud.)

The session, cache, and queue tables use the database (`database` drivers), so
the default migrations already create them.

## 3. Deploy command

Configure the environment's deploy command:

```bash
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## 4. Environment variables (production)

| Variable | Value |
|---|---|
| `APP_NAME` | `"Poorman's Budget"` |
| `APP_ENV` | `production` |
| `APP_KEY` | generate with `php artisan key:generate --show` and paste |
| `APP_DEBUG` | `false` |
| `APP_URL` | `https://your-domain.laravel.cloud` |
| `APP_LOCALE` | `es` |
| `DB_CONNECTION` | `pgsql` |
| `SESSION_DRIVER` | `database` |
| `CACHE_STORE` | `database` |
| `QUEUE_CONNECTION` | `database` |
| `ALLOWED_EMAILS` | `your-email@gmail.com` (comma-separated; the first is admin) |
| `BUDGET_BASE_CURRENCY` | `ARS` |
| `BUDGET_SECONDARY_CURRENCY` | `USD` |
| `GOOGLE_CLIENT_ID` | (from Google Cloud Console) |
| `GOOGLE_CLIENT_SECRET` | (from Google Cloud Console) |
| `GOOGLE_REDIRECT_URI` | `https://your-domain.laravel.cloud/auth/google/callback` |

## 5. Google OAuth

In [Google Cloud Console](https://console.cloud.google.com) → *APIs & Services*
→ *Credentials* → *OAuth client ID* (type *Web application*):

- **Authorized redirect URI:** `https://your-domain.laravel.cloud/auth/google/callback`
  (must match `GOOGLE_REDIRECT_URI` exactly).
- Copy the *Client ID* and *Client secret* into the environment variables.

## 6. First login

Log in with the email you set in `ALLOWED_EMAILS`. The first email in the list
becomes the administrator and the family budget is created automatically with
default categories. To add family members, add their emails to `ALLOWED_EMAILS`
and have them log in with Google.

## Notes

- The app trusts the Laravel Cloud proxy (`trustProxies`), so redirects and
  URLs are generated over HTTPS.
- Healthcheck available at `/up`.
</content>
