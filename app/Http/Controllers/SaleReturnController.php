<?php

namespace App\Http\Controllers;

use App\Enums\ImeiStatus;
use App\Enums\LedgerType;
use App\Models\Customer;
use App\Models\CustomerLedger;
use App\Models\Product;
use App\Models\ProductImei;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleReturn;
use App\Models\SaleReturnItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
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

        $query = SaleReturn::with([
            'sale:id,invoice_no,created_at,net_amount,paid_amount',
            'customer:id,name,phone,current_balance',
            'user:id,name',
            'items.product:id,name,brand,is_serialized',
            'items.productImei:id,imei_1',
            'items.saleItem',
        ])->latest();

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

        // Recent sales with items return status
        $recentSales = Sale::with([
            'customer:id,name,phone,current_balance',
            'cashier:id,name',
            'items.product:id,name,is_serialized',
            'items.productImei:id,imei_1',
            'items.returnItems',
            'returns',
        ])
            ->latest()
            ->take(50)
            ->get()
            ->map(fn (Sale $sale) => $this->formatSaleForReturn($sale));

        // All active products for manual return selection if needed
        $products = Product::get(['id', 'name', 'brand', 'category', 'is_serialized', 'sale_price', 'stock_quantity']);

        // Customers list
        $customers = Customer::orderBy('name', 'asc')->get(['id', 'name', 'phone', 'current_balance']);

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

    public function searchSale(Request $request, string $currentTeam): JsonResponse
    {
        $search = trim($request->input('search', ''));
        $invoiceNo = trim($request->input('invoice_no', ''));
        $customerQuery = trim($request->input('customer', ''));
        $date = $request->input('date');

        $query = Sale::with([
            'customer:id,name,phone,current_balance',
            'cashier:id,name',
            'items.product:id,name,brand,is_serialized',
            'items.productImei:id,imei_1,imei_2,status,condition',
            'items.returnItems',
            'returns.items.product',
            'returns.items.productImei',
            'returns.user:id,name',
        ])->latest();

        if ($invoiceNo !== '') {
            $query->where('invoice_no', 'like', "%{$invoiceNo}%");
        }

        if ($customerQuery !== '') {
            $query->whereHas('customer', function ($cq) use ($customerQuery): void {
                $cq->where('name', 'like', "%{$customerQuery}%")
                    ->orWhere('phone', 'like', "%{$customerQuery}%");
            });
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search): void {
                $q->where('invoice_no', 'like', "%{$search}%")
                    ->orWhereHas('customer', function ($cq) use ($search): void {
                        $cq->where('name', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%");
                    });
            });
        }

        if ($date) {
            $query->whereDate('created_at', $date);
        }

        $sales = $query->take(30)->get()->map(fn (Sale $sale) => $this->formatSaleForReturn($sale));

        return response()->json($sales);
    }

    public function store(Request $request, string $currentTeam): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'sale_id' => ['nullable', 'exists:sales,id'],
            'customer_id' => ['nullable', 'exists:customers,id'],
            'refund_amount' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'refund_payment_method' => ['required', 'string', 'in:cash,jazzcash,easypaisa,bank,card,khata_deduction,khata_credit'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.sale_item_id' => ['nullable', 'exists:sale_items,id'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.product_imei_id' => ['nullable', 'exists:product_imeis,id'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0', 'max:999999'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
        ]);

        $saleReturn = DB::transaction(function () use ($request, $validated) {
            $sale = ! empty($validated['sale_id']) ? Sale::lockForUpdate()->find($validated['sale_id']) : null;
            $customerId = $sale?->customer_id ?? $validated['customer_id'] ?? null;

            $nextId = (SaleReturn::max('id') ?? 0) + 1;
            $returnNo = 'RET-'.str_pad((string) $nextId, 6, '0', STR_PAD_LEFT);

            $lineItemsData = [];
            $totalReturnAmount = 0.00;

            foreach ($validated['items'] as $itemData) {
                $product = Product::findOrFail($itemData['product_id']);
                $quantity = (float) $itemData['quantity'];
                $unitPrice = (float) $itemData['unit_price'];
                $saleItemId = $itemData['sale_item_id'] ?? null;

                // Validate against original sale item if linked
                if ($sale) {
                    $saleItem = null;
                    if ($saleItemId) {
                        $saleItem = SaleItem::where('id', $saleItemId)->where('sale_id', $sale->id)->lockForUpdate()->first();
                    } elseif (! empty($itemData['product_imei_id'])) {
                        $saleItem = SaleItem::where('sale_id', $sale->id)->where('product_imei_id', $itemData['product_imei_id'])->lockForUpdate()->first();
                    } else {
                        $saleItem = SaleItem::where('sale_id', $sale->id)->where('product_id', $product->id)->lockForUpdate()->first();
                    }

                    if ($saleItem) {
                        $saleItemId = $saleItem->id;
                        $alreadyReturned = (float) SaleReturnItem::where('sale_item_id', $saleItem->id)->sum('quantity');
                        $remainingReturnable = round((float) $saleItem->quantity - $alreadyReturned, 2);

                        if ($quantity > $remainingReturnable) {
                            throw ValidationException::withMessages([
                                'items' => ["Cannot return {$quantity} of {$product->name}. Only {$remainingReturnable} remaining returnable from this sale."],
                            ]);
                        }
                    }
                }

                // Inventory restoration
                if ($product->is_serialized && ! empty($itemData['product_imei_id'])) {
                    $imei = ProductImei::where('id', $itemData['product_imei_id'])->lockForUpdate()->first();

                    if (! $imei) {
                        throw ValidationException::withMessages([
                            'items' => ["Device IMEI is not valid."],
                        ]);
                    }

                    if ($imei->status === ImeiStatus::InStock) {
                        throw ValidationException::withMessages([
                            'items' => ["IMEI {$imei->imei_1} has already been returned to stock."],
                        ]);
                    }

                    $imei->update([
                        'status' => ImeiStatus::InStock,
                        'sold_at' => null,
                    ]);

                    $product->increment('stock_quantity', 1);
                    $quantity = 1.00;
                    $productImeiId = $imei->id;
                } else {
                    $product->increment('stock_quantity', (int) $quantity);
                    $productImeiId = null;
                }

                $lineTotal = round($quantity * $unitPrice, 2);
                $totalReturnAmount += $lineTotal;

                $lineItemsData[] = [
                    'sale_item_id' => $saleItemId,
                    'product_id' => $product->id,
                    'product_imei_id' => $productImeiId,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'line_total' => $lineTotal,
                ];
            }

            $refundAmount = (float) $validated['refund_amount'];
            $refundMethod = $validated['refund_payment_method'];

            // Financial & Due Settlement Logic
            if ($sale && $customerId) {
                $customer = Customer::lockForUpdate()->find($customerId);
                if ($customer) {
                    $saleNet = (float) $sale->net_amount;
                    $salePaid = (float) $sale->paid_amount;
                    $priorReturnsOnSale = (float) SaleReturn::where('sale_id', $sale->id)->sum('total_return_amount');

                    // Remaining unpaid due specifically on this original invoice
                    $remainingUnpaidOnSale = max(0.00, round(($saleNet - $priorReturnsOnSale) - $salePaid, 2));
                    $customerCurrentDebt = max(0.00, (float) $customer->current_balance);

                    // Offset the due first if customer owes money
                    $dueToOffset = min($remainingUnpaidOnSale, $customerCurrentDebt, $totalReturnAmount);

                    if ($dueToOffset > 0) {
                        $newBalance = round((float) $customer->current_balance - $dueToOffset, 2);
                        $customer->update(['current_balance' => $newBalance]);

                        CustomerLedger::create([
                            'customer_id' => $customer->id,
                            'user_id' => $request->user()->id,
                            'type' => LedgerType::Return,
                            'amount' => $dueToOffset,
                            'payment_method' => null,
                            'balance_after' => $newBalance,
                            'reference_id' => $returnNo,
                            'notes' => "Sale return due offset for Invoice #{$sale->invoice_no}",
                        ]);
                    }

                    // For the remaining portion, if refund is stored as customer credit / advance
                    $refundablePortion = round($totalReturnAmount - $dueToOffset, 2);

                    if ($refundAmount > $refundablePortion + 0.01) {
                        throw ValidationException::withMessages([
                            'refund_amount' => ['Refund amount (Rs. '.number_format($refundAmount, 2).') cannot exceed the net refundable amount of Rs. '.number_format($refundablePortion, 2).' after due offset.'],
                        ]);
                    }

                    $actualRefund = min($refundAmount, $refundablePortion);

                    if ($actualRefund > 0 && in_array($refundMethod, ['khata_deduction', 'khata_credit'], true)) {
                        $newBalance = round((float) $customer->current_balance - $actualRefund, 2);
                        $customer->update(['current_balance' => $newBalance]);

                        CustomerLedger::create([
                            'customer_id' => $customer->id,
                            'user_id' => $request->user()->id,
                            'type' => LedgerType::Return,
                            'amount' => $actualRefund,
                            'payment_method' => 'credit_balance',
                            'balance_after' => $newBalance,
                            'reference_id' => $returnNo,
                            'notes' => "Sale return refund credited to customer balance for Invoice #{$sale->invoice_no}",
                        ]);
                    } elseif ($actualRefund > 0 && ! in_array($refundMethod, ['khata_deduction', 'khata_credit'], true)) {
                        // Customer received money in cash / bank
                        CustomerLedger::create([
                            'customer_id' => $customer->id,
                            'user_id' => $request->user()->id,
                            'type' => LedgerType::Return,
                            'amount' => $actualRefund,
                            'payment_method' => $refundMethod,
                            'balance_after' => $customer->current_balance,
                            'reference_id' => $returnNo,
                            'notes' => 'Sale return refund paid ('.ucfirst($refundMethod).") for Invoice #{$sale->invoice_no}",
                        ]);
                    }
                } else {
                    if ($refundAmount > $totalReturnAmount + 0.01) {
                        throw ValidationException::withMessages([
                            'refund_amount' => ['Refund amount (Rs. '.number_format($refundAmount, 2).') cannot exceed total return value of Rs. '.number_format($totalReturnAmount, 2)],
                        ]);
                    }
                    $actualRefund = min($refundAmount, $totalReturnAmount);
                }
            } elseif ($customerId) {
                if ($refundAmount > $totalReturnAmount + 0.01) {
                    throw ValidationException::withMessages([
                        'refund_amount' => ['Refund amount (Rs. '.number_format($refundAmount, 2).') cannot exceed total return value of Rs. '.number_format($totalReturnAmount, 2)],
                    ]);
                }
                $actualRefund = min($refundAmount, $totalReturnAmount);

                $customer = Customer::lockForUpdate()->find($customerId);
                if ($customer && in_array($refundMethod, ['khata_deduction', 'khata_credit'], true)) {
                    $newBalance = round((float) $customer->current_balance - $actualRefund, 2);
                    $customer->update(['current_balance' => $newBalance]);

                    CustomerLedger::create([
                        'customer_id' => $customer->id,
                        'user_id' => $request->user()->id,
                        'type' => LedgerType::Return,
                        'amount' => $actualRefund,
                        'payment_method' => 'credit_balance',
                        'balance_after' => $newBalance,
                        'reference_id' => $returnNo,
                        'notes' => "Sale return refund credited from Return #{$returnNo}",
                    ]);
                }
            } else {
                if ($refundAmount > $totalReturnAmount + 0.01) {
                    throw ValidationException::withMessages([
                        'refund_amount' => ['Refund amount (Rs. '.number_format($refundAmount, 2).') cannot exceed total return value of Rs. '.number_format($totalReturnAmount, 2)],
                    ]);
                }
                $actualRefund = min($refundAmount, $totalReturnAmount);
            }

            $createdReturn = SaleReturn::create([
                'return_no' => $returnNo,
                'sale_id' => $sale?->id ?? null,
                'customer_id' => $customerId,
                'user_id' => $request->user()->id,
                'total_return_amount' => $totalReturnAmount,
                'refund_amount' => $actualRefund,
                'refund_payment_method' => $refundMethod,
                'notes' => $validated['notes'] ?? null,
            ]);

            foreach ($lineItemsData as $lineData) {
                $createdReturn->items()->create($lineData);
            }

            return $createdReturn->load(['sale', 'customer', 'user', 'items.product', 'items.productImei']);
        });

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Sale return processed successfully.',
                'return' => $saleReturn,
            ]);
        }

        return redirect()->back()->with('success', 'Sale return processed successfully.');
    }

    private function formatSaleForReturn(Sale $sale): array
    {
        $items = $sale->items->map(function (SaleItem $item) {
            $returnedQty = (float) $item->returnItems->sum('quantity');
            $purchasedQty = (float) $item->quantity;
            $remainingQty = max(0.00, round($purchasedQty - $returnedQty, 2));

            return [
                'id' => $item->id,
                'sale_id' => $item->sale_id,
                'product_id' => $item->product_id,
                'product_name' => $item->product?->name ?? 'Product',
                'is_serialized' => (bool) $item->product?->is_serialized,
                'product_imei_id' => $item->product_imei_id,
                'imei_1' => $item->productImei?->imei_1,
                'quantity' => $purchasedQty,
                'returned_quantity' => $returnedQty,
                'remaining_quantity' => $remainingQty,
                'unit_price' => (float) $item->unit_price,
                'line_total' => (float) $item->line_total,
                'is_returnable' => $remainingQty > 0,
            ];
        });

        $totalReturned = (float) $sale->returns->sum('total_return_amount');
        $totalRefunded = (float) $sale->returns->sum('refund_amount');
        $dueAmount = max(0.00, round((float) $sale->net_amount - (float) $sale->paid_amount, 2));
        $allRemainingQty = $items->sum('remaining_quantity');
        $returnStatus = $allRemainingQty <= 0 ? 'fully_returned' : ($totalReturned > 0 ? 'partially_returned' : 'not_returned');

        return [
            'id' => $sale->id,
            'invoice_no' => $sale->invoice_no,
            'customer' => $sale->customer ? [
                'id' => $sale->customer->id,
                'name' => $sale->customer->name,
                'phone' => $sale->customer->phone,
                'current_balance' => (float) $sale->customer->current_balance,
            ] : null,
            'cashier' => $sale->cashier?->name,
            'sale_date' => $sale->created_at->format('Y-m-d H:i'),
            'total_amount' => (float) $sale->total_amount,
            'discount_amount' => (float) $sale->discount_amount,
            'trade_in_amount' => (float) $sale->trade_in_amount,
            'net_amount' => (float) $sale->net_amount,
            'paid_amount' => (float) $sale->paid_amount,
            'change_amount' => (float) $sale->change_amount,
            'due_amount' => $dueAmount,
            'payment_method' => $sale->payment_method->value,
            'items' => $items,
            'total_returned_amount' => $totalReturned,
            'total_refunded_amount' => $totalRefunded,
            'return_status' => $returnStatus,
            'returns_history' => $sale->returns->map(fn ($r) => [
                'id' => $r->id,
                'return_no' => $r->return_no,
                'created_at' => $r->created_at->format('Y-m-d H:i'),
                'total_return_amount' => (float) $r->total_return_amount,
                'refund_amount' => (float) $r->refund_amount,
                'refund_payment_method' => $r->refund_payment_method,
                'notes' => $r->notes,
                'user' => $r->user?->name,
            ]),
        ];
    }
}
