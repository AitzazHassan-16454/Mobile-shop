<?php

use App\Models\Customer;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierLedger;
use App\Models\User;
use App\Services\XlsxService;
use Illuminate\Http\UploadedFile;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->team = $this->user->currentTeam;
});

function makeXlsxFile(array $headers, array $rows): UploadedFile
{
    $assocRows = array_map(
        fn (array $row) => array_combine($headers, $row),
        $rows,
    );
    $content = XlsxService::build($headers, $assocRows);

    return UploadedFile::fake()->createWithContent('import.xlsx', $content);
}

function uploadImport(array $headers, array $rows, string $routeName, string $teamSlug): TestResponse
{
    return test()->actingAs(test()->user)
        ->post(route($routeName, $teamSlug), [
            'file' => makeXlsxFile($headers, $rows),
        ]);
}

test('product import template downloads as xlsx', function () {
    $response = $this->actingAs($this->user)
        ->get(route('products.imports.template', $this->team->slug));

    $response->assertOk()
        ->assertHeader(
            'content-type',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        );
});

test('customer import template downloads as xlsx', function () {
    $response = $this->actingAs($this->user)
        ->get(route('customers.imports.template', $this->team->slug));

    $response->assertOk()
        ->assertHeader(
            'content-type',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        );
});

test('supplier import template downloads as xlsx', function () {
    $response = $this->actingAs($this->user)
        ->get(route('suppliers.imports.template', $this->team->slug));

    $response->assertOk()
        ->assertHeader(
            'content-type',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        );
});

test('imports products from excel and skips sample and empty rows', function () {
    $response = uploadImport(
        ['name', 'brand', 'category', 'barcode', 'is_serialized', 'sale_price', 'cost_price', 'stock_quantity', 'alert_quantity'],
        [
            ['# Example: iPhone 13', 'Apple', 'Smartphones', '6969696969696', 'Yes', '215000', '195000', '0', '2'],
            ['Samsung A54', 'Samsung', 'Smartphones', '4848484848484', 'Yes', '120000', '110000', '0', '2'],
            ['Tempered Glass', 'Generic', 'Accessories', '7777777777777', 'No', '800', '250', '50', '10'],
            ['', '', '', '', '', '', '', '', ''],
        ],
        'products.imports.store',
        $this->team->slug,
    );

    $response->assertRedirect();

    $products = Product::where('name', 'Samsung A54')->get();
    expect($products)->toHaveCount(1);
    expect($products[0]->is_serialized)->toBeTrue();
    expect((float) $products[0]->sale_price)->toBe(120000.00);
    expect((float) $products[0]->cost_price)->toBe(0.00);
    expect((int) $products[0]->stock_quantity)->toBe(0);

    $glass = Product::where('name', 'Tempered Glass')->first();
    expect($glass)->not->toBeNull();
    expect($glass->is_serialized)->toBeFalse();
    expect((float) $glass->cost_price)->toBe(250.00);
    expect((int) $glass->stock_quantity)->toBe(50);

    // The sample (starting with #) and empty rows are not imported
    expect(Product::count())->toBe(2);
});

test('product import reports duplicate barcode rows', function () {
    $response = uploadImport(
        ['name', 'brand', 'category', 'barcode', 'is_serialized', 'sale_price', 'cost_price', 'stock_quantity', 'alert_quantity'],
        [
            ['Nice One', 'OnePlus', 'Smartphones', '1231231231231', 'Yes', '100000', '90000', '0', '2'],
            ['Nice Two', 'OnePlus', 'Smartphones', '1231231231231', 'Yes', '100000', '90000', '0', '2'],
        ],
        'products.imports.store',
        $this->team->slug,
    );

    $response->assertRedirect();

    expect(Product::where('barcode', '1231231231231')->get())->toHaveCount(1);
    expect(Product::count())->toBe(1);

    $response->assertSessionHas('importResult', fn ($flash) => $flash['created'] === 1 && count($flash['errors']) === 1);
});

test('imports customers with opening balances', function () {
    $response = uploadImport(
        ['name', 'phone', 'address', 'initial_balance'],
        [
            ['Ahmad Raza', '03001112223', 'Lahore', '5000'],
            ['Bilal Khan', '03004445556', 'Karachi', '0'],
        ],
        'customers.imports.store',
        $this->team->slug,
    );

    $response->assertRedirect();

    $customer = Customer::where('phone', '03001112223')->first();
    expect($customer)->not->toBeNull();
    expect((float) $customer->current_balance)->toBe(5000.00);

    $this->assertDatabaseHas('customer_ledger', [
        'customer_id' => $customer->id,
        'reference_id' => 'OPENING-BAL',
        'balance_after' => 5000.00,
    ]);

    expect(Customer::count())->toBe(2);
});

test('customer import skips duplicate phone numbers', function () {
    Customer::factory()->create(['phone' => '03001112223']);

    $response = uploadImport(
        ['name', 'phone', 'address', 'initial_balance'],
        [
            ['Fresh User', '03001112223', 'Lahore', '0'],
        ],
        'customers.imports.store',
        $this->team->slug,
    );

    $response->assertRedirect();

    expect(Customer::where('phone', '03001112223')->get())->toHaveCount(1);
    $response->assertSessionHas('importResult', fn ($flash) => $flash['created'] === 0 && count($flash['errors']) >= 1);
});

test('imports suppliers with payable opening balances', function () {
    $response = uploadImport(
        ['name', 'company', 'phone', 'address', 'opening_balance', 'balance_type'],
        [
            ['Raza Traders', 'Raza & Co', '03001112223', 'Karachi', '25000', 'due'],
            ['Al-Bashir Suppliers', 'Al-Bashir', '03004445556', 'Lahore', '10000', 'advance'],
        ],
        'suppliers.imports.store',
        $this->team->slug,
    );

    $response->assertRedirect();

    $due = Supplier::where('name', 'Raza Traders')->first();
    expect($due)->not->toBeNull();
    expect((float) $due->current_balance)->toBe(25000.00);

    $advance = Supplier::where('name', 'Al-Bashir Suppliers')->first();
    expect((float) $advance->current_balance)->toBe(-10000.00);

    expect(Supplier::count())->toBe(2);
    expect(SupplierLedger::count())->toBe(2);
});

test('import rejects a workbook with unrecognized headers', function () {
    $response = uploadImport(
        ['name', 'random_column', 'whatevs'],
        [
            ['Something', 'Value', 'Other'],
        ],
        'products.imports.store',
        $this->team->slug,
    );

    $response->assertRedirect();
    $response->assertSessionHas('error');
    expect(Product::count())->toBe(0);
});
