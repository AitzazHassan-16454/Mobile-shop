<?php

namespace App\Http\Controllers;

use App\Enums\PaymentMethod;
use App\Models\AppSetting;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductImei;
use App\Models\Sale;
use App\Models\UsedPhonePurchase;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MobileSaleController extends Controller
{
    public function index(Request $request, string $currentTeam): Response
    {
        $search = trim($request->input('search', ''));
        $invoiceSearch = trim($request->input('invoice_search', ''));

        $query = Product::query()
            ->where('is_serialized', true)
            ->whereHas('inStockImeis')
            ->with(['inStockImeis' => function ($q) {
                $q->orderBy('created_at', 'desc');
            }]);

        if ($search !== '') {
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('brand', 'like', "%{$search}%")
                    ->orWhereHas('inStockImeis', function ($iq) use ($search): void {
                        $iq->where('imei_1', 'like', "%{$search}%")
                            ->orWhere('imei_2', 'like', "%{$search}%");
                    });
            });
        }

        $handsets = $query->orderBy('name', 'asc')->get()
            ->filter(fn (Product $product) => $product->inStockImeis->isNotEmpty())
            ->values();

        $allInStockProducts = Product::query()
            ->where('is_serialized', true)
            ->whereHas('inStockImeis')
            ->with('inStockImeis')
            ->get();

        $customers = Customer::query()
            ->orderBy('name', 'asc')
            ->get(['id', 'name', 'phone', 'current_balance']);

        // --- Mobile sale invoices (sales containing at least one handset) ---
        $saleQuery = Sale::query()
            ->with([
                'customer:id,name,phone',
                'cashier:id,name',
                'usedPhonePurchase:id,voucher_no,purchase_amount',
                'items.product:id,name,brand,is_serialized',
                'items.productImei:id,imei_1,imei_2,color,storage,condition,pta_status,warranty_days',
            ])
            ->whereHas('items', function ($q): void {
                $q->whereHas('product', function ($pq): void {
                    $pq->where('is_serialized', true);
                });
            });

        if ($invoiceSearch !== '') {
            $saleQuery->where(function ($q) use ($invoiceSearch): void {
                $q->where('invoice_no', 'like', "%{$invoiceSearch}%")
                    ->orWhereHas('customer', function ($cq) use ($invoiceSearch): void {
                        $cq->where('name', 'like', "%{$invoiceSearch}%")
                            ->orWhere('phone', 'like', "%{$invoiceSearch}%");
                    })
                    ->orWhereHas('items.product', function ($pq) use ($invoiceSearch): void {
                        $pq->where('name', 'like', "%{$invoiceSearch}%")
                            ->orWhere('brand', 'like', "%{$invoiceSearch}%");
                    })
                    ->orWhereHas('items.productImei', function ($iq) use ($invoiceSearch): void {
                        $iq->where('imei_1', 'like', "%{$invoiceSearch}%")
                            ->orWhere('imei_2', 'like', "%{$invoiceSearch}%");
                    });
            });
        }

        $saleInvoices = $saleQuery->latest()->limit(50)->get();

        // --- Mobile buy invoices (used phone purchase vouchers) ---
        $purchaseQuery = UsedPhonePurchase::query();

        if ($invoiceSearch !== '') {
            $purchaseQuery->where(function ($q) use ($invoiceSearch): void {
                $q->where('voucher_no', 'like', "%{$invoiceSearch}%")
                    ->orWhere('seller_name', 'like', "%{$invoiceSearch}%")
                    ->orWhere('seller_cnic', 'like', "%{$invoiceSearch}%")
                    ->orWhere('seller_phone', 'like', "%{$invoiceSearch}%")
                    ->orWhere('device_model', 'like', "%{$invoiceSearch}%")
                    ->orWhere('imei_1', 'like', "%{$invoiceSearch}%")
                    ->orWhere('imei_2', 'like', "%{$invoiceSearch}%");
            });
        }

        $purchaseInvoices = $purchaseQuery->latest()->limit(50)->get();

        $unappliedPurchases = UsedPhonePurchase::query()
            ->unapplied()
            ->latest()
            ->get(['id', 'voucher_no', 'seller_name', 'device_model', 'imei_1', 'purchase_amount', 'status', 'rejection_reason', 'created_at']);

        $shopInfo = [
            'name' => AppSetting::get('shop_name', 'Horizon Studio'),
            'tagline' => AppSetting::get('shop_tagline', 'Smartphones • Accessories • Repairing'),
            'phone' => AppSetting::get('shop_phone', '+92 300 1234567'),
            'shop_phone_secondary' => AppSetting::get('shop_phone_secondary', ''),
            'address' => AppSetting::get('shop_address', 'Main Mobile Market, Shop #12'),
            'ntn' => AppSetting::get('shop_ntn', ''),
            'invoice_style' => AppSetting::get('invoice_style', 'classic'),
            'invoice_header_title' => AppSetting::get('invoice_header_title', 'CASH RECEIPT'),
            'paperWidth' => AppSetting::get('invoice_paper_size', '80mm'),
            'showBarcode' => AppSetting::get('show_barcode_on_invoice', '1') === '1',
            'showCashier' => AppSetting::get('show_cashier_name', '1') === '1',
            'return_policy' => AppSetting::get('return_policy', '7 Days Checking Warranty. Physical & Water Damage Not Covered.'),
            'invoice_footer' => AppSetting::get('invoice_footer', 'Shukriya for shopping with us! Please visit again.'),
        ];

        $summary = [
            'ready_count' => $allInStockProducts->sum(fn (Product $p) => $p->inStockImeis->count()),
            'ready_value' => (float) $allInStockProducts->sum(
                fn (Product $p) => $p->inStockImeis->count() * (float) $p->sale_price
            ),
            'today_count' => Sale::whereDate('created_at', now()->today())
                ->whereHas('items', function ($q): void {
                    $q->whereHas('product', function ($pq): void {
                        $pq->where('is_serialized', true);
                    });
                })
                ->count(),
            'today_total' => (float) Sale::whereDate('created_at', now()->today())
                ->whereHas('items', function ($q): void {
                    $q->whereHas('product', function ($pq): void {
                        $pq->where('is_serialized', true);
                    });
                })
                ->sum('net_amount'),
            'purchase_total' => (float) UsedPhonePurchase::sum('purchase_amount'),
            'purchase_count' => UsedPhonePurchase::count(),
            'pending_credit_count' => UsedPhonePurchase::pending()->unapplied()->count(),
            'pending_credit_amount' => (float) UsedPhonePurchase::pending()->unapplied()->sum('purchase_amount'),
            'approved_credit_count' => UsedPhonePurchase::approved()->unapplied()->count(),
            'approved_credit_amount' => (float) UsedPhonePurchase::approved()->unapplied()->sum('purchase_amount'),
        ];

        return Inertia::render('MobileSales/Index', [
            'handsets' => $handsets,
            'customers' => $customers,
            'saleInvoices' => $saleInvoices,
            'purchaseInvoices' => $purchaseInvoices,
            'unappliedPurchases' => $unappliedPurchases,
            'shopInfo' => $shopInfo,
            'paymentMethods' => collect([PaymentMethod::Cash, PaymentMethod::JazzCash, PaymentMethod::EasyPaisa, PaymentMethod::Bank, PaymentMethod::Card, PaymentMethod::Udhaar])
                ->map(fn (PaymentMethod $method) => [
                    'value' => $method->value,
                    'label' => $method->label(),
                ])
                ->values()
                ->all(),
            'buyPaymentMethods' => collect([PaymentMethod::Cash, PaymentMethod::Bank, PaymentMethod::JazzCash, PaymentMethod::EasyPaisa])
                ->map(fn (PaymentMethod $method) => [
                    'value' => $method->value,
                    'label' => $method->label(),
                ])
                ->values()
                ->all(),
            'filters' => [
                'search' => $search,
                'invoice_search' => $invoiceSearch,
            ],
            'summary' => $summary,
            'latestSale' => session('latest_sale'),
            'latestPurchase' => session('latest_purchase'),
        ] + $this->handsetStockData($request));
    }

    /**
     * Handset stock listing backing the merged "Stock" tab.
     *
     * Filter params are prefixed with `imei_` so they never collide with the
     * sell tab's `search` or the invoice tabs' `invoice_search`.
     *
     * @return array<string, mixed>
     */
    private function handsetStockData(Request $request): array
    {
        $search = trim($request->input('imei_search', ''));
        $condition = $request->input('imei_condition', 'all');
        $status = $request->input('imei_status', 'in_stock');
        $ptaStatus = $request->input('imei_pta_status', 'all');
        $brand = $request->input('imei_brand', 'all');
        $perPage = (int) $request->input('imei_per_page', 20);

        if (! in_array($perPage, [10, 20, 50, 100, 250], true)) {
            $perPage = 20;
        }

        $query = ProductImei::query()
            ->whereHas('product', function ($productQuery): void {
                $productQuery->where('is_serialized', true);
            })
            ->with(['product']);

        if ($search !== '') {
            $query->where(function ($q) use ($search): void {
                $q->where('imei_1', 'like', "%{$search}%")
                    ->orWhere('imei_2', 'like', "%{$search}%")
                    ->orWhere('color', 'like', "%{$search}%")
                    ->orWhere('storage', 'like', "%{$search}%")
                    ->orWhereHas('product', function ($pQuery) use ($search): void {
                        $pQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('brand', 'like', "%{$search}%");
                    });
            });
        }

        if (in_array($condition, ['new', 'used'], true)) {
            $query->where('condition', $condition);
        }

        if (in_array($status, ['in_stock', 'sold', 'repairing', 'returned'], true)) {
            $query->where('status', $status);
        }

        if (in_array($ptaStatus, ['approved', 'non_pta', 'jv', 'cpid', 'software'], true)) {
            $query->where('pta_status', $ptaStatus);
        }

        if ($brand !== 'all' && $brand !== '') {
            $query->whereHas('product', function ($pQuery) use ($brand): void {
                $pQuery->where('brand', $brand);
            });
        }

        $imeis = $query->latest()->paginate($perPage)->withQueryString();

        $brands = Product::query()
            ->where('is_serialized', true)
            ->select('brand')
            ->distinct()
            ->whereNotNull('brand')
            ->pluck('brand')
            ->sort()
            ->values();

        $stockQuery = ProductImei::query()
            ->whereHas('product', function ($productQuery): void {
                $productQuery->where('is_serialized', true);
            })
            ->where('status', 'in_stock');

        return [
            'stockImeis' => $imeis,
            'stockBrands' => $brands,
            'stockSummary' => [
                'in_stock_count' => (clone $stockQuery)->count(),
                'new_stock_count' => (clone $stockQuery)->where('condition', 'new')->count(),
                'used_stock_count' => (clone $stockQuery)->where('condition', 'used')->count(),
                'total_cost_value' => round((float) (clone $stockQuery)->sum('purchase_cost'), 2),
            ],
            'stockFilters' => [
                'search' => $search,
                'condition' => $condition,
                'status' => $status,
                'pta_status' => $ptaStatus,
                'brand' => $brand,
                'per_page' => $perPage,
            ],
        ];
    }
}
