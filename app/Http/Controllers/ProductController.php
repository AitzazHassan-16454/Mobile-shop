<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductImei;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function index(Request $request, string $currentTeam): Response
    {
        $search = trim($request->input('search', ''));
        $category = $request->input('category', 'all');
        $stockStatus = $request->input('stock_status', 'all');

        $query = Product::query()->where('is_serialized', false);

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('brand', 'like', "%{$search}%")
                    ->orWhere('category', 'like', "%{$search}%")
                    ->orWhere('barcode', 'like', "%{$search}%");
            });
        }

        if ($category !== 'all' && $category !== '') {
            $query->where('category', $category);
        }

        if ($stockStatus === 'low_stock') {
            $query->whereColumn('stock_quantity', '<=', 'alert_quantity');
        } elseif ($stockStatus === 'out_of_stock') {
            $query->where('stock_quantity', '<=', 0);
        }

        $perPage = (int) $request->input('per_page', 15);
        if ($perPage <= 0 || $perPage > 500) {
            $perPage = 15;
        }

        $products = $query->latest()->paginate($perPage)->withQueryString();
        $accessoryQuery = Product::query()->where('is_serialized', false);
        $accessoryCount = (clone $accessoryQuery)->count();
        $totalStockUnits = (int) (clone $accessoryQuery)->sum('stock_quantity');
        $totalStockValue = (float) (clone $accessoryQuery)->sum(DB::raw('cost_price * stock_quantity'));

        $categories = (clone $accessoryQuery)
            ->select('category')
            ->distinct()
            ->whereNotNull('category')
            ->pluck('category');

        $brands = (clone $accessoryQuery)
            ->select('brand')
            ->distinct()
            ->whereNotNull('brand')
            ->pluck('brand');

        $summary = [
            'total_products' => $accessoryCount,
            'accessories_count' => $accessoryCount,
            'total_stock_units' => $totalStockUnits,
            'total_stock_value' => round($totalStockValue, 2),
        ];

        return Inertia::render('Products/Index', [
            'products' => $products,
            'filters' => [
                'search' => $search,
                'category' => $category,
                'stock_status' => $stockStatus,
                'per_page' => $perPage,
            ],
            'categories' => $categories,
            'brands' => $brands,
            'summary' => $summary,
        ]);
    }

    public function store(Request $request, string $currentTeam): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'brand' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:255'],
            'barcode' => ['nullable', 'string', 'max:255', 'unique:products,barcode'],
            'is_serialized' => ['required', 'boolean'],
            'sale_price' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'cost_price' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'stock_quantity' => ['nullable', 'integer', 'min:0', 'max:999999'],
            'alert_quantity' => ['required', 'integer', 'min:0', 'max:999999'],
        ], [
            'sale_price.max' => 'The sale price cannot exceed Rs 1,000,000 (1 Million) / فروخت کی قیمت 10 لاکھ سے زیادہ نہیں ہو سکتی۔',
            'cost_price.max' => 'The cost price cannot exceed Rs 1,000,000 (1 Million) / خریداری کی قیمت 10 لاکھ سے زیادہ نہیں ہو سکتی۔',
        ]);

        if (! $validated['is_serialized']) {
            $validated['cost_price'] = $validated['cost_price'] ?? 0.00;
            $validated['stock_quantity'] = $validated['stock_quantity'] ?? 0;
        } else {
            $validated['cost_price'] = 0.00;
            $validated['stock_quantity'] = 0;
        }

        $product = Product::create($validated);

        if ($validated['is_serialized'] && $request->filled('initial_imei_1')) {
            $imeiData = $request->validate([
                'initial_imei_1' => ['required', 'string', 'max:255', 'unique:product_imeis,imei_1'],
                'initial_imei_2' => ['nullable', 'string', 'max:255', 'different:initial_imei_1', 'unique:product_imeis,imei_2'],
                'initial_color' => ['nullable', 'string', 'max:255'],
                'initial_storage' => ['nullable', 'string', 'max:255'],
                'initial_condition' => ['required', 'string', 'in:new,used'],
                'initial_pta_status' => ['required', 'string', 'in:approved,non_pta,jv,cpid,software'],
                'initial_purchase_cost' => ['required', 'numeric', 'min:0', 'max:1000000'],
                'initial_warranty_days' => ['nullable', 'integer', 'min:0'],
            ], [
                'initial_purchase_cost.max' => 'The purchase cost cannot exceed Rs 1,000,000 (1 Million) / خریداری لاگت 10 لاکھ سے زیادہ نہیں ہو سکتی۔',
            ]);

            $product->imeis()->create([
                'imei_1' => $imeiData['initial_imei_1'],
                'imei_2' => $imeiData['initial_imei_2'] ?? null,
                'color' => $imeiData['initial_color'] ?? null,
                'storage' => $imeiData['initial_storage'] ?? null,
                'condition' => $imeiData['initial_condition'],
                'pta_status' => $imeiData['initial_pta_status'],
                'purchase_cost' => $imeiData['initial_purchase_cost'],
                'warranty_days' => $imeiData['initial_warranty_days'] ?? 0,
                'status' => 'in_stock',
            ]);
        }

        return redirect()->back()->with('success', 'Product created successfully.');
    }

    public function update(Request $request, string $currentTeam, Product $product): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'brand' => ['sometimes', 'required', 'string', 'max:255'],
            'category' => ['sometimes', 'required', 'string', 'max:255'],
            'barcode' => ['nullable', 'string', 'max:255', 'unique:products,barcode,'.$product->id],
            'is_serialized' => ['sometimes', 'required', 'boolean'],
            'sale_price' => ['sometimes', 'required', 'numeric', 'min:0', 'max:1000000'],
            'cost_price' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'stock_quantity' => ['nullable', 'integer', 'min:0', 'max:999999'],
            'alert_quantity' => ['sometimes', 'required', 'integer', 'min:0', 'max:999999'],
        ], [
            'sale_price.max' => 'The sale price cannot exceed Rs 1,000,000 (1 Million) / فروخت کی قیمت 10 لاکھ سے زیادہ نہیں ہو سکتی۔',
            'cost_price.max' => 'The cost price cannot exceed Rs 1,000,000 (1 Million) / خریداری کی قیمت 10 لاکھ سے زیادہ نہیں ہو سکتی۔',
        ]);

        $isSerialized = $validated['is_serialized'] ?? $product->is_serialized;

        if (! $isSerialized) {
            if (array_key_exists('cost_price', $validated)) {
                $validated['cost_price'] = $validated['cost_price'] ?? 0.00;
            }
            if (array_key_exists('stock_quantity', $validated)) {
                $validated['stock_quantity'] = $validated['stock_quantity'] ?? 0;
            }
        } else {
            $validated['cost_price'] = 0.00;
            $validated['stock_quantity'] = 0;
        }

        $product->update($validated);

        return redirect()->back()->with('success', 'Product updated successfully.');
    }

    public function destroy(Request $request, string $currentTeam, Product $product): RedirectResponse
    {
        $product->delete();

        return redirect()->back()->with('success', 'Product deleted successfully.');
    }
}
