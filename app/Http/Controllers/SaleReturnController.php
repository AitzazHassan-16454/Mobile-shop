<?php

namespace App\Http\Controllers;

use App\Enums\ImeiStatus;
use App\Models\Customer;
use App\Models\CustomerLedger;
use App\Models\Product;
use App\Models\ProductImei;
use App\Models\Sale;
use App\Models\SaleReturn;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class SaleReturnController extends Controller
{
    public function index(Request $request, string $currentTeam): Response
    {
        $search = trim($request->input('search', ''));
        $dateFilter = $request->input('date_filter', 'all');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        $query = SaleReturn::with(['sale:id,invoice_no', 'customer:id,name,phone', 'user:id,name', 'items.product:id,name', 'items.productImei:id,imei_1'])->latest();

        if ($search !== '') {
            $query->where(function ($q) use ($search): void {
                $q->where('return_no', 'like', "%{$search}%")
                    ->orWhereHas('sale', function ($sq) use ($search): void {
                        $sq->where('invoice_no', 'like', "%{$search}%");
                    })
                    ->orWhereHas('customer', function ($cq) use ($search): void {
                        $cq->where('name', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%");
                    });
            });
        }

        if ($dateFilter === 'today') {
            $query->whereDate('created_at', now()->today());
        } elseif ($dateFilter === 'this_week') {
            $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]);
        } elseif ($dateFilter === 'this_month') {
            $query->whereMonth('created_at', now()->month)->whereYear('created_at', now()->year);
        } elseif ($dateFilter === 'custom' && $dateFrom && $dateTo) {
            $query->whereBetween('created_at', [$dateFrom.' 00:00:00', $dateTo.' 23:59:59']);
        }

        $returns = $query->paginate(20)->withQueryString();

        $todayReturnTotal = (float) SaleReturn::whereDate('created_at', now()->today())->sum('refund_amount');
        $todayReturnCount = SaleReturn::whereDate('created_at', now()->today())->count();
        $allTimeReturnTotal = (float) SaleReturn::sum('refund_amount');
        $allTimeReturnCount = SaleReturn::count();

        // Recent sales for select dropdown in Return Modal
        $recentSales = Sale::with(['customer:id,name', 'items.product:id,name,is_serialized', 'items.productImei:id,imei_1'])
            ->latest()
            ->take(50)
            ->get(['id', 'invoice_no', 'customer_id', 'net_amount', 'created_at']);

        // All active products for manual return selection
        $products = Product::get(['id', 'name', 'brand', 'category', 'is_serialized', 'sale_price']);

        // Customers list
        $customers = Customer::orderBy('name', 'asc')->get(['id', 'name', 'phone']);

        return Inertia::render('Sales/Returns', [
            'returns' => $returns,
            'recentSales' => $recentSales,
            'products' => $products,
            'customers' => $customers,
            'filters' => [
                'search' => $search,
                'date_filter' => $dateFilter,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
            'stats' => [
                'today_return_total' => $todayReturnTotal,
                'today_return_count' => $todayReturnCount,
                'all_time_return_total' => $allTimeReturnTotal,
                'all_time_return_count' => $allTimeReturnCount,
            ],
        ]);
    }

    public function store(Request $request, string $currentTeam): RedirectResponse
    {
        $validated = $request->validate([
            'sale_id' => ['nullable', 'exists:sales,id'],
            'customer_id' => ['nullable', 'exists:customers,id'],
            'refund_amount' => ['required', 'numeric', 'min:0'],
            'refund_payment_method' => ['required', 'string', 'in:cash,jazzcash,easypaisa,bank,khata_deduction'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.product_imei_id' => ['nullable', 'exists:product_imeis,id'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
        ]);

        DB::transaction(function () use ($request, $validated): void {
            $nextId = (SaleReturn::max('id') ?? 0) + 1;
            $returnNo = 'RET-'.str_pad((string) $nextId, 6, '0', STR_PAD_LEFT);

            $lineItemsData = [];
            $totalReturnAmount = 0.00;

            foreach ($validated['items'] as $itemData) {
                $product = Product::findOrFail($itemData['product_id']);
                $quantity = (float) $itemData['quantity'];
                $unitPrice = (float) $itemData['unit_price'];

                if ($product->is_serialized && ! empty($itemData['product_imei_id'])) {
                    $imei = ProductImei::where('id', $itemData['product_imei_id'])
                        ->lockForUpdate()
                        ->first();

                    if ($imei) {
                        $imei->update([
                            'status' => ImeiStatus::InStock,
                            'sold_at' => null,
                        ]);
                    }
                    $quantity = 1.00;
                    $productImeiId = $itemData['product_imei_id'];
                } else {
                    $product->increment('stock_quantity', (int) $quantity);
                    $productImeiId = null;
                }

                $lineTotal = round($quantity * $unitPrice, 2);
                $totalReturnAmount += $lineTotal;

                $lineItemsData[] = [
                    'product_id' => $product->id,
                    'product_imei_id' => $productImeiId,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'line_total' => $lineTotal,
                ];
            }

            $refundAmount = (float) $validated['refund_amount'];

            $saleReturn = SaleReturn::create([
                'return_no' => $returnNo,
                'sale_id' => $validated['sale_id'] ?? null,
                'customer_id' => $validated['customer_id'] ?? null,
                'user_id' => $request->user()->id,
                'total_return_amount' => $totalReturnAmount,
                'refund_amount' => $refundAmount,
                'refund_payment_method' => $validated['refund_payment_method'],
                'notes' => $validated['notes'] ?? null,
            ]);

            foreach ($lineItemsData as $lineData) {
                $saleReturn->items()->create($lineData);
            }

            // If refund is deducted from Khata customer balance
            if ($validated['refund_payment_method'] === 'khata_deduction' && ! empty($validated['customer_id'])) {
                $customer = Customer::lockForUpdate()->find($validated['customer_id']);
                if ($customer) {
                    $newBalance = max(0.00, round((float) $customer->current_balance - $refundAmount, 2));
                    $customer->update(['current_balance' => $newBalance]);

                    CustomerLedger::create([
                        'customer_id' => $customer->id,
                        'type' => 'return',
                        'amount' => $refundAmount,
                        'balance_after' => $newBalance,
                        'reference_id' => $returnNo,
                        'notes' => "Sale return deduction from Return #{$returnNo}",
                    ]);
                }
            }
        });

        return redirect()->back()->with('success', 'Sale return processed successfully.');
    }
}
