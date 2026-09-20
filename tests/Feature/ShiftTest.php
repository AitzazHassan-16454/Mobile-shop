<?php

use App\Enums\PaymentMethod;
use App\Models\RegisterShift;
use App\Models\Sale;
use App\Models\ShopExpense;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->team = $this->user->currentTeam;
});

test('shifts page can be rendered', function () {
    $response = $this->actingAs($this->user)
        ->get(route('shifts.index', $this->team->slug));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Shifts/Index')
            ->has('activeShift')
            ->has('pastShifts')
        );
});

test('can open register shift with opening float', function () {
    $response = $this->actingAs($this->user)
        ->post(route('shifts.open', $this->team->slug), [
            'opening_float' => 5000,
        ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('register_shifts', [
        'user_id' => $this->user->id,
        'opening_float' => 5000.00,
        'status' => 'open',
    ]);
});

test('cannot open duplicate active shift when one is already open', function () {
    RegisterShift::create([
        'user_id' => $this->user->id,
        'opened_at' => now(),
        'opening_float' => 3000,
        'status' => 'open',
    ]);

    $response = $this->actingAs($this->user)
        ->post(route('shifts.open', $this->team->slug), [
            'opening_float' => 10000,
        ]);

    $response->assertRedirect();
    $response->assertSessionHas('error');
});

test('can record shop expense during active shift', function () {
    $shift = RegisterShift::create([
        'user_id' => $this->user->id,
        'opened_at' => now(),
        'opening_float' => 5000,
        'status' => 'open',
    ]);

    $response = $this->actingAs($this->user)
        ->post(route('shifts.expense.store', $this->team->slug), [
            'category' => 'Chaye / Khana',
            'amount' => 450,
            'notes' => 'Tea for staff',
        ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('shop_expenses', [
        'register_shift_id' => $shift->id,
        'user_id' => $this->user->id,
        'category' => 'Chaye / Khana',
        'amount' => 450.00,
        'notes' => 'Tea for staff',
    ]);

    $shift->refresh();
    expect((float) $shift->expenses_amount)->toBe(450.00);
});

test('can close active shift and compute reconciliation discrepancy', function () {
    $shift = RegisterShift::create([
        'user_id' => $this->user->id,
        'opened_at' => now()->subMinutes(30),
        'opening_float' => 5000,
        'status' => 'open',
    ]);

    Sale::create([
        'invoice_no' => 'INV-SHIFT-01',
        'total_amount' => 12000,
        'discount_amount' => 0,
        'net_amount' => 12000,
        'paid_amount' => 12000,
        'payment_method' => PaymentMethod::Cash,
        'cashier_id' => $this->user->id,
        'created_at' => now()->subMinutes(15),
    ]);

    ShopExpense::create([
        'register_shift_id' => $shift->id,
        'user_id' => $this->user->id,
        'category' => 'Chaye / Khana',
        'amount' => 500,
        'created_at' => now()->subMinutes(10),
    ]);

    // Expected cash: 5000 (float) + 12000 (cash sales) - 500 (expenses) = 16500.
    // Cashier counts 16000 cash in drawer (500 shortage)
    $response = $this->actingAs($this->user)
        ->post(route('shifts.close', $this->team->slug), [
            'actual_cash' => 16000,
            'notes' => '500 Rs short in drawer count',
        ]);

    $response->assertRedirect();

    $shift->refresh();
    expect($shift->status)->toBe('closed');
    expect((float) $shift->expected_cash)->toBe(16500.00);
    expect((float) $shift->actual_cash)->toBe(16000.00);
    expect((float) $shift->discrepancy)->toBe(-500.00);
    expect($shift->closed_at)->not->toBeNull();
});
