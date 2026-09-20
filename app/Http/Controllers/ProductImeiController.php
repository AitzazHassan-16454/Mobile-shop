<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductImei;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProductImeiController extends Controller
{
    public function store(Request $request, string $currentTeam, Product $product): RedirectResponse
    {
        if (! $product->is_serialized) {
            throw ValidationException::withMessages([
                'product' => ['IMEIs can only be added to serialized products.'],
            ]);
        }

        $validated = $request->validate([
            'imei_1' => ['required', 'string', 'max:255', 'unique:product_imeis,imei_1'],
            'imei_2' => ['nullable', 'string', 'max:255', 'different:imei_1', 'unique:product_imeis,imei_2'],
            'color' => ['nullable', 'string', 'max:255'],
            'storage' => ['nullable', 'string', 'max:255'],
            'condition' => ['required', 'string', 'in:new,used'],
            'pta_status' => ['required', 'string', 'in:approved,non_pta,jv,cpid,software'],
            'purchase_cost' => ['required', 'numeric', 'min:0'],
            'warranty_days' => ['nullable', 'integer', 'min:0'],
        ]);

        $validated['status'] = 'in_stock';
        $product->imeis()->create($validated);

        return redirect()->back()->with('success', 'IMEI added successfully.');
    }

    public function bulkStore(Request $request, string $currentTeam, Product $product): RedirectResponse
    {
        if (! $product->is_serialized) {
            throw ValidationException::withMessages([
                'product' => ['IMEIs can only be added to serialized products.'],
            ]);
        }

        $common = $request->validate([
            'color' => ['nullable', 'string', 'max:255'],
            'storage' => ['nullable', 'string', 'max:255'],
            'condition' => ['required', 'string', 'in:new,used'],
            'pta_status' => ['required', 'string', 'in:approved,non_pta,jv,cpid,software'],
            'purchase_cost' => ['required', 'numeric', 'min:0'],
            'warranty_days' => ['nullable', 'integer', 'min:0'],
            'imeis' => ['required', 'array', 'min:1'],
            'imeis.*.imei_1' => ['required', 'string', 'max:255', 'distinct', 'unique:product_imeis,imei_1'],
            'imeis.*.imei_2' => ['nullable', 'string', 'max:255', 'distinct', 'different:imeis.*.imei_1', 'unique:product_imeis,imei_2'],
        ]);

        DB::transaction(function () use ($product, $common) {
            foreach ($common['imeis'] as $item) {
                $product->imeis()->create([
                    'imei_1' => $item['imei_1'],
                    'imei_2' => $item['imei_2'] ?? null,
                    'color' => $common['color'] ?? null,
                    'storage' => $common['storage'] ?? null,
                    'condition' => $common['condition'],
                    'pta_status' => $common['pta_status'],
                    'purchase_cost' => $common['purchase_cost'],
                    'warranty_days' => $common['warranty_days'] ?? 0,
                    'status' => 'in_stock',
                ]);
            }
        });

        $count = count($common['imeis']);

        return redirect()->back()->with('success', "{$count} IMEIs added successfully.");
    }

    public function update(Request $request, string $currentTeam, ProductImei $imei): RedirectResponse
    {
        $validated = $request->validate([
            'imei_1' => ['required', 'string', 'max:255', 'unique:product_imeis,imei_1,'.$imei->id],
            'imei_2' => ['nullable', 'string', 'max:255', 'different:imei_1', 'unique:product_imeis,imei_2,'.$imei->id],
            'color' => ['nullable', 'string', 'max:255'],
            'storage' => ['nullable', 'string', 'max:255'],
            'condition' => ['required', 'string', 'in:new,used'],
            'pta_status' => ['required', 'string', 'in:approved,non_pta,jv,cpid,software'],
            'purchase_cost' => ['required', 'numeric', 'min:0'],
            'warranty_days' => ['nullable', 'integer', 'min:0'],
            'status' => ['required', 'string', 'in:in_stock,sold,repairing,returned'],
        ]);

        $imei->update($validated);

        return redirect()->back()->with('success', 'IMEI record updated successfully.');
    }

    public function destroy(Request $request, string $currentTeam, ProductImei $imei): RedirectResponse
    {
        $imei->delete();

        return redirect()->back()->with('success', 'IMEI record deleted successfully.');
    }
}
