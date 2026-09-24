<?php

use App\Models\Product;
use App\Models\ProductImei;
use App\Models\StockTransfer;
use App\Models\Team;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->team = $this->user->currentTeam;
    $this->destination = Team::factory()->create();
});

test('stock transfers page can be rendered', function () {
    $response = $this->actingAs($this->user)
        ->get(route('stock-transfers.index', $this->team->slug));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('StockTransfers/Index')
            ->has('transfers')
            ->has('teams')
            ->has('currentTeam')
            ->has('catalog')
            ->has('summary')
            ->has('filters'));
});

test('can create a stock transfer with accessories', function () {
    $product = Product::factory()->accessory()->create([
        'stock_quantity' => 10,
    ]);

    $response = $this->actingAs($this->user)
        ->post(route('stock-transfers.store', $this->team->slug), [
            'to_team_id' => $this->destination->id,
            'note' => 'Monthly stock balancing',
            'accessories' => [
                ['product_id' => $product->id, 'quantity' => 3],
            ],
        ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('stock_transfers', [
        'from_team_id' => $this->team->id,
        'to_team_id' => $this->destination->id,
        'status' => 'in_transit',
        'sent_by' => $this->user->id,
    ]);

    $transfer = StockTransfer::latest()->first();
    expect($transfer->reference_no)->toStartWith('TRF-');
    expect($transfer->items()->count())->toBe(1);
    $this->assertDatabaseHas('stock_transfer_items', [
        'stock_transfer_id' => $transfer->id,
        'product_id' => $product->id,
        'quantity' => 3,
        'product_imei_id' => null,
    ]);
});

test('cannot create a stock transfer with an empty payload', function () {
    $response = $this->actingAs($this->user)
        ->post(route('stock-transfers.store', $this->team->slug), [
            'to_team_id' => $this->destination->id,
        ]);

    $response->assertSessionHasErrors('items');
    expect(StockTransfer::count())->toBe(0);
});

test('can create a stock transfer with an in-stock IMEI', function () {
    $imei = ProductImei::factory()->create();

    $response = $this->actingAs($this->user)
        ->post(route('stock-transfers.store', $this->team->slug), [
            'to_team_id' => $this->destination->id,
            'imei_ids' => [$imei->id],
        ]);

    $response->assertRedirect();

    $transfer = StockTransfer::latest()->first();
    $this->assertDatabaseHas('stock_transfer_items', [
        'stock_transfer_id' => $transfer->id,
        'product_imei_id' => $imei->id,
        'quantity' => 1,
    ]);
});

test('can mark an in-transit transfer as received', function () {
    $transfer = StockTransfer::create([
        'reference_no' => 'TRF-000001',
        'from_team_id' => $this->team->id,
        'to_team_id' => $this->destination->id,
        'status' => 'in_transit',
        'sent_by' => $this->user->id,
    ]);

    $response = $this->actingAs($this->user)
        ->post(route('stock-transfers.receive', [$this->team->slug, $transfer->id]));

    $response->assertRedirect();

    $transfer->refresh();
    expect($transfer->status)->toBe('received');
    expect($transfer->received_at)->not->toBeNull();
});

test('cannot mark a received transfer as received again', function () {
    $transfer = StockTransfer::create([
        'reference_no' => 'TRF-000002',
        'from_team_id' => $this->team->id,
        'to_team_id' => $this->destination->id,
        'status' => 'received',
        'received_at' => now(),
        'sent_by' => $this->user->id,
    ]);

    $this->actingAs($this->user)
        ->post(route('stock-transfers.receive', [$this->team->slug, $transfer->id]))
        ->assertRedirect();

    expect($transfer->refresh()->status)->toBe('received');
});

test('can delete an in-transit transfer', function () {
    $transfer = StockTransfer::create([
        'reference_no' => 'TRF-000003',
        'from_team_id' => $this->team->id,
        'to_team_id' => $this->destination->id,
        'status' => 'in_transit',
        'sent_by' => $this->user->id,
    ]);

    $this->actingAs($this->user)
        ->delete(route('stock-transfers.destroy', [$this->team->slug, $transfer->id]))
        ->assertRedirect();

    $this->assertDatabaseMissing('stock_transfers', ['id' => $transfer->id]);
});

test('cannot delete a received transfer', function () {
    $transfer = StockTransfer::create([
        'reference_no' => 'TRF-000004',
        'from_team_id' => $this->team->id,
        'to_team_id' => $this->destination->id,
        'status' => 'received',
        'sent_by' => $this->user->id,
    ]);

    $this->actingAs($this->user)
        ->delete(route('stock-transfers.destroy', [$this->team->slug, $transfer->id]))
        ->assertRedirect();

    $this->assertDatabaseHas('stock_transfers', ['id' => $transfer->id]);
});
