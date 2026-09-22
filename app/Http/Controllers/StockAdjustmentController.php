<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductImei;
use App\Models\StockAdjustment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class StockAdjustmentController extends Controller
{
    public function index(Request $request, string $currentTeam): Response
    {
        $search = trim($request->input('search', ''));
        $reason = $request->input('reason', 'all');
        $type = $request->input('type', 'all');

        $query = StockAdjustment::query()
            ->with(['product', 'imei', 'user']);

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->whereHas('product', function ($pQuery) use ($search) {
                    $pQuery->where('name', 'like', "%{$search}%")
                        ->orWhere('brand', 'like', "%{$search}%")
                        ->orWhere('barcode', 'like', "%{$search}%");
                })->orWhereHas('imei', function ($iQuery) use ($search) {
                    $iQuery->where('imei_1', 'like', "%{$search}%")
                        ->orWhere('imei_2', 'like', "%{$search}%");
                })->orWhere('notes', 'like', "%{$search}%");
            });
        }

        if ($type !== 'all' && in_array($type, ['addition', 'subtraction'])) {
            $query->where('type', $type);
        }

        if ($reason !== 'all' && $reason !== '') {
            $query->where('reason', $reason);
        }

        $adjustments = $query->latest()->paginate(15)->withQueryString();

        $products = Product::query()
            ->with(['imeis' => fn ($q) => $q->where('status', 'in_stock')])
            ->orderBy('name')
            ->get();

        $summary = [
            'total_adjustments' => StockAdjustment::count(),
            'total_additions' => (int) StockAdjustment::where('type', 'addition')->sum('quantity'),
            'total_subtractions' => (int) StockAdjustment::where('type', 'subtraction')->sum('quantity'),
            'damaged_count' => StockAdjustment::where('reason', 'damaged')->count(),
        ];

        return Inertia::render('StockAdjustments/Index', [
            'adjustments' => $adjustments,
            'products' => $products,
            'filters' => [
                'search' => $search,
                'reason' => $reason,
                'type' => $type,
            ],
            'summary' => $summary,
        ]);
    }

    public function store(Request $request, string $currentTeam): RedirectResponse
    {
        $validated = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'product_imei_id' => ['nullable', 'exists:product_imeis,id'],
            'type' => ['required', 'string', 'in:addition,subtraction'],
            'quantity' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'in:damaged,lost,stolen,audit_reconciliation,found,other'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        DB::transaction(function () use ($validated, $request): void {
            $product = Product::findOrFail($validated['product_id']);

            if (! $product->is_serialized) {
                if ($validated['type'] === 'addition') {
                    $product->increment('stock_quantity', $validated['quantity']);
                } else {
                    $product->decrement('stock_quantity', min($product->stock_quantity, $validated['quantity']));
                }
            } elseif (! empty($validated['product_imei_id'])) {
                $imei = ProductImei::find($validated['product_imei_id']);
                if ($imei && $validated['type'] === 'subtraction') {
                    if ($validated['reason'] === 'damaged') {
                        $imei->update(['status' => 'repairing']);
                    } elseif (in_array($validated['reason'], ['lost', 'stolen'])) {
                        $imei->update(['status' => 'returned']);
                    }
                }
            }

            StockAdjustment::create([
                'product_id' => $validated['product_id'],
                'product_imei_id' => $validated['product_imei_id'] ?? null,
                'user_id' => $request->user()?->id,
                'type' => $validated['type'],
                'quantity' => $validated['quantity'],
                'reason' => $validated['reason'],
                'notes' => $validated['notes'] ?? null,
            ]);
        });

        return redirect()->back()->with('success', 'Stock adjustment recorded successfully.');
    }

    public function destroy(Request $request, string $currentTeam, StockAdjustment $stockAdjustment): RedirectResponse
    {
        DB::transaction(function () use ($stockAdjustment): void {
            $product = $stockAdjustment->product;
            if ($product && ! $product->is_serialized) {
                // Revert stock change
                if ($stockAdjustment->type === 'addition') {
                    $product->decrement('stock_quantity', min($product->stock_quantity, $stockAdjustment->quantity));
                } else {
                    $product->increment('stock_quantity', $stockAdjustment->quantity);
                }
            }

            $stockAdjustment->delete();
        });

        return redirect()->back()->with('success', 'Stock adjustment record reverted.');
    }
}
