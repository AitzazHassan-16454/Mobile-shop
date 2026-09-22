<?php

namespace App\Http\Controllers;

use App\Models\Discount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DiscountController extends Controller
{
    public function index(Request $request, string $currentTeam): Response
    {
        $search = trim($request->input('search', ''));
        $type = $request->input('type', 'all');
        $status = $request->input('status', 'all');
        $perPage = (int) $request->input('per_page', 15);

        if ($perPage <= 0 || $perPage > 500) {
            $perPage = 15;
        }

        $query = Discount::query();

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%");
            });
        }

        if ($type !== 'all' && in_array($type, ['percentage', 'fixed'])) {
            $query->where('type', $type);
        }

        if ($status === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        }

        $discounts = $query->latest()->paginate($perPage)->withQueryString();

        $summary = [
            'total_discounts' => Discount::count(),
            'active_discounts' => Discount::where('is_active', true)->count(),
            'total_redemptions' => (int) Discount::sum('used_count'),
            'max_offer_value' => (float) (Discount::where('type', 'fixed')->max('value') ?? 0),
        ];

        return Inertia::render('Discounts/Index', [
            'discounts' => $discounts,
            'filters' => [
                'search' => $search,
                'type' => $type,
                'status' => $status,
                'per_page' => $perPage,
            ],
            'summary' => $summary,
        ]);
    }

    public function store(Request $request, string $currentTeam): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:100', 'unique:discounts,code'],
            'type' => ['required', 'string', 'in:percentage,fixed'],
            'value' => ['required', 'numeric', 'min:0.01'],
            'min_purchase_amount' => ['nullable', 'numeric', 'min:0'],
            'max_discount_amount' => ['nullable', 'numeric', 'min:0'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['required', 'boolean'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        if (! empty($validated['code'])) {
            $validated['code'] = strtoupper(trim($validated['code']));
        }

        Discount::create($validated);

        return redirect()->back()->with('success', 'Discount rule created successfully.');
    }

    public function update(Request $request, string $currentTeam, Discount $discount): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:100', 'unique:discounts,code,'.$discount->id],
            'type' => ['required', 'string', 'in:percentage,fixed'],
            'value' => ['required', 'numeric', 'min:0.01'],
            'min_purchase_amount' => ['nullable', 'numeric', 'min:0'],
            'max_discount_amount' => ['nullable', 'numeric', 'min:0'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['required', 'boolean'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        if (! empty($validated['code'])) {
            $validated['code'] = strtoupper(trim($validated['code']));
        }

        $discount->update($validated);

        return redirect()->back()->with('success', 'Discount rule updated successfully.');
    }

    public function toggleStatus(Request $request, string $currentTeam, Discount $discount): RedirectResponse
    {
        $discount->update([
            'is_active' => ! $discount->is_active,
        ]);

        return redirect()->back()->with('success', 'Discount status updated.');
    }

    public function destroy(Request $request, string $currentTeam, Discount $discount): RedirectResponse
    {
        $discount->delete();

        return redirect()->back()->with('success', 'Discount rule deleted.');
    }
}
