<?php

namespace App\Http\Controllers;

use App\Enums\PaymentMethod;
use App\Models\AppSetting;
use App\Models\Customer;
use App\Models\Product;
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
            ->where('stock_quantity', '>', 0)
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

        $customers = Customer::query()
            ->orderBy('name', 'asc')
            ->get(['id', 'name', 'phone', 'current_balance']);

        // --- Mobile sale invoices (sales containing at least one handset) ---
        $saleQuery = Sale::query()
            ->with([
                'customer:id,name,phone',
                'cashier:id,name',
                'items.product:id,name,brand,is_serialized',
                'items.productImei:id,imei_1,imei_2,color,storage,condition',
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
                    ->orWhereHas('items.productImei', function ($iq) use ($invoiceSearch): void {
                        $iq->where('imei_1', 'like', "%{$invoiceSearch}%");
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
                    ->orWhere('imei_1', 'like', "%{$invoiceSearch}%");
            });
        }

        $purchaseInvoices = $purchaseQuery->latest()->limit(50)->get();

        $unappliedPurchases = UsedPhonePurchase::query()
            ->whereNull('applied_at')
            ->latest()
            ->get(['id', 'voucher_no', 'seller_name', 'device_model', 'imei_1', 'purchase_amount', 'created_at']);

        $shopInfo = [
            'name' => AppSetting::where('key', 'shop_name')->value('value') ?? 'Horizon Studio',
            'phone' => AppSetting::where('key', 'shop_phone')->value('value') ?? '+92 300 1234567',
            'address' => AppSetting::where('key', 'shop_address')->value('value') ?? 'Main Mobile Market, Shop #12',
        ];

        $summary = [
            'ready_count' => $handsets->sum(fn (Product $p) => $p->inStockImeis->count()),
            'ready_value' => (float) $handsets->sum(
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
        ]);
    }
}
