# Deploy a Laravel Cloud

Esta app está lista para deployarse en [Laravel Cloud](https://cloud.laravel.com).
Usa **PostgreSQL** en producción y **SQLite** en desarrollo (el driver se elige
por la variable `DB_CONNECTION`).

## 1. Crear el proyecto en Laravel Cloud

1. Conectá el repo de GitHub `federico-duppa/poormans-need-a-budget`.
2. Elegí la branch a deployar.
3. Laravel Cloud detecta Laravel automáticamente y corre:
   - `composer install --no-dev`
   - `npm ci && npm run build` (compila Tailwind/Vite — `public/build` no se versiona)
   - El **deploy command** debe incluir las migraciones.

## 2. Base de datos

Creá una base **PostgreSQL** gestionada en Laravel Cloud y vinculala al entorno.
Laravel Cloud inyecta las credenciales (`DB_*`). Asegurate de:

```
DB_CONNECTION=pgsql
```

(El resto — host, puerto, base, usuario, password — lo provee Laravel Cloud.)

Las tablas de sesión, cache y colas usan la base (drivers `database`), así que
las migraciones por defecto ya las crean.

## 3. Deploy command

Configurá el deploy command del entorno:

```bash
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## 4. Variables de entorno (producción)

| Variable | Valor |
|---|---|
| `APP_NAME` | `"Poorman's Budget"` |
| `APP_ENV` | `production` |
| `APP_KEY` | generar con `php artisan key:generate --show` y pegar |
| `APP_DEBUG` | `false` |
| `APP_URL` | `https://tu-dominio.laravel.cloud` |
| `APP_LOCALE` | `es` |
| `DB_CONNECTION` | `pgsql` |
| `SESSION_DRIVER` | `database` |
| `CACHE_STORE` | `database` |
| `QUEUE_CONNECTION` | `database` |
| `ALLOWED_EMAILS` | `tu-email@gmail.com` (coma-separado; el primero es admin) |
| `BUDGET_BASE_CURRENCY` | `ARS` |
| `BUDGET_SECONDARY_CURRENCY` | `USD` |
| `GOOGLE_CLIENT_ID` | (de Google Cloud Console) |
| `GOOGLE_CLIENT_SECRET` | (de Google Cloud Console) |
| `GOOGLE_REDIRECT_URI` | `https://tu-dominio.laravel.cloud/auth/google/callback` |

## 5. Google OAuth

En [Google Cloud Console](https://console.cloud.google.com) → *APIs & Services*
→ *Credentials* → *OAuth client ID* (tipo *Web application*):

- **Authorized redirect URI:** `https://tu-dominio.laravel.cloud/auth/google/callback`
  (debe coincidir exactamente con `GOOGLE_REDIRECT_URI`).
- Copiá el *Client ID* y *Client secret* a las variables de entorno.

## 6. Primer login

Entrá con el email que pusiste en `ALLOWED_EMAILS`. El primer email de la lista
queda como administrador y se crea automáticamente el presupuesto familiar con
categorías por defecto. Para sumar familia, agregá sus emails a `ALLOWED_EMAILS`
y que inicien sesión con Google.

## Notas

- La app trustea el proxy de Laravel Cloud (`trustProxies`), así los redirects y
  URLs se generan en HTTPS.
- Healthcheck disponible en `/up`.
