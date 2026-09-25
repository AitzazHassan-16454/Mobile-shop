<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\InstallmentPayment;
use App\Models\InstallmentPlan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class InstallmentController extends Controller
{
    public function index(Request $request, string $currentTeam): Response
    {
        $perPage = (int) $request->input('per_page', 15);
        if (! in_array($perPage, [10, 15, 25, 50, 100, 250, 500], true)) {
            $perPage = 15;
        }

        return Inertia::render('Installments/Index', [
            'plans' => InstallmentPlan::query()->with('customer')->latest()->paginate($perPage)->withQueryString(),
            'customers' => Customer::query()->orderBy('name')->get(['id', 'name', 'phone']),
            'filters' => [
                'per_page' => $perPage,
            ],
            'summary' => [
                'active_plans' => InstallmentPlan::where('status', 'active')->count(),
                'outstanding' => (float) InstallmentPlan::where('status', 'active')
                    ->selectRaw('COALESCE(SUM(total_amount - down_payment - (paid_installments * monthly_amount)), 0) as total')
                    ->value('total'),
                'collected' => (float) InstallmentPayment::sum('amount'),
                'overdue_count' => (int) InstallmentPlan::where('status', 'active')
                    ->where('next_due_date', '<', now()->startOfDay())
                    ->count(),
                'due_soon_count' => (int) InstallmentPlan::where('status', 'active')
                    ->whereBetween('next_due_date', [now()->startOfDay(), now()->addDays(30)->endOfDay()])
                    ->count(),
            ],
        ]);
    }

    public function store(Request $request, string $currentTeam): RedirectResponse
    {
        $validated = $request->validate([
            'customer_id' => ['required', 'exists:customers,id'],
            'total_amount' => ['required', 'numeric', 'gt:0', 'max:99999999.99'],
            'down_payment' => ['required', 'numeric', 'min:0', 'max:99999999.99', 'lte:total_amount'],
            'duration_months' => ['required', 'integer', 'min:1', 'max:60'],
            'next_due_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $remaining = (float) $validated['total_amount'] - (float) $validated['down_payment'];
        $monthlyAmount = round($remaining / (int) $validated['duration_months'], 2);

        InstallmentPlan::create([
            ...$validated,
            'monthly_amount' => $monthlyAmount,
            'status' => $remaining > 0 ? 'active' : 'completed',
        ]);

        return redirect()->back()->with('success', 'Installment plan created.');
    }

    public function recordPayment(Request $request, string $currentTeam, InstallmentPlan $plan): RedirectResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0', 'max:99999999.99'],
            'payment_method' => ['required', 'string', 'in:cash,jazzcash,easypaisa,bank,card'],
            'reference_id' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        DB::transaction(function () use ($plan, $validated): void {
            $plan->refresh();
            $remaining = max(0.00, round((float) $plan->total_amount - (float) $plan->down_payment - ((int) $plan->paid_installments * (float) $plan->monthly_amount), 2));
            if ((float) $validated['amount'] > $remaining + 0.01) {
                throw ValidationException::withMessages([
                    'amount' => ['Payment amount (Rs. '.number_format((float) $validated['amount'], 2).') cannot exceed the remaining installment balance of Rs. '.number_format($remaining, 2)],
                ]);
            }

            $paidInstallments = $plan->paid_installments + 1;
            InstallmentPayment::create([
                'installment_plan_id' => $plan->id,
                ...$validated,
                'paid_at' => now()->toDateString(),
            ]);

            $plan->update([
                'paid_installments' => $paidInstallments,
                'next_due_date' => now()->addMonth()->toDateString(),
                'status' => $paidInstallments >= $plan->duration_months ? 'completed' : 'active',
            ]);
        });

        return redirect()->back()->with('success', 'Installment payment recorded.');
    }
}
