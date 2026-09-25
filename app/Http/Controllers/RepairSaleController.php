<?php

namespace App\Http\Controllers;

use App\Enums\PaymentMethod;
use App\Models\RepairSale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RepairSaleController extends Controller
{
    public function index(Request $request, string $currentTeam): Response
    {
        $search = trim($request->input('search', ''));
        $perPage = (int) $request->input('per_page', 15);
        if (! in_array($perPage, [10, 15, 25, 50, 100, 250, 500], true)) {
            $perPage = 15;
        }

        $query = RepairSale::query()->with('user:id,name');

        if ($search !== '') {
            $query->where('item_name', 'like', "%{$search}%");
        }

        $repairSales = $query->latest()->paginate($perPage)->withQueryString();

        $summary = [
            'today_total' => (float) RepairSale::whereDate('created_at', now()->today())->sum('total_amount'),
            'today_count' => RepairSale::whereDate('created_at', now()->today())->count(),
            'today_profit' => (float) RepairSale::whereDate('created_at', now()->today())
                ->selectRaw('COALESCE(SUM(total_amount), 0) - COALESCE(SUM(cost_price * quantity), 0) AS profit')
                ->value('profit'),
            'month_total' => (float) RepairSale::whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->sum('total_amount'),
            'month_profit' => (float) RepairSale::whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->selectRaw('COALESCE(SUM(total_amount), 0) - COALESCE(SUM(cost_price * quantity), 0) AS profit')
                ->value('profit'),
            'all_time_total' => (float) RepairSale::sum('total_amount'),
        ];

        return Inertia::render('RepairSales/Index', [
            'repairSales' => $repairSales,
            'paymentMethods' => collect([PaymentMethod::Cash, PaymentMethod::JazzCash, PaymentMethod::EasyPaisa, PaymentMethod::Bank, PaymentMethod::Udhaar])
                ->map(fn (PaymentMethod $method) => [
                    'value' => $method->value,
                    'label' => $method->label(),
                ])
                ->values()
                ->all(),
            'filters' => [
                'search' => $search,
                'per_page' => $perPage,
            ],
            'summary' => $summary,
        ]);
    }

    public function store(Request $request, string $currentTeam): RedirectResponse
    {
        $validated = $request->validate([
            'item_name' => ['required', 'string', 'max:255'],
            'quantity' => ['nullable', 'numeric', 'min:0.01', 'max:999999.99'],
            'cost_price' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'sell_price' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'payment_method' => ['required', 'string', 'in:cash,jazzcash,easypaisa,bank,udhaar'],
        ], [
            'cost_price.required' => 'Cost price is required.',
            'cost_price.max' => 'Cost price cannot exceed Rs 1,000,000 (1 Million) / لاگت قیمت 10 لاکھ سے زیادہ نہیں ہو سکتی۔',
            'sell_price.required' => 'Sell price is required.',
            'sell_price.max' => 'Sell price cannot exceed Rs 1,000,000 (1 Million) / فروخت قیمت 10 لاکھ سے زیادہ نہیں ہو سکتی۔',
        ]);

        $quantity = (float) ($validated['quantity'] ?? 1);
        $costPrice = round((float) $validated['cost_price'], 2);
        $sellPrice = round((float) $validated['sell_price'], 2);

        RepairSale::create([
            'item_name' => trim($validated['item_name']),
            'quantity' => $quantity,
            'cost_price' => $costPrice,
            'sell_price' => $sellPrice,
            'total_amount' => round($quantity * $sellPrice, 2),
            'payment_method' => $validated['payment_method'],
            'user_id' => $request->user()?->id,
        ]);

        return redirect()->back()->with('success', 'Repair item sold successfully.');
    }

    public function destroy(Request $request, string $currentTeam, RepairSale $repairSale): RedirectResponse
    {
        $repairSale->delete();

        return redirect()->back()->with('success', 'Repair sale deleted.');
    }
}
