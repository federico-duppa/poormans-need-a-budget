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

it('assigns and reads the assigned amount of a category', function () {
    ['budget' => $budget, 'category' => $cat, 'service' => $svc] = budgetSetup();

    $svc->assign($budget, $cat, '2026-06', 30000);

    expect($svc->assigned($budget, $cat, '2026-06'))->toBe(30000);
});

it('computes the month activity (expenses as negative)', function () {
    ['budget' => $b, 'account' => $acc, 'category' => $cat, 'service' => $svc] = budgetSetup();

    tx($acc, -20000, '2026-06-10', $cat->id);
    tx($acc, -5000, '2026-06-20', $cat->id);
    tx($acc, -9999, '2026-07-01', $cat->id); // another month, does not count

    expect($svc->activity($b, $cat, '2026-06'))->toBe(-25000);
});

it('carries over the available from one month to the next', function () {
    ['budget' => $b, 'account' => $acc, 'category' => $cat, 'service' => $svc] = budgetSetup();

    // June: assign 30000, spend 10000 => available June = 20000
    $svc->assign($b, $cat, '2026-06', 30000);
    tx($acc, -10000, '2026-06-15', $cat->id);
    expect($svc->available($b, $cat, '2026-06'))->toBe(20000);

    // July: assign 5000, spend 8000 => available July = 20000 + 5000 - 8000 = 17000
    $svc->assign($b, $cat, '2026-07', 5000);
    tx($acc, -8000, '2026-07-10', $cat->id);
    expect($svc->available($b, $cat, '2026-07'))->toBe(17000);
});

it('computes the money to assign as income minus assigned', function () {
    ['budget' => $b, 'account' => $acc, 'category' => $cat, 'service' => $svc] = budgetSetup();

    tx($acc, 100000, '2026-06-01');          // income without a category
    $svc->assign($b, $cat, '2026-06', 30000); // assign
    tx($acc, -20000, '2026-06-10', $cat->id); // expense

    expect($svc->readyToAssign($b))->toBe(70000); // 100000 - 30000
});

it('respects the invariant: on-budget balance = RTA + Σ available', function () {
    ['budget' => $b, 'account' => $acc, 'category' => $cat, 'service' => $svc] = budgetSetup();

    tx($acc, 100000, '2026-06-01');
    $svc->assign($b, $cat, '2026-06', 30000);
    tx($acc, -20000, '2026-06-10', $cat->id);

    $onBudgetBalance = $acc->balance();           // 80000
    $rta = $svc->readyToAssign($b);               // 70000
    $available = $svc->available($b, $cat, '2026-06'); // 10000

    expect($onBudgetBalance)->toBe($rta + $available);
});

it('moves money between categories in the same month', function () {
    ['budget' => $b, 'category' => $from, 'service' => $svc] = budgetSetup();
    $to = $b->categoryGroups()->first()->categories()->skip(1)->first();

    $svc->assign($b, $from, '2026-06', 50000);
    $svc->move($b, $from, $to, '2026-06', 20000);

    expect($svc->assigned($b, $from, '2026-06'))->toBe(30000)
        ->and($svc->assigned($b, $to, '2026-06'))->toBe(20000);
});

it('consolidates USD accounts to base currency in the RTA', function () {
    ['budget' => $b, 'service' => $svc] = budgetSetup();
    $usd = $b->accounts()->create([
        'name' => 'USD', 'type' => 'checking', 'currency' => 'USD', 'on_budget' => true,
    ]);

    // Income of USD 100 with exchange rate 1000 => 100,000 ARS (10,000,000 cents)
    Transaction::create([
        'account_id' => $usd->id,
        'date' => '2026-06-01',
        'amount' => 10000,        // USD 100,00
        'currency' => 'USD',
        'exchange_rate' => 1000,
    ]);

    expect($svc->readyToAssign($b))->toBe(10000000);
});
