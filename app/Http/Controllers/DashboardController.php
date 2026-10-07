<?php

namespace App\Http\Controllers;

use App\Enums\LedgerType;
use App\Enums\PaymentMethod;
use App\Models\Customer;
use App\Models\CustomerLedger;
use App\Models\InstallmentPayment;
use App\Models\InstallmentPlan;
use App\Models\Product;
use App\Models\ProductImei;
use App\Models\RegisterShift;
use App\Models\RepairSale;
use App\Models\RepairTicket;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Models\ShopExpense;
use App\Models\Supplier;
use App\Models\SupplierLedger;
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

        $saleReturns = SaleReturn::query()
            ->when($start && $end, fn ($query) => $query->whereBetween('created_at', [$start, $end]))
            ->get();

        $repairSales = RepairSale::query()
            ->when($start && $end, fn ($query) => $query->whereBetween('created_at', [$start, $end]))
            ->get();

        $deliveredRepairTickets = RepairTicket::query()
            ->where('status', 'delivered')
            ->when($start && $end, fn ($query) => $query->whereBetween('delivered_at', [$start, $end]))
            ->get();

        $usedPhonePurchases = UsedPhonePurchase::query()
            ->when($start && $end, fn ($query) => $query->whereBetween('created_at', [$start, $end]))
            ->get();

        $supplierLedgers = SupplierLedger::query()
            ->when($start && $end, fn ($query) => $query->whereBetween('created_at', [$start, $end]))
            ->get();

        $customerLedgers = CustomerLedger::query()
            ->when($start && $end, fn ($query) => $query->whereBetween('created_at', [$start, $end]))
            ->get();

        $installmentPayments = InstallmentPayment::query()
            ->when($start && $end, fn ($query) => $query->whereBetween('paid_at', [$start->toDateString(), $end->toDateString()]))
            ->get();

        $expenses = ShopExpense::query()
            ->when($start && $end, fn ($query) => $query->whereBetween('created_at', [$start, $end]))
            ->orderBy('created_at')
            ->get();

        // Inventory Overview
        $inStockPhonesCount = ProductImei::query()->where('status', 'in_stock')->count();
        $totalAccessoriesCount = (int) Product::query()->where('is_serialized', false)->sum('stock_quantity');

        // Repairs pending
        $pendingRepairsCount = RepairTicket::query()->whereIn('status', ['received', 'in_diagnosis', 'waiting_parts', 'ready'])->count();

        // Stock Valuation
        $phoneValuation = (float) ProductImei::query()->where('status', 'in_stock')->sum('purchase_cost');
        $accessoryValuation = (float) Product::query()->where('is_serialized', false)->selectRaw('SUM(cost_price * stock_quantity) as total')->value('total');
        $totalValuation = round($phoneValuation + $accessoryValuation, 2);

        $posStats = $this->posStats(
            $sales,
            $saleReturns,
            $repairSales,
            $deliveredRepairTickets,
            $usedPhonePurchases,
            $supplierLedgers,
            $customerLedgers,
            $installmentPayments,
            $expenses,
            $totalValuation,
            $start,
            $end
        );

        $paymentMethods = $this->paymentMethodStats(
            $sales,
            $customerLedgers,
            $repairSales,
            $installmentPayments,
            $saleReturns
        );

        $expenseCategories = $expenses
            ->groupBy('category')
            ->map(fn (Collection $rows) => round((float) $rows->sum('amount'), 2))
            ->sortDesc()
            ->map(fn (float $amount, string $category) => [
                'name' => $category,
                'amount' => $amount,
            ])
            ->values();

        $graph = $this->buildGraphData(
            $sales,
            $saleReturns,
            $repairSales,
            $deliveredRepairTickets,
            $expenses,
            $customerLedgers,
            $installmentPayments,
            $start,
            $end
        );

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

        $reminders = $this->reminderStats();
        $alerts = $this->alerts($reminders);

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
     *     gross_sale: float,
     *     sales_count: int,
     *     total_returns: float,
     *     total_refunds: float,
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
     *     total_purchase: float,
     *     total_purchase_payment: float,
     *     total_purchase_returned: float,
     *     opening_balance_dues: float,
     *     stock_valuation: float,
     *     repair_revenue: float,
     *     repair_profit: float,
     * }
     */
    private function posStats(
        Collection $sales,
        Collection $saleReturns,
        Collection $repairSales,
        Collection $repairTickets,
        Collection $usedPhonePurchases,
        Collection $supplierLedgers,
        Collection $customerLedgers,
        Collection $installmentPayments,
        Collection $expenses,
        float $totalValuation,
        ?CarbonInterface $start,
        ?CarbonInterface $end
    ): array {
        $posGrossSale = 0.00;
        $posPaidReceived = 0.00;
        $totalDiscount = 0.00;
        $posCost = 0.00;

        foreach ($sales as $sale) {
            $posGrossSale += (float) $sale->net_amount;
            $posPaidReceived += min((float) $sale->paid_amount, (float) $sale->net_amount);
            $totalDiscount += (float) $sale->discount_amount;
            $posCost += $sale->items->sum(fn ($item) => (float) $item->unit_cost * (float) $item->quantity);
        }

        $returnsTotal = round((float) $saleReturns->sum('total_return_amount'), 2);
        $refundsTotal = round((float) $saleReturns->sum('refund_amount'), 2);
        $posNetSale = max(0.00, round($posGrossSale - $returnsTotal, 2));

        $repairSaleRevenue = round((float) $repairSales->sum('total_amount'), 2);
        $repairSaleCost = round((float) $repairSales->sum(fn ($s) => (float) $s->cost_price * (float) $s->quantity), 2);
        $repairSaleProfit = round($repairSaleRevenue - $repairSaleCost, 2);

        $repairTicketRevenue = round((float) $repairTickets->sum('estimated_cost'), 2);
        $repairTicketCost = round((float) $repairTickets->sum('spare_parts_cost'), 2);
        $repairTicketProfit = round($repairTicketRevenue - $repairTicketCost, 2);

        $totalRepairRevenue = round($repairSaleRevenue + $repairTicketRevenue, 2);
        $totalRepairProfit = round($repairSaleProfit + $repairTicketProfit, 2);
        $totalRepairCost = round($repairSaleCost + $repairTicketCost, 2);

        $totalSale = round($posNetSale + $totalRepairRevenue, 2);
        $grossSale = round($posGrossSale + $totalRepairRevenue, 2);

        $totalCogs = round($posCost + $totalRepairCost, 2);
        $grossProfit = round($totalSale - $totalCogs, 2);
        $totalExpense = round((float) $expenses->sum('amount'), 2);
        $netProfit = round($grossProfit - $totalExpense, 2);

        $udhaarCreated = round((float) $customerLedgers->filter(fn ($l) => ($l->type instanceof LedgerType ? $l->type->value : (string) $l->type) === 'sale')->sum('amount'), 2);
        $wasooliCollected = round((float) $customerLedgers->filter(fn ($l) => ($l->type instanceof LedgerType ? $l->type->value : (string) $l->type) === 'payment')->sum('amount'), 2);
        $advancesReceived = round((float) $customerLedgers->filter(fn ($l) => ($l->type instanceof LedgerType ? $l->type->value : (string) $l->type) === 'advance')->sum('amount'), 2);
        $advancesRefunded = round((float) $customerLedgers->filter(fn ($l) => ($l->type instanceof LedgerType ? $l->type->value : (string) $l->type) === 'advance_return')->sum('amount'), 2);
        $installmentCollected = round((float) $installmentPayments->sum('amount'), 2);

        $paymentReceived = max(0.00, round(
            $posPaidReceived + $wasooliCollected + $advancesReceived + $installmentCollected + $totalRepairRevenue - $advancesRefunded - $refundsTotal,
            2
        ));

        $usedPhoneBuying = round((float) $usedPhonePurchases->sum('purchase_amount'), 2);
        $supplierPurchases = round((float) $supplierLedgers->where('type', 'purchase')->sum('amount'), 2);
        $supplierPayments = round((float) $supplierLedgers->where('type', 'payment')->sum('amount'), 2);
        $supplierReturns = round((float) $supplierLedgers->where('type', 'return')->sum('amount'), 2);

        $totalPurchase = round($usedPhoneBuying + $supplierPurchases, 2);
        $totalPurchasePayment = round($usedPhoneBuying + $supplierPayments, 2);
        $totalPurchaseReturned = round($supplierReturns, 2);

        $saleDue = round((float) Customer::query()->where('current_balance', '>', 0)->sum('current_balance'), 2);
        $purchaseDue = round((float) Supplier::query()->where('current_balance', '>', 0)->sum('current_balance'), 2);
        $totalAdvance = round((float) abs(Customer::query()->where('current_balance', '<', 0)->sum('current_balance')), 2);

        $customerOpeningDuesQuery = CustomerLedger::query()
            ->where('type', 'adjustment')
            ->where('balance_after', '>', 0);
        $supplierOpeningDuesQuery = SupplierLedger::query()
            ->where('type', 'adjustment')
            ->where('balance_after', '>', 0);
        if ($start && $end) {
            $customerOpeningDuesQuery->whereBetween('created_at', [$start, $end]);
            $supplierOpeningDuesQuery->whereBetween('created_at', [$start, $end]);
        }
        $openingBalanceDues = round((float) $customerOpeningDuesQuery->sum('amount') + (float) $supplierOpeningDuesQuery->sum('amount'), 2);

        return [
            'total_sale' => $totalSale,
            'gross_sale' => $grossSale,
            'sales_count' => $sales->count() + $repairSales->count() + $repairTickets->count(),
            'total_returns' => $returnsTotal,
            'total_refunds' => $refundsTotal,
            'total_expense' => $totalExpense,
            'gross_profit' => $grossProfit,
            'net_profit' => $netProfit,
            'payment_received' => $paymentReceived,
            'total_discount' => round($totalDiscount, 2),
            'total_advance' => $totalAdvance,
            'udhaar_created' => $udhaarCreated,
            'wasooli_collected' => $wasooliCollected,
            'sale_due' => $saleDue,
            'purchase_due' => $purchaseDue,
            'total_due' => round($saleDue + $purchaseDue, 2),
            'used_phone_buying' => $usedPhoneBuying,
            'total_purchase' => $totalPurchase,
            'total_purchase_payment' => $totalPurchasePayment,
            'total_purchase_returned' => $totalPurchaseReturned,
            'opening_balance_dues' => $openingBalanceDues,
            'stock_valuation' => $totalValuation,
            'repair_revenue' => $totalRepairRevenue,
            'repair_profit' => $totalRepairProfit,
        ];
    }

    /**
     * @return list<array{method: string, label: string, amount: float}>
     */
    private function paymentMethodStats(
        Collection $sales,
        Collection $customerLedgers,
        Collection $repairSales,
        Collection $installmentPayments,
        Collection $saleReturns
    ): array {
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

        foreach ($customerLedgers as $ledger) {
            $type = $ledger->type instanceof LedgerType ? $ledger->type->value : (string) $ledger->type;
            $method = strtolower($ledger->payment_method ?? '');
            if (in_array($method, self::PAYMENT_METHODS, true)) {
                if ($type === 'payment' || $type === 'advance') {
                    $totals[$method] += (float) $ledger->amount;
                } elseif ($type === 'advance_return') {
                    $totals[$method] -= (float) $ledger->amount;
                }
            }
        }

        foreach ($repairSales as $repair) {
            $method = strtolower($repair->payment_method ?? 'cash');
            if (in_array($method, self::PAYMENT_METHODS, true)) {
                $totals[$method] += (float) $repair->total_amount;
            } else {
                $totals['cash'] += (float) $repair->total_amount;
            }
        }

        foreach ($installmentPayments as $inst) {
            $method = strtolower($inst->payment_method ?? 'cash');
            if (in_array($method, self::PAYMENT_METHODS, true)) {
                $totals[$method] += (float) $inst->amount;
            } else {
                $totals['cash'] += (float) $inst->amount;
            }
        }

        foreach ($saleReturns as $return) {
            $method = strtolower($return->refund_payment_method ?? 'cash');
            if (in_array($method, self::PAYMENT_METHODS, true)) {
                $totals[$method] -= (float) $return->refund_amount;
            }
        }

        return collect(self::PAYMENT_METHODS)
            ->map(fn (string $method) => [
                'method' => $method,
                'label' => PaymentMethod::from($method)->label(),
                'amount' => max(0.00, round($totals[$method], 2)),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array{labels: list<string>, sales: list<float>, payments: list<float>, profit: list<float>, expenses: list<float>}
     */
    private function buildGraphData(
        Collection $sales,
        Collection $saleReturns,
        Collection $repairSales,
        Collection $repairTickets,
        Collection $expenses,
        Collection $customerLedgers,
        Collection $installmentPayments,
        ?CarbonInterface $start,
        ?CarbonInterface $end
    ): array {
        $end ??= now();
        $earliest = collect([
            $sales->min('created_at'),
            $saleReturns->min('created_at'),
            $repairSales->min('created_at'),
            $repairTickets->min('delivered_at'),
            $expenses->min('created_at'),
            $customerLedgers->min('created_at'),
        ])->filter()->min();
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
            $profitData[$key] += ((float) $sale->net_amount - $cost);
        }

        foreach ($saleReturns as $return) {
            $key = $return->created_at->format($bucketFormat);
            if (array_key_exists($key, $salesData)) {
                $salesData[$key] -= (float) $return->total_return_amount;
                $paymentsData[$key] -= (float) $return->refund_amount;
                $profitData[$key] -= (float) $return->total_return_amount;
            }
        }

        foreach ($repairSales as $repair) {
            $key = $repair->created_at->format($bucketFormat);
            if (array_key_exists($key, $salesData)) {
                $cost = (float) $repair->cost_price * (float) $repair->quantity;
                $rev = (float) $repair->total_amount;
                $salesData[$key] += $rev;
                $paymentsData[$key] += $rev;
                $profitData[$key] += ($rev - $cost);
            }
        }

        foreach ($repairTickets as $ticket) {
            $key = $ticket->delivered_at?->format($bucketFormat);
            if ($key && array_key_exists($key, $salesData)) {
                $rev = (float) $ticket->estimated_cost;
                $cost = (float) $ticket->spare_parts_cost;
                $salesData[$key] += $rev;
                $paymentsData[$key] += $rev;
                $profitData[$key] += ($rev - $cost);
            }
        }

        foreach ($customerLedgers as $ledger) {
            $key = $ledger->created_at->format($bucketFormat);
            if (array_key_exists($key, $paymentsData)) {
                $type = $ledger->type instanceof LedgerType ? $ledger->type->value : (string) $ledger->type;
                if ($type === 'payment' || $type === 'advance') {
                    $paymentsData[$key] += (float) $ledger->amount;
                } elseif ($type === 'advance_return') {
                    $paymentsData[$key] -= (float) $ledger->amount;
                }
            }
        }

        foreach ($installmentPayments as $inst) {
            $key = Carbon::parse($inst->paid_at)->format($bucketFormat);
            if (array_key_exists($key, $paymentsData)) {
                $paymentsData[$key] += (float) $inst->amount;
            }
        }

        foreach ($expenses as $expense) {
            $key = $expense->created_at->format($bucketFormat);
            if (array_key_exists($key, $expensesData)) {
                $amt = (float) $expense->amount;
                $expensesData[$key] += $amt;
                $profitData[$key] -= $amt;
            }
        }

        return [
            'labels' => $labels,
            'sales' => array_values(array_map(fn ($v) => max(0.00, round($v, 2)), $salesData)),
            'payments' => array_values(array_map(fn ($v) => max(0.00, round($v, 2)), $paymentsData)),
            'profit' => array_values(array_map(fn ($v) => round($v, 2), $profitData)),
            'expenses' => array_values(array_map(fn ($v) => max(0.00, round($v, 2)), $expensesData)),
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
