<?php

use App\Models\Supplier;
use App\Models\SupplierLedger;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->team = $this->user->currentTeam;
});

test('supplier payable page can be rendered', function () {
    $response = $this->actingAs($this->user)
        ->get(route('suppliers.index', $this->team->slug));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Suppliers/Index')
            ->has('suppliers')
            ->has('summary')
            ->has('filters'));
});

test('supplier purchase and payment update payable balance', function () {
    $supplier = Supplier::create([
        'name' => 'Hall Road Distributor',
        'current_balance' => 10000,
    ]);

    $purchaseResponse = $this->actingAs($this->user)
        ->post(route('suppliers.purchases.store', [$this->team->slug, $supplier->id]), [
            'amount' => 25000,
            'reference_id' => 'BILL-100',
        ]);

    $purchaseResponse->assertRedirect();
    expect((float) $supplier->refresh()->current_balance)->toBe(35000.00);

    $paymentResponse = $this->actingAs($this->user)
        ->post(route('suppliers.payments.store', [$this->team->slug, $supplier->id]), [
            'amount' => 12000,
            'reference_id' => 'PAY-100',
        ]);

    $paymentResponse->assertRedirect();
    expect((float) $supplier->refresh()->current_balance)->toBe(23000.00);
    expect(SupplierLedger::where('supplier_id', $supplier->id)->count())->toBe(2);
    $this->assertDatabaseHas('supplier_ledgers', [
        'supplier_id' => $supplier->id,
        'type' => 'payment',
        'amount' => 12000.00,
        'balance_after' => 23000.00,
    ]);
});
test('can create a new supplier with opening balance', function () {
    $response = $this->actingAs($this->user)
        ->post(route('suppliers.store', $this->team->slug), [
            'name' => 'Hafeez Center Electronics',
            'company' => 'Hafeez Trader',
            'phone' => '03211234567',
            'opening_balance' => 5000,
        ]);

    $response->assertRedirect();
    $supplier = Supplier::where('name', 'Hafeez Center Electronics')->first();
    expect($supplier)->not->toBeNull();
    expect((float) $supplier->current_balance)->toBe(5000.00);
    $this->assertDatabaseHas('supplier_ledgers', [
        'supplier_id' => $supplier->id,
        'type' => 'adjustment',
        'amount' => 5000.00,
    ]);
});

test('can update supplier details', function () {
    $supplier = Supplier::create([
        'name' => 'Old Supplier Name',
        'phone' => '03000000000',
    ]);

    $response = $this->actingAs($this->user)
        ->put(route('suppliers.update', [$this->team->slug, $supplier->id]), [
            'name' => 'Updated Supplier Name',
            'company' => 'New Company',
            'phone' => '03119998877',
            'address' => 'Shop #12, Market',
        ]);

    $response->assertRedirect();
    expect($supplier->refresh()->name)->toBe('Updated Supplier Name');
    expect($supplier->company)->toBe('New Company');
});

test('can create a new supplier with advance opening balance', function () {
    $response = $this->actingAs($this->user)
        ->post(route('suppliers.store', $this->team->slug), [
            'name' => 'Advance Supplier',
            'company' => 'Advance Co',
            'phone' => '03211234567',
            'opening_balance' => 3000,
            'balance_type' => 'advance',
        ]);

    $response->assertRedirect();
    $supplier = Supplier::where('name', 'Advance Supplier')->first();
    expect($supplier)->not->toBeNull();
    expect((float) $supplier->current_balance)->toBe(-3000.00);
    $this->assertDatabaseHas('supplier_ledgers', [
        'supplier_id' => $supplier->id,
        'type' => 'adjustment',
        'amount' => 3000.00,
        'notes' => 'Opening Balance (Advance / Peshgi)',
    ]);
});

test('can filter suppliers by balance status', function () {
    Supplier::create(['name' => 'Payable Supplier', 'current_balance' => 1500]);
    Supplier::create(['name' => 'Advance Supplier', 'current_balance' => -2000]);
    Supplier::create(['name' => 'Zero Supplier', 'current_balance' => 0]);

    $responsePayable = $this->actingAs($this->user)
        ->get(route('suppliers.index', ['current_team' => $this->team->slug, 'balance_filter' => 'payable']));

    $responsePayable->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('suppliers.data.0.name', 'Payable Supplier')
            ->has('suppliers.data', 1));

    $responseAdvance = $this->actingAs($this->user)
        ->get(route('suppliers.index', ['current_team' => $this->team->slug, 'balance_filter' => 'advance']));

    $responseAdvance->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('suppliers.data.0.name', 'Advance Supplier')
            ->has('suppliers.data', 1));
});

test('can delete a supplier', function () {
    $supplier = Supplier::create([
        'name' => 'Temporary Supplier',
    ]);

    $response = $this->actingAs($this->user)
        ->delete(route('suppliers.destroy', [$this->team->slug, $supplier->id]));

    $response->assertRedirect();
    $this->assertDatabaseMissing('suppliers', ['id' => $supplier->id]);
});
