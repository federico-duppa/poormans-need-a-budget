<?php

use App\Models\Category;
use App\Models\CategoryGroup;
use App\Models\Transaction;
use Livewire\Livewire;

it('renderiza la página de categorías', function () {
    loginFamilyUser();

    $this->get(route('categories'))
        ->assertOk()
        ->assertSee('Nuevo grupo');
});

it('crea un grupo nuevo', function () {
    $user = loginFamilyUser();

    Livewire::test('categories.manage')
        ->set('newGroupName', 'Mascotas')
        ->call('addGroup')
        ->assertHasNoErrors();

    expect($user->currentBudget()->categoryGroups()->where('name', 'Mascotas')->exists())->toBeTrue();
});

it('agrega una categoría a un grupo', function () {
    $user = loginFamilyUser();
    $group = $user->currentBudget()->categoryGroups()->where('is_system', false)->first();

    Livewire::test('categories.manage')
        ->set("newCategoryInputs.{$group->id}", 'Veterinaria')
        ->call('addCategory', $group->id);

    expect($group->categories()->where('name', 'Veterinaria')->exists())->toBeTrue();
});

it('renombra una categoría editando el campo', function () {
    $user = loginFamilyUser();
    $category = $user->currentBudget()->categoryGroups()->first()->categories()->first();

    Livewire::test('categories.manage')
        ->set("categoryNames.{$category->id}", 'Súper y limpieza');

    expect($category->fresh()->name)->toBe('Súper y limpieza');
});

it('elimina una categoría sin movimientos', function () {
    $user = loginFamilyUser();
    $category = $user->currentBudget()->categoryGroups()->first()->categories()->first();

    Livewire::test('categories.manage')->call('deleteCategory', $category->id);

    expect(Category::find($category->id))->toBeNull();
});

it('archiva (no elimina) una categoría con movimientos', function () {
    $user = loginFamilyUser();
    $budget = $user->currentBudget();
    $account = $budget->accounts()->create(['name' => 'Banco', 'type' => 'checking', 'currency' => 'ARS', 'on_budget' => true]);
    $category = $budget->categoryGroups()->first()->categories()->first();
    Transaction::create(['account_id' => $account->id, 'date' => '2026-06-10', 'amount' => -5000, 'currency' => 'ARS', 'category_id' => $category->id]);

    Livewire::test('categories.manage')->call('deleteCategory', $category->id);

    $category->refresh();
    expect($category->archived_at)->not->toBeNull();
    // Ya no aparece entre las categorías activas del grupo
    expect($category->group->categories()->whereKey($category->id)->exists())->toBeFalse();
});

it('no permite eliminar un grupo con categorías', function () {
    $user = loginFamilyUser();
    $group = $user->currentBudget()->categoryGroups()->where('is_system', false)->first();

    Livewire::test('categories.manage')
        ->call('deleteGroup', $group->id)
        ->assertSee('Primero quitá las categorías');

    expect(CategoryGroup::find($group->id))->not->toBeNull();
});

it('no toca las categorías-sistema de pago de tarjeta', function () {
    $user = loginFamilyUser();
    $budget = $user->currentBudget();
    $card = $budget->accounts()->create(['name' => 'Visa', 'type' => 'credit_card', 'currency' => 'ARS', 'on_budget' => true]);
    $payment = $card->paymentCategory()->firstOrFail();

    // Intentar eliminar la categoría de pago no debe hacer nada
    Livewire::test('categories.manage')->call('deleteCategory', $payment->id);

    expect(Category::find($payment->id))->not->toBeNull();
    // Y el grupo-sistema no se ofrece para gestión
    Livewire::test('categories.manage')->assertDontSee('Pagos de tarjeta');
});
