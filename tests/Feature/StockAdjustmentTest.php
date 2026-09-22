<?php

use App\Models\Product;
use App\Models\StockAdjustment;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->team = $this->user->currentTeam;
});

test('stock adjustments page can be rendered', function () {
    $response = $this->actingAs($this->user)
        ->get(route('stock-adjustments.index', $this->team->slug));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('StockAdjustments/Index')
            ->has('adjustments')
            ->has('products')
            ->has('summary')
            ->has('filters')
        );
});

test('can create a stock subtraction adjustment for non-serialized accessory', function () {
    $product = Product::factory()->accessory()->create([
        'stock_quantity' => 20,
    ]);

    $response = $this->actingAs($this->user)
        ->post(route('stock-adjustments.store', $this->team->slug), [
            'product_id' => $product->id,
            'type' => 'subtraction',
            'quantity' => 3,
            'reason' => 'damaged',
            'notes' => 'Damaged charger in store box',
        ]);

    $response->assertRedirect();

    expect($product->refresh()->stock_quantity)->toBe(17);

    $this->assertDatabaseHas('stock_adjustments', [
        'product_id' => $product->id,
        'type' => 'subtraction',
        'quantity' => 3,
        'reason' => 'damaged',
        'notes' => 'Damaged charger in store box',
    ]);
});

test('can create a stock addition adjustment for non-serialized accessory', function () {
    $product = Product::factory()->accessory()->create([
        'stock_quantity' => 10,
    ]);

    $response = $this->actingAs($this->user)
        ->post(route('stock-adjustments.store', $this->team->slug), [
            'product_id' => $product->id,
            'type' => 'addition',
            'quantity' => 5,
            'reason' => 'found',
            'notes' => 'Found extra stock in rear warehouse',
        ]);

    $response->assertRedirect();

    expect($product->refresh()->stock_quantity)->toBe(15);

    $this->assertDatabaseHas('stock_adjustments', [
        'product_id' => $product->id,
        'type' => 'addition',
        'quantity' => 5,
        'reason' => 'found',
    ]);
});

test('can filter stock adjustments by search and reason', function () {
    $product = Product::factory()->accessory()->create(['name' => 'Anker Cable 2M']);
    StockAdjustment::create([
        'product_id' => $product->id,
        'user_id' => $this->user->id,
        'type' => 'subtraction',
        'quantity' => 2,
        'reason' => 'damaged',
        'notes' => 'Broken pin',
    ]);

    $response = $this->actingAs($this->user)
        ->get(route('stock-adjustments.index', [
            'current_team' => $this->team->slug,
            'reason' => 'damaged',
            'search' => 'Anker',
        ]));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('adjustments.data.0.reason', 'damaged')
            ->has('adjustments.data', 1));
});

test('can delete a stock adjustment and revert stock changes', function () {
    $product = Product::factory()->accessory()->create([
        'stock_quantity' => 15,
    ]);

    $adjustment = StockAdjustment::create([
        'product_id' => $product->id,
        'user_id' => $this->user->id,
        'type' => 'subtraction',
        'quantity' => 5,
        'reason' => 'lost',
    ]);

    // Initial deduction happened before deletion test
    $product->decrement('stock_quantity', 5);
    expect($product->refresh()->stock_quantity)->toBe(10);

    $response = $this->actingAs($this->user)
        ->delete(route('stock-adjustments.destroy', [$this->team->slug, $adjustment->id]));

    $response->assertRedirect();
    expect($product->refresh()->stock_quantity)->toBe(15);
    $this->assertDatabaseMissing('stock_adjustments', ['id' => $adjustment->id]);
});
