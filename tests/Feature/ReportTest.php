<?php

use App\Enums\ImeiStatus;
use App\Enums\PaymentMethod;
use App\Models\Product;
use App\Models\ProductImei;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->team = $this->user->currentTeam;
});

test('reports page can be rendered with analytics summary', function () {
    $response = $this->actingAs($this->user)
        ->get(route('reports.index', $this->team->slug));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Reports/Index')
            ->has('summary')
            ->has('valuation')
            ->has('deviceProfits')
            ->has('slowMovingStock')
        );
});

test('reports compute exact device-wise profit for sold handset IMEIs', function () {
    $product = Product::factory()->create(['name' => 'iPhone 13', 'is_serialized' => true]);
    $imei = ProductImei::factory()->create([
        'product_id' => $product->id,
        'imei_1' => '359000111222333',
        'purchase_cost' => 120000,
        'status' => ImeiStatus::Sold,
        'sold_at' => now(),
    ]);

    $sale = Sale::create([
        'invoice_no' => 'INV-999001',
        'total_amount' => 145000,
        'discount_amount' => 0,
        'net_amount' => 145000,
        'paid_amount' => 145000,
        'payment_method' => PaymentMethod::Cash,
        'cashier_id' => $this->user->id,
    ]);

    SaleItem::create([
        'sale_id' => $sale->id,
        'product_id' => $product->id,
        'product_imei_id' => $imei->id,
        'quantity' => 1,
        'unit_cost' => 120000,
        'unit_price' => 145000,
        'line_total' => 145000,
    ]);

    $response = $this->actingAs($this->user)
        ->get(route('reports.index', $this->team->slug));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('summary.net_profit', 25000)
            ->where('deviceProfits.0.profit', 25000)
            ->where('deviceProfits.0.purchase_cost', 120000)
            ->where('deviceProfits.0.sale_price', 145000)
        );
});

test('reports detect slow moving stock created over 30 days ago', function () {
    $oldProduct = Product::factory()->create([
        'name' => 'Old Slow Charger',
        'is_serialized' => false,
        'cost_price' => 200,
        'stock_quantity' => 10,
        'created_at' => now()->subDays(45),
    ]);

    $response = $this->actingAs($this->user)
        ->get(route('reports.index', $this->team->slug));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('slowMovingStock', 1)
            ->where('slowMovingStock.0.name', 'Old Slow Charger')
        );
});
