# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Qué es

App de presupuesto familiar **base-cero** (método de sobres): asignás cada peso a una
categoría, registrás movimientos y seguís el "disponible" mes a mes. Multi-moneda
(ARS base + USD), login con Google restringido por whitelist, presupuesto familiar
único y compartido. Producción en Laravel Cloud (PostgreSQL); desarrollo en SQLite.

> **El foco de la experiencia es mobile-first.** Toda UI nueva se diseña primero para
> el celular: layout angosto (`max-w-2xl`), navegación inferior (bottom-nav), botón
> flotante de carga rápida, targets táctiles grandes. La app es una PWA instalable.
> Verificá cómo se ve en viewport chico antes de dar algo por terminado.

## Comandos

```bash
composer setup                 # instala deps, .env, key, migra, build (primer arranque)
composer dev                   # server + queue + logs (pail) + vite, todo junto
composer test                  # config:clear + php artisan test
./vendor/bin/pest              # correr toda la suite
./vendor/bin/pest --filter="texto del it()"   # un solo test / grupo
php artisan migrate            # migraciones (dev, SQLite)
npm run build                  # compilar assets (Tailwind v4 / Vite)
```

Tests usan SQLite `:memory:` con `RefreshDatabase` (ver `phpunit.xml`, `tests/Pest.php`).
Hay un helper global `loginFamilyUser(email?)` en `tests/Pest.php` que aprovisiona y
autentica un usuario del presupuesto familiar — usalo como base de casi todos los tests.

## Stack

Laravel 13 (PHP 8.4) · **Livewire 4** + Alpine + Blade · Tailwind v4 (Vite) ·
Socialite (Google) · Pest. Reportes con barras CSS (sin JS de charts).

## Convención crítica: componentes Livewire 4 (single-file)

Livewire 4 usa **single-file components**: clase + vista en UN archivo con prefijo `⚡`
bajo `resources/views/components/<grupo>/⚡<nombre>.blade.php`. Generalos con
`php artisan make:livewire Grupo/Nombre`. Se referencian por nombre punteado
(`accounts.index`, `budget.dashboard`).

- Las páginas full-page se montan **embebidas** en una vista wrapper que aplica el
  layout: `resources/views/app/*.blade.php` contiene `<x-layouts.app heading="..."><livewire:budget.dashboard/></x-layouts.app>`, y `routes/web.php` rutea con `Route::view(...)`.
- El layout es un componente anónimo en `resources/views/components/layouts/app.blade.php`
  (`<x-layouts.app>`), que incluye la bottom-nav y el FAB de carga rápida.
- En tests, `Livewire::test('budget.dashboard')` resuelve por el mismo nombre punteado.
- **No inyectes dependencias en hooks de lifecycle** (`updated`, `mount` salvo casos
  simples): resolvé servicios con `app(BudgetService::class)`.

## Arquitectura del dominio (el "por qué")

**Dinero en centavos.** Todos los montos son enteros (minor units) para evitar floats.
`App\Support\Money` formatea (es-AR) y parsea input. Nunca uses floats para plata.

**Multi-moneda.** Cada `Account` tiene `currency` (ARS/USD). Cada `Transaction` guarda
`amount` (en la moneda de la cuenta), `exchange_rate` y `amount_base` (cache del monto
en moneda base, calculado en `Transaction::saving`). **Toda la matemática de presupuesto
opera sobre `amount_base`** para consolidar a la moneda base del presupuesto.

**`App\Services\BudgetService` es el corazón.** No dupliques su lógica en componentes.
Mantiene este invariante (verificado por tests, no romperlo):

```
saldo cuentas efectivo/banco on-budget = dinero por asignar + Σ disponible de categorías
```

- `assigned/activity/available` son por categoría y mes; `available` es **acumulado**
  (arrastre mes a mes: Σ de meses ≤ el dado).
- `readyToAssign(budget)` = saldo de cuentas no-tarjeta − Σ disponible de todas las categorías.
- Los meses se normalizan a `YYYY-MM-01`; las comparaciones usan `whereDate` (el campo
  `month` puede traer componente horario).

**Tarjetas de crédito (reserva de fondos).** Al crear una `Account` tipo `credit_card`,
un observer (`AccountObserver`) crea su categoría de pago en el grupo-sistema "Pagos de
tarjeta" (`Category::linked_account_id` → cuenta). En el motor:
- Las tarjetas se **excluyen del lado efectivo** del Ready-to-Assign (gastar a crédito no
  cambia el RTA; reduce el disponible de la categoría del gasto).
- La categoría de pago acumula los fondos de las compras a crédito (`creditFunded`) menos
  los pagos hechos (`paymentActivity`). `payCreditCard()` registra el pago como
  transferencia de dos patas (salida de efectivo categorizada al pago + entrada a la tarjeta).
- Las categorías-sistema (con `linked_account_id`) y sus grupos no son editables por el usuario.

**Categorías.** `CategoryGroup::categories()` devuelve solo las **activas** (oculta
`archived_at`); `allCategories()` incluye archivadas. "Quitar" una categoría con historial
la **archiva** (preserva reportes), sin historial la elimina. Reportes leen transacciones,
no la relación, así que las archivadas con historial siguen apareciendo en reportes.

**Auth y presupuesto familiar.** Login solo con Google (`GoogleController` + Socialite).
`FamilyBudgetProvisioner` valida el email contra `config('budget.allowed_emails')`
(env `ALLOWED_EMAILS`, coma-separado; el **primer** email es admin), crea/actualiza el
usuario y lo asocia al **único** presupuesto familiar (sembrando categorías por defecto la
primera vez). El middleware `whitelisted` revoca acceso si el email sale de la lista.
Acceso al presupuesto del usuario actual: `auth()->user()->currentBudget()`.

## Notas de producción

- `ALLOWED_EMAILS` y demás env se congelan con `config:cache` en el deploy: si cambian,
  hay que redeployar. Ver `DEPLOY.md`.
- `GOOGLE_REDIRECT_URI` debe coincidir exacto con la consola de Google y apuntar a
  `/auth/google/callback`. `trustProxies('*')` está activo para HTTPS detrás del proxy.

## Git

Desarrollar en la branch `claude/laravel-family-budget-app-cnk8so`. No crear PRs sin
pedido explícito. Evitar mencionar marcas registradas (p. ej. "YNAB") en código, docs,
comentarios o mensajes de commit — usar términos genéricos (base-cero, dinero por asignar,
antigüedad del dinero).
