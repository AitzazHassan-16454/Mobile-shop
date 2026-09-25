<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductImei;
use App\Models\StockTransfer;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class StockTransferController extends Controller
{
    public function index(Request $request, string $currentTeam): Response
    {
        $search = trim($request->input('search', ''));
        $status = $request->input('status', 'all');
        $perPage = (int) $request->input('per_page', 15);
        if (! in_array($perPage, [10, 15, 25, 50, 100, 250, 500], true)) {
            $perPage = 15;
        }

        $query = StockTransfer::query()->with(['fromTeam', 'toTeam', 'sender', 'items.product', 'items.imei']);

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('reference_no', 'like', "%{$search}%")
                    ->orWhereHas('fromTeam', fn ($t) => $t->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('toTeam', fn ($t) => $t->where('name', 'like', "%{$search}%"));
            });
        }

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        $transfers = $query->latest()->paginate($perPage)->withQueryString();

        $user = $request->user();
        $currentTeamModel = Team::where('slug', $currentTeam)->first();

        return Inertia::render('StockTransfers/Index', [
            'transfers' => $transfers,
            'teams' => $user->teams()
                ->orderBy('name')
                ->get(['teams.id', 'teams.name', 'teams.slug', 'teams.is_personal']),
            'currentTeam' => $currentTeamModel ? ['id' => $currentTeamModel->id, 'name' => $currentTeamModel->name] : null,
            'catalog' => [
                'imeis' => ProductImei::query()
                    ->with(['product:id,name,brand'])
                    ->where('status', 'in_stock')
                    ->orderBy('imei_1')
                    ->get(['id', 'product_id', 'imei_1', 'imei_2', 'color', 'storage', 'purchase_cost']),
                'accessories' => Product::query()
                    ->where('is_serialized', false)
                    ->where('stock_quantity', '>', 0)
                    ->orderBy('name')
                    ->get(['id', 'name', 'brand', 'sale_price', 'stock_quantity']),
            ],
            'filters' => [
                'search' => $search,
                'status' => $status,
                'per_page' => $perPage,
            ],
            'summary' => [
                'total' => StockTransfer::count(),
                'in_transit' => StockTransfer::where('status', 'in_transit')->count(),
                'received' => StockTransfer::where('status', 'received')->count(),
                'total_units' => DB::table('stock_transfer_items')->sum('quantity'),
            ],
        ]);
    }

    public function store(Request $request, string $currentTeam): RedirectResponse
    {
        $user = $request->user();
        $currentTeamModel = Team::where('slug', $currentTeam)->firstOrFail();

        $validated = $request->validate([
            'to_team_id' => ['required', 'exists:teams,id', 'different:'.$currentTeamModel->id],
            'note' => ['nullable', 'string', 'max:1000'],
            'imei_ids' => ['nullable', 'array'],
            'imei_ids.*' => ['integer', 'exists:product_imeis,id'],
            'accessories' => ['nullable', 'array'],
            'accessories.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'accessories.*.quantity' => ['required', 'integer', 'min:1', 'max:999999'],
        ]);

        $imeiIds = $validated['imei_ids'] ?? [];
        $accessories = $validated['accessories'] ?? [];

        if ($imeiIds === [] && $accessories === []) {
            return redirect()->back()->withErrors([
                'items' => 'Select at least one IMEI or accessory to transfer.',
            ]);
        }

        DB::transaction(function () use ($validated, $imeiIds, $accessories, $currentTeamModel, $user) {
            $nextId = (StockTransfer::max('id') ?? 0) + 1;
            $referenceNo = 'TRF-'.str_pad((string) $nextId, 6, '0', STR_PAD_LEFT);

            $transfer = StockTransfer::create([
                'reference_no' => $referenceNo,
                'from_team_id' => $currentTeamModel->id,
                'to_team_id' => $validated['to_team_id'],
                'status' => 'in_transit',
                'note' => $validated['note'] ?? null,
                'sent_by' => $user->id,
            ]);

            $imeis = ProductImei::query()
                ->whereIn('id', $imeiIds)
                ->where('status', 'in_stock')
                ->get();

            foreach ($imeis as $imei) {
                $transfer->items()->create([
                    'product_id' => $imei->product_id,
                    'product_imei_id' => $imei->id,
                    'quantity' => 1,
                ]);
            }

            foreach ($accessories as $item) {
                $transfer->items()->create([
                    'product_id' => $item['product_id'],
                    'product_imei_id' => null,
                    'quantity' => $item['quantity'],
                ]);
            }
        });

        return redirect()->back()->with('success', 'Stock transfer created successfully.');
    }

    public function receive(Request $request, string $currentTeam, StockTransfer $transfer): RedirectResponse
    {
        if ($transfer->status === 'in_transit') {
            $transfer->update([
                'status' => 'received',
                'received_at' => now(),
            ]);

            return redirect()->back()->with('success', 'Transfer marked as received.');
        }

        return redirect()->back()->with('error', 'This transfer is no longer in transit.');
    }

    public function destroy(Request $request, string $currentTeam, StockTransfer $transfer): RedirectResponse
    {
        if ($transfer->status !== 'in_transit') {
            return redirect()->back()->with('error', 'Only in-transit transfers can be removed.');
        }

        $transfer->delete();

        return redirect()->back()->with('success', 'Stock transfer removed.');
    }
}
