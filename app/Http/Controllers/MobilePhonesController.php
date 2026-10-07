<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductImei;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MobilePhonesController extends Controller
{
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
            'purchase_cost' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'sale_price' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'warranty_days' => ['nullable', 'integer', 'min:0'],
        ], [
            'sale_price.max' => 'The phone sale price cannot exceed Rs 1,000,000 (1 Million) / موبائل کی فروخت قیمت 10 لاکھ سے زیادہ نہیں ہو سکتی۔',
            'purchase_cost.max' => 'The purchase cost cannot exceed Rs 1,000,000 (1 Million) / خریداری لاگت 10 لاکھ سے زیادہ نہیں ہو سکتی۔',
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

            $product->update([
                'stock_quantity' => $product->inStockImeis()->count(),
            ]);
        });

        return redirect()->back()->with('success', 'Mobile phone added to stock successfully.');
    }
}
