<?php

use App\Models\Customer;
use App\Models\CustomerLedger;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierLedger;
use App\Models\User;
use App\Services\XlsxService;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->team = $this->user->currentTeam;
});

test('reports export returns a multi-section xlsx workbook', function () {
    $response = $this->actingAs($this->user)
        ->get(route('reports.export', [$this->team->slug, 'format' => 'xlsx']));

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
        ->assertHeader('Content-Disposition', 'attachment; filename="reports-'.now()->format('Y-m-d').'.xlsx"');

    $path = tempnam(sys_get_temp_dir(), 'xlsx');
    file_put_contents($path, $response->getContent());
    $summary = XlsxService::parse($path);
    unlink($path);

    expect($summary[0])->toBe(['Metric', 'Value']);
});

test('reports export returns csv of device profits', function () {
    $response = $this->actingAs($this->user)
        ->get(route('reports.export', [$this->team->slug, 'format' => 'csv']));

    $response->assertOk()
        ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

    expect($response->getContent())
        ->toContain('Invoice')
        ->toStartWith("\xEF\xBB\xBF");
});

test('products export returns xlsx matching the import template headers', function () {
    Product::factory()->create([
        'name' => 'USB Cable 1m',
        'brand' => 'Baseus',
        'category' => 'Chargers & Cables',
        'barcode' => '1112223334445',
        'is_serialized' => false,
        'sale_price' => 500,
        'cost_price' => 300,
        'stock_quantity' => 20,
        'alert_quantity' => 5,
    ]);

    $response = $this->actingAs($this->user)
        ->get(route('products.export', [$this->team->slug, 'format' => 'xlsx']));

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
        ->assertHeader('Content-Disposition', 'attachment; filename="products-'.now()->format('Y-m-d').'.xlsx"');

    $path = tempnam(sys_get_temp_dir(), 'xlsx');
    file_put_contents($path, $response->getContent());
    $rows = XlsxService::parse($path);
    unlink($path);

    expect($rows[0])->toBe([
        'name',
        'brand',
        'category',
        'barcode',
        'is_serialized',
        'sale_price',
        'cost_price',
        'stock_quantity',
        'alert_quantity',
    ])
        ->and($rows[1][0])->toBe('USB Cable 1m')
        ->and($rows[1][4])->toBe('No')
        ->and($rows[1][5])->toBe('500.00');
});

test('products export returns csv of all products', function () {
    Product::factory()->create(['name' => 'Tempered Glass']);
    Product::factory()->phone()->create(['name' => 'iPhone 15']);

    $response = $this->actingAs($this->user)
        ->get(route('products.export', [$this->team->slug, 'format' => 'csv']));

    $response->assertOk()
        ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

    expect($response->getContent())
        ->toContain('"name","brand","category","barcode","is_serialized","sale_price","cost_price","stock_quantity","alert_quantity"')
        ->toContain('Tempered Glass')
        ->toContain('iPhone 15')
        ->toStartWith("\xEF\xBB\xBF");
});

test('customer khata statement is available for print', function () {
    $customer = Customer::factory()->create(['current_balance' => 30000]);

    CustomerLedger::create([
        'customer_id' => $customer->id,
        'type' => 'sale',
        'amount' => 50000,
        'balance_after' => 50000,
        'reference_id' => 'INV-000001',
        'notes' => 'Sale invoice',
    ]);

    CustomerLedger::create([
        'customer_id' => $customer->id,
        'type' => 'payment',
        'amount' => 20000,
        'balance_after' => 30000,
        'reference_id' => 'RCPT-00001',
        'notes' => 'Wasooli (Cash)',
    ]);

    $response = $this->actingAs($this->user)
        ->get(route('customers.statement', [$this->team->slug, $customer->id]));

    $response->assertOk()
        ->assertJsonPath('entity.current_balance', 30000)
        ->assertJsonPath('entries.0.Debit', 50000)
        ->assertJsonPath('entries.0.Credit', 0)
        ->assertJsonPath('entries.1.Credit', 20000)
        ->assertJsonPath('entries.1.Debit', 0);
});

test('customer khata statement exports to xlsx with running balances', function () {
    $customer = Customer::factory()->create(['current_balance' => 30000]);

    CustomerLedger::create([
        'customer_id' => $customer->id,
        'type' => 'sale',
        'amount' => 50000,
        'balance_after' => 50000,
        'reference_id' => 'INV-000001',
        'notes' => 'Sale invoice',
    ]);

    CustomerLedger::create([
        'customer_id' => $customer->id,
        'type' => 'payment',
        'amount' => 20000,
        'balance_after' => 30000,
        'reference_id' => 'RCPT-00001',
        'notes' => 'Wasooli (Cash)',
    ]);

    $response = $this->actingAs($this->user)
        ->get(route('customers.statement.export', [$this->team->slug, $customer->id, 'format' => 'xlsx']));

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

    $path = tempnam(sys_get_temp_dir(), 'xlsx');
    file_put_contents($path, $response->getContent());
    $rows = XlsxService::parse($path);
    unlink($path);

    expect($rows[0])->toBe(['Date', 'Reference', 'Type', 'Method', 'Staff', 'Notes', 'Debit', 'Credit', 'Balance'])
        ->and($rows[1][6])->toBe('50000')
        ->and($rows[1][8])->toBe('50000')
        ->and($rows[2][7])->toBe('20000')
        ->and($rows[2][8])->toBe('30000');
});

test('customer khata statement exports to csv', function () {
    $customer = Customer::factory()->create(['current_balance' => 2500]);

    CustomerLedger::create([
        'customer_id' => $customer->id,
        'type' => 'payment',
        'amount' => 2500,
        'balance_after' => 2500,
        'reference_id' => 'RCPT-00001',
        'notes' => 'Wasooli (Cash)',
    ]);

    $response = $this->actingAs($this->user)
        ->get(route('customers.statement.export', [$this->team->slug, $customer->id, 'format' => 'csv']));

    $response->assertOk()
        ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

    expect($response->getContent())
        ->toContain('2500')
        ->toContain('RCPT-00001');
});

test('supplier khata statement is available for print', function () {
    $supplier = Supplier::factory()->create(['current_balance' => 15000]);

    SupplierLedger::create([
        'supplier_id' => $supplier->id,
        'type' => 'purchase',
        'amount' => 15000,
        'balance_after' => 15000,
        'reference_id' => 'BILL-001',
        'notes' => 'Purchase payable',
    ]);

    $response = $this->actingAs($this->user)
        ->get(route('suppliers.statement', [$this->team->slug, $supplier->id]));

    $response->assertOk()
        ->assertJsonPath('entity.current_balance', 15000)
        ->assertJsonPath('entries.0.Debit', 15000)
        ->assertJsonPath('entries.0.Balance', 15000);
});

test('supplier khata statement exports to xlsx', function () {
    $supplier = Supplier::factory()->create(['current_balance' => 15000]);

    SupplierLedger::create([
        'supplier_id' => $supplier->id,
        'type' => 'purchase',
        'amount' => 15000,
        'balance_after' => 15000,
        'reference_id' => 'BILL-001',
        'notes' => 'Purchase payable',
    ]);

    $response = $this->actingAs($this->user)
        ->get(route('suppliers.statement.export', [$this->team->slug, $supplier->id, 'format' => 'xlsx']));

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

    $path = tempnam(sys_get_temp_dir(), 'xlsx');
    file_put_contents($path, $response->getContent());
    $rows = XlsxService::parse($path);
    unlink($path);

    expect($rows[0])->toBe(['Date', 'Type', 'Reference', 'Notes', 'Debit', 'Credit', 'Balance'])
        ->and($rows[1][1])->toBe('purchase')
        ->and($rows[1][4])->toBe('15000');
});

test('customers index provides shop info for statement printing', function () {
    $this->actingAs($this->user)
        ->get(route('customers.index', $this->team->slug))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Customers/Index')
            ->has('shopInfo.name')
            ->has('shopInfo.phone')
            ->has('shopInfo.address'));
});
