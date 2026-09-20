<?php

use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductImei;
use App\Models\Sale;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->team = $this->user->currentTeam;
});

test('pos terminal page can be rendered', function () {
    $response = $this->actingAs($this->user)
        ->get(route('pos.index', $this->team->slug));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Pos/Terminal')
            ->has('products')
            ->has('customers')
            ->has('shopInfo')
        );
});

test('can quick register customer from pos', function () {
    $response = $this->actingAs($this->user)
        ->post(route('pos.customers.store', $this->team->slug), [
            'name' => 'Muhammad Ali',
            'phone' => '03009876543',
            'address' => 'Lahore, Pakistan',
        ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('customers', [
        'name' => 'Muhammad Ali',
        'phone' => '03009876543',
        'current_balance' => 0.00,
    ]);
});

test('can complete cash sale for handset and accessory', function () {
    $phoneProduct = Product::factory()->phone()->create(['sale_price' => 200000]);
    $imei = ProductImei::factory()->create([
        'product_id' => $phoneProduct->id,
        'imei_1' => '351111222233334',
        'purchase_cost' => 170000,
        'status' => 'in_stock',
    ]);

    $accessoryProduct = Product::factory()->accessory()->create([
        'sale_price' => 2500,
        'cost_price' => 1500,
        'stock_quantity' => 10,
    ]);

    $response = $this->actingAs($this->user)
        ->post(route('pos.sales.store', $this->team->slug), [
            'payment_method' => 'cash',
            'discount_amount' => 500,
            'paid_amount' => 202000,
            'items' => [
                [
                    'product_id' => $phoneProduct->id,
                    'product_imei_id' => $imei->id,
                    'quantity' => 1,
                    'unit_price' => 200000,
                ],
                [
                    'product_id' => $accessoryProduct->id,
                    'product_imei_id' => null,
                    'quantity' => 1,
                    'unit_price' => 2500,
                ],
            ],
        ]);

    $response->assertRedirect();

    // Verify Sale record created
    $sale = Sale::latest()->first();
    expect($sale)->not->toBeNull();
    expect((float) $sale->total_amount)->toBe(202500.00);
    expect((float) $sale->discount_amount)->toBe(500.00);
    expect((float) $sale->net_amount)->toBe(202000.00);
    expect((float) $sale->change_amount)->toBe(0.00);

    // Verify IMEI status updated to sold
    $imei->refresh();
    expect($imei->status->value)->toBe('sold');
    expect($imei->sold_at)->not->toBeNull();

    // Verify accessory stock decremented
    $accessoryProduct->refresh();
    expect($accessoryProduct->stock_quantity)->toBe(9);
});

test('cannot sell an imei that is already sold', function () {
    $phoneProduct = Product::factory()->phone()->create();
    $imei = ProductImei::factory()->create([
        'product_id' => $phoneProduct->id,
        'status' => 'sold',
    ]);

    $response = $this->actingAs($this->user)
        ->post(route('pos.sales.store', $this->team->slug), [
            'payment_method' => 'cash',
            'paid_amount' => 100000,
            'items' => [
                [
                    'product_id' => $phoneProduct->id,
                    'product_imei_id' => $imei->id,
                    'quantity' => 1,
                    'unit_price' => 100000,
                ],
            ],
        ]);

    $response->assertSessionHasErrors('items');
});

test('cannot complete udhaar sale without selecting customer', function () {
    $accessory = Product::factory()->accessory()->create(['stock_quantity' => 5, 'sale_price' => 1000]);

    $response = $this->actingAs($this->user)
        ->post(route('pos.sales.store', $this->team->slug), [
            'customer_id' => null,
            'payment_method' => 'udhaar',
            'paid_amount' => 0,
            'items' => [
                [
                    'product_id' => $accessory->id,
                    'quantity' => 1,
                    'unit_price' => 1000,
                ],
            ],
        ]);

    $response->assertSessionHasErrors('customer_id');
});

test('completing udhaar sale debits customer ledger and balance', function () {
    $customer = Customer::factory()->create(['current_balance' => 5000]);
    $accessory = Product::factory()->accessory()->create(['stock_quantity' => 10, 'sale_price' => 10000]);

    $response = $this->actingAs($this->user)
        ->post(route('pos.sales.store', $this->team->slug), [
            'customer_id' => $customer->id,
            'payment_method' => 'udhaar',
            'paid_amount' => 2000, // partial payment
            'items' => [
                [
                    'product_id' => $accessory->id,
                    'quantity' => 1,
                    'unit_price' => 10000,
                ],
            ],
        ]);

    $response->assertRedirect();

    $customer->refresh();
    // Previous balance 5000 + unpaid 8000 = 13000
    expect((float) $customer->current_balance)->toBe(13000.00);

    $this->assertDatabaseHas('customer_ledger', [
        'customer_id' => $customer->id,
        'type' => 'sale',
        'amount' => 8000.00,
        'balance_after' => 13000.00,
    ]);
});
