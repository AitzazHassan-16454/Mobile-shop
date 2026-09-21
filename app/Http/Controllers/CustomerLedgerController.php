<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Models\Customer;
use App\Models\CustomerLedger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class CustomerLedgerController extends Controller
{
    public function index(Request $request, string $currentTeam): Response
    {
        $search = trim($request->input('search', ''));
        $balanceFilter = $request->input('balance_filter', 'all');

        $query = Customer::query()->with(['ledgers' => function ($q) {
            $q->latest()->limit(30);
        }]);

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('address', 'like', "%{$search}%");
            });
        }

        if ($balanceFilter === 'has_debt') {
            $query->where('current_balance', '>', 0);
        } elseif ($balanceFilter === 'advance') {
            $query->where('current_balance', '<', 0);
        } elseif ($balanceFilter === 'zero') {
            $query->where('current_balance', 0);
        }

        $customers = $query->orderBy('name', 'asc')->paginate(15)->withQueryString();

        $summary = [
            'total_customers' => Customer::count(),
            'total_receivables' => (float) Customer::where('current_balance', '>', 0)->sum('current_balance'),
            'total_advances' => (float) abs(Customer::where('current_balance', '<', 0)->sum('current_balance')),
        ];

        return Inertia::render('Customers/Index', [
            'customers' => $customers,
            'filters' => [
                'search' => $search,
                'balance_filter' => $balanceFilter,
            ],
            'summary' => $summary,
        ]);
    }

    public function store(Request $request, string $currentTeam): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:50', 'unique:customers,phone'],
            'address' => ['nullable', 'string', 'max:500'],
            'initial_balance' => ['nullable', 'numeric'],
        ]);

        $initialBalance = (float) ($validated['initial_balance'] ?? 0.00);

        DB::transaction(function () use ($validated, $initialBalance) {
            $customer = Customer::create([
                'name' => $validated['name'],
                'phone' => $validated['phone'],
                'address' => $validated['address'] ?? null,
                'current_balance' => $initialBalance,
            ]);

            if ($initialBalance != 0) {
                CustomerLedger::create([
                    'customer_id' => $customer->id,
                    'type' => 'adjustment',
                    'amount' => abs($initialBalance),
                    'balance_after' => $initialBalance,
                    'reference_id' => 'OPENING-BAL',
                    'notes' => $initialBalance > 0 ? 'Opening Balance (Udhaar)' : 'Opening Balance (Advance)',
                ]);
            }
        });

        return redirect()->back()->with('success', 'Customer added successfully.');
    }

    public function update(Request $request, string $currentTeam, Customer $customer): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:50', 'unique:customers,phone,'.$customer->id],
            'address' => ['nullable', 'string', 'max:500'],
        ]);

        $customer->update($validated);

        return redirect()->back()->with('success', 'Customer updated.');
    }

    public function recordPayment(Request $request, string $currentTeam, Customer $customer): RedirectResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0'],
            'payment_method' => ['required', 'string', 'in:cash,jazzcash,easypaisa,bank'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        DB::transaction(function () use ($customer, $validated) {
            $paidAmount = (float) $validated['amount'];
            $newBalance = round((float) $customer->current_balance - $paidAmount, 2);

            $customer->update([
                'current_balance' => $newBalance,
            ]);

            $refNo = 'RCPT-'.str_pad((string) ((CustomerLedger::max('id') ?? 0) + 1), 5, '0', STR_PAD_LEFT);

            CustomerLedger::create([
                'customer_id' => $customer->id,
                'type' => 'payment',
                'amount' => $paidAmount,
                'balance_after' => $newBalance,
                'reference_id' => $refNo,
                'notes' => $validated['notes'] ?? 'Payment Received ('.ucfirst($validated['payment_method']).')',
            ]);
        });

        return redirect()->back()->with('success', 'Payment recorded successfully.');
    }

    public function destroy(Request $request, string $currentTeam, Customer $customer): RedirectResponse
    {
        $customer->delete();

        return redirect()->back()->with('success', 'Customer deleted.');
    }
}
