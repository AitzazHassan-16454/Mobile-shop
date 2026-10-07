<?php

use App\Enums\TeamRole;
use App\Enums\TradeInStatus;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Team;
use App\Models\UsedPhonePurchase;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->team = $this->user->currentTeam;
});

test('new trade-in credits are created as pending approval', function () {
    $response = $this->actingAs($this->user)
        ->post(route('used-phones.store', $this->team->slug), [
            'seller_name' => 'Tariq Mehmood',
            'seller_cnic' => '35201-9876543-1',
            'seller_phone' => '03004445556',
            'device_model' => 'iPhone 12 Pro',
            'imei_1' => '359998887776661',
            'purchase_amount' => 120000,
            'payment_method' => 'cash',
        ]);

    $response->assertRedirect();

    $purchase = UsedPhonePurchase::latest()->first();

    expect($purchase->status)->toBe(TradeInStatus::Pending);
    expect($purchase->reviewed_at)->toBeNull();
    expect($purchase->reviewed_by)->toBeNull();
    expect($purchase->applied_at)->toBeNull();
});

test('admin can approve a pending trade-in credit', function () {
    $purchase = UsedPhonePurchase::factory()->pending()->create();

    $response = $this->actingAs($this->user)
        ->put(route('used-phones.status.update', [$this->team->slug, $purchase->id]), [
            'status' => 'approved',
        ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $purchase->refresh();

    expect($purchase->status)->toBe(TradeInStatus::Approved);
    expect($purchase->reviewed_by)->toBe($this->user->id);
    expect($purchase->reviewed_at)->not->toBeNull();
    expect($purchase->rejection_reason)->toBeNull();
});

test('admin can reject a trade-in credit with a reason', function () {
    $purchase = UsedPhonePurchase::factory()->pending()->create();

    $response = $this->actingAs($this->user)
        ->put(route('used-phones.status.update', [$this->team->slug, $purchase->id]), [
            'status' => 'rejected',
            'rejection_reason' => 'IMEI 1 failed verification.',
        ]);

    $response->assertRedirect();

    $purchase->refresh();

    expect($purchase->status)->toBe(TradeInStatus::Rejected);
    expect($purchase->rejection_reason)->toBe('IMEI 1 failed verification.');
    expect($purchase->reviewed_by)->toBe($this->user->id);
});

test('rejecting a trade-in credit requires a reason', function () {
    $purchase = UsedPhonePurchase::factory()->pending()->create();

    $response = $this->actingAs($this->user)
        ->put(route('used-phones.status.update', [$this->team->slug, $purchase->id]), [
            'status' => 'rejected',
        ]);

    $response->assertSessionHasErrors('rejection_reason');

    expect($purchase->fresh()->status)->toBe(TradeInStatus::Pending);
});

test('an approved credit can be moved back to pending which clears the rejection reason', function () {
    $purchase = UsedPhonePurchase::factory()->rejected('Wrong IMEI logged.')->create();

    $this->actingAs($this->user)
        ->put(route('used-phones.status.update', [$this->team->slug, $purchase->id]), [
            'status' => 'approved',
        ])
        ->assertRedirect();

    expect($purchase->fresh()->rejection_reason)->toBeNull();
    expect($purchase->fresh()->status)->toBe(TradeInStatus::Approved);

    $this->actingAs($this->user)
        ->put(route('used-phones.status.update', [$this->team->slug, $purchase->id]), [
            'status' => 'pending',
        ])
        ->assertRedirect();

    expect($purchase->fresh()->status)->toBe(TradeInStatus::Pending);
});

test('an unknown status is rejected', function () {
    $purchase = UsedPhonePurchase::factory()->pending()->create();

    $response = $this->actingAs($this->user)
        ->put(route('used-phones.status.update', [$this->team->slug, $purchase->id]), [
            'status' => 'banana',
        ]);

    $response->assertSessionHasErrors('status');

    expect($purchase->fresh()->status)->toBe(TradeInStatus::Pending);
});

test('a trade-in credit that was already applied to a sale cannot be re-reviewed', function () {
    $purchase = UsedPhonePurchase::factory()->approved()->applied()->create();

    $response = $this->actingAs($this->user)
        ->put(route('used-phones.status.update', [$this->team->slug, $purchase->id]), [
            'status' => 'rejected',
            'rejection_reason' => 'Too late.',
        ]);

    $response->assertSessionHasErrors('status');

    expect($purchase->fresh()->status)->toBe(TradeInStatus::Approved);
});

test('team members cannot approve trade-in credits', function () {
    $team = Team::factory()->create();
    $member = User::factory()->create();

    $team->members()->attach($member, ['role' => TeamRole::Member->value]);

    $purchase = UsedPhonePurchase::factory()->pending()->create();

    $response = $this->actingAs($member)
        ->put(route('used-phones.status.update', [$team->slug, $purchase->id]), [
            'status' => 'approved',
        ]);

    $response->assertForbidden();

    expect($purchase->fresh()->status)->toBe(TradeInStatus::Pending);
});

test('the used phones log filters and summarises by approval status', function () {
    UsedPhonePurchase::factory()->pending()->create(['purchase_amount' => 10000]);
    UsedPhonePurchase::factory()->approved()->create(['purchase_amount' => 20000]);
    UsedPhonePurchase::factory()->approved()->applied()->create(['purchase_amount' => 30000]);
    UsedPhonePurchase::factory()->rejected()->create(['purchase_amount' => 40000]);

    $this->actingAs($this->user)
        ->get(route('used-phones.index', ['current_team' => $this->team->slug, 'status' => 'pending']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('UsedPhones/Index')
            ->has('purchases.data', 1)
            ->where('purchases.data.0.status', 'pending')
            ->where('filters.status', 'pending')
            ->where('summary.pending_count', 1)
            ->where('summary.pending_amount', 10000)
            ->where('summary.approved_count', 2)
            ->where('summary.approved_amount', 20000)
            ->where('summary.rejected_count', 1)
        );
});

test('pos only lists approved and unapplied trade-in credits', function () {
    UsedPhonePurchase::factory()->approved()->create(['purchase_amount' => 30000]);
    UsedPhonePurchase::factory()->pending()->create(['purchase_amount' => 40000]);
    UsedPhonePurchase::factory()->rejected()->create(['purchase_amount' => 45000]);
    UsedPhonePurchase::factory()->approved()->applied()->create(['purchase_amount' => 50000]);

    $this->actingAs($this->user)
        ->get(route('pos.index', $this->team->slug))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Pos/Terminal')
            ->has('usedPhonePurchases', 1)
            ->where('usedPhonePurchases.0.purchase_amount', '30000.00')
        );
});

test('a pending trade-in credit cannot be used on a sale', function () {
    $accessory = Product::factory()->accessory()->create(['stock_quantity' => 10, 'sale_price' => 50000]);
    $tradeIn = UsedPhonePurchase::factory()->pending()->create(['purchase_amount' => 30000]);

    $response = $this->actingAs($this->user)
        ->post(route('pos.sales.store', $this->team->slug), [
            'payment_method' => 'cash',
            'paid_amount' => 50000,
            'trade_in_purchase_id' => $tradeIn->id,
            'items' => [
                [
                    'product_id' => $accessory->id,
                    'quantity' => 1,
                    'unit_price' => 50000,
                ],
            ],
        ]);

    $response->assertSessionHasErrors('trade_in_purchase_id');

    expect(Sale::count())->toBe(0);
    expect($tradeIn->fresh()->applied_at)->toBeNull();
});

test('a rejected trade-in credit cannot be used on a sale', function () {
    $accessory = Product::factory()->accessory()->create(['stock_quantity' => 10, 'sale_price' => 50000]);
    $tradeIn = UsedPhonePurchase::factory()->rejected()->create(['purchase_amount' => 30000]);

    $response = $this->actingAs($this->user)
        ->post(route('pos.sales.store', $this->team->slug), [
            'payment_method' => 'cash',
            'paid_amount' => 50000,
            'trade_in_purchase_id' => $tradeIn->id,
            'items' => [
                [
                    'product_id' => $accessory->id,
                    'quantity' => 1,
                    'unit_price' => 50000,
                ],
            ],
        ]);

    $response->assertSessionHasErrors('trade_in_purchase_id');

    expect(Sale::count())->toBe(0);
    expect($tradeIn->fresh()->applied_at)->toBeNull();
});

test('a credit becomes usable at pos only after it is approved', function () {
    $accessory = Product::factory()->accessory()->create(['stock_quantity' => 10, 'sale_price' => 50000]);
    $tradeIn = UsedPhonePurchase::factory()->pending()->create(['purchase_amount' => 30000]);

    $this->actingAs($this->user)
        ->put(route('used-phones.status.update', [$this->team->slug, $tradeIn->id]), [
            'status' => 'approved',
        ])
        ->assertRedirect();

    $response = $this->actingAs($this->user)
        ->post(route('pos.sales.store', $this->team->slug), [
            'payment_method' => 'cash',
            'paid_amount' => 20000,
            'trade_in_purchase_id' => $tradeIn->id,
            'items' => [
                [
                    'product_id' => $accessory->id,
                    'quantity' => 1,
                    'unit_price' => 50000,
                ],
            ],
        ]);

    $response->assertRedirect();

    $sale = Sale::latest()->first();

    expect((float) $sale->trade_in_amount)->toBe(30000.00);
    expect((float) $sale->net_amount)->toBe(20000.00);
    expect($tradeIn->fresh()->applied_at)->not->toBeNull();
});

test('mobile sales page separates pending and approved credits', function () {
    UsedPhonePurchase::factory()->pending()->create(['purchase_amount' => 10000]);
    UsedPhonePurchase::factory()->approved()->create(['purchase_amount' => 20000]);

    $this->actingAs($this->user)
        ->get(route('mobile-sales.index', $this->team->slug))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('MobileSales/Index')
            ->has('unappliedPurchases', 2)
            ->where('summary.pending_credit_count', 1)
            ->where('summary.pending_credit_amount', 10000)
            ->where('summary.approved_credit_count', 1)
            ->where('summary.approved_credit_amount', 20000)
        );
});
