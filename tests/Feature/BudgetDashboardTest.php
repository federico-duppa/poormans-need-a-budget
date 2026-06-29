<?php

use App\Models\Transaction;
use App\Services\BudgetService;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

it('renderiza el tablero de presupuesto', function () {
    loginFamilyUser();

    $this->get(route('budget'))
        ->assertOk()
        ->assertSee('Listo para asignar');
});

it('asigna dinero a una categoría desde el tablero', function () {
    $user = loginFamilyUser();
    $budget = $user->currentBudget();
    $category = $budget->categoryGroups()->first()->categories()->first();
    $month = CarbonImmutable::now()->startOfMonth()->toDateString();

    Livewire::test('budget.dashboard')
        ->set("assignedInputs.{$category->id}", '300');

    expect(app(BudgetService::class)->assigned($budget, $category, $month))->toBe(30000);
});

it('refleja el dinero disponible (ingreso) en Ready to Assign', function () {
    $user = loginFamilyUser();
    $budget = $user->currentBudget();
    $account = $budget->accounts()->create([
        'name' => 'Banco', 'type' => 'checking', 'currency' => 'ARS', 'on_budget' => true,
    ]);
    Transaction::create([
        'account_id' => $account->id,
        'date' => CarbonImmutable::now()->toDateString(),
        'amount' => 5000000, // ingreso ARS 50.000,00
        'currency' => 'ARS',
    ]);

    Livewire::test('budget.dashboard')
        ->assertSet('month', CarbonImmutable::now()->startOfMonth()->toDateString())
        ->assertSeeHtml('50.000,00');
});

it('navega entre meses', function () {
    loginFamilyUser();
    $thisMonth = CarbonImmutable::now()->startOfMonth();

    Livewire::test('budget.dashboard')
        ->call('changeMonth', 1)
        ->assertSet('month', $thisMonth->addMonth()->toDateString())
        ->call('changeMonth', -1)
        ->assertSet('month', $thisMonth->toDateString());
});
