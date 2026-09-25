<?php

namespace App\Http\Controllers;

use App\Enums\PaymentMethod;
use App\Models\Customer;
use App\Models\CustomerLedger;
use App\Models\InstallmentPlan;
use App\Models\Product;
use App\Models\ProductImei;
use App\Models\RegisterShift;
use App\Models\RepairTicket;
use App\Models\Sale;
use App\Models\ShopExpense;
use App\Models\Supplier;
use App\Models\TeamInvitation;
use App\Models\UsedPhonePurchase;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    private const PAYMENT_METHODS = ['cash', 'jazzcash', 'easypaisa', 'bank', 'card', 'udhaar'];

    public function __invoke(Request $request): Response
    {
        $email = strtolower($request->user()->email);

        $pendingInvitations = TeamInvitation::query()
            ->with(['inviter', 'team'])
            ->whereRaw('LOWER(email) = ?', [$email])
            ->whereNull('accepted_at')
            ->where(fn ($query) => $query
                ->whereNull('expires_at')
                ->orWhere('expires_at', '>=', now()))
            ->latest()
            ->get()
            ->map(fn (TeamInvitation $invitation) => [
                'code' => $invitation->code,
                'inviterName' => $invitation->inviter->name,
                'team' => [
                    'name' => $invitation->team->name,
                    'slug' => $invitation->team->slug,
                ],
            ]);

        [$start, $end, $preset] = $this->resolveRange($request);

        $sales = Sale::query()
            ->with('items')
            ->when($start && $end, fn ($query) => $query->whereBetween('created_at', [$start, $end]))
            ->get();

        $expenses = ShopExpense::query()
            ->when($start && $end, fn ($query) => $query->whereBetween('created_at', [$start, $end]))
            ->orderBy('created_at')
            ->get();

        $posStats = $this->posStats($sales, $expenses, $start, $end);

        $paymentMethods = $this->paymentMethodStats($sales);

        $expenseCategories = $expenses
            ->groupBy('category')
            ->map(fn (Collection $rows) => round((float) $rows->sum('amount'), 2))
            ->sortDesc()
            ->map(fn (float $amount, string $category) => [
                'name' => $category,
                'amount' => $amount,
            ])
            ->values();

        $graph = $this->buildGraphData($sales, $expenses, $start, $end);

        $reminders = $this->reminderStats();

        $alerts = $this->alerts($reminders);

        // Inventory Overview
        $inStockPhonesCount = ProductImei::query()->where('status', 'in_stock')->count();
        $totalAccessoriesCount = (int) Product::query()->where('is_serialized', false)->sum('stock_quantity');

        // Repairs pending
        $pendingRepairsCount = RepairTicket::query()->whereIn('status', ['received', 'in_diagnosis', 'waiting_parts', 'ready'])->count();

        // Stock Valuation
        $phoneValuation = (float) ProductImei::query()->where('status', 'in_stock')->sum('purchase_cost');
        $accessoryValuation = (float) Product::query()->where('is_serialized', false)->selectRaw('SUM(cost_price * stock_quantity) as total')->value('total');
        $totalValuation = round($phoneValuation + $accessoryValuation, 2);

        // Active Shift Status
        $activeShift = RegisterShift::query()
            ->where('user_id', $request->user()->id)
            ->where('status', 'open')
            ->first();

        // Recent Sales
        $recentSales = Sale::query()
            ->with(['customer', 'items.product'])
            ->latest()
            ->take(5)
            ->get()
            ->map(fn (Sale $s) => [
                'id' => $s->id,
                'invoice_no' => $s->invoice_no,
                'customer' => $s->customer?->name ?? 'Walk-in',
                'net_amount' => (float) $s->net_amount,
                'payment_method' => $s->payment_method->value,
                'created_at' => $s->created_at->format('H:i'),
            ]);

        return Inertia::render('Dashboard', [
            'pendingInvitations' => $pendingInvitations,
            'metrics' => [
                'todayRevenue' => $posStats['total_sale'],
                'todaySalesCount' => $posStats['sales_count'],
                'inStockPhones' => $inStockPhonesCount,
                'totalAccessories' => $totalAccessoriesCount,
                'pendingRepairs' => $pendingRepairsCount,
                'totalCustomerDebt' => $posStats['sale_due'],
                'totalValuation' => $totalValuation,
                'hasActiveShift' => $activeShift !== null,
                'activeShiftOpenedAt' => $activeShift?->opened_at?->format('H:i'),
            ],
            'filters' => [
                'preset' => $preset,
                'start' => $start?->toDateString(),
                'end' => $end?->toDateString(),
            ],
            'posStats' => $posStats,
            'paymentMethods' => $paymentMethods,
            'expenseCategories' => $expenseCategories,
            'graph' => $graph,
            'reminders' => $reminders,
            'alerts' => $alerts,
            'recentSales' => $recentSales,
        ]);
    }

    /**
     * @return array{CarbonInterface|null, CarbonInterface|null, string}
     */
    private function resolveRange(Request $request): array
    {
        $preset = (string) $request->query('range', 'this_month');

        return match ($preset) {
            'yesterday' => [now()->subDay()->startOfDay(), now()->subDay()->endOfDay(), $preset],
            'this_week' => [now()->startOfWeek(CarbonInterface::SUNDAY), now()->endOfWeek(CarbonInterface::SUNDAY), $preset],
            'last_week' => [now()->subWeek()->startOfWeek(CarbonInterface::SUNDAY), now()->subWeek()->endOfWeek(CarbonInterface::SUNDAY), $preset],
            'this_month' => [now()->startOfMonth(), now()->endOfMonth(), $preset],
            'last_month' => [now()->subMonth()->startOfMonth(), now()->subMonth()->endOfMonth(), $preset],
            'this_year' => [now()->startOfYear(), now()->endOfYear(), $preset],
            'last_year' => [now()->subYear()->startOfYear(), now()->subYear()->endOfYear(), $preset],
            'all_time' => [null, null, $preset],
            'custom' => $this->customRange($request),
            default => [now()->startOfDay(), now()->endOfDay(), 'today'],
        };
    }

    /**
     * @return array{CarbonInterface|null, CarbonInterface|null, string}
     */
    private function customRange(Request $request): array
    {
        $from = $request->filled('from') ? Carbon::parse($request->query('from'))->startOfDay() : now()->startOfWeek(CarbonInterface::SUNDAY);
        $to = $request->filled('to') ? Carbon::parse($request->query('to'))->endOfDay() : now();

        if ($from->greaterThan($to)) {
            [$from, $to] = [$to, $from];
        }

        return [$from, $to, 'custom'];
    }

    /**
     * @return array{
     *     total_sale: float,
     *     sales_count: int,
     *     total_expense: float,
     *     gross_profit: float,
     *     net_profit: float,
     *     payment_received: float,
     *     total_discount: float,
     *     total_advance: float,
     *     udhaar_created: float,
     *     wasooli_collected: float,
     *     sale_due: float,
     *     purchase_due: float,
     *     total_due: float,
     *     used_phone_buying: float,
     *     repair_revenue: float,
     *     repair_profit: float,
     * }
     */
    private function posStats(Collection $sales, Collection $expenses, ?CarbonInterface $start, ?CarbonInterface $end): array
    {
        $totalSale = 0.00;
        $paymentReceived = 0.00;
        $totalDiscount = 0.00;
        $totalCost = 0.00;

        foreach ($sales as $sale) {
            $totalSale += (float) $sale->net_amount;
            $paymentReceived += min((float) $sale->paid_amount, (float) $sale->net_amount);
            $totalDiscount += (float) $sale->discount_amount;
            $totalCost += $sale->items->sum(fn ($item) => (float) $item->unit_cost * (float) $item->quantity);
        }

        $returnsQuery = \App\Models\SaleReturn::query();
        if ($start && $end) {
            $returnsQuery->whereBetween('created_at', [$start, $end]);
        }
        $returnsTotal = (float) $returnsQuery->sum('total_return_amount');
        $refundsTotal = (float) $returnsQuery->sum('refund_amount');

        $effectiveTotalSale = max(0.00, round($totalSale - $returnsTotal, 2));
        $grossProfit = round($effectiveTotalSale - $totalCost, 2);
        $totalExpense = round((float) $expenses->sum('amount'), 2);

        $repairQuery = RepairTicket::query()->where('status', 'delivered');
        if ($start && $end) {
            $repairQuery->whereBetween('delivered_at', [$start, $end]);
        }
        $repairRevenue = round((float) $repairQuery->sum('estimated_cost'), 2);
        $repairProfit = round((float) $repairQuery->get()->sum(fn ($ticket) => (float) $ticket->estimated_cost - (float) $ticket->spare_parts_cost), 2);

        $netProfit = round($grossProfit + $repairProfit - $totalExpense, 2);

        $ledgerQuery = CustomerLedger::query();
        if ($start && $end) {
            $ledgerQuery->whereBetween('created_at', [$start, $end]);
        }
        $udhaarCreated = round((float) (clone $ledgerQuery)->where('type', 'sale')->sum('amount'), 2);
        $wasooliCollected = round((float) (clone $ledgerQuery)->where('type', 'payment')->sum('amount'), 2);
        $advancesReceived = round((float) (clone $ledgerQuery)->where('type', 'advance')->sum('amount'), 2);
        $advancesRefunded = round((float) (clone $ledgerQuery)->where('type', 'advance_return')->sum('amount'), 2);

        $usedPhoneBuyingQuery = UsedPhonePurchase::query();
        if ($start && $end) {
            $usedPhoneBuyingQuery->whereBetween('created_at', [$start, $end]);
        }
        $usedPhoneBuying = round((float) $usedPhoneBuyingQuery->sum('purchase_amount'), 2);

        $saleDue = round((float) Customer::query()->where('current_balance', '>', 0)->sum('current_balance'), 2);
        $purchaseDue = round((float) Supplier::query()->where('current_balance', '>', 0)->sum('current_balance'), 2);
        $totalAdvance = round((float) abs(Customer::query()->where('current_balance', '<', 0)->sum('current_balance')), 2);

        return [
            'total_sale' => $effectiveTotalSale,
            'sales_count' => $sales->count(),
            'total_returns' => $returnsTotal,
            'total_refunds' => $refundsTotal,
            'total_expense' => $totalExpense,
            'gross_profit' => $grossProfit,
            'net_profit' => $netProfit,
            'payment_received' => round($paymentReceived, 2),
            'total_discount' => round($totalDiscount, 2),
            'total_advance' => $totalAdvance,
            'udhaar_created' => $udhaarCreated,
            'wasooli_collected' => $wasooliCollected,
            'sale_due' => $saleDue,
            'purchase_due' => $purchaseDue,
            'total_due' => round($saleDue + $purchaseDue, 2),
            'used_phone_buying' => $usedPhoneBuying,
            'repair_revenue' => $repairRevenue,
            'repair_profit' => $repairProfit,
        ];
    }

    /**
     * @return list<array{method: string, label: string, amount: float}>
     */
    private function paymentMethodStats(Collection $sales): array
    {
        $totals = array_fill_keys(self::PAYMENT_METHODS, 0.0);

        foreach ($sales as $sale) {
            $method = $sale->payment_method->value;
            $paid = (float) $sale->paid_amount;
            $net = (float) $sale->net_amount;
            $received = min($paid, $net);

            if ($method === 'split' && is_array($sale->payment_details)) {
                foreach (self::PAYMENT_METHODS as $key) {
                    $totals[$key] += (float) ($sale->payment_details[$key] ?? 0);
                }

                continue;
            }

            $totals[$method] ??= 0.0;

            if ($method === 'udhaar') {
                $totals['udhaar'] += max(0.0, $net - $paid);
                $totals['cash'] += $received;
            } else {
                $totals[$method] += $received;
            }
        }

        return collect(self::PAYMENT_METHODS)
            ->map(fn (string $method) => [
                'method' => $method,
                'label' => PaymentMethod::from($method)->label(),
                'amount' => round($totals[$method], 2),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array{labels: list<string>, sales: list<float>, payments: list<float>, profit: list<float>, expenses: list<float>}
     */
    private function buildGraphData(Collection $sales, Collection $expenses, ?CarbonInterface $start, ?CarbonInterface $end): array
    {
        $end ??= now();
        $earliest = collect([$sales->min('created_at'), $expenses->min('created_at')])->filter()->min();
        $start ??= $earliest ?? $end->copy()->startOfMonth();

        $step = 'day';
        $bucketFormat = 'Y-m-d';

        if ($start->diffInDays($end) > 45) {
            $step = 'month';
            $bucketFormat = 'Y-m';
        }

        if ($start->diffInMonths($end) > 36) {
            $step = 'year';
            $bucketFormat = 'Y';
        }

        $labels = [];
        $cursor = $start->copy()->{"startOf{$step}"}();

        while ($cursor->lte($end)) {
            $labels[] = $cursor->format($bucketFormat);
            $cursor = $cursor->{'add'.ucfirst($step)}(1);
        }

        $salesData = array_fill_keys($labels, 0.0);
        $paymentsData = array_fill_keys($labels, 0.0);
        $profitData = array_fill_keys($labels, 0.0);
        $expensesData = array_fill_keys($labels, 0.0);

        foreach ($sales as $sale) {
            $key = $sale->created_at->format($bucketFormat);
            if (! array_key_exists($key, $salesData)) {
                continue;
            }
            $cost = (float) $sale->items->sum(fn ($item) => (float) $item->unit_cost * (float) $item->quantity);
            $salesData[$key] += (float) $sale->net_amount;
            $paymentsData[$key] += min((float) $sale->paid_amount, (float) $sale->net_amount);
            $profitData[$key] += (float) $sale->net_amount - $cost;
        }

        foreach ($expenses as $expense) {
            $key = $expense->created_at->format($bucketFormat);
            if (array_key_exists($key, $expensesData)) {
                $expensesData[$key] += (float) $expense->amount;
            }
        }

        return [
            'labels' => $labels,
            'sales' => array_values($salesData),
            'payments' => array_values($paymentsData),
            'profit' => array_values($profitData),
            'expenses' => array_values($expensesData),
        ];
    }

    /**
     * @return array{overdue: int, today: int, upcoming: int, list: list<array{id: int, customer: string, amount: float, due_date: string, status: string}>}
     */
    private function reminderStats(): array
    {
        $plans = InstallmentPlan::query()
            ->where('status', 'active')
            ->with('customer')
            ->whereNotNull('next_due_date')
            ->get();

        return [
            'overdue' => $plans->filter(fn ($plan) => $plan->next_due_date->lt(today()))->count(),
            'today' => $plans->filter(fn ($plan) => $plan->next_due_date->isToday())->count(),
            'upcoming' => $plans->filter(fn ($plan) => $plan->next_due_date->gt(today()) && $plan->next_due_date->lte(today()->addDays(7)))->count(),
            'list' => $plans
                ->sortBy('next_due_date')
                ->take(8)
                ->map(fn (InstallmentPlan $plan) => [
                    'id' => $plan->id,
                    'customer' => $plan->customer?->name ?? 'Customer',
                    'amount' => (float) $plan->monthly_amount,
                    'due_date' => $plan->next_due_date->format('Y-m-d'),
                    'status' => $plan->next_due_date->lt(today()) ? 'overdue' : ($plan->next_due_date->isToday() ? 'today' : 'upcoming'),
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  array{overdue: int, today: int, upcoming: int, list: list<array{id: int, customer: string, amount: float, due_date: string, status: string}>}  $reminders
     * @return list<array{type: string, title: string, message: string}>
     */
    private function alerts(array $reminders): array
    {
        $alerts = [];

        $lowStock = Product::query()
            ->where('is_serialized', false)
            ->where('alert_quantity', '>', 0)
            ->whereColumn('stock_quantity', '<=', 'alert_quantity')
            ->get()
            ->take(5);

        foreach ($lowStock as $product) {
            $alerts[] = [
                'type' => 'low_stock',
                'title' => $product->name,
                'message' => "{$product->stock_quantity} left (alert at {$product->alert_quantity}).",
            ];
        }

        $pendingRepairs = RepairTicket::query()->whereIn('status', ['received', 'in_diagnosis', 'waiting_parts'])->count();
        if ($pendingRepairs > 0) {
            $alerts[] = [
                'type' => 'repairs',
                'title' => "{$pendingRepairs} repairs need attention",
                'message' => 'Tickets waiting in the diagnosis or parts pipeline.',
            ];
        }

        if ($reminders['overdue'] > 0) {
            $alerts[] = [
                'type' => 'installments',
                'title' => 'Overdue installment(s)',
                'message' => 'Some installment plans have passed their due date.',
            ];
        }

        return $alerts;
    }
}
