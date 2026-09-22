<?php

use App\Enums\ImeiStatus;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductImei;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->team = $this->user->currentTeam;
});

test('sales index page can be rendered', function () {
    $response = $this->actingAs($this->user)
        ->get(route('sales.index', $this->team->slug));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Sales/Index')
            ->has('sales')
            ->has('products')
            ->has('customers')
            ->has('stats'));
});

test('can create a direct sale for accessories', function () {
    $product = Product::factory()->create([
        'is_serialized' => false,
        'stock_quantity' => 10,
        'cost_price' => 500,
        'sale_price' => 1000,
    ]);

    $response = $this->actingAs($this->user)
        ->post(route('sales.store', $this->team->slug), [
            'payment_method' => 'cash',
            'paid_amount' => 2000,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 2,
                    'unit_price' => 1000,
                ],
            ],
        ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('sales', [
        'total_amount' => 2000,
        'net_amount' => 2000,
        'payment_method' => 'cash',
    ]);
    expect($product->refresh()->stock_quantity)->toBe(8);
});

test('can create a direct sale for serialized handset', function () {
    $product = Product::factory()->create([
        'is_serialized' => true,
        'stock_quantity' => 1,
    ]);

    $imei = ProductImei::create([
        'product_id' => $product->id,
        'imei_1' => '869403029104958',
        'status' => ImeiStatus::InStock,
        'purchase_cost' => 40000,
    ]);

    $response = $this->actingAs($this->user)
        ->post(route('sales.store', $this->team->slug), [
            'payment_method' => 'cash',
            'paid_amount' => 50000,
            'items' => [
                [
                    'product_id' => $product->id,
                    'product_imei_id' => $imei->id,
                    'quantity' => 1,
                    'unit_price' => 50000,
                ],
            ],
        ]);

    $response->assertRedirect();
    expect($imei->refresh()->status->value)->toBe('sold');
});

test('can create an udhaar sale and update customer khata balance', function () {
    $customer = Customer::factory()->create(['current_balance' => 0]);
    $product = Product::factory()->create([
        'is_serialized' => false,
        'stock_quantity' => 5,
        'sale_price' => 3000,
    ]);

    $response = $this->actingAs($this->user)
        ->post(route('sales.store', $this->team->slug), [
            'customer_id' => $customer->id,
            'payment_method' => 'udhaar',
            'paid_amount' => 1000,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 1,
                    'unit_price' => 3000,
                ],
            ],
        ]);

    $response->assertRedirect();
    expect((float) $customer->refresh()->current_balance)->toBe(2000.00);
    $this->assertDatabaseHas('customer_ledger', [
        'customer_id' => $customer->id,
        'type' => 'sale',
        'amount' => 2000,
    ]);
});
