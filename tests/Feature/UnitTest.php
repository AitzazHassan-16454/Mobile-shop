<?php

use App\Models\Unit;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->team = $this->user->currentTeam;
});

test('units page can be rendered', function () {
    Unit::factory()->create(['name' => 'Piece', 'short_name' => 'Pcs']);

    $response = $this->actingAs($this->user)
        ->get(route('units.index', $this->team->slug));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Units/Index')
            ->has('units')
            ->has('filters')
            ->has('stats'));
});

test('can create a new measurement unit', function () {
    $response = $this->actingAs($this->user)
        ->post(route('units.store', $this->team->slug), [
            'name' => 'Kilogram',
            'short_name' => 'Kg',
            'allow_decimal' => true,
            'is_active' => true,
            'description' => 'Weight measurement unit',
        ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('units', [
        'name' => 'Kilogram',
        'short_name' => 'Kg',
        'allow_decimal' => true,
        'is_active' => true,
    ]);
});

test('validates required fields when creating a unit', function () {
    $response = $this->actingAs($this->user)
        ->post(route('units.store', $this->team->slug), [
            'name' => '',
            'short_name' => '',
            'allow_decimal' => false,
            'is_active' => true,
        ]);

    $response->assertSessionHasErrors(['name', 'short_name']);
});

test('can update a measurement unit', function () {
    $unit = Unit::factory()->create([
        'name' => 'Packet',
        'short_name' => 'Pkt',
        'allow_decimal' => false,
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->user)
        ->put(route('units.update', [$this->team->slug, $unit->id]), [
            'name' => 'Small Packet',
            'short_name' => 'SPkt',
            'allow_decimal' => false,
            'is_active' => false,
            'description' => 'Updated unit description',
        ]);

    $response->assertRedirect();
    expect($unit->refresh()->name)->toBe('Small Packet');
    expect($unit->short_name)->toBe('SPkt');
    expect($unit->is_active)->toBeFalse();
});

test('can filter units by status', function () {
    Unit::factory()->create(['name' => 'Active Unit', 'short_name' => 'ACT', 'is_active' => true]);
    Unit::factory()->create(['name' => 'Inactive Unit', 'short_name' => 'INA', 'is_active' => false]);
    Unit::factory()->create(['name' => 'Decimal Unit', 'short_name' => 'DEC', 'allow_decimal' => true]);

    $responseActive = $this->actingAs($this->user)
        ->get(route('units.index', ['current_team' => $this->team->slug, 'status_filter' => 'active']));

    $responseActive->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('units.data'));

    $responseDecimal = $this->actingAs($this->user)
        ->get(route('units.index', ['current_team' => $this->team->slug, 'status_filter' => 'decimal']));

    $responseDecimal->assertOk();
});

test('can delete a measurement unit', function () {
    $unit = Unit::factory()->create([
        'name' => 'Temporary Unit',
        'short_name' => 'TMP',
    ]);

    $response = $this->actingAs($this->user)
        ->delete(route('units.destroy', [$this->team->slug, $unit->id]));

    $response->assertRedirect();
    $this->assertDatabaseMissing('units', ['id' => $unit->id]);
});
