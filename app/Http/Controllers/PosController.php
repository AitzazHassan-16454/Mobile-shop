<?php

namespace App\Http\Controllers;

use App\Enums\ImeiStatus;
use App\Models\AppSetting;
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

        $shopInfo = [
            'name' => AppSetting::where('key', 'shop_name')->value('value') ?? 'Faizan Mobile & POS',
            'phone' => AppSetting::where('key', 'shop_phone')->value('value') ?? '+92 300 1234567',
            'address' => AppSetting::where('key', 'shop_address')->value('value') ?? 'Main Mobile Market, Shop #12',
            'return_policy' => AppSetting::where('key', 'return_policy')->value('value') ?? '7 Days Checking Warranty. Physical & Water Damage Not Covered.',
        ];

        return Inertia::render('Pos/Terminal', [
            'products' => $products,
            'customers' => $customers,
            'shopInfo' => $shopInfo,
            'latestSale' => session('latest_sale'),
        ]);
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
            'payment_method' => ['required', 'string', 'in:cash,jazzcash,easypaisa,bank,card,split,udhaar'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'paid_amount' => ['required', 'numeric', 'min:0'],
            'payment_details' => ['nullable', 'array'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.product_imei_id' => ['nullable', 'exists:product_imeis,id'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
        ]);

        $discountAmount = (float) ($validated['discount_amount'] ?? 0.00);
        $paidAmount = (float) $validated['paid_amount'];
        $paymentMethod = $validated['payment_method'];

        if ($paymentMethod === 'udhaar' && empty($validated['customer_id'])) {
            throw ValidationException::withMessages([
                'customer_id' => ['A customer must be selected for Udhaar (Khata) sales.'],
            ]);
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
                            'items' => ["IMEI {$imeiCode} for {$product->name} is no longer available in stock."],
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
                        'notes' => "Unpaid balance from Invoice #{$invoiceNo}",
                    ]);
                }
            }

            return $sale->load(['customer', 'cashier', 'items.product', 'items.productImei']);
        });

        return redirect()->back()->with('latest_sale', $completedSale);
    }
}
