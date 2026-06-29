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
