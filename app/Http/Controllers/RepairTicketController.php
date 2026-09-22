<?php

namespace App\Http\Controllers;

use App\Enums\RepairStatus;
use App\Models\AppSetting;
use App\Models\Product;
use App\Models\RepairTicket;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class RepairTicketController extends Controller
{
    public function index(Request $request, string $currentTeam): Response
    {
        $search = trim($request->input('search', ''));
        $statusFilter = $request->input('status', 'all');
        $perPage = (int) $request->input('per_page', 15);
        if (! in_array($perPage, [10, 15, 25, 50, 100, 250, 500], true)) {
            $perPage = 15;
        }

        $query = RepairTicket::query();

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('ticket_no', 'like', "%{$search}%")
                    ->orWhere('customer_name', 'like', "%{$search}%")
                    ->orWhere('customer_phone', 'like', "%{$search}%")
                    ->orWhere('device_model', 'like', "%{$search}%")
                    ->orWhere('imei', 'like', "%{$search}%");
            });
        }

        if ($statusFilter !== 'all' && $statusFilter !== '') {
            $query->where('status', $statusFilter);
        }

        $tickets = $query->latest()->paginate($perPage)->withQueryString();

        $spareParts = Product::query()
            ->where('is_serialized', false)
            ->where('stock_quantity', '>', 0)
            ->select('id', 'name', 'brand', 'category', 'cost_price', 'sale_price', 'stock_quantity')
            ->get();

        $shopInfo = [
            'name' => AppSetting::where('key', 'shop_name')->value('value') ?? 'Faizan Mobile & POS',
            'phone' => AppSetting::where('key', 'shop_phone')->value('value') ?? '+92 300 1234567',
            'address' => AppSetting::where('key', 'shop_address')->value('value') ?? 'Main Mobile Market, Shop #12',
        ];

        $summary = [
            'total_active' => RepairTicket::whereNotIn('status', ['delivered', 'cancelled'])->count(),
            'received_count' => RepairTicket::where('status', 'received')->count(),
            'in_progress_count' => RepairTicket::whereIn('status', ['in_diagnosis', 'waiting_parts'])->count(),
            'ready_count' => RepairTicket::where('status', 'ready')->count(),
            'delivered_revenue' => (float) RepairTicket::where('status', 'delivered')->sum('estimated_cost'),
        ];

        return Inertia::render('Repairs/Index', [
            'tickets' => $tickets,
            'spareParts' => $spareParts,
            'shopInfo' => $shopInfo,
            'filters' => [
                'search' => $search,
                'status' => $statusFilter,
                'per_page' => $perPage,
            ],
            'summary' => $summary,
            'latestRepair' => session('latest_repair'),
        ]);
    }

    public function store(Request $request, string $currentTeam): RedirectResponse
    {
        $validated = $request->validate([
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_phone' => ['required', 'string', 'max:255'],
            'device_model' => ['required', 'string', 'max:255'],
            'imei' => ['nullable', 'string', 'max:255'],
            'pattern_or_pin' => ['nullable', 'string', 'max:255'],
            'problem_description' => ['required', 'string'],
            'condition_notes' => ['nullable', 'string'],
            'estimated_cost' => ['required', 'numeric', 'min:0'],
            'advance_paid' => ['nullable', 'numeric', 'min:0'],
        ]);

        $nextId = (RepairTicket::max('id') ?? 0) + 1;
        $ticketNo = 'REP-'.str_pad((string) $nextId, 5, '0', STR_PAD_LEFT);

        $validated['ticket_no'] = $ticketNo;
        $validated['advance_paid'] = $validated['advance_paid'] ?? 0.00;
        $validated['status'] = RepairStatus::Received;

        $ticket = RepairTicket::create($validated);

        return redirect()->back()->with('latest_repair', $ticket);
    }

    public function updateStatus(Request $request, string $currentTeam, RepairTicket $ticket): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string', 'in:received,in_diagnosis,waiting_parts,ready,delivered,cancelled'],
            'estimated_cost' => ['nullable', 'numeric', 'min:0'],
            'advance_paid' => ['nullable', 'numeric', 'min:0'],
        ]);

        $updateData = [
            'status' => $validated['status'],
        ];

        if (isset($validated['estimated_cost'])) {
            $updateData['estimated_cost'] = $validated['estimated_cost'];
        }

        if (isset($validated['advance_paid'])) {
            $updateData['advance_paid'] = $validated['advance_paid'];
        }

        if ($validated['status'] === 'delivered' && ! $ticket->delivered_at) {
            $updateData['delivered_at'] = now();
        }

        $ticket->update($updateData);

        return redirect()->back()->with('success', 'Repair ticket status updated successfully.');
    }

    public function addSparePart(Request $request, string $currentTeam, RepairTicket $ticket): RedirectResponse
    {
        $validated = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        DB::transaction(function () use ($ticket, $validated) {
            $product = Product::lockForUpdate()->findOrFail($validated['product_id']);

            if ($product->stock_quantity < $validated['quantity']) {
                throw ValidationException::withMessages([
                    'product_id' => ["Insufficient stock for {$product->name}."],
                ]);
            }

            $product->decrement('stock_quantity', $validated['quantity']);

            $partCost = round($product->cost_price * $validated['quantity'], 2);
            $ticket->increment('spare_parts_cost', $partCost);

            if ($ticket->status->value === 'received' || $ticket->status->value === 'waiting_parts') {
                $ticket->update(['status' => RepairStatus::InDiagnosis]);
            }
        });

        return redirect()->back()->with('success', 'Spare part consumed and stock updated.');
    }

    public function destroy(Request $request, string $currentTeam, RepairTicket $ticket): RedirectResponse
    {
        $ticket->delete();

        return redirect()->back()->with('success', 'Repair ticket deleted.');
    }
}
