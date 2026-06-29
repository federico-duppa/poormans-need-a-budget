# 💸 Poorman's Budget

Aplicación de presupuesto familiar al estilo **YNAB (You Need A Budget)** para
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
- **Chart.js** para reportes

## Metodología YNAB que replicamos

1. **Dale un trabajo a cada peso** — presupuesto base-cero (Ready to Assign + asignación mensual).
2. **Aceptá tus gastos reales** — metas/targets por categoría (post-MVP).
3. **Rodá con los golpes** — arrastre de saldos disponibles mes a mes.
4. **Envejecé tu dinero** — métrica Age of Money.

Más el manejo de **tarjetas de crédito al estilo YNAB** (mover fondos a la
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
- [x] Fase 4 — Tarjetas de crédito estilo YNAB
- [x] Fase 5 — Reportes + PWA
- [x] Fase 6 — Deploy Laravel Cloud (ver [DEPLOY.md](DEPLOY.md))

### Próximos pasos (post-MVP)

- Metas/targets por categoría (ahorrar $X para tal fecha, gastar hasta $Y/mes).
- Importar movimientos por CSV y transacciones recurrentes.
- Splits en el UI de carga, edición/borrado de movimientos.
- Conciliación de cuentas.
