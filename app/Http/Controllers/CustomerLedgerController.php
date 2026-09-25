<?php

namespace App\Http\Controllers;

use App\Enums\LedgerType;
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
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class CustomerLedgerController extends Controller
{
    public function index(Request $request, string $currentTeam): Response
    {
        $search = trim($request->input('search', ''));
        $balanceFilter = $request->input('balance_filter', 'all');

        $query = Customer::query()->with(['ledgers' => function ($q): void {
            $q->with('user:id,name')->latest()->limit(50);
        }]);

        if ($search !== '') {
            $query->where(function ($q) use ($search): void {
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
            'initial_balance' => ['nullable', 'numeric', 'between:-99999999.99,99999999.99'],
        ]);

        $initialBalance = (float) ($validated['initial_balance'] ?? 0.00);

        DB::transaction(function () use ($request, $validated, $initialBalance): void {
            $customer = Customer::create([
                'name' => $validated['name'],
                'phone' => $validated['phone'],
                'address' => $validated['address'] ?? null,
                'current_balance' => $initialBalance,
            ]);

            if ($initialBalance != 0) {
                CustomerLedger::create([
                    'customer_id' => $customer->id,
                    'user_id' => $request->user()->id,
                    'type' => LedgerType::Adjustment,
                    'amount' => abs($initialBalance),
                    'payment_method' => null,
                    'balance_after' => $initialBalance,
                    'reference_id' => 'OPENING-BAL',
                    'notes' => $initialBalance > 0 ? 'Opening Balance (Udhaar/Due)' : 'Opening Balance (Advance)',
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

    public function recordPayment(Request $request, string $currentTeam, Customer $customer): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0', 'max:99999999.99'],
            'payment_method' => ['required', 'string', 'in:cash,jazzcash,easypaisa,bank,card'],
            'allow_overpayment_as_advance' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $paidAmount = (float) $validated['amount'];
        $currentDue = $customer->due_balance;
        $allowAdvance = $request->boolean('allow_overpayment_as_advance', true);

        if ($currentDue > 0 && $paidAmount > $currentDue && ! $allowAdvance) {
            throw ValidationException::withMessages([
                'amount' => ['Payment amount ('.number_format($paidAmount, 2).') exceeds outstanding due balance of '.number_format($currentDue, 2).'. Enable overpayment advance or enter up to exact due.'],
            ]);
        }

        DB::transaction(function () use ($request, $customer, $validated, $paidAmount, $currentDue): void {
            $customer->lockForUpdate();
            $newBalance = round((float) $customer->current_balance - $paidAmount, 2);
            $customer->update(['current_balance' => $newBalance]);

            $refNo = 'PAY-'.str_pad((string) ((CustomerLedger::max('id') ?? 0) + 1), 6, '0', STR_PAD_LEFT);

            $notes = $validated['notes'];
            if (empty($notes)) {
                if ($currentDue > 0 && $paidAmount >= $currentDue) {
                    $notes = 'Full due settlement ('.ucfirst($validated['payment_method']).')';
                    if ($paidAmount > $currentDue) {
                        $over = round($paidAmount - $currentDue, 2);
                        $notes .= " + Rs. {$over} converted to advance credit";
                    }
                } else {
                    $notes = 'Due payment received ('.ucfirst($validated['payment_method']).')';
                }
            }

            CustomerLedger::create([
                'customer_id' => $customer->id,
                'user_id' => $request->user()->id,
                'type' => LedgerType::Payment,
                'amount' => $paidAmount,
                'payment_method' => $validated['payment_method'],
                'balance_after' => $newBalance,
                'reference_id' => $refNo,
                'notes' => $notes,
            ]);
        });

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Due payment recorded successfully.',
                'customer' => $customer->fresh(),
            ]);
        }

        return redirect()->back()->with('success', 'Payment recorded successfully.');
    }

    public function recordAdvance(Request $request, string $currentTeam, Customer $customer): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0', 'max:99999999.99'],
            'payment_method' => ['required', 'string', 'in:cash,jazzcash,easypaisa,bank,card'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $advanceAmount = (float) $validated['amount'];

        DB::transaction(function () use ($request, $customer, $validated, $advanceAmount): void {
            $customer->lockForUpdate();
            // In our ledger, negative balance = advance credit owed to customer
            $newBalance = round((float) $customer->current_balance - $advanceAmount, 2);
            $customer->update(['current_balance' => $newBalance]);

            $refNo = 'ADV-'.str_pad((string) ((CustomerLedger::max('id') ?? 0) + 1), 6, '0', STR_PAD_LEFT);

            CustomerLedger::create([
                'customer_id' => $customer->id,
                'user_id' => $request->user()->id,
                'type' => LedgerType::Advance,
                'amount' => $advanceAmount,
                'payment_method' => $validated['payment_method'],
                'balance_after' => $newBalance,
                'reference_id' => $refNo,
                'notes' => $validated['notes'] ?? 'Advance received from customer ('.ucfirst($validated['payment_method']).')',
            ]);
        });

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Advance received and credited successfully.',
                'customer' => $customer->fresh(),
            ]);
        }

        return redirect()->back()->with('success', 'Customer advance recorded successfully.');
    }

    public function refundAdvance(Request $request, string $currentTeam, Customer $customer): RedirectResponse|JsonResponse
    {
        $availableAdvance = $customer->advance_balance;

        if ($availableAdvance <= 0.001) {
            throw ValidationException::withMessages([
                'amount' => ['Customer has no available advance balance to refund.'],
            ]);
        }

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0', 'max:99999999.99', "max:{$availableAdvance}"],
            'payment_method' => ['required', 'string', 'in:cash,jazzcash,easypaisa,bank,card'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $refundAmount = (float) $validated['amount'];

        DB::transaction(function () use ($request, $customer, $validated, $refundAmount): void {
            $customer->lockForUpdate();
            // Refunding advance moves balance back towards 0 (balance + refundAmount)
            $newBalance = round((float) $customer->current_balance + $refundAmount, 2);
            $customer->update(['current_balance' => $newBalance]);

            $refNo = 'REF-ADV-'.str_pad((string) ((CustomerLedger::max('id') ?? 0) + 1), 6, '0', STR_PAD_LEFT);

            CustomerLedger::create([
                'customer_id' => $customer->id,
                'user_id' => $request->user()->id,
                'type' => LedgerType::AdvanceReturn,
                'amount' => $refundAmount,
                'payment_method' => $validated['payment_method'],
                'balance_after' => $newBalance,
                'reference_id' => $refNo,
                'notes' => $validated['notes'] ?? 'Advance refund returned to customer ('.ucfirst($validated['payment_method']).')',
            ]);
        });

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Advance refund processed successfully.',
                'customer' => $customer->fresh(),
            ]);
        }

        return redirect()->back()->with('success', 'Advance refund processed successfully.');
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
                'due_balance' => $customer->due_balance,
                'advance_balance' => $customer->advance_balance,
            ],
            'entries' => $this->statementEntries($customer)->values()->all(),
        ]);
    }

    public function statementExport(Request $request, string $currentTeam, Customer $customer): HttpResponse
    {
        $format = $request->query('format', 'csv') === 'xlsx' ? 'xlsx' : 'csv';
        $headers = ['Date', 'Reference', 'Type', 'Method', 'Staff', 'Notes', 'Debit', 'Credit', 'Balance'];
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
            ->with('user:id,name')
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
                    'Reference' => $entry->reference_id ?? ('TXN-'.$entry->id),
                    'Type' => $entry->type->label(),
                    'Method' => $entry->payment_method ? ucfirst($entry->payment_method) : '-',
                    'Staff' => $entry->user?->name ?? 'Staff',
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
