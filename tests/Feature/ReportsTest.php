<?php

use App\Models\Transaction;
use App\Services\ReportService;

function reportSetup(): array
{
    $user = loginFamilyUser();
    $budget = $user->currentBudget();
    $account = $budget->accounts()->create([
        'name' => 'Banco', 'type' => 'checking', 'currency' => 'ARS', 'on_budget' => true,
    ]);

    return ['budget' => $budget, 'account' => $account, 'svc' => app(ReportService::class)];
}

it('groups spending by category from highest to lowest', function () {
    ['budget' => $b, 'account' => $acc, 'svc' => $svc] = reportSetup();
    $cats = $b->categoryGroups()->first()->categories;
    $super = $cats[0];
    $transporte = $cats[1];

    Transaction::create(['account_id' => $acc->id, 'date' => '2026-06-05', 'amount' => -10000, 'currency' => 'ARS', 'category_id' => $super->id]);
    Transaction::create(['account_id' => $acc->id, 'date' => '2026-06-15', 'amount' => -5000, 'currency' => 'ARS', 'category_id' => $super->id]);
    Transaction::create(['account_id' => $acc->id, 'date' => '2026-06-20', 'amount' => -8000, 'currency' => 'ARS', 'category_id' => $transporte->id]);
    Transaction::create(['account_id' => $acc->id, 'date' => '2026-06-21', 'amount' => 99999, 'currency' => 'ARS']); // income, does not count

    $report = $svc->spendingByCategory($b, '2026-06');

    expect($report)->toHaveCount(2);
    expect($report[0]['total'])->toBe(15000)         // groceries, the largest
        ->and($report[1]['total'])->toBe(8000);      // transport
});

it('computes income vs expense for the month', function () {
    ['budget' => $b, 'account' => $acc, 'svc' => $svc] = reportSetup();

    Transaction::create(['account_id' => $acc->id, 'date' => '2026-06-01', 'amount' => 500000, 'currency' => 'ARS']);
    Transaction::create(['account_id' => $acc->id, 'date' => '2026-06-10', 'amount' => -120000, 'currency' => 'ARS']);

    $report = $svc->incomeVsExpense($b, 1, '2026-06');

    expect($report)->toHaveCount(1);
    expect($report[0]['income'])->toBe(500000)
        ->and($report[0]['expense'])->toBe(120000);
});

it('computes the age of money with FIFO', function () {
    ['budget' => $b, 'account' => $acc, 'svc' => $svc] = reportSetup();

    Transaction::create(['account_id' => $acc->id, 'date' => '2026-06-01', 'amount' => 100000, 'currency' => 'ARS']);
    Transaction::create(['account_id' => $acc->id, 'date' => '2026-06-11', 'amount' => -10000, 'currency' => 'ARS']); // 10 days
    Transaction::create(['account_id' => $acc->id, 'date' => '2026-06-21', 'amount' => -10000, 'currency' => 'ARS']); // 20 days

    expect($svc->ageOfMoney($b, '2026-06-30'))->toBe(15);
});

it('returns null for the age of money with no outflows', function () {
    ['budget' => $b, 'svc' => $svc] = reportSetup();

    expect($svc->ageOfMoney($b))->toBeNull();
});

it('renders the reports page', function () {
    loginFamilyUser();

    $this->get(route('reports'))
        ->assertOk()
        ->assertSee('Antigüedad del dinero')
        ->assertSee('Gasto por categoría')
        ->assertSee('Ingreso vs egreso');
});
