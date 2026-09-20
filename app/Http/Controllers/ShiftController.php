<?php

namespace App\Http\Controllers;

use App\Models\CustomerLedger;
use App\Models\RegisterShift;
use App\Models\Sale;
use App\Models\ShopExpense;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ShiftController extends Controller
{
    public function index(Request $request, string $currentTeam): Response
    {
        $activeShift = RegisterShift::query()
            ->with(['user', 'expenses.user'])
            ->where('user_id', $request->user()->id)
            ->where('status', 'open')
            ->first();

        if ($activeShift) {
            $shiftStartTime = $activeShift->opened_at;

            $sales = Sale::query()
                ->whereBetween('created_at', [$shiftStartTime, now()])
                ->get();

            $cashSales = 0.00;
            $jazzcashSales = 0.00;
            $easypaisaSales = 0.00;
            $bankSales = 0.00;
            $udhaarSales = 0.00;

            foreach ($sales as $sale) {
                $method = $sale->payment_method->value;
                if ($method === 'split' && is_array($sale->payment_details)) {
                    $cashSales += (float) ($sale->payment_details['cash'] ?? 0);
                    $jazzcashSales += (float) ($sale->payment_details['jazzcash'] ?? 0);
                    $easypaisaSales += (float) ($sale->payment_details['easypaisa'] ?? 0);
                    $bankSales += (float) ($sale->payment_details['bank'] ?? 0);
                    $udhaarSales += (float) ($sale->payment_details['udhaar'] ?? 0);
                } else {
                    $netPaid = (float) min($sale->paid_amount, $sale->net_amount);
                    if ($method === 'cash') {
                        $cashSales += $netPaid;
                    } elseif ($method === 'jazzcash') {
                        $jazzcashSales += $netPaid;
                    } elseif ($method === 'easypaisa') {
                        $easypaisaSales += $netPaid;
                    } elseif ($method === 'bank' || $method === 'card') {
                        $bankSales += $netPaid;
                    } elseif ($method === 'udhaar') {
                        $udhaarSales += (float) max(0, $sale->net_amount - $sale->paid_amount);
                        $cashSales += (float) min($sale->paid_amount, $sale->net_amount);
                    }
                }
            }

            $wasooliCash = (float) CustomerLedger::query()
                ->where('type', 'payment')
                ->whereBetween('created_at', [$shiftStartTime, now()])
                ->sum('amount');

            $expensesAmount = (float) ShopExpense::query()
                ->whereBetween('created_at', [$shiftStartTime, now()])
                ->sum('amount');

            $openingFloat = (float) $activeShift->opening_float;
            $expectedCash = round($openingFloat + $cashSales + $wasooliCash - $expensesAmount, 2);

            $activeShiftData = [
                'id' => $activeShift->id,
                'user' => $activeShift->user->name,
                'opened_at' => $activeShift->opened_at->toDateTimeString(),
                'opening_float' => $openingFloat,
                'cash_sales' => round($cashSales, 2),
                'jazzcash_sales' => round($jazzcashSales, 2),
                'easypaisa_sales' => round($easypaisaSales, 2),
                'bank_sales' => round($bankSales, 2),
                'udhaar_sales' => round($udhaarSales, 2),
                'wasooli_cash' => round($wasooliCash, 2),
                'expenses_amount' => round($expensesAmount, 2),
                'expected_cash' => $expectedCash,
                'expenses' => $activeShift->expenses->map(fn ($e) => [
                    'id' => $e->id,
                    'category' => $e->category,
                    'amount' => (float) $e->amount,
                    'notes' => $e->notes,
                    'created_at' => $e->created_at->format('Y-m-d H:i:s'),
                ]),
            ];
        } else {
            $activeShiftData = null;
        }

        $pastShifts = RegisterShift::query()
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->take(20)
            ->get()
            ->map(fn (RegisterShift $s) => [
                'id' => $s->id,
                'cashier' => $s->user->name,
                'opened_at' => $s->opened_at->format('Y-m-d H:i'),
                'closed_at' => $s->closed_at?->format('Y-m-d H:i'),
                'opening_float' => (float) $s->opening_float,
                'cash_sales' => (float) $s->cash_sales,
                'expected_cash' => (float) $s->expected_cash,
                'actual_cash' => $s->actual_cash !== null ? (float) $s->actual_cash : null,
                'discrepancy' => $s->discrepancy !== null ? (float) $s->discrepancy : null,
                'status' => $s->status,
                'notes' => $s->notes,
            ]);

        return Inertia::render('Shifts/Index', [
            'activeShift' => $activeShiftData,
            'pastShifts' => $pastShifts,
        ]);
    }

    public function open(Request $request, string $currentTeam): RedirectResponse
    {
        $validated = $request->validate([
            'opening_float' => ['required', 'numeric', 'min:0'],
        ]);

        $existingOpen = RegisterShift::query()
            ->where('user_id', $request->user()->id)
            ->where('status', 'open')
            ->first();

        if ($existingOpen) {
            return redirect()->back()->with('error', 'You already have an active open shift.');
        }

        RegisterShift::create([
            'user_id' => $request->user()->id,
            'opened_at' => now(),
            'opening_float' => (float) $validated['opening_float'],
            'status' => 'open',
        ]);

        return redirect()->back()->with('success', 'Shift opened successfully with cash float.');
    }

    public function storeExpense(Request $request, string $currentTeam): RedirectResponse
    {
        $validated = $request->validate([
            'category' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $activeShift = RegisterShift::query()
            ->where('user_id', $request->user()->id)
            ->where('status', 'open')
            ->first();

        $expense = ShopExpense::create([
            'register_shift_id' => $activeShift?->id,
            'user_id' => $request->user()->id,
            'category' => $validated['category'],
            'amount' => (float) $validated['amount'],
            'notes' => $validated['notes'] ?? null,
        ]);

        if ($activeShift) {
            $activeShift->increment('expenses_amount', (float) $validated['amount']);
        }

        return redirect()->back()->with('success', 'Shop expense recorded successfully.');
    }

    public function close(Request $request, string $currentTeam): RedirectResponse
    {
        $validated = $request->validate([
            'actual_cash' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $activeShift = RegisterShift::query()
            ->where('user_id', $request->user()->id)
            ->where('status', 'open')
            ->first();

        if (! $activeShift) {
            return redirect()->back()->with('error', 'No active open shift found to close.');
        }

        $shiftStartTime = $activeShift->opened_at;

        $sales = Sale::query()
            ->whereBetween('created_at', [$shiftStartTime, now()])
            ->get();

        $cashSales = 0.00;
        $jazzcashSales = 0.00;
        $easypaisaSales = 0.00;
        $bankSales = 0.00;
        $udhaarSales = 0.00;

        foreach ($sales as $sale) {
            $method = $sale->payment_method->value;
            if ($method === 'split' && is_array($sale->payment_details)) {
                $cashSales += (float) ($sale->payment_details['cash'] ?? 0);
                $jazzcashSales += (float) ($sale->payment_details['jazzcash'] ?? 0);
                $easypaisaSales += (float) ($sale->payment_details['easypaisa'] ?? 0);
                $bankSales += (float) ($sale->payment_details['bank'] ?? 0);
                $udhaarSales += (float) ($sale->payment_details['udhaar'] ?? 0);
            } else {
                $netPaid = (float) min($sale->paid_amount, $sale->net_amount);
                if ($method === 'cash') {
                    $cashSales += $netPaid;
                } elseif ($method === 'jazzcash') {
                    $jazzcashSales += $netPaid;
                } elseif ($method === 'easypaisa') {
                    $easypaisaSales += $netPaid;
                } elseif ($method === 'bank' || $method === 'card') {
                    $bankSales += $netPaid;
                } elseif ($method === 'udhaar') {
                    $udhaarSales += (float) max(0, $sale->net_amount - $sale->paid_amount);
                    $cashSales += (float) min($sale->paid_amount, $sale->net_amount);
                }
            }
        }

        $wasooliCash = (float) CustomerLedger::query()
            ->where('type', 'payment')
            ->whereBetween('created_at', [$shiftStartTime, now()])
            ->sum('amount');

        $expensesAmount = (float) ShopExpense::query()
            ->where('register_shift_id', $activeShift->id)
            ->sum('amount');

        $openingFloat = (float) $activeShift->opening_float;
        $expectedCash = round($openingFloat + $cashSales + $wasooliCash - $expensesAmount, 2);
        $actualCash = (float) $validated['actual_cash'];
        $discrepancy = round($actualCash - $expectedCash, 2);

        $activeShift->update([
            'closed_at' => now(),
            'cash_sales' => round($cashSales, 2),
            'jazzcash_sales' => round($jazzcashSales, 2),
            'easypaisa_sales' => round($easypaisaSales, 2),
            'bank_sales' => round($bankSales, 2),
            'udhaar_sales' => round($udhaarSales, 2),
            'wasooli_cash' => round($wasooliCash, 2),
            'expenses_amount' => round($expensesAmount, 2),
            'expected_cash' => $expectedCash,
            'actual_cash' => $actualCash,
            'discrepancy' => $discrepancy,
            'status' => 'closed',
            'notes' => $validated['notes'] ?? null,
        ]);

        return redirect()->back()->with('success', 'Shift closed successfully. Reconciliation saved.');
    }
}
