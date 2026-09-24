<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Models\Customer;
use App\Models\CustomerLedger;
use App\Models\InstallmentPayment;
use App\Models\InstallmentPlan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class YearlyDuesController extends Controller
{
    public function index(Request $request, string $currentTeam): Response
    {
        $year = (int) $request->input('year', now()->year);
        $year = max(2020, min($year, now()->year));

        $yearStart = Carbon::create($year, 1, 1)->startOfYear();
        $yearEnd = $yearStart->copy()->endOfYear();

        $outstandingCustomers = Customer::query()
            ->where('current_balance', '>', 0)
            ->orderByDesc('current_balance')
            ->get(['id', 'name', 'phone', 'current_balance']);

        $totalDues = round($outstandingCustomers->sum('current_balance'), 2);
        $duesCount = $outstandingCustomers->count();

        $installmentOutstanding = (float) InstallmentPlan::where('status', 'active')
            ->selectRaw('COALESCE(SUM(total_amount - down_payment - (paid_installments * monthly_amount)), 0) as total')
            ->value('total');

        $collectedThisYear =
            (float) CustomerLedger::where('type', 'payment')
                ->whereBetween('created_at', [$yearStart, $yearEnd])
                ->sum('amount')
            + (float) InstallmentPayment::whereBetween('paid_at', [$yearStart, $yearEnd])->sum('amount');

        $target = AppSetting::get('yearly_collection_target', '');

        $monthly = $this->monthlyBreakdown($year, $yearStart, $yearEnd);

        $reminders = InstallmentPlan::query()
            ->with('customer:id,name,phone')
            ->where('status', 'active')
            ->where('next_due_date', '<=', now()->addDays(30))
            ->orderBy('next_due_date')
            ->get();

        $reminders = $reminders->map(function (InstallmentPlan $plan) {
            $remaining = round(
                (float) $plan->total_amount
                - (float) $plan->down_payment
                - ((int) $plan->paid_installments * (float) $plan->monthly_amount),
                2,
            );

            return [
                'id' => $plan->id,
                'customer' => $plan->customer,
                'monthly_amount' => $plan->monthly_amount,
                'remaining' => $remaining,
                'next_due_date' => $plan->next_due_date?->toDateString(),
                'overdue_days' => $plan->next_due_date
                    ? max(0, (int) $plan->next_due_date->startOfDay()->diffInDays(now()->startOfDay()))
                    : 0,
            ];
        });

        return Inertia::render('YearlyDues/Index', [
            'year' => $year,
            'year_options' => collect(range(now()->year - 4, now()->year))->reverse()->values(),
            'dues' => [
                'total' => $totalDues,
                'count' => $duesCount,
                'top_debtors' => $outstandingCustomers->take(10)->values(),
            ],
            'installments' => [
                'outstanding' => $installmentOutstanding,
                'reminders' => $reminders,
                'reminders_count' => $reminders->count(),
            ],
            'collections' => [
                'target' => $target,
                'target_numeric' => is_numeric($target) ? (float) $target : null,
                'collected' => $collectedThisYear,
                'monthly' => $monthly,
            ],
        ]);
    }

    public function updateTarget(Request $request, string $currentTeam): RedirectResponse
    {
        $validated = $request->validate([
            'target' => ['required', 'numeric', 'min:0'],
        ]);

        AppSetting::set('yearly_collection_target', (string) $validated['target']);

        return redirect()->back()->with('success', 'Yearly collection target updated.');
    }

    private function monthlyBreakdown(int $year, Carbon $yearStart, Carbon $yearEnd): array
    {
        $customerMonths = CustomerLedger::query()
            ->where('type', 'payment')
            ->whereBetween('created_at', [$yearStart, $yearEnd])
            ->get(['created_at', 'amount'])
            ->groupBy(fn ($row) => (int) Carbon::parse($row->created_at)->format('m'))
            ->map(fn ($rows) => (float) $rows->sum('amount'));

        $installmentMonths = InstallmentPayment::query()
            ->whereBetween('paid_at', [$yearStart, $yearEnd])
            ->get(['paid_at', 'amount'])
            ->groupBy(fn ($row) => (int) Carbon::parse($row->paid_at)->format('m'))
            ->map(fn ($rows) => (float) $rows->sum('amount'));

        $monthNames = [
            1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr',
            5 => 'May', 6 => 'Jun', 7 => 'Jul', 8 => 'Aug',
            9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dec',
        ];

        $out = [];

        foreach ($monthNames as $number => $label) {
            $monthlyTotal = round(
                (float) ($customerMonths->get($number, 0))
                + (float) ($installmentMonths->get($number, 0)),
                2,
            );

            $out[] = [
                'month' => $label,
                'number' => $number,
                'total' => $monthlyTotal,
            ];
        }

        return $out;
    }
}
