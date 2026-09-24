<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductImei;
use App\Models\UsedPhonePurchase;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class MobilePhonesController extends Controller
{
    public function index(Request $request, string $currentTeam): Response
    {
        $search = trim($request->input('search', ''));
        $condition = $request->input('condition', 'all');
        $status = $request->input('status', 'in_stock');
        $ptaStatus = $request->input('pta_status', 'all');
        $brand = $request->input('brand', 'all');
        $perPage = (int) $request->input('per_page', 20);

        if (! in_array($perPage, [10, 20, 50, 100, 250], true)) {
            $perPage = 20;
        }

        $query = ProductImei::query()->with(['product']);

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('imei_1', 'like', "%{$search}%")
                    ->orWhere('imei_2', 'like', "%{$search}%")
                    ->orWhere('color', 'like', "%{$search}%")
                    ->orWhere('storage', 'like', "%{$search}%")
                    ->orWhereHas('product', function ($pQuery) use ($search) {
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
            $query->whereHas('product', function ($pQuery) use ($brand) {
                $pQuery->where('brand', $brand);
            });
        }

        $imeis = $query->latest()->paginate($perPage)->withQueryString();

        $brands = Product::query()
            ->where('is_serialized', true)
            ->select('brand')
            ->distinct()
            ->whereNotNull('brand')
            ->pluck('brand');

        $products = Product::query()
            ->where('is_serialized', true)
            ->select(['id', 'name', 'brand', 'category', 'sale_price', 'alert_quantity'])
            ->orderBy('name', 'asc')
            ->get();

        $inStockCount = ProductImei::where('status', 'in_stock')->count();
        $newStockCount = ProductImei::where('status', 'in_stock')->where('condition', 'new')->count();
        $usedStockCount = ProductImei::where('status', 'in_stock')->where('condition', 'used')->count();
        $totalCostValue = (float) ProductImei::where('status', 'in_stock')->sum('purchase_cost');

        $summary = [
            'in_stock_count' => $inStockCount,
            'new_stock_count' => $newStockCount,
            'used_stock_count' => $usedStockCount,
            'total_cost_value' => round($totalCostValue, 2),
        ];

        return Inertia::render('MobilePhones/Index', [
            'imeis' => $imeis,
            'products' => $products,
            'brands' => $brands,
            'filters' => [
                'search' => $search,
                'condition' => $condition,
                'status' => $status,
                'pta_status' => $ptaStatus,
                'brand' => $brand,
                'per_page' => $perPage,
            ],
            'summary' => $summary,
        ]);
    }

    public function store(Request $request, string $currentTeam): RedirectResponse
    {
        $validated = $request->validate([
            'brand' => ['required', 'string', 'max:255'],
            'model_name' => ['required', 'string', 'max:255'],
            'color' => ['nullable', 'string', 'max:255'],
            'storage' => ['nullable', 'string', 'max:255'],
            'condition' => ['required', 'string', 'in:new,used'],
            'pta_status' => ['required', 'string', 'in:approved,non_pta,jv,cpid,software'],
            'imei_1' => ['required', 'string', 'max:255', 'unique:product_imeis,imei_1'],
            'imei_2' => ['nullable', 'string', 'max:255', 'different:imei_1', 'unique:product_imeis,imei_2'],
            'purchase_cost' => ['required', 'numeric', 'min:0'],
            'sale_price' => ['required', 'numeric', 'min:0'],
            'warranty_days' => ['nullable', 'integer', 'min:0'],
        ]);

        DB::transaction(function () use ($validated) {
            $product = Product::firstOrCreate(
                [
                    'name' => $validated['model_name'],
                    'brand' => $validated['brand'],
                    'is_serialized' => true,
                ],
                [
                    'category' => 'Mobile Handsets',
                    'sale_price' => $validated['sale_price'],
                    'cost_price' => 0.00,
                    'stock_quantity' => 0,
                    'alert_quantity' => 2,
                ]
            );

            // Update sale price if modified
            if ((float) $product->sale_price !== (float) $validated['sale_price']) {
                $product->update(['sale_price' => $validated['sale_price']]);
            }

            ProductImei::create([
                'product_id' => $product->id,
                'imei_1' => $validated['imei_1'],
                'imei_2' => $validated['imei_2'] ?? null,
                'color' => $validated['color'] ?? null,
                'storage' => $validated['storage'] ?? null,
                'condition' => $validated['condition'],
                'pta_status' => $validated['pta_status'],
                'purchase_cost' => $validated['purchase_cost'],
                'warranty_days' => $validated['warranty_days'] ?? 0,
                'status' => 'in_stock',
            ]);
        });

        return redirect()->back()->with('success', 'Mobile phone added to stock successfully.');
    }

    public function storeUsedPurchase(Request $request, string $currentTeam): RedirectResponse
    {
        $validated = $request->validate([
            'seller_name' => ['required', 'string', 'max:255'],
            'seller_father_name' => ['nullable', 'string', 'max:255'],
            'seller_cnic' => ['required', 'string', 'max:255'],
            'seller_phone' => ['required', 'string', 'max:255'],
            'seller_address' => ['nullable', 'string', 'max:500'],
            'brand' => ['required', 'string', 'max:255'],
            'device_model' => ['required', 'string', 'max:255'],
            'color' => ['nullable', 'string', 'max:255'],
            'storage' => ['nullable', 'string', 'max:255'],
            'pta_status' => ['required', 'string', 'in:approved,non_pta,jv,cpid,software'],
            'imei_1' => ['required', 'string', 'max:255', 'unique:product_imeis,imei_1'],
            'imei_2' => ['nullable', 'string', 'max:255', 'different:imei_1', 'unique:product_imeis,imei_2'],
            'purchase_amount' => ['required', 'numeric', 'min:0'],
            'sale_price' => ['required', 'numeric', 'min:0'],
            'payment_method' => ['required', 'string', 'in:cash,bank,jazzcash,easypaisa'],
        ]);

        DB::transaction(function () use ($validated) {
            $nextId = (UsedPhonePurchase::max('id') ?? 0) + 1;
            $voucherNo = 'VOUCH-'.str_pad((string) $nextId, 5, '0', STR_PAD_LEFT);

            UsedPhonePurchase::create([
                'voucher_no' => $voucherNo,
                'seller_name' => $validated['seller_name'],
                'seller_father_name' => $validated['seller_father_name'] ?? null,
                'seller_cnic' => $validated['seller_cnic'],
                'seller_phone' => $validated['seller_phone'],
                'seller_address' => $validated['seller_address'] ?? null,
                'device_model' => $validated['device_model'],
                'imei_1' => $validated['imei_1'],
                'imei_2' => $validated['imei_2'] ?? null,
                'purchase_amount' => $validated['purchase_amount'],
                'payment_method' => $validated['payment_method'],
                'agreement_signed' => true,
            ]);

            $product = Product::firstOrCreate(
                [
                    'name' => $validated['device_model'],
                    'brand' => $validated['brand'],
                    'is_serialized' => true,
                ],
                [
                    'category' => 'Mobile Handsets',
                    'sale_price' => $validated['sale_price'],
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
                'pta_status' => $validated['pta_status'],
                'purchase_cost' => $validated['purchase_amount'],
                'warranty_days' => 7,
                'status' => 'in_stock',
            ]);
        });

        return redirect()->back()->with('success', 'Used phone purchased and added to mobile stock.');
    }
}
