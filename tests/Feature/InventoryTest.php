<?php

use App\Models\Product;
use App\Models\ProductImei;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->team = $this->user->currentTeam;
});

test('inventory index page can be rendered', function () {
    $response = $this->actingAs($this->user)
        ->get(route('inventory.index', $this->team->slug));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Inventory/Index')
            ->has('products')
            ->has('filters')
            ->has('summary')
        );
});

test('can create a non-serialized accessory product', function () {
    $response = $this->actingAs($this->user)
        ->post(route('products.store', $this->team->slug), [
            'name' => 'Fast Charger 20W',
            'brand' => 'Anker',
            'category' => 'Chargers',
            'barcode' => '8901234567890',
            'is_serialized' => false,
            'sale_price' => 2500,
            'cost_price' => 1800,
            'stock_quantity' => 15,
            'alert_quantity' => 5,
        ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('products', [
        'name' => 'Fast Charger 20W',
        'brand' => 'Anker',
        'category' => 'Chargers',
        'barcode' => '8901234567890',
        'is_serialized' => false,
        'sale_price' => 2500.00,
        'cost_price' => 1800.00,
        'stock_quantity' => 15,
    ]);
});

test('can create a serialized handset product with initial IMEI', function () {
    $response = $this->actingAs($this->user)
        ->post(route('products.store', $this->team->slug), [
            'name' => 'iPhone 15 Pro',
            'brand' => 'Apple',
            'category' => 'Mobile Handsets',
            'is_serialized' => true,
            'sale_price' => 350000,
            'alert_quantity' => 2,
            'initial_imei_1' => '358901234567890',
            'initial_imei_2' => '358901234567891',
            'initial_color' => 'Natural Titanium',
            'initial_storage' => '256GB',
            'initial_condition' => 'new',
            'initial_pta_status' => 'approved',
            'initial_purchase_cost' => 310000,
            'initial_warranty_days' => 7,
        ]);

    $response->assertRedirect();

    $product = Product::where('name', 'iPhone 15 Pro')->first();
    expect($product)->not->toBeNull();
    expect($product->is_serialized)->toBeTrue();

    $this->assertDatabaseHas('product_imeis', [
        'product_id' => $product->id,
        'imei_1' => '358901234567890',
        'imei_2' => '358901234567891',
        'color' => 'Natural Titanium',
        'storage' => '256GB',
        'condition' => 'new',
        'pta_status' => 'approved',
        'purchase_cost' => 310000.00,
        'status' => 'in_stock',
    ]);
});

test('can update a product', function () {
    $product = Product::factory()->create([
        'name' => 'Old Name',
        'sale_price' => 1000,
    ]);

    $response = $this->actingAs($this->user)
        ->put(route('products.update', [$this->team->slug, $product->id]), [
            'name' => 'Updated Name',
            'brand' => $product->brand,
            'category' => $product->category,
            'barcode' => $product->barcode,
            'is_serialized' => false,
            'sale_price' => 1200,
            'cost_price' => 800,
            'stock_quantity' => 20,
            'alert_quantity' => 5,
        ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('products', [
        'id' => $product->id,
        'name' => 'Updated Name',
        'sale_price' => 1200.00,
    ]);
});

test('can delete a product', function () {
    $product = Product::factory()->create();

    $response = $this->actingAs($this->user)
        ->delete(route('products.destroy', [$this->team->slug, $product->id]));

    $response->assertRedirect();

    $this->assertDatabaseMissing('products', [
        'id' => $product->id,
    ]);
});

test('can add single imei to a handset product', function () {
    $product = Product::factory()->phone()->create();

    $response = $this->actingAs($this->user)
        ->post(route('products.imeis.store', [$this->team->slug, $product->id]), [
            'imei_1' => '351234567890123',
            'imei_2' => '351234567890124',
            'color' => 'Black',
            'storage' => '128GB',
            'condition' => 'used',
            'pta_status' => 'non_pta',
            'purchase_cost' => 150000,
            'warranty_days' => 15,
        ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('product_imeis', [
        'product_id' => $product->id,
        'imei_1' => '351234567890123',
        'imei_2' => '351234567890124',
        'condition' => 'used',
        'pta_status' => 'non_pta',
        'purchase_cost' => 150000.00,
    ]);
});

test('cannot add imei to a non-serialized accessory', function () {
    $product = Product::factory()->accessory()->create();

    $response = $this->actingAs($this->user)
        ->post(route('products.imeis.store', [$this->team->slug, $product->id]), [
            'imei_1' => '351234567890999',
            'condition' => 'new',
            'pta_status' => 'approved',
            'purchase_cost' => 500,
        ]);

    $response->assertSessionHasErrors('product');
});

test('can bulk add imeis to a handset product', function () {
    $product = Product::factory()->phone()->create();

    $response = $this->actingAs($this->user)
        ->post(route('products.imeis.bulk-store', [$this->team->slug, $product->id]), [
            'color' => 'Blue',
            'storage' => '256GB',
            'condition' => 'new',
            'pta_status' => 'approved',
            'purchase_cost' => 200000,
            'warranty_days' => 7,
            'imeis' => [
                ['imei_1' => '359990000000001', 'imei_2' => '359990000000002'],
                ['imei_1' => '359990000000003', 'imei_2' => null],
                ['imei_1' => '359990000000004', 'imei_2' => '359990000000005'],
            ],
        ]);

    $response->assertRedirect();

    expect(ProductImei::where('product_id', $product->id)->count())->toBe(3);

    $this->assertDatabaseHas('product_imeis', [
        'product_id' => $product->id,
        'imei_1' => '359990000000001',
        'color' => 'Blue',
        'storage' => '256GB',
    ]);
});

test('validates duplicate imeis in bulk payload or existing database', function () {
    $product = Product::factory()->phone()->create();
    ProductImei::factory()->create(['product_id' => $product->id, 'imei_1' => '357777777777777']);

    // Attempting to bulk add an IMEI that already exists in DB
    $response = $this->actingAs($this->user)
        ->post(route('products.imeis.bulk-store', [$this->team->slug, $product->id]), [
            'condition' => 'new',
            'pta_status' => 'approved',
            'purchase_cost' => 100000,
            'imeis' => [
                ['imei_1' => '357777777777777'],
            ],
        ]);

    $response->assertSessionHasErrors('imeis.0.imei_1');
});

test('can update an imei record', function () {
    $product = Product::factory()->phone()->create();
    $imei = ProductImei::factory()->create([
        'product_id' => $product->id,
        'imei_1' => '356666666666666',
        'status' => 'in_stock',
    ]);

    $response = $this->actingAs($this->user)
        ->put(route('imeis.update', [$this->team->slug, $imei->id]), [
            'imei_1' => '356666666666666',
            'imei_2' => '356666666666667',
            'color' => 'Silver',
            'storage' => '512GB',
            'condition' => 'used',
            'pta_status' => 'cpid',
            'purchase_cost' => 180000,
            'warranty_days' => 30,
            'status' => 'sold',
        ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('product_imeis', [
        'id' => $imei->id,
        'color' => 'Silver',
        'storage' => '512GB',
        'condition' => 'used',
        'pta_status' => 'cpid',
        'status' => 'sold',
    ]);
});

test('can delete an imei record', function () {
    $product = Product::factory()->phone()->create();
    $imei = ProductImei::factory()->create(['product_id' => $product->id]);

    $response = $this->actingAs($this->user)
        ->delete(route('imeis.destroy', [$this->team->slug, $imei->id]));

    $response->assertRedirect();

    $this->assertDatabaseMissing('product_imeis', [
        'id' => $imei->id,
    ]);
});
