<?php

use App\Models\Category;
use App\Models\CategoryGroup;
use App\Models\Transaction;
use Livewire\Livewire;

it('renders the categories page', function () {
    loginFamilyUser();

    $this->get(route('categories'))
        ->assertOk()
        ->assertSee('Nuevo grupo');
});

it('creates a new group', function () {
    $user = loginFamilyUser();

    Livewire::test('categories.manage')
        ->set('newGroupName', 'Mascotas')
        ->call('addGroup')
        ->assertHasNoErrors();

    expect($user->currentBudget()->categoryGroups()->where('name', 'Mascotas')->exists())->toBeTrue();
});

it('adds a category to a group', function () {
    $user = loginFamilyUser();
    $group = $user->currentBudget()->categoryGroups()->where('is_system', false)->first();

    Livewire::test('categories.manage')
        ->set("newCategoryInputs.{$group->id}", 'Veterinaria')
        ->call('addCategory', $group->id);

    expect($group->categories()->where('name', 'Veterinaria')->exists())->toBeTrue();
});

it('renames a category by editing the field', function () {
    $user = loginFamilyUser();
    $category = $user->currentBudget()->categoryGroups()->first()->categories()->first();

    Livewire::test('categories.manage')
        ->set("categoryNames.{$category->id}", 'Súper y limpieza');

    expect($category->fresh()->name)->toBe('Súper y limpieza');
});

it('deletes a category without transactions', function () {
    $user = loginFamilyUser();
    $category = $user->currentBudget()->categoryGroups()->first()->categories()->first();

    Livewire::test('categories.manage')->call('deleteCategory', $category->id);

    expect(Category::find($category->id))->toBeNull();
});

it('archives (does not delete) a category with transactions', function () {
    $user = loginFamilyUser();
    $budget = $user->currentBudget();
    $account = $budget->accounts()->create(['name' => 'Banco', 'type' => 'checking', 'currency' => 'ARS', 'on_budget' => true]);
    $category = $budget->categoryGroups()->first()->categories()->first();
    Transaction::create(['account_id' => $account->id, 'date' => '2026-06-10', 'amount' => -5000, 'currency' => 'ARS', 'category_id' => $category->id]);

    Livewire::test('categories.manage')->call('deleteCategory', $category->id);

    $category->refresh();
    expect($category->archived_at)->not->toBeNull();
    // No longer appears among the group's active categories
    expect($category->group->categories()->whereKey($category->id)->exists())->toBeFalse();
});

it('does not allow deleting a group with categories', function () {
    $user = loginFamilyUser();
    $group = $user->currentBudget()->categoryGroups()->where('is_system', false)->first();

    Livewire::test('categories.manage')
        ->call('deleteGroup', $group->id)
        ->assertSee('Primero quitá las categorías');

    expect(CategoryGroup::find($group->id))->not->toBeNull();
});

it('does not touch the credit card payment system categories', function () {
    $user = loginFamilyUser();
    $budget = $user->currentBudget();
    $card = $budget->accounts()->create(['name' => 'Visa', 'type' => 'credit_card', 'currency' => 'ARS', 'on_budget' => true]);
    $payment = $card->paymentCategory()->firstOrFail();

    // Attempting to delete the payment category must do nothing
    Livewire::test('categories.manage')->call('deleteCategory', $payment->id);

    expect(Category::find($payment->id))->not->toBeNull();
    // And the system group is not offered for management
    Livewire::test('categories.manage')->assertDontSee('Pagos de tarjeta');
});
