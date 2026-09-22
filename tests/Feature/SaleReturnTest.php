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

test('sale returns index page can be rendered', function () {
    $response = $this->actingAs($this->user)
        ->get(route('sales.returns.index', $this->team->slug));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Sales/Returns')
            ->has('returns')
            ->has('products')
            ->has('customers')
            ->has('stats'));
});

test('can process a sale return and restore product stock', function () {
    $product = Product::factory()->create([
        'is_serialized' => false,
        'stock_quantity' => 2,
        'sale_price' => 1500,
    ]);

    $response = $this->actingAs($this->user)
        ->post(route('sales.returns.store', $this->team->slug), [
            'refund_amount' => 1500,
            'refund_payment_method' => 'cash',
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 1,
                    'unit_price' => 1500,
                ],
            ],
        ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('sale_returns', [
        'refund_amount' => 1500,
        'refund_payment_method' => 'cash',
    ]);
    expect($product->refresh()->stock_quantity)->toBe(3);
});

test('can process a sale return for IMEI handset and restore IMEI status to in stock', function () {
    $product = Product::factory()->create(['is_serialized' => true]);
    $imei = ProductImei::create([
        'product_id' => $product->id,
        'imei_1' => '990000862471854',
        'status' => ImeiStatus::Sold,
        'purchase_cost' => 20000,
        'sold_at' => now(),
    ]);

    $response = $this->actingAs($this->user)
        ->post(route('sales.returns.store', $this->team->slug), [
            'refund_amount' => 25000,
            'refund_payment_method' => 'cash',
            'items' => [
                [
                    'product_id' => $product->id,
                    'product_imei_id' => $imei->id,
                    'quantity' => 1,
                    'unit_price' => 25000,
                ],
            ],
        ]);

    $response->assertRedirect();
    expect($imei->refresh()->status->value)->toBe('in_stock');
});

test('can process a sale return with khata balance deduction for customer', function () {
    $customer = Customer::factory()->create(['current_balance' => 5000]);
    $product = Product::factory()->create(['is_serialized' => false, 'stock_quantity' => 0]);

    $response = $this->actingAs($this->user)
        ->post(route('sales.returns.store', $this->team->slug), [
            'customer_id' => $customer->id,
            'refund_amount' => 2000,
            'refund_payment_method' => 'khata_deduction',
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 1,
                    'unit_price' => 2000,
                ],
            ],
        ]);

    $response->assertRedirect();
    expect((float) $customer->refresh()->current_balance)->toBe(3000.00);
});
