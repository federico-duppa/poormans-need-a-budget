<?php

use App\Models\Account;
use App\Models\Budget;
use App\Models\Category;
use App\Models\Transaction;
use App\Services\BudgetService;

/**
 * @return array{budget: Budget, cash: Account, card: Account, groceries: Category, payment: Category, svc: BudgetService}
 */
function ccSetup(): array
{
    $user = loginFamilyUser();
    $budget = $user->currentBudget();

    $cash = $budget->accounts()->create([
        'name' => 'Banco', 'type' => 'checking', 'currency' => 'ARS', 'on_budget' => true,
    ]);
    $card = $budget->accounts()->create([
        'name' => 'Visa', 'type' => 'credit_card', 'currency' => 'ARS', 'on_budget' => true,
    ]);

    $groceries = $budget->categoryGroups()->first()->categories()->first();
    $payment = $card->paymentCategory()->firstOrFail();

    return compact('budget', 'cash', 'card', 'groceries', 'payment') + ['svc' => app(BudgetService::class)];
}

function totalAvailable(Budget $budget, BudgetService $svc, string $month): int
{
    $total = 0;
    foreach ($budget->categoryGroups()->with('categories')->get() as $group) {
        foreach ($group->categories as $category) {
            $total += $svc->available($budget, $category, $month);
        }
    }

    return $total;
}

it('automatically creates the payment category when adding a card', function () {
    ['card' => $card, 'budget' => $budget] = ccSetup();

    $payment = $card->paymentCategory()->first();
    expect($payment)->not->toBeNull()
        ->and($payment->name)->toBe('Pago Visa')
        ->and($payment->group->is_system)->toBeTrue()
        ->and($payment->group->name)->toBe('Pagos de tarjeta');
});

it('reserves funds in the payment category when spending with a card', function () {
    ['budget' => $b, 'cash' => $cash, 'card' => $card, 'groceries' => $g, 'payment' => $p, 'svc' => $svc] = ccSetup();

    // Cash inflow + assignment to groceries
    Transaction::create(['account_id' => $cash->id, 'date' => '2026-06-01', 'amount' => 100000, 'currency' => 'ARS']);
    $svc->assign($b, $g, '2026-06', 50000);

    // Credit purchase of 30000 in groceries
    Transaction::create(['account_id' => $card->id, 'date' => '2026-06-10', 'amount' => -30000, 'currency' => 'ARS', 'category_id' => $g->id]);

    expect($svc->available($b, $g, '2026-06'))->toBe(20000)      // 50000 - 30000
        ->and($svc->available($b, $p, '2026-06'))->toBe(30000)   // reserved to pay
        ->and($svc->readyToAssign($b))->toBe(50000);             // 100000 - 50000 (does not change from spending on credit)
});

it('keeps the invariant after a credit purchase', function () {
    ['budget' => $b, 'cash' => $cash, 'card' => $card, 'groceries' => $g, 'svc' => $svc] = ccSetup();

    Transaction::create(['account_id' => $cash->id, 'date' => '2026-06-01', 'amount' => 100000, 'currency' => 'ARS']);
    $svc->assign($b, $g, '2026-06', 50000);
    Transaction::create(['account_id' => $card->id, 'date' => '2026-06-10', 'amount' => -30000, 'currency' => 'ARS', 'category_id' => $g->id]);

    $cashBalance = $cash->balance(); // 100000 (the card is excluded from the cash side)
    expect($cashBalance)->toBe($svc->readyToAssign($b) + totalAvailable($b, $svc, '2026-06'));
});

it('settles the payment category when paying the card', function () {
    ['budget' => $b, 'cash' => $cash, 'card' => $card, 'groceries' => $g, 'payment' => $p, 'svc' => $svc] = ccSetup();

    Transaction::create(['account_id' => $cash->id, 'date' => '2026-06-01', 'amount' => 100000, 'currency' => 'ARS']);
    $svc->assign($b, $g, '2026-06', 50000);
    Transaction::create(['account_id' => $card->id, 'date' => '2026-06-10', 'amount' => -30000, 'currency' => 'ARS', 'category_id' => $g->id]);

    // Pay the card: 30000 from the bank
    $svc->payCreditCard($cash, $card, 30000, '2026-06-20');

    expect($card->balance())->toBe(0)                          // debt settled
        ->and($svc->available($b, $p, '2026-06'))->toBe(0)     // nothing left to reserve
        ->and($cash->balance())->toBe(70000)                   // 100000 - 30000
        ->and($svc->readyToAssign($b))->toBe(50000);           // RTA does not change on payment

    // Invariant still holds
    expect($cash->balance())->toBe($svc->readyToAssign($b) + totalAvailable($b, $svc, '2026-06'));
});

it('monthlySummary matches the per-category methods', function () {
    ['budget' => $b, 'cash' => $cash, 'card' => $card, 'groceries' => $g, 'payment' => $p, 'svc' => $svc] = ccSetup();

    Transaction::create(['account_id' => $cash->id, 'date' => '2026-06-01', 'amount' => 100000, 'currency' => 'ARS']);
    $svc->assign($b, $g, '2026-06', 50000);
    Transaction::create(['account_id' => $card->id, 'date' => '2026-06-10', 'amount' => -30000, 'currency' => 'ARS', 'category_id' => $g->id]);

    $summary = $svc->monthlySummary($b, '2026-06');
    $find = fn (int $id) => collect($summary['groups'])
        ->pluck('categories')->flatten(1)->firstWhere('id', $id);

    expect($summary['readyToAssign'])->toBe($svc->readyToAssign($b));

    $gs = $find($g->id);
    expect($gs['assigned'])->toBe($svc->assigned($b, $g, '2026-06'))
        ->and($gs['activity'])->toBe($svc->activity($b, $g, '2026-06'))
        ->and($gs['available'])->toBe($svc->available($b, $g, '2026-06'));

    $ps = $find($p->id);
    expect($ps['activity'])->toBe($svc->activity($b, $p, '2026-06'))
        ->and($ps['available'])->toBe($svc->available($b, $p, '2026-06'));
});
