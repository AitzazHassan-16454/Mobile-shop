<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Models\Customer;
use App\Models\CustomerLedger;
use App\Services\CsvService;
use App\Services\XlsxService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Collection;
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

        $perPage = (int) $request->input('per_page', 15);
        if ($perPage <= 0 || $perPage > 500) {
            $perPage = 15;
        }

        $customers = $query->orderBy('name', 'asc')->paginate($perPage)->withQueryString();

        $summary = [
            'total_customers' => Customer::count(),
            'total_receivables' => (float) Customer::where('current_balance', '>', 0)->sum('current_balance'),
            'total_advances' => (float) abs(Customer::where('current_balance', '<', 0)->sum('current_balance')),
        ];

        return Inertia::render('Customers/Index', [
            'customers' => $customers,
            'shopInfo' => [
                'name' => AppSetting::where('key', 'shop_name')->value('value') ?? 'Horizon Studio',
                'phone' => AppSetting::where('key', 'shop_phone')->value('value') ?? '+92 300 1234567',
                'address' => AppSetting::where('key', 'shop_address')->value('value') ?? 'Main Mobile Market, Shop #12',
            ],
            'filters' => [
                'search' => $search,
                'balance_filter' => $balanceFilter,
                'per_page' => $perPage,
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

    public function statement(Request $request, string $currentTeam, Customer $customer): JsonResponse
    {
        return response()->json([
            'entity' => [
                'id' => $customer->id,
                'name' => $customer->name,
                'phone' => $customer->phone,
                'address' => $customer->address,
                'current_balance' => (float) $customer->current_balance,
            ],
            'entries' => $this->statementEntries($customer)->values()->all(),
        ]);
    }

    public function statementExport(Request $request, string $currentTeam, Customer $customer): HttpResponse
    {
        $format = $request->query('format', 'csv') === 'xlsx' ? 'xlsx' : 'csv';
        $headers = ['Date', 'Type', 'Reference', 'Notes', 'Debit', 'Credit', 'Balance'];
        $rows = $this->statementEntries($customer)
            ->map(fn (array $entry) => collect($entry)->only($headers)->all())
            ->values()
            ->all();

        $content = $format === 'xlsx'
            ? XlsxService::build($headers, $rows)
            : CsvService::build($headers, $rows);

        return $this->downloadExport($content, "khata-statement-customer-{$customer->id}", $format);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function statementEntries(Customer $customer): Collection
    {
        $previous = 0.00;

        return $customer->ledgers()
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->map(function (CustomerLedger $entry) use (&$previous): array {
                $balanceAfter = (float) $entry->balance_after;
                $delta = round($balanceAfter - $previous, 2);
                $amount = (float) $entry->amount;
                $previous = $balanceAfter;

                return [
                    'Date' => $entry->created_at->format('Y-m-d H:i'),
                    'Type' => $entry->type->value,
                    'Reference' => $entry->reference_id ?? '',
                    'Notes' => $entry->notes ?? '',
                    'Debit' => $delta > 0 ? $amount : 0.0,
                    'Credit' => $delta < 0 ? $amount : 0.0,
                    'Balance' => $balanceAfter,
                ];
            });
    }

    public function destroy(Request $request, string $currentTeam, Customer $customer): RedirectResponse
    {
        $customer->delete();

        return redirect()->back()->with('success', 'Customer deleted.');
    }
}
