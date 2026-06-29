<?php

use App\Models\Account;
use App\Models\Budget;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use App\Services\BudgetService;

/**
 * @return array{user: User, budget: Budget, account: Account, category: Category, service: BudgetService}
 */
function budgetSetup(): array
{
    $user = loginFamilyUser();
    $budget = $user->currentBudget();
    $account = $budget->accounts()->create([
        'name' => 'Banco', 'type' => 'checking', 'currency' => 'ARS', 'on_budget' => true,
    ]);
    $category = $budget->categoryGroups()->first()->categories()->first();

    return [
        'user' => $user,
        'budget' => $budget,
        'account' => $account,
        'category' => $category,
        'service' => app(BudgetService::class),
    ];
}

function tx(Account $account, int $amount, string $date, ?int $categoryId = null): Transaction
{
    return Transaction::create([
        'account_id' => $account->id,
        'date' => $date,
        'amount' => $amount,
        'currency' => $account->currency,
        'category_id' => $categoryId,
    ]);
}

it('asigna y lee el monto asignado de una categoría', function () {
    ['budget' => $budget, 'category' => $cat, 'service' => $svc] = budgetSetup();

    $svc->assign($budget, $cat, '2026-06', 30000);

    expect($svc->assigned($budget, $cat, '2026-06'))->toBe(30000);
});

it('calcula la actividad del mes (gastos en negativo)', function () {
    ['budget' => $b, 'account' => $acc, 'category' => $cat, 'service' => $svc] = budgetSetup();

    tx($acc, -20000, '2026-06-10', $cat->id);
    tx($acc, -5000, '2026-06-20', $cat->id);
    tx($acc, -9999, '2026-07-01', $cat->id); // otro mes, no cuenta

    expect($svc->activity($b, $cat, '2026-06'))->toBe(-25000);
});

it('arrastra el disponible de un mes al siguiente', function () {
    ['budget' => $b, 'account' => $acc, 'category' => $cat, 'service' => $svc] = budgetSetup();

    // Junio: asigno 30000, gasto 10000 => disponible junio = 20000
    $svc->assign($b, $cat, '2026-06', 30000);
    tx($acc, -10000, '2026-06-15', $cat->id);
    expect($svc->available($b, $cat, '2026-06'))->toBe(20000);

    // Julio: asigno 5000, gasto 8000 => disponible julio = 20000 + 5000 - 8000 = 17000
    $svc->assign($b, $cat, '2026-07', 5000);
    tx($acc, -8000, '2026-07-10', $cat->id);
    expect($svc->available($b, $cat, '2026-07'))->toBe(17000);
});

it('calcula el dinero por asignar como ingresos menos asignado', function () {
    ['budget' => $b, 'account' => $acc, 'category' => $cat, 'service' => $svc] = budgetSetup();

    tx($acc, 100000, '2026-06-01');          // ingreso sin categoría
    $svc->assign($b, $cat, '2026-06', 30000); // asigno
    tx($acc, -20000, '2026-06-10', $cat->id); // gasto

    expect($svc->readyToAssign($b))->toBe(70000); // 100000 - 30000
});

it('respeta el invariante: saldo on-budget = RTA + Σ disponible', function () {
    ['budget' => $b, 'account' => $acc, 'category' => $cat, 'service' => $svc] = budgetSetup();

    tx($acc, 100000, '2026-06-01');
    $svc->assign($b, $cat, '2026-06', 30000);
    tx($acc, -20000, '2026-06-10', $cat->id);

    $onBudgetBalance = $acc->balance();           // 80000
    $rta = $svc->readyToAssign($b);               // 70000
    $available = $svc->available($b, $cat, '2026-06'); // 10000

    expect($onBudgetBalance)->toBe($rta + $available);
});

it('mueve dinero entre categorías en el mismo mes', function () {
    ['budget' => $b, 'category' => $from, 'service' => $svc] = budgetSetup();
    $to = $b->categoryGroups()->first()->categories()->skip(1)->first();

    $svc->assign($b, $from, '2026-06', 50000);
    $svc->move($b, $from, $to, '2026-06', 20000);

    expect($svc->assigned($b, $from, '2026-06'))->toBe(30000)
        ->and($svc->assigned($b, $to, '2026-06'))->toBe(20000);
});

it('consolida cuentas USD a moneda base en el RTA', function () {
    ['budget' => $b, 'service' => $svc] = budgetSetup();
    $usd = $b->accounts()->create([
        'name' => 'USD', 'type' => 'checking', 'currency' => 'USD', 'on_budget' => true,
    ]);

    // Ingreso de USD 100 con tipo de cambio 1000 => 100.000 ARS (10.000.000 centavos)
    Transaction::create([
        'account_id' => $usd->id,
        'date' => '2026-06-01',
        'amount' => 10000,        // USD 100,00
        'currency' => 'USD',
        'exchange_rate' => 1000,
    ]);

    expect($svc->readyToAssign($b))->toBe(10000000);
});
