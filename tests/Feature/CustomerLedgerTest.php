<?php

use App\Models\Customer;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->team = $this->user->currentTeam;
});

test('customers page can be rendered', function () {
    $response = $this->actingAs($this->user)
        ->get(route('customers.index', $this->team->slug));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Customers/Index')
            ->has('customers')
            ->has('summary')
        );
});

test('can add new customer with opening balance', function () {
    $response = $this->actingAs($this->user)
        ->post(route('customers.store', $this->team->slug), [
            'name' => 'Kashif Traders',
            'phone' => '03007654321',
            'address' => 'Hall Road, Lahore',
            'initial_balance' => 25000,
        ]);

    $response->assertRedirect();

    $customer = Customer::where('phone', '03007654321')->first();
    expect($customer)->not->toBeNull();
    expect((float) $customer->current_balance)->toBe(25000.00);

    $this->assertDatabaseHas('customer_ledger', [
        'customer_id' => $customer->id,
        'type' => 'adjustment',
        'amount' => 25000.00,
        'balance_after' => 25000.00,
    ]);
});

test('can record wasooli payment received from customer', function () {
    $customer = Customer::factory()->create(['current_balance' => 10000]);

    $response = $this->actingAs($this->user)
        ->post(route('customers.payments.store', [$this->team->slug, $customer->id]), [
            'amount' => 4000,
            'payment_method' => 'cash',
            'notes' => 'Partial Wasooli Payment',
        ]);

    $response->assertRedirect();

    $customer->refresh();
    expect((float) $customer->current_balance)->toBe(6000.00);

    $this->assertDatabaseHas('customer_ledger', [
        'customer_id' => $customer->id,
        'type' => 'payment',
        'amount' => 4000.00,
        'balance_after' => 6000.00,
    ]);
});

test('can update customer details', function () {
    $customer = Customer::factory()->create(['name' => 'Old Name']);

    $response = $this->actingAs($this->user)
        ->put(route('customers.update', [$this->team->slug, $customer->id]), [
            'name' => 'Updated Name',
            'phone' => $customer->phone,
            'address' => 'New Address',
        ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('customers', [
        'id' => $customer->id,
        'name' => 'Updated Name',
        'address' => 'New Address',
    ]);
});

test('can delete customer', function () {
    $customer = Customer::factory()->create();

    $response = $this->actingAs($this->user)
        ->delete(route('customers.destroy', [$this->team->slug, $customer->id]));

    $response->assertRedirect();

    $this->assertDatabaseMissing('customers', [
        'id' => $customer->id,
    ]);
});
