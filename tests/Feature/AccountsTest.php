<?php

use App\Models\Account;
use Livewire\Livewire;

it('renderiza la página de cuentas', function () {
    loginFamilyUser();

    $this->get(route('accounts'))
        ->assertOk()
        ->assertSee('Tus cuentas');
});

it('crea una cuenta a través del componente Livewire', function () {
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

it('valida los datos de la cuenta', function () {
    loginFamilyUser();

    Livewire::test('accounts.index')
        ->set('name', '')
        ->call('save')
        ->assertHasErrors(['name' => 'required']);

    expect(Account::count())->toBe(0);
});

it('crear una tarjeta de crédito genera su categoría de pago', function () {
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

it('registra un pago de tarjeta desde el componente de cuentas', function () {
    $user = loginFamilyUser();
    $budget = $user->currentBudget();
    $cash = $budget->accounts()->create(['name' => 'Banco', 'type' => 'checking', 'currency' => 'ARS', 'on_budget' => true]);
    $card = $budget->accounts()->create(['name' => 'Visa', 'type' => 'credit_card', 'currency' => 'ARS', 'on_budget' => true]);
    // Deuda inicial en la tarjeta
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
