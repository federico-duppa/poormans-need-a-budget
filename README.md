# 💸 Poorman's Budget

Aplicación de presupuesto familiar con **método de sobres / base-cero** para
controlar los gastos del grupo familiar. Presupuesto base-cero ("dale un trabajo
a cada peso"), multi-moneda **ARS + USD**, login con Google restringido por
whitelist, y pensada **mobile-first** (PWA).

## Stack

- **Laravel 13** (PHP 8.4)
- **Livewire 4** + **Alpine.js** + **Blade**
- **Tailwind CSS v4** (Vite)
- **SQLite** en desarrollo · **PostgreSQL** en producción (Laravel Cloud)
- **Laravel Socialite** (Google OAuth)
- **Pest** para tests
- Reportes con barras CSS (sin dependencias JS)

## Metodología de presupuesto base-cero

1. **Dale un trabajo a cada peso** — presupuesto base-cero (dinero por asignar + asignación mensual).
2. **Aceptá tus gastos reales** — metas/targets por categoría (post-MVP).
3. **Rodá con los golpes** — arrastre de saldos disponibles mes a mes.
4. **Antigüedad del dinero** — métrica de cuántos días viven tus pesos antes de gastarse.

Más el manejo de **tarjetas de crédito con reserva de fondos** (mover dinero a la
categoría de pago al gastar) y atribución de **autor** por transacción dentro
del presupuesto familiar compartido.

## Puesta en marcha (desarrollo)

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate
npm run build   # o: npm run dev
php artisan serve
```

### Variables de entorno relevantes

| Variable | Descripción |
|---|---|
| `ALLOWED_EMAILS` | Emails (coma-separados) habilitados para entrar. El primero es admin. |
| `BUDGET_BASE_CURRENCY` | Moneda base del presupuesto (default `ARS`). |
| `BUDGET_SECONDARY_CURRENCY` | Moneda secundaria soportada (default `USD`). |
| `GOOGLE_CLIENT_ID` / `GOOGLE_CLIENT_SECRET` / `GOOGLE_REDIRECT_URI` | Credenciales OAuth de Google. |

## Tests

```bash
./vendor/bin/pest
```

## Convenciones

- Los montos se almacenan como **enteros en centavos** (minor units) para evitar
  errores de redondeo de punto flotante en la lógica financiera.

## Estado del desarrollo

- [x] Fase 0 — Scaffold base (Laravel 13 + Livewire + Tailwind + layout mobile)
- [x] Fase 1 — Auth Google + whitelist
- [x] Fase 2 — Cuentas y transacciones
- [x] Fase 3 — Motor de presupuesto base-cero
- [x] Fase 4 — Tarjetas de crédito (reserva de fondos)
- [x] Fase 5 — Reportes + PWA
- [x] Fase 6 — Deploy Laravel Cloud (ver [DEPLOY.md](DEPLOY.md))

### Próximos pasos (post-MVP)

- Metas/targets por categoría (ahorrar $X para tal fecha, gastar hasta $Y/mes).
- Importar movimientos por CSV y transacciones recurrentes.
- Splits en el UI de carga, edición/borrado de movimientos.
- Conciliación de cuentas.
