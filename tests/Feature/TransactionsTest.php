<?php

use App\Models\Account;
use App\Models\Payee;
use App\Models\Transaction;
use Livewire\Livewire;

function makeAccount(\App\Models\User $user, string $currency = 'ARS', string $type = 'checking'): Account
{
    return $user->currentBudget()->accounts()->create([
        'name' => 'Cuenta '.$currency,
        'type' => $type,
        'currency' => $currency,
        'on_budget' => true,
    ]);
}

it('renderiza la página de carga rápida', function () {
    $user = loginFamilyUser();
    makeAccount($user);

    $this->get(route('transactions.new'))->assertOk();
});

it('registra un gasto con monto negativo y autor', function () {
    $user = loginFamilyUser();
    $account = makeAccount($user);
    $category = $user->currentBudget()->categoryGroups()->first()->categories()->first();

    Livewire::test('transactions.quick-add')
        ->set('account_id', $account->id)
        ->set('direction', 'outflow')
        ->set('amount', '1.234,56')
        ->set('category_id', $category->id)
        ->set('payee', 'Coto')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('transactions'));

    $tx = Transaction::first();
    expect($tx->amount)->toBe(-123456)
        ->and($tx->user_id)->toBe($user->id)
        ->and($tx->category_id)->toBe($category->id)
        ->and($tx->amount_base)->toBe(-123456);

    expect(Payee::where('name', 'Coto')->exists())->toBeTrue();
});

it('registra un ingreso positivo sin categoría (listo para asignar)', function () {
    $user = loginFamilyUser();
    $account = makeAccount($user);

    Livewire::test('transactions.quick-add')
        ->set('account_id', $account->id)
        ->set('direction', 'inflow')
        ->set('amount', '500000')
        ->set('payee', 'Sueldo')
        ->call('save')
        ->assertHasNoErrors();

    $tx = Transaction::first();
    expect($tx->amount)->toBe(50000000)
        ->and($tx->category_id)->toBeNull();
});

it('convierte a moneda base usando el tipo de cambio en cuentas USD', function () {
    $user = loginFamilyUser();
    $account = makeAccount($user, 'USD');

    Livewire::test('transactions.quick-add')
        ->set('account_id', $account->id)
        ->set('direction', 'outflow')
        ->set('amount', '100')        // USD 100,00 => 10000 centavos USD
        ->set('exchange_rate', '1000') // 1 USD = 1000 ARS
        ->call('save')
        ->assertHasNoErrors();

    $tx = Transaction::first();
    expect($tx->amount)->toBe(-10000)            // centavos en USD
        ->and($tx->currency)->toBe('USD')
        ->and($tx->amount_base)->toBe(-10000000); // -100 USD * 1000 = -100.000 ARS (en centavos)
});

it('rechaza monto cero o negativo', function () {
    $user = loginFamilyUser();
    $account = makeAccount($user);

    Livewire::test('transactions.quick-add')
        ->set('account_id', $account->id)
        ->set('amount', '0')
        ->call('save')
        ->assertHasErrors('amount');

    expect(Transaction::count())->toBe(0);
});

it('lista los movimientos del presupuesto', function () {
    $user = loginFamilyUser();
    $account = makeAccount($user);
    Transaction::create([
        'account_id' => $account->id,
        'date' => now()->toDateString(),
        'amount' => -5000,
        'currency' => 'ARS',
        'user_id' => $user->id,
    ]);

    Livewire::test('transactions.index')
        ->assertOk()
        ->assertSee('Cuenta ARS');
});
