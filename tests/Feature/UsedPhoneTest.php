<?php

use App\Models\Product;
use App\Models\ProductImei;
use App\Models\UsedPhonePurchase;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->team = $this->user->currentTeam;
});

test('used phone purchases page can be rendered', function () {
    $response = $this->actingAs($this->user)
        ->get(route('used-phones.index', $this->team->slug));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('UsedPhones/Index')
            ->has('purchases')
            ->has('shopInfo')
            ->has('summary')
        );
});

test('can log used phone purchase with cnic and auto add to inventory stock', function () {
    $response = $this->actingAs($this->user)
        ->post(route('used-phones.store', $this->team->slug), [
            'seller_name' => 'Tariq Mehmood',
            'seller_father_name' => 'Mehmood Khan',
            'seller_cnic' => '35201-9876543-1',
            'seller_phone' => '03004445556',
            'seller_address' => 'Gulberg, Lahore',
            'device_model' => 'iPhone 12 Pro',
            'brand' => 'Apple',
            'color' => 'Pacific Blue',
            'storage' => '128GB',
            'pta_status' => 'approved',
            'imei_1' => '359998887776661',
            'imei_2' => '359998887776662',
            'purchase_amount' => 120000,
            'payment_method' => 'cash',
            'auto_add_stock' => true,
        ]);

    $response->assertRedirect();

    // Verify UsedPhonePurchase created
    $purchase = UsedPhonePurchase::latest()->first();
    expect($purchase)->not->toBeNull();
    expect($purchase->voucher_no)->toBe('VOUCH-00001');
    expect($purchase->seller_name)->toBe('Tariq Mehmood');
    expect($purchase->seller_cnic)->toBe('35201-9876543-1');
    expect((float) $purchase->purchase_amount)->toBe(120000.00);

    // Verify auto stock inflow created Product & ProductImei
    $product = Product::where('name', 'iPhone 12 Pro')->first();
    expect($product)->not->toBeNull();
    expect($product->is_serialized)->toBeTrue();

    $this->assertDatabaseHas('product_imeis', [
        'product_id' => $product->id,
        'imei_1' => '359998887776661',
        'imei_2' => '359998887776662',
        'condition' => 'used',
        'status' => 'in_stock',
        'purchase_cost' => 120000.00,
    ]);
});

test('can delete used phone purchase record', function () {
    $purchase = UsedPhonePurchase::create([
        'voucher_no' => 'VOUCH-00099',
        'seller_name' => 'Waqas',
        'seller_cnic' => '35202-1111111-1',
        'seller_phone' => '03001231231',
        'device_model' => 'OPPO F21',
        'imei_1' => '358888888888888',
        'purchase_amount' => 35000,
        'payment_method' => 'cash',
    ]);

    $response = $this->actingAs($this->user)
        ->delete(route('used-phones.destroy', [$this->team->slug, $purchase->id]));

    $response->assertRedirect();

    $this->assertDatabaseMissing('used_phone_purchases', [
        'id' => $purchase->id,
    ]);
});
