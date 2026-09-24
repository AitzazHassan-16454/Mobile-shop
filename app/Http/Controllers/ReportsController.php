<?php

namespace App\Http\Controllers;

use App\Models\CustomerLedger;
use App\Models\Product;
use App\Models\ProductImei;
use App\Models\RepairTicket;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Services\CsvService;
use App\Services\XlsxService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class ReportsController extends Controller
{
    public function index(Request $request, string $currentTeam): InertiaResponse
    {
        return Inertia::render('Reports/Index', $this->reportData($request));
    }

    public function export(Request $request, string $currentTeam): Response
    {
        $format = $request->query('format', 'xlsx') === 'csv' ? 'csv' : 'xlsx';
        $data = $this->reportData($request);

        if ($format === 'csv') {
            $content = CsvService::build(
                ['Invoice', 'Customer', 'Device', 'IMEI', 'Condition', 'Purchase Cost', 'Sale Price', 'Profit', 'Margin %', 'Sold At'],
                $data['deviceProfits']->map(fn (array $row) => [
                    'Invoice' => $row['invoice_no'],
                    'Customer' => $row['customer_name'],
                    'Device' => $row['device_name'],
                    'IMEI' => $row['imei'],
                    'Condition' => $row['condition'],
                    'Purchase Cost' => $row['purchase_cost'],
                    'Sale Price' => $row['sale_price'],
                    'Profit' => $row['profit'],
                    'Margin %' => $row['margin_pct'],
                    'Sold At' => $row['sold_at'],
                ])->all()
            );

            return $this->downloadExport($content, 'reports', $format);
        }

        $summaryRows = [];
        foreach ($data['summary'] as $label => $value) {
            $summaryRows[] = ['Metric' => $label, 'Value' => $value];
        }

        $valuationRows = [];
        foreach ($data['valuation'] as $label => $value) {
            $valuationRows[] = ['Category' => $label, 'Value' => $value];
        }

        $content = XlsxService::buildSheets([
            [
                'name' => 'Summary',
                'headers' => ['Metric', 'Value'],
                'rows' => $summaryRows,
            ],
            [
                'name' => 'Valuation',
                'headers' => ['Category', 'Value'],
                'rows' => $valuationRows,
            ],
            [
                'name' => 'Device Profits',
                'headers' => ['Invoice', 'Customer', 'Device', 'IMEI', 'Condition', 'Purchase Cost', 'Sale Price', 'Profit', 'Margin %', 'Sold At'],
                'rows' => $data['deviceProfits']->map(fn (array $row) => [
                    'Invoice' => $row['invoice_no'],
                    'Customer' => $row['customer_name'],
                    'Device' => $row['device_name'],
                    'IMEI' => $row['imei'],
                    'Condition' => $row['condition'],
                    'Purchase Cost' => $row['purchase_cost'],
                    'Sale Price' => $row['sale_price'],
                    'Profit' => $row['profit'],
                    'Margin %' => $row['margin_pct'],
                    'Sold At' => $row['sold_at'],
                ])->all(),
            ],
            [
                'name' => 'Slow Moving',
                'headers' => ['Type', 'Name', 'Detail', 'Cost', 'Days In Stock', 'Created'],
                'rows' => $data['slowMovingStock']->map(fn (array $row) => [
                    'Type' => $row['type'],
                    'Name' => $row['name'],
                    'Detail' => $row['detail'],
                    'Cost' => $row['cost'],
                    'Days In Stock' => $row['days_in_stock'],
                    'Created' => $row['created_at'],
                ])->all(),
            ],
        ]);

        return $this->downloadExport($content, 'reports', $format);
    }

    /**
     * @return Collection<string, mixed>
     */
    private function reportData(Request $request): Collection
    {
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');

        $salesQuery = Sale::query()->with(['items.product', 'items.productImei', 'customer']);

        if ($startDate) {
            $salesQuery->whereDate('created_at', '>=', $startDate);
        }
        if ($endDate) {
            $salesQuery->whereDate('created_at', '<=', $endDate);
        }

        $sales = $salesQuery->get();

        $totalRevenue = 0.00;
        $totalCost = 0.00;
        $newPhoneCount = 0;
        $usedPhoneCount = 0;
        $accessoryItemCount = 0;
        $accessoryRevenue = 0.00;

        foreach ($sales as $sale) {
            $totalRevenue += (float) $sale->net_amount;
            foreach ($sale->items as $item) {
                $totalCost += (float) ($item->unit_cost * $item->quantity);
                if ($item->productImei) {
                    if ($item->productImei->condition->value === 'new') {
                        $newPhoneCount++;
                    } else {
                        $usedPhoneCount++;
                    }
                } else {
                    $accessoryItemCount += (int) $item->quantity;
                    $accessoryRevenue += (float) $item->line_total;
                }
            }
        }

        $netProfit = round($totalRevenue - $totalCost, 2);

        // Repairing revenue
        $repairQuery = RepairTicket::query()->where('status', 'delivered');
        if ($startDate) {
            $repairQuery->whereDate('delivered_at', '>=', $startDate);
        }
        if ($endDate) {
            $repairQuery->whereDate('delivered_at', '<=', $endDate);
        }
        $repairRevenue = (float) $repairQuery->sum('estimated_cost');

        // Udhaar vs Wasooli
        $ledgerQuery = CustomerLedger::query();
        if ($startDate) {
            $ledgerQuery->whereDate('created_at', '>=', $startDate);
        }
        if ($endDate) {
            $ledgerQuery->whereDate('created_at', '<=', $endDate);
        }
        $udhaarCreated = (float) (clone $ledgerQuery)->where('type', 'sale')->sum('amount');
        $wasooliCollected = (float) (clone $ledgerQuery)->where('type', 'payment')->sum('amount');

        // Device-Wise Profit Table (exact IMEI cost vs sale price)
        $deviceProfitQuery = SaleItem::query()
            ->with(['sale.customer', 'sale.cashier', 'product', 'productImei'])
            ->whereNotNull('product_imei_id');

        if ($startDate) {
            $deviceProfitQuery->whereHas('sale', fn ($q) => $q->whereDate('created_at', '>=', $startDate));
        }
        if ($endDate) {
            $deviceProfitQuery->whereHas('sale', fn ($q) => $q->whereDate('created_at', '<=', $endDate));
        }

        $deviceProfits = $deviceProfitQuery->latest()->get()->map(function (SaleItem $item) {
            $cost = (float) $item->unit_cost;
            $salePrice = (float) $item->unit_price;
            $profit = round($salePrice - $cost, 2);
            $marginPct = $cost > 0 ? round(($profit / $cost) * 100, 1) : 0;

            return [
                'id' => $item->id,
                'invoice_no' => $item->sale->invoice_no,
                'customer_name' => $item->sale->customer?->name ?? 'Walk-in Customer',
                'device_name' => $item->product->name,
                'imei' => $item->productImei?->imei_1,
                'color' => $item->productImei?->color,
                'storage' => $item->productImei?->storage,
                'condition' => $item->productImei?->condition->value ?? 'new',
                'purchase_cost' => $cost,
                'sale_price' => $salePrice,
                'profit' => $profit,
                'margin_pct' => $marginPct,
                'sold_at' => $item->sale->created_at->format('Y-m-d H:i'),
            ];
        });

        // Stock Valuation Report
        $newPhoneValuation = (float) ProductImei::query()
            ->where('status', 'in_stock')
            ->where('condition', 'new')
            ->sum('purchase_cost');

        $usedPhoneValuation = (float) ProductImei::query()
            ->where('status', 'in_stock')
            ->where('condition', 'used')
            ->sum('purchase_cost');

        $accessoryValuation = (float) Product::query()
            ->where('is_serialized', false)
            ->selectRaw('SUM(cost_price * stock_quantity) as total')
            ->value('total');

        $totalValuation = round($newPhoneValuation + $usedPhoneValuation + $accessoryValuation, 2);

        // Slow-Moving Stock Alerts (in stock > 30 days)
        $thresholdDate = now()->subDays(30);

        $slowMovingPhones = ProductImei::query()
            ->with('product')
            ->where('status', 'in_stock')
            ->where('created_at', '<=', $thresholdDate)
            ->get()
            ->map(fn (ProductImei $imei) => [
                'type' => 'Handset',
                'name' => $imei->product->name,
                'detail' => "IMEI: {$imei->imei_1} ({$imei->color}, {$imei->storage})",
                'cost' => (float) $imei->purchase_cost,
                'days_in_stock' => (int) round(now()->diffInDays($imei->created_at)),
                'created_at' => $imei->created_at->format('Y-m-d'),
            ]);

        $slowMovingAccessories = Product::query()
            ->where('is_serialized', false)
            ->where('stock_quantity', '>', 0)
            ->where('created_at', '<=', $thresholdDate)
            ->get()
            ->map(fn (Product $p) => [
                'type' => 'Accessory',
                'name' => $p->name,
                'detail' => "Qty: {$p->stock_quantity}, Barcode: {$p->barcode}",
                'cost' => (float) ($p->cost_price * $p->stock_quantity),
                'days_in_stock' => (int) round(now()->diffInDays($p->created_at)),
                'created_at' => $p->created_at->format('Y-m-d'),
            ]);

        $slowMovingStock = $slowMovingPhones->concat($slowMovingAccessories)->sortByDesc('days_in_stock')->values();

        return collect([
            'filters' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
            'summary' => [
                'total_sales_count' => $sales->count(),
                'total_revenue' => round($totalRevenue, 2),
                'net_profit' => $netProfit,
                'new_phone_count' => $newPhoneCount,
                'used_phone_count' => $usedPhoneCount,
                'accessory_item_count' => $accessoryItemCount,
                'accessory_revenue' => round($accessoryRevenue, 2),
                'repair_revenue' => round($repairRevenue, 2),
                'udhaar_created' => round($udhaarCreated, 2),
                'wasooli_collected' => round($wasooliCollected, 2),
            ],
            'valuation' => [
                'new_phones' => round($newPhoneValuation, 2),
                'used_phones' => round($usedPhoneValuation, 2),
                'accessories' => round($accessoryValuation, 2),
                'total' => $totalValuation,
            ],
            'deviceProfits' => $deviceProfits,
            'slowMovingStock' => $slowMovingStock,
        ]);
    }
}
