<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use App\Models\SupplierLedger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class SupplierController extends Controller
{
    public function index(Request $request, string $currentTeam): Response
    {
        $search = trim($request->input('search', ''));
        $balanceFilter = $request->input('balance_filter', 'all');
        $perPage = (int) $request->input('per_page', 15);
        if (! in_array($perPage, [10, 15, 25, 50, 100, 250, 500], true)) {
            $perPage = 15;
        }

        $query = Supplier::query()->with(['ledgers' => fn ($ledgerQuery) => $ledgerQuery->latest()->limit(50)]);

        if ($search !== '') {
            $query->where(function ($supplierQuery) use ($search) {
                $supplierQuery->where('name', 'like', "%{$search}%")
                    ->orWhere('company', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if ($balanceFilter === 'payable') {
            $query->where('current_balance', '>', 0);
        } elseif ($balanceFilter === 'advance') {
            $query->where('current_balance', '<', 0);
        } elseif ($balanceFilter === 'zero') {
            $query->where('current_balance', 0);
        }

        return Inertia::render('Suppliers/Index', [
            'suppliers' => $query->latest()->paginate($perPage)->withQueryString(),
            'filters' => [
                'search' => $search,
                'balance_filter' => $balanceFilter,
                'per_page' => $perPage,
            ],
            'summary' => [
                'total_suppliers' => Supplier::count(),
                'total_payables' => (float) Supplier::where('current_balance', '>', 0)->sum('current_balance'),
                'total_credits' => (float) abs(Supplier::where('current_balance', '<', 0)->sum('current_balance')),
            ],
        ]);
    }

    public function store(Request $request, string $currentTeam): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'company' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'opening_balance' => ['nullable', 'numeric'],
            'balance_type' => ['nullable', 'string', 'in:due,advance'],
        ]);

        DB::transaction(function () use ($validated): void {
            $rawBalance = (float) ($validated['opening_balance'] ?? 0);
            $balanceType = $validated['balance_type'] ?? 'due';

            if ($balanceType === 'advance' && $rawBalance > 0) {
                $openingBalance = -$rawBalance;
            } else {
                $openingBalance = $rawBalance;
            }

            $supplier = Supplier::create([
                'name' => $validated['name'],
                'company' => $validated['company'] ?? null,
                'phone' => $validated['phone'] ?? null,
                'address' => $validated['address'] ?? null,
                'current_balance' => $openingBalance,
            ]);

            if ($openingBalance != 0.0) {
                SupplierLedger::create([
                    'supplier_id' => $supplier->id,
                    'type' => 'adjustment',
                    'amount' => abs($openingBalance),
                    'balance_after' => $openingBalance,
                    'reference_id' => 'OPENING',
                    'notes' => $openingBalance > 0
                        ? 'Opening Balance (Payable / Udhaar)'
                        : 'Opening Balance (Advance / Peshgi)',
                ]);
            }
        });

        return redirect()->back()->with('success', 'Supplier added successfully.');
    }

    public function update(Request $request, string $currentTeam, Supplier $supplier): RedirectResponse
    {
        $supplier->update($request->validate([
            'name' => ['required', 'string', 'max:255'],
            'company' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
        ]));

        return redirect()->back()->with('success', 'Supplier details updated.');
    }

    public function recordPurchase(Request $request, string $currentTeam, Supplier $supplier): RedirectResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0'],
            'reference_id' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        DB::transaction(function () use ($supplier, $validated): void {
            $supplier->refresh();
            $newBalance = round((float) $supplier->current_balance + (float) $validated['amount'], 2);
            $supplier->update(['current_balance' => $newBalance]);

            SupplierLedger::create([
                'supplier_id' => $supplier->id,
                'type' => 'purchase',
                'amount' => $validated['amount'],
                'balance_after' => $newBalance,
                'reference_id' => $validated['reference_id'] ?? null,
                'notes' => $validated['notes'] ?? 'Purchase payable recorded',
            ]);
        });

        return redirect()->back()->with('success', 'Supplier purchase payable recorded.');
    }

    public function recordPayment(Request $request, string $currentTeam, Supplier $supplier): RedirectResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0'],
            'reference_id' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        DB::transaction(function () use ($supplier, $validated): void {
            $supplier->refresh();
            $newBalance = round((float) $supplier->current_balance - (float) $validated['amount'], 2);
            $supplier->update(['current_balance' => $newBalance]);

            SupplierLedger::create([
                'supplier_id' => $supplier->id,
                'type' => 'payment',
                'amount' => $validated['amount'],
                'balance_after' => $newBalance,
                'reference_id' => $validated['reference_id'] ?? null,
                'notes' => $validated['notes'] ?? 'Payment made to supplier',
            ]);
        });

        return redirect()->back()->with('success', 'Supplier payment recorded.');
    }

    public function destroy(Request $request, string $currentTeam, Supplier $supplier): RedirectResponse
    {
        $supplier->delete();

        return redirect()->back()->with('success', 'Supplier deleted.');
    }
}
