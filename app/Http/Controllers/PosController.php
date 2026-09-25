<?php

namespace App\Http\Controllers;

use App\Enums\ImeiStatus;
use App\Models\AppSetting;
use App\Models\Customer;
use App\Models\CustomerLedger;
use App\Models\Product;
use App\Models\ProductImei;
use App\Models\Sale;
use App\Models\UsedPhonePurchase;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PosController extends Controller
{
    public function index(Request $request, string $currentTeam): Response
    {
        $products = Product::query()
            ->with(['inStockImeis' => function ($q) {
                $q->orderBy('created_at', 'desc');
            }])
            ->get();

        $customers = Customer::query()
            ->orderBy('name', 'asc')
            ->get();

        $usedPhonePurchases = UsedPhonePurchase::query()
            ->whereNull('applied_at')
            ->latest()
            ->get(['id', 'voucher_no', 'seller_name', 'device_model', 'imei_1', 'purchase_amount', 'created_at']);

        $shopInfo = [
            'name' => AppSetting::get('shop_name', 'Horizon Studio'),
            'tagline' => AppSetting::get('shop_tagline', 'Smartphones • Accessories • Repairing'),
            'phone' => AppSetting::get('shop_phone', '+92 300 1234567'),
            'shop_phone_secondary' => AppSetting::get('shop_phone_secondary', ''),
            'address' => AppSetting::get('shop_address', 'Main Mobile Market, Shop #12, Lahore'),
            'ntn' => AppSetting::get('shop_ntn', ''),
            'invoice_header_title' => AppSetting::get('invoice_header_title', 'CASH RECEIPT'),
            'paperWidth' => AppSetting::get('invoice_paper_size', '80mm'),
            'showBarcode' => AppSetting::get('show_barcode_on_invoice', '1') === '1',
            'showCashier' => AppSetting::get('show_cashier_name', '1') === '1',
            'return_policy' => AppSetting::get('return_policy', '7 Days Checking Warranty. Physical & Water Damage Not Covered.'),
            'invoice_footer' => AppSetting::get('invoice_footer', 'Shukriya for shopping with us! Please visit again.'),
            'default_payment_method' => AppSetting::get('default_payment_method', 'Cash'),
            'enable_sound_effects' => AppSetting::get('enable_sound_effects', '1') === '1',
        ];

        $salesmen = User::query()
            ->select(['id', 'name', 'email'])
            ->orderBy('name', 'asc')
            ->get();

        return Inertia::render('Pos/Terminal', [
            'products' => $products,
            'customers' => $customers,
            'salesmen' => $salesmen,
            'usedPhonePurchases' => $usedPhonePurchases,
            'shopInfo' => $shopInfo,
            'latestSale' => session('latest_sale'),
        ]);
    }

    public function getProductsApi(Request $request, string $currentTeam)
    {
        $query = Product::query()
            ->with(['inStockImeis' => function ($q) {
                $q->orderBy('created_at', 'desc');
            }]);

        if ($search = trim($request->input('search', ''))) {
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('brand', 'like', "%{$search}%")
                    ->orWhere('barcode', 'like', "%{$search}%")
                    ->orWhere('category', 'like', "%{$search}%")
                    ->orWhereHas('inStockImeis', function ($iq) use ($search): void {
                        $iq->where('imei_1', 'like', "%{$search}%")
                            ->orWhere('imei_2', 'like', "%{$search}%");
                    });
            });
        }

        if ($category = $request->input('category')) {
            if ($category !== 'all') {
                $query->where('category', $category);
            }
        }

        return response()->json($query->orderBy('name', 'asc')->get());
    }

    public function storeCustomer(Request $request, string $currentTeam): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:255', 'unique:customers,phone'],
            'address' => ['nullable', 'string', 'max:500'],
        ]);

        $validated['current_balance'] = 0.00;
        Customer::create($validated);

        return redirect()->back()->with('success', 'Customer added successfully.');
    }

    public function storeSale(Request $request, string $currentTeam): RedirectResponse
    {
        $validated = $request->validate([
            'customer_id' => ['nullable', 'exists:customers,id'],
            'salesman_id' => ['nullable', 'exists:users,id'],
            'sale_date' => ['nullable', 'date'],
            'payment_method' => ['required', 'string', 'in:cash,jazzcash,easypaisa,bank,card,split,udhaar'],
            'discount_amount' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'trade_in_purchase_id' => ['nullable', 'exists:used_phone_purchases,id'],
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

        if (empty($validated['customer_id'])) {
            if ($paymentMethod === 'udhaar') {
                throw ValidationException::withMessages([
                    'customer_id' => ['Walk-In Customers cannot have due / Udhaar sales. Please select or register a customer account.'],
                ]);
            }
        }

        $completedSale = DB::transaction(function () use ($request, $validated, $discountAmount, $paidAmount, $paymentMethod) {
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
                        $availableImei = ProductImei::where('product_id', $product->id)
                            ->where('status', 'in_stock')
                            ->first();

                        if ($availableImei) {
                            $itemData['product_imei_id'] = $availableImei->id;
                        } else {
                            $autoImei = ProductImei::create([
                                'product_id' => $product->id,
                                'imei_1' => '35'.str_pad((string) rand(10000000000, 99999999999), 13, '0', STR_PAD_LEFT),
                                'condition' => 'new',
                                'pta_status' => 'approved',
                                'purchase_cost' => $product->cost_price ?? 0,
                                'warranty_days' => 7,
                                'status' => 'in_stock',
                            ]);
                            $itemData['product_imei_id'] = $autoImei->id;
                        }
                    }

                    $imei = ProductImei::where('id', $itemData['product_imei_id'])
                        ->lockForUpdate()
                        ->first();

                    if (! $imei) {
                        throw ValidationException::withMessages([
                            'items' => ["IMEI unit for {$product->name} is no longer available."],
                        ]);
                    }

                    if ($imei->status !== ImeiStatus::InStock) {
                        throw ValidationException::withMessages([
                            'items' => ["IMEI unit for {$product->name} is no longer available."],
                        ]);
                    }

                    $imei->update([
                        'status' => ImeiStatus::Sold,
                        'sold_at' => now(),
                    ]);

                    $product->decrement('stock_quantity', 1);

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

            $tradeInAmount = 0.00;
            $usedPhonePurchaseId = null;

            if (! empty($validated['trade_in_purchase_id'])) {
                $purchase = UsedPhonePurchase::lockForUpdate()->findOrFail($validated['trade_in_purchase_id']);

                if ($purchase->applied_at !== null) {
                    throw ValidationException::withMessages([
                        'trade_in_purchase_id' => ['This trade-in credit has already been applied to another sale.'],
                    ]);
                }

                $tradeInAmount = round((float) $purchase->purchase_amount, 2);
                $purchase->update(['applied_at' => now()]);
                $usedPhonePurchaseId = $purchase->id;
            }

            $netAmount = max(0.00, round($totalAmount - $discountAmount - $tradeInAmount, 2));

            if ($paymentMethod === 'udhaar') {
                $changeAmount = 0.00;
                $unpaidPortion = max(0.00, round($netAmount - $paidAmount, 2));
            } else {
                $changeAmount = max(0.00, round($paidAmount - $netAmount, 2));
                $unpaidPortion = $paidAmount < $netAmount ? round($netAmount - $paidAmount, 2) : 0.00;
            }

            $cashierId = ! empty($validated['salesman_id']) ? (int) $validated['salesman_id'] : $request->user()->id;

            $saleData = [
                'invoice_no' => $invoiceNo,
                'customer_id' => $validated['customer_id'] ?? null,
                'total_amount' => $totalAmount,
                'discount_amount' => $discountAmount,
                'trade_in_amount' => $tradeInAmount,
                'used_phone_purchase_id' => $usedPhonePurchaseId,
                'net_amount' => $netAmount,
                'paid_amount' => $paidAmount,
                'change_amount' => $changeAmount,
                'payment_method' => $paymentMethod,
                'payment_details' => $validated['payment_details'] ?? null,
                'cashier_id' => $cashierId,
            ];

            if (! empty($validated['sale_date'])) {
                $saleData['created_at'] = \Illuminate\Support\Carbon::parse($validated['sale_date']);
            }

            $sale = Sale::create($saleData);

            foreach ($lineItemsData as $lineData) {
                $sale->items()->create($lineData);
            }

            if (! empty($validated['customer_id'])) {
                $customer = Customer::lockForUpdate()->find($validated['customer_id']);
                if ($customer) {
                    $oldBalance = (float) $customer->current_balance;
                    $newBalance = $oldBalance;

                    if ($paymentMethod === 'udhaar' || $paidAmount < $netAmount) {
                        $unpaidPortion = max(0.00, round($netAmount - $paidAmount, 2));
                        if ($unpaidPortion > 0) {
                            $newBalance = round($oldBalance + $unpaidPortion, 2);
                            $customer->update(['current_balance' => $newBalance]);

                            CustomerLedger::create([
                                'customer_id' => $customer->id,
                                'user_id' => $cashierId,
                                'type' => \App\Enums\LedgerType::Sale,
                                'amount' => $unpaidPortion,
                                'payment_method' => $paymentMethod,
                                'balance_after' => $newBalance,
                                'reference_id' => $invoiceNo,
                                'notes' => "Unpaid balance added from Invoice #{$invoiceNo}",
                            ]);
                        }
                    } elseif ($paidAmount > $netAmount && $oldBalance > 0) {
                        $overpayment = round($paidAmount - $netAmount, 2);
                        $debtSettled = min($oldBalance, $overpayment);
                        $newBalance = round($oldBalance - $debtSettled, 2);
                        $customer->update(['current_balance' => $newBalance]);

                        CustomerLedger::create([
                            'customer_id' => $customer->id,
                            'user_id' => $cashierId,
                            'type' => \App\Enums\LedgerType::Payment,
                            'amount' => $debtSettled,
                            'payment_method' => $paymentMethod,
                            'balance_after' => $newBalance,
                            'reference_id' => $invoiceNo,
                            'notes' => "Previous debt payment from Invoice #{$invoiceNo} overpayment",
                        ]);

                        $changeAmount = max(0.00, round($overpayment - $debtSettled, 2));
                        $sale->update(['change_amount' => $changeAmount]);
                    }

                    $sale->setAttribute('previous_customer_balance', $oldBalance);
                    $sale->setAttribute('new_customer_balance', $newBalance);
                }
            }

            return $sale->load(['customer', 'cashier', 'items.product', 'items.productImei', 'usedPhonePurchase']);
        });

        return redirect()->back()->with('latest_sale', $completedSale);
    }

    public function getRecentSalesApi(Request $request, string $currentTeam)
    {
        $sales = Sale::with([
            'customer:id,name,phone',
            'cashier:id,name',
            'items.product:id,name,brand',
            'items.productImei:id,imei_1',
            'usedPhonePurchase:id,voucher_no,device_model,purchase_amount',
        ])
            ->latest()
            ->take(50)
            ->get();

        return response()->json($sales);
    }

    public function updateSaleApi(Request $request, string $currentTeam, Sale $sale)
    {
        $validated = $request->validate([
            'customer_id' => ['nullable', 'exists:customers,id'],
            'cashier_id' => ['nullable', 'exists:users,id'],
            'payment_method' => ['required', 'string', 'in:cash,jazzcash,easypaisa,bank,card,split,udhaar'],
            'discount_amount' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'paid_amount' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'payment_details' => ['nullable', 'array'],
            'items' => ['nullable', 'array'],
            'items.*.id' => ['required_with:items', 'exists:sale_items,id'],
            'items.*.quantity' => ['required_with:items', 'numeric', 'gt:0', 'max:999999'],
            'items.*.unit_price' => ['required_with:items', 'numeric', 'min:0', 'max:1000000'],
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

        DB::transaction(function () use ($sale, $validated, $discountAmount, $paidAmount, $paymentMethod): void {
            // Update items if provided
            if (! empty($validated['items'])) {
                $newTotalAmount = 0.00;
                foreach ($validated['items'] as $itemData) {
                    $saleItem = $sale->items()->find($itemData['id']);
                    if ($saleItem) {
                        $qty = (float) $itemData['quantity'];
                        $price = (float) $itemData['unit_price'];
                        $lineTotal = round($qty * $price, 2);
                        $saleItem->update([
                            'quantity' => $qty,
                            'unit_price' => $price,
                            'line_total' => $lineTotal,
                        ]);
                        $newTotalAmount += $lineTotal;
                    }
                }
                $totalAmount = round($newTotalAmount, 2);
            } else {
                $totalAmount = (float) $sale->total_amount;
            }

            $netAmount = max(0.00, round($totalAmount - $discountAmount, 2));

            if ($paymentMethod === 'udhaar') {
                $changeAmount = 0.00;
                $unpaidPortion = max(0.00, round($netAmount - $paidAmount, 2));
            } else {
                $changeAmount = max(0.00, round($paidAmount - $netAmount, 2));
                $unpaidPortion = $paidAmount < $netAmount ? round($netAmount - $paidAmount, 2) : 0.00;
            }

            // Adjust previous customer balance if customer changed
            if ($sale->customer_id && $sale->customer_id != ($validated['customer_id'] ?? null)) {
                $oldUnpaid = max(0.00, round((float) $sale->net_amount - (float) $sale->paid_amount, 2));
                $prevMethod = is_object($sale->payment_method) ? $sale->payment_method->value : (string) $sale->payment_method;
                if ($oldUnpaid > 0 && $prevMethod === 'udhaar') {
                    $oldCustomer = Customer::lockForUpdate()->find($sale->customer_id);
                    if ($oldCustomer) {
                        $oldCustomer->decrement('current_balance', $oldUnpaid);
                    }
                }
            }

            $sale->update([
                'customer_id' => $validated['customer_id'] ?? null,
                'cashier_id' => $validated['cashier_id'] ?? $sale->cashier_id,
                'total_amount' => $totalAmount,
                'discount_amount' => $discountAmount,
                'net_amount' => $netAmount,
                'paid_amount' => $paidAmount,
                'change_amount' => $changeAmount,
                'payment_method' => $paymentMethod,
                'payment_details' => $validated['payment_details'] ?? $sale->payment_details,
            ]);

            // Create ledger entry if unpaid balance exists
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
                        'reference_id' => $sale->invoice_no,
                        'notes' => "Updated Invoice #{$sale->invoice_no}",
                    ]);
                }
            }
        });

        return response()->json(
            $sale->fresh(['customer', 'cashier', 'items.product', 'items.productImei', 'usedPhonePurchase'])
        );
    }

    public function searchSalesForReturn(Request $request, string $currentTeam)
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

        $sales = $query->take(30)->get()->map(function (Sale $sale) {
            $items = $sale->items->map(function (\App\Models\SaleItem $item) {
                $returnedQty = (float) $item->returnItems->sum('quantity');
                $purchasedQty = (float) $item->quantity;
                $remainingQty = max(0.00, round($purchasedQty - $returnedQty, 2));

                return [
                    'id' => $item->id,
                    'sale_id' => $item->sale_id,
                    'product_id' => $item->product_id,
                    'product_name' => $item->product?->name ?? 'Unknown Item',
                    'is_serialized' => (bool) $item->product?->is_serialized,
                    'product_imei_id' => $item->product_imei_id,
                    'imei_1' => $item->productImei?->imei_1,
                    'imei_status' => $item->productImei?->status?->value ?? null,
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
        });

        return response()->json($sales);
    }
}
