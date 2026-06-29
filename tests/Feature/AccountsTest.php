<?php

use App\Models\Account;
use Livewire\Livewire;

it('renders the accounts page', function () {
    loginFamilyUser();

    $this->get(route('accounts'))
        ->assertOk()
        ->assertSee('Tus cuentas');
});

it('creates an account through the Livewire component', function () {
    $user = loginFamilyUser();

    Livewire::test('accounts.index')
        ->set('name', 'Banco Galicia')
        ->set('type', 'checking')
        ->set('currency', 'ARS')
        ->call('save')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('accounts', [
        'budget_id' => $user->currentBudget()->id,
        'name' => 'Banco Galicia',
        'type' => 'checking',
        'currency' => 'ARS',
        'on_budget' => true,
    ]);
});

it('validates the account data', function () {
    loginFamilyUser();

    Livewire::test('accounts.index')
        ->set('name', '')
        ->call('save')
        ->assertHasErrors(['name' => 'required']);

    expect(Account::count())->toBe(0);
});

it('creating a credit card generates its payment category', function () {
    loginFamilyUser();

    Livewire::test('accounts.index')
        ->set('name', 'Visa')
        ->set('type', 'credit_card')
        ->set('currency', 'ARS')
        ->call('save')
        ->assertHasNoErrors();

    $card = Account::where('name', 'Visa')->first();
    expect($card->paymentCategory()->first()?->name)->toBe('Pago Visa');
});

it('records a card payment from the accounts component', function () {
    $user = loginFamilyUser();
    $budget = $user->currentBudget();
    $cash = $budget->accounts()->create(['name' => 'Banco', 'type' => 'checking', 'currency' => 'ARS', 'on_budget' => true]);
    $card = $budget->accounts()->create(['name' => 'Visa', 'type' => 'credit_card', 'currency' => 'ARS', 'on_budget' => true]);
    // Initial debt on the card
    \App\Models\Transaction::create(['account_id' => $card->id, 'date' => '2026-06-10', 'amount' => -30000, 'currency' => 'ARS']);

    Livewire::test('accounts.index')
        ->call('startPay', $card->id)
        ->set('payAmount', '300')
        ->set('payDate', '2026-06-20')
        ->call('payCard')
        ->assertHasNoErrors();

    expect($card->balance())->toBe(0)
        ->and($cash->balance())->toBe(-30000);
});
