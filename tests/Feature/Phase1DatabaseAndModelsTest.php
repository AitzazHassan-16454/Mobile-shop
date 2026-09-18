<?php

use App\Enums\ImeiStatus;
use App\Enums\LedgerType;
use App\Enums\PaymentMethod;
use App\Enums\PhoneCondition;
use App\Enums\PtaStatus;
use App\Enums\RepairStatus;
use App\Models\AppSetting;
use App\Models\Customer;
use App\Models\CustomerLedger;
use App\Models\Product;
use App\Models\ProductImei;
use App\Models\RepairTicket;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\UsedPhonePurchase;
use App\Models\User;

test('app settings can set and get values', function () {
    AppSetting::set('shop_title', 'Faizan Mobile Shop');

    expect(AppSetting::get('shop_title'))->toBe('Faizan Mobile Shop');
    expect(AppSetting::get('non_existent_key', 'default_val'))->toBe('default_val');
});

test('products and imeis can be created with relationships and enum casts', function () {
    $product = Product::factory()->phone()->create([
        'name' => 'iPhone 15 Pro',
        'brand' => 'Apple',
        'sale_price' => 350000.00,
    ]);

    $imei = ProductImei::factory()->create([
        'product_id' => $product->id,
        'imei_1' => '123456789012345',
        'condition' => PhoneCondition::New,
        'pta_status' => PtaStatus::Approved,
        'status' => ImeiStatus::InStock,
        'purchase_cost' => 320000.00,
    ]);

    expect($product->imeis)->toHaveCount(1);
    expect($imei->product->id)->toBe($product->id);
    expect($imei->pta_status)->toBe(PtaStatus::Approved);
    expect($imei->condition)->toBe(PhoneCondition::New);
    expect($imei->status)->toBe(ImeiStatus::InStock);
});

test('customer ledgers track running transactions', function () {
    $customer = Customer::factory()->create([
        'name' => 'Usman Ghani',
        'current_balance' => 0.00,
    ]);

    CustomerLedger::create([
        'customer_id' => $customer->id,
        'type' => LedgerType::Sale,
        'amount' => 50000.00,
        'balance_after' => 50000.00,
        'notes' => 'Credit sale invoice #INV-001',
    ]);

    $customer->update(['current_balance' => 50000.00]);

    expect($customer->ledgers)->toHaveCount(1);
    expect($customer->ledgers->first()->type)->toBe(LedgerType::Sale);
    expect((float) $customer->fresh()->current_balance)->toBe(50000.00);
});

test('repair tickets track job status and details', function () {
    $ticket = RepairTicket::create([
        'ticket_no' => 'REP-1001',
        'customer_name' => 'Hamza Ali',
        'customer_phone' => '03001112233',
        'device_model' => 'Samsung Galaxy S22',
        'imei' => '987654321098765',
        'pattern_or_pin' => '124578',
        'problem_description' => 'Display glass broken, touch working',
        'estimated_cost' => 12000.00,
        'advance_paid' => 2000.00,
        'status' => RepairStatus::Received,
    ]);

    expect($ticket->status)->toBe(RepairStatus::Received);
    expect((float) $ticket->estimated_cost)->toBe(12000.00);
    expect((float) $ticket->advance_paid)->toBe(2000.00);
});

test('used phone purchases store seller verification and create legal vouchers', function () {
    $purchase = UsedPhonePurchase::create([
        'voucher_no' => 'PUR-5001',
        'seller_name' => 'Bilal Khan',
        'seller_father_name' => 'Tariq Khan',
        'seller_cnic' => '35202-1234567-1',
        'seller_phone' => '03334445566',
        'device_model' => 'Xiaomi Redmi Note 12',
        'imei_1' => '864201357924680',
        'purchase_amount' => 28000.00,
        'payment_method' => 'cash',
        'agreement_signed' => true,
    ]);

    expect($purchase->voucher_no)->toBe('PUR-5001');
    expect($purchase->agreement_signed)->toBeTrue();
    expect((float) $purchase->purchase_amount)->toBe(28000.00);
});

test('sales transaction creates invoice and sale items', function () {
    $cashier = User::factory()->create();
    $customer = Customer::factory()->create();
    $product = Product::factory()->accessory()->create([
        'sale_price' => 1500.00,
    ]);

    $sale = Sale::create([
        'invoice_no' => 'INV-2001',
        'customer_id' => $customer->id,
        'total_amount' => 1500.00,
        'discount_amount' => 0.00,
        'net_amount' => 1500.00,
        'paid_amount' => 1500.00,
        'change_amount' => 0.00,
        'payment_method' => PaymentMethod::Cash,
        'cashier_id' => $cashier->id,
    ]);

    SaleItem::create([
        'sale_id' => $sale->id,
        'product_id' => $product->id,
        'quantity' => 1,
        'unit_cost' => 1000.00,
        'unit_price' => 1500.00,
        'line_total' => 1500.00,
    ]);

    expect($sale->items)->toHaveCount(1);
    expect($sale->payment_method)->toBe(PaymentMethod::Cash);
    expect((float) $sale->net_amount)->toBe(1500.00);
});
