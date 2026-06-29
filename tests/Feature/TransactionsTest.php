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

it('renders the quick-add page', function () {
    $user = loginFamilyUser();
    makeAccount($user);

    $this->get(route('transactions.new'))->assertOk();
});

it('records an expense with a negative amount and author', function () {
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

it('records a positive income without a category (ready to assign)', function () {
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

it('converts to base currency using the exchange rate in USD accounts', function () {
    $user = loginFamilyUser();
    $account = makeAccount($user, 'USD');

    Livewire::test('transactions.quick-add')
        ->set('account_id', $account->id)
        ->set('direction', 'outflow')
        ->set('amount', '100')        // USD 100,00 => 10000 USD cents
        ->set('exchange_rate', '1000') // 1 USD = 1000 ARS
        ->call('save')
        ->assertHasNoErrors();

    $tx = Transaction::first();
    expect($tx->amount)->toBe(-10000)            // cents in USD
        ->and($tx->currency)->toBe('USD')
        ->and($tx->amount_base)->toBe(-10000000); // -100 USD * 1000 = -100.000 ARS (in cents)
});

it('rejects a zero or negative amount', function () {
    $user = loginFamilyUser();
    $account = makeAccount($user);

    Livewire::test('transactions.quick-add')
        ->set('account_id', $account->id)
        ->set('amount', '0')
        ->call('save')
        ->assertHasErrors('amount');

    expect(Transaction::count())->toBe(0);
});

it('lists the budget transactions', function () {
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

it('edits a transaction and recomputes amount_base', function () {
    $user = loginFamilyUser();
    $account = makeAccount($user);
    $category = $user->currentBudget()->categoryGroups()->first()->categories()->first();
    $tx = Transaction::create([
        'account_id' => $account->id, 'date' => '2026-06-10', 'amount' => -5000,
        'currency' => 'ARS', 'user_id' => $user->id,
    ]);

    Livewire::test('transactions.index')
        ->call('edit', $tx->id)
        ->assertSet('e_amount', '50.00')
        ->set('e_amount', '1.234,56')
        ->set('e_category_id', $category->id)
        ->set('e_payee', 'Carrefour')
        ->call('saveEdit')
        ->assertHasNoErrors();

    $tx->refresh();
    expect($tx->amount)->toBe(-123456)
        ->and($tx->amount_base)->toBe(-123456)
        ->and($tx->category_id)->toBe($category->id)
        ->and($tx->payee->name)->toBe('Carrefour');
});

it('deletes a transaction', function () {
    $user = loginFamilyUser();
    $account = makeAccount($user);
    $tx = Transaction::create([
        'account_id' => $account->id, 'date' => '2026-06-10', 'amount' => -5000, 'currency' => 'ARS',
    ]);

    Livewire::test('transactions.index')->call('delete', $tx->id);

    expect(Transaction::find($tx->id))->toBeNull();
});

it('deletes both legs when deleting a transfer', function () {
    $user = loginFamilyUser();
    $budget = $user->currentBudget();
    $cash = $budget->accounts()->create(['name' => 'Bank', 'type' => 'checking', 'currency' => 'ARS', 'on_budget' => true]);
    $card = $budget->accounts()->create(['name' => 'Visa', 'type' => 'credit_card', 'currency' => 'ARS', 'on_budget' => true]);
    app(\App\Services\BudgetService::class)->payCreditCard($cash, $card, 30000, '2026-06-20', $user->id);

    expect(Transaction::count())->toBe(2);
    $leg = Transaction::first();

    Livewire::test('transactions.index')->call('delete', $leg->id);

    expect(Transaction::count())->toBe(0);
});

it('does not field-edit a transfer leg', function () {
    $user = loginFamilyUser();
    $budget = $user->currentBudget();
    $cash = $budget->accounts()->create(['name' => 'Bank', 'type' => 'checking', 'currency' => 'ARS', 'on_budget' => true]);
    $card = $budget->accounts()->create(['name' => 'Visa', 'type' => 'credit_card', 'currency' => 'ARS', 'on_budget' => true]);
    app(\App\Services\BudgetService::class)->payCreditCard($cash, $card, 30000, '2026-06-20', $user->id);
    $leg = Transaction::whereNotNull('transfer_pair_id')->first();

    Livewire::test('transactions.index')
        ->call('edit', $leg->id)
        ->set('e_amount', '999')
        ->call('saveEdit');

    expect($leg->fresh()->amount)->toBe((int) $leg->amount); // unchanged
});
