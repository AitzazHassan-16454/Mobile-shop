<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerLedger;
use App\Models\InstallmentPayment;
use App\Models\Sale;
use App\Models\ShopExpense;
use App\Models\Supplier;
use App\Models\SupplierLedger;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PaymentsController extends Controller
{
    public function index(Request $request, string $currentTeam): Response
    {
        $filters = [
            'search' => trim($request->input('search', '')),
            'source' => $request->input('source', 'all'),
            'direction' => $request->input('direction', 'all'),
            'method' => $request->input('method', 'all'),
            'date_from' => $request->input('date_from', ''),
            'date_to' => $request->input('date_to', ''),
            'per_page' => (int) $request->input('per_page', 25),
        ];

        if (! in_array($filters['per_page'], [10, 15, 25, 50, 100, 250, 500], true)) {
            $filters['per_page'] = 25;
        }

        $sales = DB::table('sales as s')
            ->leftJoin('customers as c', 'c.id', '=', 's.customer_id')
            ->selectRaw(
                "'sale' as source, s.invoice_no as ref, 'Sale received' as description, "
                ."s.payment_method as method, COALESCE(c.name, 'Cash / Walk-in') as party, "
                ."'in' as direction, s.paid_amount as amount, s.created_at as paid_at"
            )
            ->where('s.paid_amount', '>', 0);

        $customerPayments = DB::table('customer_ledger as cl')
            ->join('customers as c', 'c.id', '=', 'cl.customer_id')
            ->where('cl.type', 'payment')
            ->selectRaw(
                "'customer_payment' as source, COALESCE(cl.reference_id, cl.id) as ref, "
                ."'Customer payment received' as description, '' as method, c.name as party, "
                ."'in' as direction, cl.amount as amount, cl.created_at as paid_at"
            );

        $installments = DB::table('installment_payments as ip')
            ->join('installment_plans as p', 'p.id', '=', 'ip.installment_plan_id')
            ->leftJoin('customers as c', 'c.id', '=', 'p.customer_id')
            ->selectRaw(
                "'installment' as source, ip.id as ref, 'Installment receipt' as description, "
                ."ip.payment_method as method, COALESCE(c.name, '-') as party, "
                ."'in' as direction, ip.amount as amount, ip.paid_at as paid_at"
            );

        $supplierPayments = DB::table('supplier_ledgers as sl')
            ->join('suppliers as sp', 'sp.id', '=', 'sl.supplier_id')
            ->where('sl.type', 'payment')
            ->selectRaw(
                "'supplier_payment' as source, COALESCE(sl.reference_id, '') as ref, "
                ."'Supplier payment made' as description, '' as method, sp.name as party, "
                ."'out' as direction, sl.amount as amount, sl.created_at as paid_at"
            );

        $expenses = DB::table('shop_expenses as e')
            ->selectRaw(
                "'expense' as source, e.id as ref, e.category as description, "
                ."'cash' as method, 'Shop expense' as party, "
                ."'out' as direction, e.amount as amount, e.created_at as paid_at"
            );

        $union = $sales
            ->unionAll($customerPayments)
            ->unionAll($installments)
            ->unionAll($supplierPayments)
            ->unionAll($expenses);

        $query = DB::query()->fromSub($union, 'payments');

        if ($filters['search'] !== '') {
            $query->where(function ($q) use ($filters) {
                $q->where('party', 'like', "%{$filters['search']}%")
                    ->orWhere('ref', 'like', "%{$filters['search']}%")
                    ->orWhere('description', 'like', "%{$filters['search']}%");
            });
        }

        if ($filters['source'] !== 'all') {
            $query->where('source', $filters['source']);
        }

        if ($filters['direction'] !== 'all') {
            $query->where('direction', $filters['direction']);
        }

        if ($filters['method'] !== 'all') {
            $query->where('method', $filters['method']);
        }

        if ($filters['date_from'] !== '') {
            $query->whereDate('paid_at', '>=', Carbon::parse($filters['date_from'])->startOfDay());
        }

        if ($filters['date_to'] !== '') {
            $query->whereDate('paid_at', '<=', Carbon::parse($filters['date_to'])->endOfDay());
        }

        $payments = $query->orderByDesc('paid_at')->paginate($filters['per_page'])->withQueryString();

        return Inertia::render('Payments/Index', [
            'payments' => $payments,
            'customers' => Customer::query()->orderBy('name')->get(['id', 'name', 'current_balance']),
            'suppliers' => Supplier::query()->orderBy('name')->get(['id', 'name', 'current_balance']),
            'filters' => $filters,
            'summary' => $this->summary(),
        ]);
    }

    private function summary(): array
    {
        $startOfMonth = now()->startOfMonth();

        $receivedInMonth =
            (float) CustomerLedger::where('type', 'payment')->where('created_at', '>=', $startOfMonth)->sum('amount')
            + (float) InstallmentPayment::where('paid_at', '>=', $startOfMonth)->sum('amount');

        $paidInMonth =
            (float) SupplierLedger::where('type', 'payment')->where('created_at', '>=', $startOfMonth)->sum('amount')
            + (float) ShopExpense::where('created_at', '>=', $startOfMonth)->sum('amount');

        return [
            'received_this_month' => $receivedInMonth,
            'paid_this_month' => $paidInMonth,
            'received_all_time' => (float) Sale::where('paid_amount', '>', 0)->sum('paid_amount')
                + (float) CustomerLedger::where('type', 'payment')->sum('amount')
                + (float) InstallmentPayment::sum('amount'),
            'paid_all_time' => (float) SupplierLedger::where('type', 'payment')->sum('amount')
                + (float) ShopExpense::sum('amount'),
        ];
    }
}
