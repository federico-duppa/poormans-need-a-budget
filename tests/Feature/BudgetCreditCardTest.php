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

it('crea automáticamente la categoría de pago al dar de alta una tarjeta', function () {
    ['card' => $card, 'budget' => $budget] = ccSetup();

    $payment = $card->paymentCategory()->first();
    expect($payment)->not->toBeNull()
        ->and($payment->name)->toBe('Pago Visa')
        ->and($payment->group->is_system)->toBeTrue()
        ->and($payment->group->name)->toBe('Pagos de tarjeta');
});

it('reserva fondos en la categoría de pago al gastar con tarjeta', function () {
    ['budget' => $b, 'cash' => $cash, 'card' => $card, 'groceries' => $g, 'payment' => $p, 'svc' => $svc] = ccSetup();

    // Ingreso a efectivo + asignación a super
    Transaction::create(['account_id' => $cash->id, 'date' => '2026-06-01', 'amount' => 100000, 'currency' => 'ARS']);
    $svc->assign($b, $g, '2026-06', 50000);

    // Compra a crédito de 30000 en super
    Transaction::create(['account_id' => $card->id, 'date' => '2026-06-10', 'amount' => -30000, 'currency' => 'ARS', 'category_id' => $g->id]);

    expect($svc->available($b, $g, '2026-06'))->toBe(20000)      // 50000 - 30000
        ->and($svc->available($b, $p, '2026-06'))->toBe(30000)   // reservado para pagar
        ->and($svc->readyToAssign($b))->toBe(50000);             // 100000 - 50000 (no cambia por gastar a crédito)
});

it('mantiene el invariante tras una compra a crédito', function () {
    ['budget' => $b, 'cash' => $cash, 'card' => $card, 'groceries' => $g, 'svc' => $svc] = ccSetup();

    Transaction::create(['account_id' => $cash->id, 'date' => '2026-06-01', 'amount' => 100000, 'currency' => 'ARS']);
    $svc->assign($b, $g, '2026-06', 50000);
    Transaction::create(['account_id' => $card->id, 'date' => '2026-06-10', 'amount' => -30000, 'currency' => 'ARS', 'category_id' => $g->id]);

    $cashBalance = $cash->balance(); // 100000 (la tarjeta se excluye del lado efectivo)
    expect($cashBalance)->toBe($svc->readyToAssign($b) + totalAvailable($b, $svc, '2026-06'));
});

it('liquida la categoría de pago al pagar la tarjeta', function () {
    ['budget' => $b, 'cash' => $cash, 'card' => $card, 'groceries' => $g, 'payment' => $p, 'svc' => $svc] = ccSetup();

    Transaction::create(['account_id' => $cash->id, 'date' => '2026-06-01', 'amount' => 100000, 'currency' => 'ARS']);
    $svc->assign($b, $g, '2026-06', 50000);
    Transaction::create(['account_id' => $card->id, 'date' => '2026-06-10', 'amount' => -30000, 'currency' => 'ARS', 'category_id' => $g->id]);

    // Pago la tarjeta: 30000 desde el banco
    $svc->payCreditCard($cash, $card, 30000, '2026-06-20');

    expect($card->balance())->toBe(0)                          // deuda saldada
        ->and($svc->available($b, $p, '2026-06'))->toBe(0)     // ya no hay que reservar
        ->and($cash->balance())->toBe(70000)                   // 100000 - 30000
        ->and($svc->readyToAssign($b))->toBe(50000);           // RTA no cambia al pagar

    // Invariante sigue valiendo
    expect($cash->balance())->toBe($svc->readyToAssign($b) + totalAvailable($b, $svc, '2026-06'));
});
