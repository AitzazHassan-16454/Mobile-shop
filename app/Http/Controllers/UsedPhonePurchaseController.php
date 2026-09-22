<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Models\Product;
use App\Models\ProductImei;
use App\Models\UsedPhonePurchase;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class UsedPhonePurchaseController extends Controller
{
    public function index(Request $request, string $currentTeam): Response
    {
        $search = trim($request->input('search', ''));
        $perPage = (int) $request->input('per_page', 15);
        if (! in_array($perPage, [10, 15, 25, 50, 100, 250, 500], true)) {
            $perPage = 15;
        }

        $query = UsedPhonePurchase::query();

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('voucher_no', 'like', "%{$search}%")
                    ->orWhere('seller_name', 'like', "%{$search}%")
                    ->orWhere('seller_cnic', 'like', "%{$search}%")
                    ->orWhere('seller_phone', 'like', "%{$search}%")
                    ->orWhere('device_model', 'like', "%{$search}%")
                    ->orWhere('imei_1', 'like', "%{$search}%")
                    ->orWhere('imei_2', 'like', "%{$search}%");
            });
        }

        $purchases = $query->latest()->paginate($perPage)->withQueryString();

        $shopInfo = [
            'name' => AppSetting::where('key', 'shop_name')->value('value') ?? 'Faizan Mobile & POS',
            'phone' => AppSetting::where('key', 'shop_phone')->value('value') ?? '+92 300 1234567',
            'address' => AppSetting::where('key', 'shop_address')->value('value') ?? 'Main Mobile Market, Shop #12',
        ];

        $summary = [
            'total_purchases' => UsedPhonePurchase::count(),
            'total_payout' => (float) UsedPhonePurchase::sum('purchase_amount'),
        ];

        return Inertia::render('UsedPhones/Index', [
            'purchases' => $purchases,
            'shopInfo' => $shopInfo,
            'filters' => [
                'search' => $search,
                'per_page' => $perPage,
            ],
            'summary' => $summary,
            'latestPurchase' => session('latest_purchase'),
        ]);
    }

    public function store(Request $request, string $currentTeam): RedirectResponse
    {
        $validated = $request->validate([
            'seller_name' => ['required', 'string', 'max:255'],
            'seller_father_name' => ['nullable', 'string', 'max:255'],
            'seller_cnic' => ['required', 'string', 'max:255'],
            'seller_phone' => ['required', 'string', 'max:255'],
            'seller_address' => ['nullable', 'string', 'max:500'],
            'device_model' => ['required', 'string', 'max:255'],
            'brand' => ['nullable', 'string', 'max:255'],
            'color' => ['nullable', 'string', 'max:255'],
            'storage' => ['nullable', 'string', 'max:255'],
            'pta_status' => ['nullable', 'string', 'in:approved,non_pta,jv,cpid,software'],
            'imei_1' => ['required', 'string', 'max:255', 'unique:product_imeis,imei_1'],
            'imei_2' => ['nullable', 'string', 'max:255', 'different:imei_1', 'unique:product_imeis,imei_2'],
            'purchase_amount' => ['required', 'numeric', 'min:0'],
            'payment_method' => ['required', 'string', 'in:cash,bank,jazzcash,easypaisa'],
            'cnic_front_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:4096'],
            'cnic_back_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:4096'],
            'auto_add_stock' => ['nullable', 'boolean'],
        ]);

        $cnicFrontPath = null;
        if ($request->hasFile('cnic_front_image')) {
            $cnicFrontPath = $request->file('cnic_front_image')->store('cnic_scans', 'public');
        }

        $cnicBackPath = null;
        if ($request->hasFile('cnic_back_image')) {
            $cnicBackPath = $request->file('cnic_back_image')->store('cnic_scans', 'public');
        }

        $purchase = DB::transaction(function () use ($validated, $cnicFrontPath, $cnicBackPath) {
            $nextId = (UsedPhonePurchase::max('id') ?? 0) + 1;
            $voucherNo = 'VOUCH-'.str_pad((string) $nextId, 5, '0', STR_PAD_LEFT);

            $purchaseRecord = UsedPhonePurchase::create([
                'voucher_no' => $voucherNo,
                'seller_name' => $validated['seller_name'],
                'seller_father_name' => $validated['seller_father_name'] ?? null,
                'seller_cnic' => $validated['seller_cnic'],
                'seller_phone' => $validated['seller_phone'],
                'seller_address' => $validated['seller_address'] ?? null,
                'cnic_front_image' => $cnicFrontPath,
                'cnic_back_image' => $cnicBackPath,
                'device_model' => $validated['device_model'],
                'imei_1' => $validated['imei_1'],
                'imei_2' => $validated['imei_2'] ?? null,
                'purchase_amount' => $validated['purchase_amount'],
                'payment_method' => $validated['payment_method'],
                'agreement_signed' => true,
            ]);

            $autoAdd = $validated['auto_add_stock'] ?? true;
            if ($autoAdd) {
                $brand = $validated['brand'] ?? 'Used Handset';
                $product = Product::firstOrCreate(
                    [
                        'name' => $validated['device_model'],
                        'is_serialized' => true,
                    ],
                    [
                        'brand' => $brand,
                        'category' => 'Mobile Handsets',
                        'sale_price' => round((float) $validated['purchase_amount'] * 1.10, 2),
                        'cost_price' => 0.00,
                        'stock_quantity' => 0,
                        'alert_quantity' => 2,
                    ]
                );

                ProductImei::create([
                    'product_id' => $product->id,
                    'imei_1' => $validated['imei_1'],
                    'imei_2' => $validated['imei_2'] ?? null,
                    'color' => $validated['color'] ?? 'Used',
                    'storage' => $validated['storage'] ?? null,
                    'condition' => 'used',
                    'pta_status' => $validated['pta_status'] ?? 'approved',
                    'purchase_cost' => $validated['purchase_amount'],
                    'warranty_days' => 7,
                    'status' => 'in_stock',
                ]);
            }

            return $purchaseRecord;
        });

        return redirect()->back()->with('latest_purchase', $purchase);
    }

    public function destroy(Request $request, string $currentTeam, UsedPhonePurchase $purchase): RedirectResponse
    {
        if ($purchase->cnic_front_image) {
            Storage::disk('public')->delete($purchase->cnic_front_image);
        }
        if ($purchase->cnic_back_image) {
            Storage::disk('public')->delete($purchase->cnic_back_image);
        }

        $purchase->delete();

        return redirect()->back()->with('success', 'Used phone purchase record deleted.');
    }
}
