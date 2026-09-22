<?php

namespace App\Http\Controllers;

use App\Models\Unit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UnitController extends Controller
{
    public function index(Request $request, string $currentTeam): Response
    {
        $search = trim($request->input('search', ''));
        $statusFilter = $request->input('status_filter', 'all');
        $perPage = (int) $request->input('per_page', 15);
        if (! in_array($perPage, [10, 15, 25, 50, 100, 250, 500], true)) {
            $perPage = 15;
        }

        $query = Unit::query()->orderBy('name', 'asc');

        if ($search !== '') {
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('short_name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($statusFilter === 'active') {
            $query->where('is_active', true);
        } elseif ($statusFilter === 'inactive') {
            $query->where('is_active', false);
        } elseif ($statusFilter === 'decimal') {
            $query->where('allow_decimal', true);
        }

        $units = $query->paginate($perPage)->withQueryString();

        $stats = [
            'total_units' => Unit::count(),
            'active_units' => Unit::where('is_active', true)->count(),
            'decimal_units' => Unit::where('allow_decimal', true)->count(),
        ];

        return Inertia::render('Units/Index', [
            'units' => $units,
            'filters' => [
                'search' => $search,
                'status_filter' => $statusFilter,
                'per_page' => $perPage,
            ],
            'stats' => $stats,
        ]);
    }

    public function store(Request $request, string $currentTeam): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'short_name' => ['required', 'string', 'max:50', 'unique:units,short_name'],
            'allow_decimal' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        Unit::create($validated);

        return redirect()->back()->with('success', 'Unit created successfully.');
    }

    public function update(Request $request, string $currentTeam, Unit $unit): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'short_name' => ['required', 'string', 'max:50', 'unique:units,short_name,'.$unit->id],
            'allow_decimal' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        $unit->update($validated);

        return redirect()->back()->with('success', 'Unit updated successfully.');
    }

    public function destroy(Request $request, string $currentTeam, Unit $unit): RedirectResponse
    {
        $unit->delete();

        return redirect()->back()->with('success', 'Unit deleted successfully.');
    }
}
