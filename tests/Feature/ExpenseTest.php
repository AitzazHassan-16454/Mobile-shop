<?php

use App\Models\RegisterShift;
use App\Models\ShopExpense;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->team = $this->user->currentTeam;
});

test('expenses page can be rendered', function () {
    ShopExpense::create([
        'user_id' => $this->user->id,
        'category' => 'Utilities (Electricity/Water/Net)',
        'amount' => 1500.00,
        'notes' => 'Monthly internet bill',
    ]);

    $response = $this->actingAs($this->user)
        ->get(route('expenses.index', $this->team->slug));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Expenses/Index')
            ->has('expenses')
            ->has('categories')
            ->has('filters')
            ->has('stats'));
});

test('can create a new shop expense', function () {
    $response = $this->actingAs($this->user)
        ->post(route('expenses.store', $this->team->slug), [
            'category' => 'Tea & Refreshment',
            'amount' => 450.00,
            'notes' => 'Tea for shop guests',
        ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('shop_expenses', [
        'user_id' => $this->user->id,
        'category' => 'Tea & Refreshment',
        'amount' => 450.00,
        'notes' => 'Tea for shop guests',
    ]);
});

test('automatically links expense to active open register shift', function () {
    $shift = RegisterShift::create([
        'user_id' => $this->user->id,
        'opening_cash' => 5000.00,
        'status' => 'open',
        'opened_at' => now(),
    ]);

    $response = $this->actingAs($this->user)
        ->post(route('expenses.store', $this->team->slug), [
            'category' => 'Shop Rent',
            'amount' => 12000.00,
            'notes' => 'Advance shop rent portion',
        ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('shop_expenses', [
        'user_id' => $this->user->id,
        'register_shift_id' => $shift->id,
        'category' => 'Shop Rent',
        'amount' => 12000.00,
    ]);
});

test('validates required fields when recording an expense', function () {
    $response = $this->actingAs($this->user)
        ->post(route('expenses.store', $this->team->slug), [
            'category' => '',
            'amount' => -50,
        ]);

    $response->assertSessionHasErrors(['category', 'amount']);
});

test('can update an existing expense', function () {
    $expense = ShopExpense::create([
        'user_id' => $this->user->id,
        'category' => 'Miscellaneous',
        'amount' => 200.00,
        'notes' => 'Cleaning items',
    ]);

    $response = $this->actingAs($this->user)
        ->put(route('expenses.update', [$this->team->slug, $expense->id]), [
            'category' => 'Cleaning & Maintenance',
            'amount' => 350.00,
            'notes' => 'Cleaning items + phenyl',
        ]);

    $response->assertRedirect();
    expect($expense->refresh()->category)->toBe('Cleaning & Maintenance');
    expect((float) $expense->amount)->toBe(350.00);
    expect($expense->notes)->toBe('Cleaning items + phenyl');
});

test('can delete an expense', function () {
    $expense = ShopExpense::create([
        'user_id' => $this->user->id,
        'category' => 'Temporary Expense',
        'amount' => 100.00,
    ]);

    $response = $this->actingAs($this->user)
        ->delete(route('expenses.destroy', [$this->team->slug, $expense->id]));

    $response->assertRedirect();
    $this->assertDatabaseMissing('shop_expenses', ['id' => $expense->id]);
});
