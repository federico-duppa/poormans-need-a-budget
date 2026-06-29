<?php

use App\Models\Transaction;
use App\Services\BudgetService;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

it('renders the budget dashboard', function () {
    loginFamilyUser();

    $this->get(route('budget'))
        ->assertOk()
        ->assertSee('Listo para asignar');
});

it('assigns money to a category from the dashboard', function () {
    $user = loginFamilyUser();
    $budget = $user->currentBudget();
    $category = $budget->categoryGroups()->first()->categories()->first();
    $month = CarbonImmutable::now()->startOfMonth()->toDateString();

    Livewire::test('budget.dashboard')
        ->set("assignedInputs.{$category->id}", '300');

    expect(app(BudgetService::class)->assigned($budget, $category, $month))->toBe(30000);
});

it('reflects the available money (income) in the money to assign', function () {
    $user = loginFamilyUser();
    $budget = $user->currentBudget();
    $account = $budget->accounts()->create([
        'name' => 'Banco', 'type' => 'checking', 'currency' => 'ARS', 'on_budget' => true,
    ]);
    Transaction::create([
        'account_id' => $account->id,
        'date' => CarbonImmutable::now()->toDateString(),
        'amount' => 5000000, // income ARS 50.000,00
        'currency' => 'ARS',
    ]);

    Livewire::test('budget.dashboard')
        ->assertSet('month', CarbonImmutable::now()->startOfMonth()->toDateString())
        ->assertSeeHtml('50.000,00');
});

it('navigates between months', function () {
    loginFamilyUser();
    $thisMonth = CarbonImmutable::now()->startOfMonth();

    Livewire::test('budget.dashboard')
        ->call('changeMonth', 1)
        ->assertSet('month', $thisMonth->addMonth()->toDateString())
        ->call('changeMonth', -1)
        ->assertSet('month', $thisMonth->toDateString());
});
