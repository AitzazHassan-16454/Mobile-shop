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
test('example', function () {
    $response = $this->get('/');

    $response->assertStatus(200);
});
