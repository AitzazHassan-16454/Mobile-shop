<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerLedger;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierLedger;
use App\Services\CsvService;
use App\Services\XlsxService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ImportController extends Controller
{
    private const PRODUCT_HEADERS = [
        'name',
        'brand',
        'category',
        'barcode',
        'is_serialized',
        'sale_price',
        'cost_price',
        'stock_quantity',
        'alert_quantity',
    ];

    private const CUSTOMER_HEADERS = [
        'name',
        'phone',
        'address',
        'initial_balance',
    ];

    private const SUPPLIER_HEADERS = [
        'name',
        'company',
        'phone',
        'address',
        'opening_balance',
        'balance_type',
    ];

    public function productTemplate(string $currentTeam): BinaryFileResponse
    {
        return $this->downloadTemplate(
            'products-import-template.xlsx',
            self::PRODUCT_HEADERS,
            [
                [
                    'name' => '# Example: iPhone 13',
                    'brand' => 'Apple',
                    'category' => 'Smartphones',
                    'barcode' => '6969696969696',
                    'is_serialized' => 'Yes',
                    'sale_price' => '215000',
                    'cost_price' => '195000',
                    'stock_quantity' => '0',
                    'alert_quantity' => '2',
                ],
            ],
        );
    }

    public function productExport(Request $request, string $currentTeam): Response
    {
        $format = $request->query('format', 'xlsx') === 'csv' ? 'csv' : 'xlsx';

        $rows = Product::query()
            ->orderBy('id')
            ->get()
            ->map(fn (Product $product): array => [
                'name' => $product->name,
                'brand' => $product->brand,
                'category' => $product->category,
                'barcode' => (string) $product->barcode,
                'is_serialized' => $product->is_serialized ? 'Yes' : 'No',
                'sale_price' => (string) $product->sale_price,
                'cost_price' => (string) $product->cost_price,
                'stock_quantity' => $product->stock_quantity,
                'alert_quantity' => $product->alert_quantity,
            ])
            ->all();

        $content = $format === 'csv'
            ? CsvService::build(self::PRODUCT_HEADERS, $rows)
            : XlsxService::build(self::PRODUCT_HEADERS, $rows);

        return $this->downloadExport($content, 'products', $format);
    }

    public function customerTemplate(string $currentTeam): BinaryFileResponse
    {
        return $this->downloadTemplate(
            'customers-import-template.xlsx',
            self::CUSTOMER_HEADERS,
            [
                [
                    'name' => '# Example: Ahmad Raza',
                    'phone' => '03001234567',
                    'address' => 'Lahore',
                    'initial_balance' => '0',
                ],
            ],
        );
    }

    public function supplierTemplate(string $currentTeam): BinaryFileResponse
    {
        return $this->downloadTemplate(
            'suppliers-import-template.xlsx',
            self::SUPPLIER_HEADERS,
            [
                [
                    'name' => '# Example: Raza Traders',
                    'company' => 'Raza & Sons',
                    'phone' => '03001234567',
                    'address' => 'Karachi',
                    'opening_balance' => '0',
                    'balance_type' => 'due',
                ],
            ],
        );
    }

    public function importProducts(Request $request, string $currentTeam): RedirectResponse
    {
        [$rows, $rowErrors] = $this->readRows($request, self::PRODUCT_HEADERS);

        if ($rowErrors !== []) {
            return $this->failedImport('products', $rowErrors);
        }

        $created = 0;
        $seenBarcodes = [];

        foreach ($rows as $rowNumber => $data) {
            if ($this->isSkippableRow($data)) {
                continue;
            }

            $excelRow = $rowNumber + 2;
            $validator = Validator::make($data, [
                'name' => ['required', 'string', 'max:255'],
                'brand' => ['required', 'string', 'max:255'],
                'category' => ['required', 'string', 'max:255'],
                'barcode' => ['nullable', 'string', 'max:255', 'unique:products,barcode'],
                'is_serialized' => ['required', 'string'],
                'sale_price' => ['required', 'numeric', 'min:0'],
                'cost_price' => ['nullable', 'numeric', 'min:0'],
                'stock_quantity' => ['nullable', 'integer', 'min:0'],
                'alert_quantity' => ['required', 'integer', 'min:0'],
            ]);

            $barcode = trim((string) ($data['barcode'] ?? ''));
            if ($barcode !== '' && isset($seenBarcodes[$barcode])) {
                $validator->errors()->add('barcode', 'Duplicate barcode within this file.');
            }

            if ($validator->fails()) {
                $rowErrors[] = $this->formatRowErrors($excelRow, $validator->errors()->all());

                continue;
            }

            $isSerialized = $this->parseBoolean($data['is_serialized']);

            DB::transaction(function () use ($data, $isSerialized): void {
                $product = Product::create([
                    'name' => trim($data['name']),
                    'brand' => trim($data['brand']),
                    'category' => trim($data['category']),
                    'barcode' => trim((string) ($data['barcode'] ?? '')) ?: null,
                    'is_serialized' => $isSerialized,
                    'sale_price' => (float) $data['sale_price'],
                    'cost_price' => $isSerialized ? 0.00 : (float) ($data['cost_price'] ?? 0),
                    'stock_quantity' => $isSerialized ? 0 : (int) ($data['stock_quantity'] ?? 0),
                    'alert_quantity' => (int) $data['alert_quantity'],
                ]);
            });

            if ($barcode !== '') {
                $seenBarcodes[$barcode] = true;
            }

            $created++;
        }

        return $this->successfulImport('products', $created, count($rows) - $created, $rowErrors);
    }

    public function importCustomers(Request $request, string $currentTeam): RedirectResponse
    {
        [$rows, $rowErrors] = $this->readRows($request, self::CUSTOMER_HEADERS);

        if ($rowErrors !== []) {
            return $this->failedImport('customers', $rowErrors);
        }

        $created = 0;
        $seenPhones = [];

        foreach ($rows as $rowNumber => $data) {
            if ($this->isSkippableRow($data)) {
                continue;
            }

            $excelRow = $rowNumber + 2;
            $validator = Validator::make($data, [
                'name' => ['required', 'string', 'max:255'],
                'phone' => ['required', 'string', 'max:50', 'unique:customers,phone'],
                'address' => ['nullable', 'string', 'max:500'],
                'initial_balance' => ['nullable', 'numeric'],
            ]);

            $phone = trim((string) ($data['phone'] ?? ''));
            if (isset($seenPhones[$phone])) {
                $validator->errors()->add('phone', 'Duplicate phone within this file.');
            }

            if ($validator->fails()) {
                $rowErrors[] = $this->formatRowErrors($excelRow, $validator->errors()->all());

                continue;
            }

            $initialBalance = (float) ($data['initial_balance'] ?? 0);

            DB::transaction(function () use ($data, $phone, $initialBalance): void {
                $customer = Customer::create([
                    'name' => trim($data['name']),
                    'phone' => $phone,
                    'address' => trim((string) ($data['address'] ?? '')) ?: null,
                    'current_balance' => $initialBalance,
                ]);

                if ($initialBalance != 0) {
                    CustomerLedger::create([
                        'customer_id' => $customer->id,
                        'type' => 'adjustment',
                        'amount' => abs($initialBalance),
                        'balance_after' => $initialBalance,
                        'reference_id' => 'OPENING-BAL',
                        'notes' => $initialBalance > 0 ? 'Opening Balance (Udhaar)' : 'Opening Balance (Advance)',
                    ]);
                }
            });

            $seenPhones[$phone] = true;
            $created++;
        }

        return $this->successfulImport('customers', $created, count($rows) - $created, $rowErrors);
    }

    public function importSuppliers(Request $request, string $currentTeam): RedirectResponse
    {
        [$rows, $rowErrors] = $this->readRows($request, self::SUPPLIER_HEADERS);

        if ($rowErrors !== []) {
            return $this->failedImport('suppliers', $rowErrors);
        }

        $created = 0;
        $seenNames = [];

        foreach ($rows as $rowNumber => $data) {
            if ($this->isSkippableRow($data)) {
                continue;
            }

            $excelRow = $rowNumber + 2;
            $validator = Validator::make($data, [
                'name' => ['required', 'string', 'max:255', 'unique:suppliers,name'],
                'company' => ['nullable', 'string', 'max:255'],
                'phone' => ['nullable', 'string', 'max:255'],
                'address' => ['nullable', 'string', 'max:500'],
                'opening_balance' => ['nullable', 'numeric'],
                'balance_type' => ['nullable', 'string', 'in:due,advance'],
            ]);

            $name = trim((string) ($data['name'] ?? ''));
            if (isset($seenNames[$name])) {
                $validator->errors()->add('name', 'Duplicate supplier within this file.');
            }

            if ($validator->fails()) {
                $rowErrors[] = $this->formatRowErrors($excelRow, $validator->errors()->all());

                continue;
            }

            DB::transaction(function () use ($data, $name): void {
                $rawBalance = (float) ($data['opening_balance'] ?? 0);
                $balanceType = trim((string) ($data['balance_type'] ?? 'due'));

                $openingBalance = ($balanceType === 'advance' && $rawBalance > 0)
                    ? -$rawBalance
                    : $rawBalance;

                $supplier = Supplier::create([
                    'name' => $name,
                    'company' => trim((string) ($data['company'] ?? '')) ?: null,
                    'phone' => trim((string) ($data['phone'] ?? '')) ?: null,
                    'address' => trim((string) ($data['address'] ?? '')) ?: null,
                    'current_balance' => $openingBalance,
                ]);

                if ($openingBalance != 0) {
                    SupplierLedger::create([
                        'supplier_id' => $supplier->id,
                        'type' => 'adjustment',
                        'amount' => abs($openingBalance),
                        'balance_after' => $openingBalance,
                        'reference_id' => 'OPENING',
                        'notes' => $openingBalance > 0
                            ? 'Opening Balance (Payable / Udhaar)'
                            : 'Opening Balance (Advance / Peshgi)',
                    ]);
                }
            });

            $seenNames[$name] = true;
            $created++;
        }

        return $this->successfulImport('suppliers', $created, count($rows) - $created, $rowErrors);
    }

    /**
     * @param  array<int, string>  $headers
     * @return array{0: array<int, array<string, string>>, 1: array<int, string>}
     */
    private function readRows(Request $request, array $headers): array
    {
        $request->validate([
            'file' => ['required', 'file', 'max:4096'],
        ]);

        $path = $request->file('file')->getRealPath();

        if ($path === false) {
            return [[], ['The uploaded file could not be read.']];
        }

        try {
            $table = XlsxService::parse($path);
        } catch (\Throwable $e) {
            return [[], ['The uploaded file is not a valid Excel workbook.']];
        }

        if ($table === []) {
            return [[], ['The workbook contains no data to import.']];
        }

        $headerRow = array_map(fn ($cell) => strtolower(trim((string) $cell)), array_shift($table));

        $rows = [];

        foreach ($table as $row) {
            $assoc = [];

            foreach ($headers as $index => $header) {
                $assoc[$header] = trim((string) ($row[$index] ?? ''));
            }

            $rows[] = $assoc;
        }

        $unknownColumns = array_diff($headerRow, $headers);

        if (count(array_intersect($headerRow, $headers)) < 1 || $unknownColumns !== []) {
            return [[], [
                'The workbook headers do not match the expected template. '
                    .'Download the template and fill it in, keeping the first row as-is.',
            ]];
        }

        return [$rows, []];
    }

    /**
     * @param  array<string, string>  $data
     */
    private function isSkippableRow(array $data): bool
    {
        $firstCell = trim($data[array_key_first($data)] ?? '');

        if ($firstCell === '' || str_starts_with($firstCell, '#')) {
            return true;
        }

        return empty(array_filter($data, fn ($value) => trim((string) $value) !== ''));
    }

    private function parseBoolean(mixed $value): bool
    {
        return in_array(strtolower(trim((string) $value)), ['yes', 'y', 'true', '1'], true);
    }

    /**
     * @param  array<int, string>  $messages
     */
    private function formatRowErrors(int $row, array $messages): string
    {
        return 'Row '.$row.': '.implode(' ', $messages);
    }

    /**
     * @param  array<int, string>  $rowErrors
     */
    private function successfulImport(string $label, int $created, int $skipped, array $rowErrors): RedirectResponse
    {
        $labelPlural = ucfirst($label);

        $this->importResultFlash($labelPlural, [
            'created' => $created,
            'skipped' => $skipped,
            'errors' => array_slice($rowErrors, 0, 20),
        ]);

        if ($rowErrors !== []) {
            return redirect()->back()
                ->with('success', "Imported {$created} {$label}. ".count($rowErrors).' rows skipped due to errors.');
        }

        return redirect()->back()->with('success', "Imported {$created} {$label} successfully.");
    }

    /**
     * @param  array<int, string>  $rowErrors
     */
    private function failedImport(string $label, array $rowErrors): RedirectResponse
    {
        $this->importResultFlash(ucfirst($label), [
            'created' => 0,
            'skipped' => 0,
            'errors' => array_slice($rowErrors, 0, 20),
        ]);

        return redirect()->back()->with('error', implode(' ', $rowErrors));
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function importResultFlash(string $label, array $result): void
    {
        session()->flash('importResult', [
            'label' => $label,
            ...$result,
        ]);
    }

    /**
     * @param  array<int, string>  $headers
     * @param  array<int, array<string, mixed>>  $sampleRows
     */
    private function downloadTemplate(string $fileName, array $headers, array $sampleRows): BinaryFileResponse
    {
        $content = XlsxService::build($headers, $sampleRows);
        $tempPath = tempnam(sys_get_temp_dir(), 'xlsx');

        if ($tempPath === false || file_put_contents($tempPath, $content) === false) {
            abort(500, 'Unable to generate the import template.');
        }

        return response()
            ->download($tempPath, $fileName, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])
            ->deleteFileAfterSend();
    }
}
