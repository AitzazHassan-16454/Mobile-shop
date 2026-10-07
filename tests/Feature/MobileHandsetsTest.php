<?php

use App\Models\Product;
use App\Models\ProductImei;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->team = $this->user->currentTeam;
});

test('the merged page exposes the handset stock tab alongside sales and buying', function () {
    $product = Product::factory()->phone()->create();
    ProductImei::factory()->create([
        'product_id' => $product->id,
        'imei_1' => '356000000000001',
        'status' => 'in_stock',
        'condition' => 'new',
        'purchase_cost' => 50000,
    ]);

    $this->actingAs($this->user)
        ->get(route('mobile-sales.index', $this->team->slug))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('MobileSales/Index')
            ->has('stockImeis')
            ->has('stockBrands')
            ->has('stockSummary')
            ->has('stockFilters')
            ->where('stockSummary.in_stock_count', 1)
            ->where('stockSummary.new_stock_count', 1)
            ->where('stockSummary.used_stock_count', 0)
            ->where('stockSummary.total_cost_value', 50000)
            ->where('stockFilters.status', 'in_stock')
        );
});

test('the handset stock tab only lists in stock phones by default', function () {
    $product = Product::factory()->phone()->create();
    ProductImei::factory()->create([
        'product_id' => $product->id,
        'imei_1' => '356000000000010',
        'status' => 'in_stock',
    ]);
    ProductImei::factory()->create([
        'product_id' => $product->id,
        'imei_1' => '356000000000011',
        'status' => 'sold',
    ]);

    $this->actingAs($this->user)
        ->get(route('mobile-sales.index', $this->team->slug))
        ->assertInertia(fn ($page) => $page
            ->has('stockImeis.data', 1)
            ->where('stockImeis.data.0.imei_1', '356000000000010')
        );
});

test('the handset stock tab filters by condition and pta status', function () {
    $product = Product::factory()->phone()->create();
    ProductImei::factory()->create([
        'product_id' => $product->id,
        'imei_1' => '356000000000020',
        'status' => 'in_stock',
        'condition' => 'used',
        'pta_status' => 'non_pta',
    ]);
    ProductImei::factory()->create([
        'product_id' => $product->id,
        'imei_1' => '356000000000021',
        'status' => 'in_stock',
        'condition' => 'new',
        'pta_status' => 'approved',
    ]);

    $this->actingAs($this->user)
        ->get(route('mobile-sales.index', [
            $this->team->slug,
            'imei_condition' => 'used',
            'imei_pta_status' => 'non_pta',
        ]))
        ->assertInertia(fn ($page) => $page
            ->has('stockImeis.data', 1)
            ->where('stockImeis.data.0.imei_1', '356000000000020')
            ->where('stockFilters.condition', 'used')
            ->where('stockFilters.pta_status', 'non_pta')
        );
});

test('the handset stock tab search does not clash with the sell tab search', function () {
    $product = Product::factory()->phone()->create(['name' => 'Nova X1']);

    ProductImei::factory()->create([
        'product_id' => $product->id,
        'imei_1' => '356000000000030',
        'status' => 'sold',
    ]);

    $this->actingAs($this->user)
        ->get(route('mobile-sales.index', [
            $this->team->slug,
            'search' => 'Nova',
            'imei_status' => 'sold',
        ]))
        ->assertInertia(fn ($page) => $page
            ->has('stockImeis.data', 1)
            ->where('filters.search', 'Nova')
            ->where('stockFilters.status', 'sold')
        );
});

test('the legacy mobile phones page redirects to the merged page', function () {
    $this->actingAs($this->user)
        ->get('/'.$this->team->slug.'/mobile-phones')
        ->assertRedirect(route('mobile-sales.index', $this->team->slug));
});

test('a phone can be added from the merged page', function () {
    $this->actingAs($this->user)
        ->post(route('mobile-phones.store', $this->team->slug), [
            'brand' => 'Samsung',
            'model_name' => 'Galaxy A55',
            'color' => 'Navy',
            'storage' => '128GB',
            'condition' => 'new',
            'pta_status' => 'approved',
            'imei_1' => '356000000000040',
            'imei_2' => null,
            'purchase_cost' => 40000,
            'sale_price' => 55000,
            'warranty_days' => 365,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('product_imeis', [
        'imei_1' => '356000000000040',
        'condition' => 'new',
        'pta_status' => 'approved',
        'purchase_cost' => 40000,
        'status' => 'in_stock',
    ]);

    $this->assertDatabaseHas('products', [
        'name' => 'Galaxy A55',
        'brand' => 'Samsung',
        'is_serialized' => true,
    ]);
});

test('a handset can be deleted from the merged page', function () {
    $product = Product::factory()->phone()->create();
    $imei = ProductImei::factory()->create([
        'product_id' => $product->id,
        'imei_1' => '356000000000050',
    ]);

    $this->actingAs($this->user)
        ->delete(route('imeis.destroy', [$this->team->slug, $imei->id]))
        ->assertRedirect();

    $this->assertDatabaseMissing('product_imeis', ['id' => $imei->id]);
});

test('handsets with in stock imeis appear on sell tab even if stock_quantity is 0', function () {
    $product = Product::factory()->phone()->create([
        'name' => 'Pixel 8 Pro',
        'stock_quantity' => 0,
    ]);
    ProductImei::factory()->create([
        'product_id' => $product->id,
        'imei_1' => '356000000000099',
        'status' => 'in_stock',
    ]);

    $this->actingAs($this->user)
        ->get(route('mobile-sales.index', $this->team->slug))
        ->assertInertia(fn ($page) => $page
            ->has('handsets', 1)
            ->where('handsets.0.name', 'Pixel 8 Pro')
            ->where('summary.ready_count', 1)
        );
});

