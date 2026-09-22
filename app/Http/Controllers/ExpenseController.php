<?php

namespace App\Http\Controllers;

use App\Models\RegisterShift;
use App\Models\ShopExpense;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ExpenseController extends Controller
{
    public function index(Request $request, string $currentTeam): Response
    {
        $search = trim($request->input('search', ''));
        $categoryFilter = $request->input('category_filter', 'all');
        $dateFilter = $request->input('date_filter', 'all');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        $query = ShopExpense::with(['user:id,name', 'shift:id,status,created_at'])->latest();

        if ($search !== '') {
            $query->where(function ($q) use ($search): void {
                $q->where('category', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($uq) use ($search): void {
                        $uq->where('name', 'like', "%{$search}%");
                    });
            });
        }

        if ($categoryFilter !== 'all' && $categoryFilter !== '') {
            $query->where('category', $categoryFilter);
        }

        if ($dateFilter === 'today') {
            $query->whereDate('created_at', now()->today());
        } elseif ($dateFilter === 'this_week') {
            $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]);
        } elseif ($dateFilter === 'this_month') {
            $query->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year);
        } elseif ($dateFilter === 'custom' && $dateFrom && $dateTo) {
            $query->whereBetween('created_at', [
                $dateFrom.' 00:00:00',
                $dateTo.' 23:59:59',
            ]);
        }

        $expenses = $query->paginate(20)->withQueryString();

        $todayTotal = (float) ShopExpense::whereDate('created_at', now()->today())->sum('amount');
        $todayCount = ShopExpense::whereDate('created_at', now()->today())->count();
        $monthTotal = (float) ShopExpense::whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->sum('amount');
        $monthCount = ShopExpense::whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();
        $allTimeTotal = (float) ShopExpense::sum('amount');
        $allTimeCount = ShopExpense::count();

        // Get active open shift for logged-in user if exists
        $activeShift = RegisterShift::where('user_id', $request->user()?->id)
            ->where('status', 'open')
            ->first(['id', 'status', 'created_at']);

        // Distinct existing categories in DB
        $existingCategories = ShopExpense::distinct()->pluck('category')->toArray();
        $defaultCategories = [
            'Tea & Refreshment',
            'Utilities (Electricity/Water/Net)',
            'Shop Rent',
            'Staff Salary / Advance',
            'Cleaning & Maintenance',
            'Stationery & Printing',
            'Repair Tools & Parts',
            'Transport / Fuel',
            'Miscellaneous',
        ];
        $allCategories = array_values(array_unique(array_merge($defaultCategories, $existingCategories)));

        return Inertia::render('Expenses/Index', [
            'expenses' => $expenses,
            'categories' => $allCategories,
            'activeShift' => $activeShift,
            'filters' => [
                'search' => $search,
                'category_filter' => $categoryFilter,
                'date_filter' => $dateFilter,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
            'stats' => [
                'today_total' => $todayTotal,
                'today_count' => $todayCount,
                'month_total' => $monthTotal,
                'month_count' => $monthCount,
                'all_time_total' => $allTimeTotal,
                'all_time_count' => $allTimeCount,
            ],
        ]);
    }

    public function store(Request $request, string $currentTeam): RedirectResponse
    {
        $validated = $request->validate([
            'category' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'register_shift_id' => ['nullable', 'exists:register_shifts,id'],
        ]);

        $shiftId = $validated['register_shift_id'] ?? null;

        if (! $shiftId) {
            $activeShift = RegisterShift::where('user_id', $request->user()?->id)
                ->where('status', 'open')
                ->first();
            if ($activeShift) {
                $shiftId = $activeShift->id;
            }
        }

        ShopExpense::create([
            'user_id' => $request->user()->id,
            'register_shift_id' => $shiftId,
            'category' => trim($validated['category']),
            'amount' => $validated['amount'],
            'notes' => $validated['notes'] ?? null,
        ]);

        return redirect()->back()->with('success', 'Expense recorded successfully.');
    }

    public function update(Request $request, string $currentTeam, ShopExpense $expense): RedirectResponse
    {
        $validated = $request->validate([
            'category' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $expense->update([
            'category' => trim($validated['category']),
            'amount' => $validated['amount'],
            'notes' => $validated['notes'] ?? null,
        ]);

        return redirect()->back()->with('success', 'Expense updated successfully.');
    }

    public function destroy(Request $request, string $currentTeam, ShopExpense $expense): RedirectResponse
    {
        $expense->delete();

        return redirect()->back()->with('success', 'Expense deleted successfully.');
    }
}
