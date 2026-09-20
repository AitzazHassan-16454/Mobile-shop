<?php

use App\Models\Product;
use App\Models\RepairTicket;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->team = $this->user->currentTeam;
});

test('repairing lab page can be rendered', function () {
    $response = $this->actingAs($this->user)
        ->get(route('repairs.index', $this->team->slug));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Repairs/Index')
            ->has('tickets')
            ->has('spareParts')
            ->has('shopInfo')
            ->has('summary')
        );
});

test('can create a new repair ticket and token', function () {
    $response = $this->actingAs($this->user)
        ->post(route('repairs.store', $this->team->slug), [
            'customer_name' => 'Usman Tariq',
            'customer_phone' => '03211234567',
            'device_model' => 'Samsung Galaxy S22',
            'imei' => '359876543210987',
            'pattern_or_pin' => 'L-Pattern',
            'problem_description' => 'Display glass broken & charging port issue',
            'condition_notes' => 'Body scratched, screen cracked',
            'estimated_cost' => 15000,
            'advance_paid' => 3000,
        ]);

    $response->assertRedirect();

    $ticket = RepairTicket::latest()->first();
    expect($ticket)->not->toBeNull();
    expect($ticket->ticket_no)->toBe('REP-00001');
    expect($ticket->customer_name)->toBe('Usman Tariq');
    expect((float) $ticket->estimated_cost)->toBe(15000.00);
    expect((float) $ticket->advance_paid)->toBe(3000.00);
    expect($ticket->status->value)->toBe('received');
});

test('can update repair ticket status', function () {
    $ticket = RepairTicket::create([
        'ticket_no' => 'REP-00099',
        'customer_name' => 'Bilal',
        'customer_phone' => '03001112223',
        'device_model' => 'iPhone 13',
        'problem_description' => 'Battery replacement',
        'estimated_cost' => 8000,
        'advance_paid' => 0,
        'status' => 'received',
    ]);

    $response = $this->actingAs($this->user)
        ->put(route('repairs.update-status', [$this->team->slug, $ticket->id]), [
            'status' => 'delivered',
        ]);

    $response->assertRedirect();

    $ticket->refresh();
    expect($ticket->status->value)->toBe('delivered');
    expect($ticket->delivered_at)->not->toBeNull();
});

test('can consume spare part from inventory for repair ticket', function () {
    $sparePart = Product::factory()->accessory()->create([
        'name' => 'Original LCD Display Panel',
        'cost_price' => 4500,
        'stock_quantity' => 5,
    ]);

    $ticket = RepairTicket::create([
        'ticket_no' => 'REP-00100',
        'customer_name' => 'Zubair',
        'customer_phone' => '03334445556',
        'device_model' => 'Redmi Note 11',
        'problem_description' => 'Screen damage',
        'estimated_cost' => 7500,
        'advance_paid' => 1000,
        'status' => 'received',
        'spare_parts_cost' => 0,
    ]);

    $response = $this->actingAs($this->user)
        ->post(route('repairs.spare-parts.add', [$this->team->slug, $ticket->id]), [
            'product_id' => $sparePart->id,
            'quantity' => 1,
        ]);

    $response->assertRedirect();

    // Verify product stock decremented
    $sparePart->refresh();
    expect($sparePart->stock_quantity)->toBe(4);

    // Verify ticket spare_parts_cost updated
    $ticket->refresh();
    expect((float) $ticket->spare_parts_cost)->toBe(4500.00);
    expect($ticket->status->value)->toBe('in_diagnosis');
});

test('can delete a repair ticket', function () {
    $ticket = RepairTicket::create([
        'ticket_no' => 'REP-00101',
        'customer_name' => 'Hamza',
        'customer_phone' => '03009998877',
        'device_model' => 'Vivo Y20',
        'problem_description' => 'Mic issue',
        'estimated_cost' => 1500,
        'status' => 'received',
    ]);

    $response = $this->actingAs($this->user)
        ->delete(route('repairs.destroy', [$this->team->slug, $ticket->id]));

    $response->assertRedirect();

    $this->assertDatabaseMissing('repair_tickets', [
        'id' => $ticket->id,
    ]);
});
