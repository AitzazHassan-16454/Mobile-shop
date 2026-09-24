<?php

use App\Models\Customer;
use App\Models\CustomerLedger;
use App\Models\Sale;
use App\Models\ShopExpense;
use App\Models\Supplier;
use App\Models\SupplierLedger;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->team = $this->user->currentTeam;
});

test('all payments page can be rendered', function () {
    $response = $this->actingAs($this->user)
        ->get(route('payments.index', $this->team->slug));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Payments/Index')
            ->has('payments')
            ->has('customers')
            ->has('suppliers')
            ->has('summary')
            ->has('filters'));
});

test('payments desk aggregates inflows and outflows', function () {
    $customer = Customer::factory()->create();
    $supplier = Supplier::factory()->create();

    Sale::create([
        'invoice_no' => 'INV-000001',
        'customer_id' => $customer->id,
        'total_amount' => 50000,
        'net_amount' => 50000,
        'paid_amount' => 50000,
        'change_amount' => 0,
        'payment_method' => 'cash',
        'cashier_id' => $this->user->id,
    ]);

    CustomerLedger::create([
        'customer_id' => $customer->id,
        'type' => 'payment',
        'amount' => 12000,
        'balance_after' => -12000,
        'reference_id' => 'RCPT-00001',
        'notes' => 'Payment Received (Cash)',
    ]);

    SupplierLedger::create([
        'supplier_id' => $supplier->id,
        'type' => 'payment',
        'amount' => 8000,
        'balance_after' => -8000,
        'reference_id' => 'SUP-PAY-0001',
        'notes' => 'Supplier settled',
    ]);

    ShopExpense::create([
        'user_id' => $this->user->id,
        'category' => 'Utilities (Electricity/Water/Net)',
        'amount' => 1500,
        'notes' => 'Internet bill',
    ]);

    $response = $this->actingAs($this->user)
        ->get(route('payments.index', $this->team->slug));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('summary.received_all_time', 62000)
            ->where('summary.paid_all_time', 9500)
            ->has('payments.data', 4));
});
