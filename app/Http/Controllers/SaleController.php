<?php

namespace App\Http\Controllers;

use App\Enums\ImeiStatus;
use App\Models\Customer;
use App\Models\CustomerLedger;
use App\Models\Product;
use App\Models\ProductImei;
use App\Models\Sale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SaleController extends Controller
{
    public function index(Request $request, string $currentTeam): Response
    {
        $search = trim($request->input('search', ''));
        $paymentMethodFilter = $request->input('payment_method', 'all');
        $dateFilter = $request->input('date_filter', 'all');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        $query = Sale::with(['customer:id,name,phone', 'cashier:id,name', 'items.product:id,name', 'items.productImei:id,imei_1'])->latest();

        if ($search !== '') {
            $query->where(function ($q) use ($search): void {
                $q->where('invoice_no', 'like', "%{$search}%")
                    ->orWhereHas('customer', function ($cq) use ($search): void {
                        $cq->where('name', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%");
                    });
            });
        }

        if ($paymentMethodFilter !== 'all' && $paymentMethodFilter !== '') {
            $query->where('payment_method', $paymentMethodFilter);
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

        $sales = $query->paginate(20)->withQueryString();

        $todayRevenue = (float) Sale::whereDate('created_at', now()->today())->sum('net_amount');
        $todayCount = Sale::whereDate('created_at', now()->today())->count();
        $monthRevenue = (float) Sale::whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->sum('net_amount');
        $allTimeRevenue = (float) Sale::sum('net_amount');

        // Products for direct sale creation
        $products = Product::with(['availableImeis'])
            ->where(function ($q) {
                $q->where('is_serialized', true)
                    ->orWhere('stock_quantity', '>', 0);
            })
            ->get(['id', 'name', 'brand', 'category', 'is_serialized', 'sale_price', 'cost_price', 'stock_quantity']);

        // Customers for direct sale creation
        $customers = Customer::orderBy('name', 'asc')->get(['id', 'name', 'phone', 'current_balance']);

        return Inertia::render('Sales/Index', [
            'sales' => $sales,
            'products' => $products,
            'customers' => $customers,
            'filters' => [
                'search' => $search,
                'payment_method' => $paymentMethodFilter,
                'date_filter' => $dateFilter,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
            'stats' => [
                'today_revenue' => $todayRevenue,
                'today_count' => $todayCount,
                'month_revenue' => $monthRevenue,
                'all_time_revenue' => $allTimeRevenue,
            ],
        ]);
    }

    public function store(Request $request, string $currentTeam): RedirectResponse
    {
        $validated = $request->validate([
            'customer_id' => ['nullable', 'exists:customers,id'],
            'payment_method' => ['required', 'string', 'in:cash,jazzcash,easypaisa,bank,card,split,udhaar'],
            'discount_amount' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'paid_amount' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'payment_details' => ['nullable', 'array'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.product_imei_id' => ['nullable', 'exists:product_imeis,id'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0', 'max:999999'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0', 'max:1000000'],
        ], [
            'items.*.unit_price.max' => 'Item unit price cannot exceed Rs 1,000,000 (1 Million) / پراڈکٹ کی فی یونٹ قیمت 10 لاکھ سے زیادہ نہیں ہو سکتی۔',
        ]);

        $discountAmount = (float) ($validated['discount_amount'] ?? 0.00);
        $paidAmount = (float) $validated['paid_amount'];
        $paymentMethod = $validated['payment_method'];

        if ($paymentMethod === 'udhaar' && empty($validated['customer_id'])) {
            throw ValidationException::withMessages([
                'customer_id' => ['A customer must be selected for Udhaar (Khata) sales.'],
            ]);
        }

        DB::transaction(function () use ($request, $validated, $discountAmount, $paidAmount, $paymentMethod): void {
            $nextId = (Sale::max('id') ?? 0) + 1;
            $invoiceNo = 'INV-'.str_pad((string) $nextId, 6, '0', STR_PAD_LEFT);

            $lineItemsData = [];
            $totalAmount = 0.00;

            foreach ($validated['items'] as $itemData) {
                $product = Product::findOrFail($itemData['product_id']);
                $quantity = (float) $itemData['quantity'];
                $unitPrice = (float) $itemData['unit_price'];

                if ($product->is_serialized) {
                    if (empty($itemData['product_imei_id'])) {
                        throw ValidationException::withMessages([
                            'items' => ["Handset item {$product->name} requires an IMEI."],
                        ]);
                    }

                    $imei = ProductImei::where('id', $itemData['product_imei_id'])
                        ->lockForUpdate()
                        ->first();

                    if (! $imei || $imei->status->value !== 'in_stock') {
                        $imeiCode = $imei ? $imei->imei_1 : 'selected';
                        throw ValidationException::withMessages([
                            'items' => ["IMEI {$imeiCode} for {$product->name} is no longer in stock."],
                        ]);
                    }

                    $imei->update([
                        'status' => ImeiStatus::Sold,
                        'sold_at' => now(),
                    ]);

                    $unitCost = (float) $imei->purchase_cost;
                    $quantity = 1.00;
                    $productImeiId = $imei->id;
                } else {
                    if ($product->stock_quantity < $quantity) {
                        throw ValidationException::withMessages([
                            'items' => ["Insufficient stock for accessory: {$product->name}."],
                        ]);
                    }

                    $product->decrement('stock_quantity', (int) $quantity);
                    $unitCost = (float) $product->cost_price;
                    $productImeiId = null;
                }

                $lineTotal = round($quantity * $unitPrice, 2);
                $totalAmount += $lineTotal;

                $lineItemsData[] = [
                    'product_id' => $product->id,
                    'product_imei_id' => $productImeiId,
                    'quantity' => $quantity,
                    'unit_cost' => $unitCost,
                    'unit_price' => $unitPrice,
                    'line_total' => $lineTotal,
                ];
            }

            $netAmount = max(0.00, round($totalAmount - $discountAmount, 2));

            if ($paymentMethod === 'udhaar') {
                $changeAmount = 0.00;
                $unpaidPortion = max(0.00, round($netAmount - $paidAmount, 2));
            } else {
                $changeAmount = max(0.00, round($paidAmount - $netAmount, 2));
                $unpaidPortion = $paidAmount < $netAmount ? round($netAmount - $paidAmount, 2) : 0.00;
            }

            $sale = Sale::create([
                'invoice_no' => $invoiceNo,
                'customer_id' => $validated['customer_id'] ?? null,
                'total_amount' => $totalAmount,
                'discount_amount' => $discountAmount,
                'net_amount' => $netAmount,
                'paid_amount' => $paidAmount,
                'change_amount' => $changeAmount,
                'payment_method' => $paymentMethod,
                'payment_details' => $validated['payment_details'] ?? null,
                'cashier_id' => $request->user()->id,
            ]);

            foreach ($lineItemsData as $lineData) {
                $sale->items()->create($lineData);
            }

            if ($unpaidPortion > 0 && ! empty($validated['customer_id'])) {
                $customer = Customer::lockForUpdate()->find($validated['customer_id']);
                if ($customer) {
                    $newBalance = round((float) $customer->current_balance + $unpaidPortion, 2);
                    $customer->update(['current_balance' => $newBalance]);

                    CustomerLedger::create([
                        'customer_id' => $customer->id,
                        'type' => 'sale',
                        'amount' => $unpaidPortion,
                        'balance_after' => $newBalance,
                        'reference_id' => $invoiceNo,
                        'notes' => "Unpaid balance from Direct Invoice #{$invoiceNo}",
                    ]);
                }
            }
        });

        return redirect()->back()->with('success', 'Sale created successfully.');
    }
}
