<?php

use App\Enums\TeamRole;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CustomerLedgerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DiscountController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\InstallmentController;
use App\Http\Controllers\MobilePhonesController;
use App\Http\Controllers\MobileSaleController;
use App\Http\Controllers\PosController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductImeiController;
use App\Http\Controllers\RepairSaleController;
use App\Http\Controllers\RepairTicketController;
use App\Http\Controllers\ReportsController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\SaleReturnController;
use App\Http\Controllers\ShiftController;
use App\Http\Controllers\StockAdjustmentController;
use App\Http\Controllers\StockTransferController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\Teams\TeamInvitationController;
use App\Http\Controllers\UnitController;
use App\Http\Controllers\UsedPhonePurchaseController;
use App\Http\Controllers\YearlyDuesController;
use App\Http\Middleware\EnsureTeamMembership;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

// Direct shorthand routes protected by authentication
Route::middleware(['auth'])->group(function () {
    Route::get('dashboard', function (Request $request) {
        $team = $request->user()->currentTeam ?? $request->user()->personalTeam();
        return redirect("/{$team->slug}/dashboard");
    });

    Route::get('pos', function (Request $request) {
        $team = $request->user()->currentTeam ?? $request->user()->personalTeam();
        return redirect("/{$team->slug}/pos");
    });

    Route::get('customers', function (Request $request) {
        $team = $request->user()->currentTeam ?? $request->user()->personalTeam();
        return redirect("/{$team->slug}/customers");
    });

    Route::get('sales', function (Request $request) {
        $team = $request->user()->currentTeam ?? $request->user()->personalTeam();
        return redirect("/{$team->slug}/sales");
    });

    Route::get('sales-returns', function (Request $request) {
        $team = $request->user()->currentTeam ?? $request->user()->personalTeam();
        return redirect("/{$team->slug}/sales-returns");
    });

    Route::get('reports', function (Request $request) {
        $team = $request->user()->currentTeam ?? $request->user()->personalTeam();
        $role = $request->user()->teamRole($team);
        abort_if(! $role || ! $role->isAtLeast(TeamRole::Admin), 403);
        return redirect("/{$team->slug}/reports");
    });

    Route::get('inventory', function (Request $request) {
        $team = $request->user()->currentTeam ?? $request->user()->personalTeam();
        return redirect("/{$team->slug}/inventory");
    });

    Route::get('products', function (Request $request) {
        $team = $request->user()->currentTeam ?? $request->user()->personalTeam();
        return redirect("/{$team->slug}/products");
    });

    Route::get('repairs', function (Request $request) {
        $team = $request->user()->currentTeam ?? $request->user()->personalTeam();
        return redirect("/{$team->slug}/repairs");
    });

    Route::get('mobile-sales', function (Request $request) {
        $team = $request->user()->currentTeam ?? $request->user()->personalTeam();
        return redirect("/{$team->slug}/mobile-sales");
    });

    Route::get('repair-sales', function (Request $request) {
        $team = $request->user()->currentTeam ?? $request->user()->personalTeam();
        return redirect("/{$team->slug}/repair-sales");
    });

    Route::get('used-phones', function (Request $request) {
        $team = $request->user()->currentTeam ?? $request->user()->personalTeam();
        return redirect("/{$team->slug}/used-phones");
    });

    Route::get('shifts', function (Request $request) {
        $team = $request->user()->currentTeam ?? $request->user()->personalTeam();
        return redirect("/{$team->slug}/shifts");
    });

    Route::get('installments', function (Request $request) {
        $team = $request->user()->currentTeam ?? $request->user()->personalTeam();
        return redirect("/{$team->slug}/installments");
    });

    Route::get('yearly-dues', function (Request $request) {
        $team = $request->user()->currentTeam ?? $request->user()->personalTeam();
        return redirect("/{$team->slug}/yearly-dues");
    });

    Route::get('suppliers', function (Request $request) {
        $team = $request->user()->currentTeam ?? $request->user()->personalTeam();
        return redirect("/{$team->slug}/suppliers");
    });

    Route::get('expenses', function (Request $request) {
        $team = $request->user()->currentTeam ?? $request->user()->personalTeam();
        return redirect("/{$team->slug}/expenses");
    });

    Route::get('categories', function (Request $request) {
        $team = $request->user()->currentTeam ?? $request->user()->personalTeam();
        return redirect("/{$team->slug}/categories");
    });

    Route::get('units', function (Request $request) {
        $team = $request->user()->currentTeam ?? $request->user()->personalTeam();
        return redirect("/{$team->slug}/units");
    });

    Route::get('discounts', function (Request $request) {
        $team = $request->user()->currentTeam ?? $request->user()->personalTeam();
        return redirect("/{$team->slug}/discounts");
    });

    Route::get('stock-adjustments', function (Request $request) {
        $team = $request->user()->currentTeam ?? $request->user()->personalTeam();
        return redirect("/{$team->slug}/stock-adjustments");
    });

    Route::get('stock-transfers', function (Request $request) {
        $team = $request->user()->currentTeam ?? $request->user()->personalTeam();
        return redirect("/{$team->slug}/stock-transfers");
    });

    Route::get('admin', function (Request $request) {
        $team = $request->user()->currentTeam ?? $request->user()->personalTeam();
        $role = $request->user()->teamRole($team);
        abort_if(! $role || ! $role->isAtLeast(TeamRole::Admin), 403);
        return redirect("/{$team->slug}/dashboard");
    });
});

Route::prefix('{current_team}')
    ->middleware(['auth', 'verified', EnsureTeamMembership::class])
    ->group(function () {
        Route::get('dashboard', DashboardController::class)->name('dashboard');

        // POS Billing Terminal Routes
        Route::get('pos', [PosController::class, 'index'])->name('pos.index');
        Route::get('pos/products', [PosController::class, 'getProductsApi'])->name('pos.products');
        Route::get('pos/recent-sales', [PosController::class, 'getRecentSalesApi'])->name('pos.recent-sales');
        Route::get('pos/sales-search', [PosController::class, 'searchSalesForReturn'])->name('pos.sales-search');
        Route::post('pos/sales', [PosController::class, 'storeSale'])->name('pos.sales.store');
        Route::put('pos/sales/{sale}', [PosController::class, 'updateSaleApi'])->name('pos.sales.update');
        Route::post('pos/customers', [PosController::class, 'storeCustomer'])->name('pos.customers.store');
        Route::post('pos/customers/{customer}/pay-due', [PosController::class, 'recordDuePayment'])
            ->name('pos.customers.pay-due');
        Route::post('pos/customers/{customer}/refund-advance', [PosController::class, 'refundAdvance'])
            ->name('pos.customers.refund-advance');
        Route::post('pos/returns', [SaleReturnController::class, 'store'])->name('pos.returns.store');

        // Mobile Repairing Lab & Ticketing Routes
        Route::get('repairs', [RepairTicketController::class, 'index'])->name('repairs.index');
        Route::post('repairs', [RepairTicketController::class, 'store'])->name('repairs.store');
        Route::put('repairs/{ticket}/status', [RepairTicketController::class, 'updateStatus'])->name('repairs.update-status');
        Route::post('repairs/{ticket}/spare-parts', [RepairTicketController::class, 'addSparePart'])->name('repairs.spare-parts.add');
        Route::delete('repairs/{ticket}', [RepairTicketController::class, 'destroy'])
            ->middleware(EnsureTeamMembership::class.':admin')
            ->name('repairs.destroy');

        // Quick Mobile Handset Sale Routes
        Route::get('mobile-sales', [MobileSaleController::class, 'index'])->name('mobile-sales.index');

        // Quick Repair Sales Routes
        Route::get('repair-sales', [RepairSaleController::class, 'index'])->name('repair-sales.index');
        Route::post('repair-sales', [RepairSaleController::class, 'store'])->name('repair-sales.store');
        Route::delete('repair-sales/{repairSale}', [RepairSaleController::class, 'destroy'])
            ->middleware(EnsureTeamMembership::class.':admin')
            ->name('repair-sales.destroy');

        // Used Phone Purchases (Legal Log)
        Route::get('used-phones', [UsedPhonePurchaseController::class, 'index'])->name('used-phones.index');
        Route::post('used-phones', [UsedPhonePurchaseController::class, 'store'])->name('used-phones.store');
        Route::put('used-phones/{purchase}/status', [UsedPhonePurchaseController::class, 'updateStatus'])
            ->middleware(EnsureTeamMembership::class.':admin')
            ->name('used-phones.status.update');
        Route::delete('used-phones/{purchase}', [UsedPhonePurchaseController::class, 'destroy'])
            ->middleware(EnsureTeamMembership::class.':admin')
            ->name('used-phones.destroy');

        // Customer Khata Ledger Routes
        Route::get('customers', [CustomerLedgerController::class, 'index'])->name('customers.index');
        Route::post('customers', [CustomerLedgerController::class, 'store'])->name('customers.store');
        Route::put('customers/{customer}', [CustomerLedgerController::class, 'update'])->name('customers.update');
        Route::post('customers/{customer}/payments', [CustomerLedgerController::class, 'recordPayment'])->name('customers.payments.store');
        Route::post('customers/{customer}/advance', [CustomerLedgerController::class, 'recordAdvance'])->name('customers.advance.store');
        Route::post('customers/{customer}/refund-advance', [CustomerLedgerController::class, 'refundAdvance'])->name('customers.advance.refund');
        Route::get('customers/{customer}/statement', [CustomerLedgerController::class, 'statement'])->name('customers.statement');
        Route::get('customers/{customer}/statement/export', [CustomerLedgerController::class, 'statementExport'])->name('customers.statement.export');
        Route::delete('customers/{customer}', [CustomerLedgerController::class, 'destroy'])
            ->middleware(EnsureTeamMembership::class.':admin')
            ->name('customers.destroy');

        // Supplier Payables Routes
        Route::get('suppliers', [SupplierController::class, 'index'])->name('suppliers.index');
        Route::post('suppliers', [SupplierController::class, 'store'])->name('suppliers.store');
        Route::put('suppliers/{supplier}', [SupplierController::class, 'update'])->name('suppliers.update');
        Route::post('suppliers/{supplier}/purchases', [SupplierController::class, 'recordPurchase'])->name('suppliers.purchases.store');
        Route::post('suppliers/{supplier}/payments', [SupplierController::class, 'recordPayment'])->name('suppliers.payments.store');
        Route::get('suppliers/{supplier}/statement', [SupplierController::class, 'statement'])->name('suppliers.statement');
        Route::get('suppliers/{supplier}/statement/export', [SupplierController::class, 'statementExport'])->name('suppliers.statement.export');
        Route::delete('suppliers/{supplier}', [SupplierController::class, 'destroy'])
            ->middleware(EnsureTeamMembership::class.':admin')
            ->name('suppliers.destroy');

        // Customer Installment Routes
        Route::get('installments', [InstallmentController::class, 'index'])->name('installments.index');
        Route::post('installments', [InstallmentController::class, 'store'])->name('installments.store');
        Route::post('installments/{plan}/payments', [InstallmentController::class, 'recordPayment'])->name('installments.payments.store');

        // Handset Stock Write Routes
        // The handset stock *page* now lives inside MobileSales/Index (Stock tab),
        // so the old /mobile-phones URL just redirects there.
        Route::get('mobile-phones', function (Request $request, string $currentTeam) {
            return redirect()->route('mobile-sales.index', $currentTeam);
        });
        Route::post('mobile-phones', [MobilePhonesController::class, 'store'])->name('mobile-phones.store');

        // Inventory & Product Routes
        Route::get('inventory', [ProductController::class, 'index'])->name('inventory.index');
        Route::get('products', [ProductController::class, 'index'])->name('products.index');
        Route::post('products', [ProductController::class, 'store'])->name('products.store');
        Route::put('products/{product}', [ProductController::class, 'update'])->name('products.update');
        Route::delete('products/{product}', [ProductController::class, 'destroy'])
            ->middleware(EnsureTeamMembership::class.':admin')
            ->name('products.destroy');

        // Product IMEI Routes
        Route::post('products/{product}/imeis', [ProductImeiController::class, 'store'])->name('products.imeis.store');
        Route::post('products/{product}/imeis/bulk', [ProductImeiController::class, 'bulkStore'])->name('products.imeis.bulk-store');
        Route::put('imeis/{imei}', [ProductImeiController::class, 'update'])->name('imeis.update');
        Route::delete('imeis/{imei}', [ProductImeiController::class, 'destroy'])
            ->middleware(EnsureTeamMembership::class.':admin')
            ->name('imeis.destroy');

        // Import Templates & Bulk Upload Routes
        Route::get('products/import/template', [ImportController::class, 'productTemplate'])->name('products.imports.template');
        Route::post('products/import', [ImportController::class, 'importProducts'])->name('products.imports.store');
        Route::get('products/export', [ImportController::class, 'productExport'])->name('products.export');
        Route::get('customers/import/template', [ImportController::class, 'customerTemplate'])->name('customers.imports.template');
        Route::post('customers/import', [ImportController::class, 'importCustomers'])->name('customers.imports.store');
        Route::get('suppliers/import/template', [ImportController::class, 'supplierTemplate'])->name('suppliers.imports.template');
        Route::post('suppliers/import', [ImportController::class, 'importSuppliers'])->name('suppliers.imports.store');

        // Shifts & Cash Drawer Management
        Route::get('shifts', [ShiftController::class, 'index'])->name('shifts.index');
        Route::post('shifts/open', [ShiftController::class, 'open'])->name('shifts.open');
        Route::post('shifts/expense', [ShiftController::class, 'storeExpense'])->name('shifts.expense.store');
        Route::post('shifts/close', [ShiftController::class, 'close'])->name('shifts.close');

        // Analytics & Reports (Admin / Owner only)
        Route::get('reports', [ReportsController::class, 'index'])
            ->middleware(EnsureTeamMembership::class.':admin')
            ->name('reports.index');
        Route::get('reports/export', [ReportsController::class, 'export'])
            ->middleware(EnsureTeamMembership::class.':admin')
            ->name('reports.export');

        // Expenses Routes
        Route::get('expenses', [ExpenseController::class, 'index'])->name('expenses.index');
        Route::post('expenses', [ExpenseController::class, 'store'])->name('expenses.store');
        Route::put('expenses/{expense}', [ExpenseController::class, 'update'])->name('expenses.update');
        Route::delete('expenses/{expense}', [ExpenseController::class, 'destroy'])
            ->middleware(EnsureTeamMembership::class.':admin')
            ->name('expenses.destroy');

        // Direct Sales & Sale Returns Routes
        Route::get('sales', [SaleController::class, 'index'])->name('sales.index');
        Route::post('sales', [SaleController::class, 'store'])->name('sales.store');
        Route::get('sales-returns', [SaleReturnController::class, 'index'])->name('sales.returns.index');
        Route::post('sales-returns', [SaleReturnController::class, 'store'])->name('sales.returns.store');
        Route::get('sales-returns/search', [SaleReturnController::class, 'searchSale'])->name('sales.returns.search');

        // Category Routes
        Route::get('categories', [CategoryController::class, 'index'])->name('categories.index');
        Route::post('categories', [CategoryController::class, 'store'])->name('categories.store');
        Route::put('categories/{category}', [CategoryController::class, 'update'])->name('categories.update');
        Route::patch('categories/{category}/toggle', [CategoryController::class, 'toggleStatus'])->name('categories.toggle');
        Route::delete('categories/{category}', [CategoryController::class, 'destroy'])
            ->middleware(EnsureTeamMembership::class.':admin')
            ->name('categories.destroy');

        // Stock Adjustments Routes
        Route::get('stock-adjustments', [StockAdjustmentController::class, 'index'])->name('stock-adjustments.index');
        Route::post('stock-adjustments', [StockAdjustmentController::class, 'store'])->name('stock-adjustments.store');
        Route::delete('stock-adjustments/{stock_adjustment}', [StockAdjustmentController::class, 'destroy'])
            ->middleware(EnsureTeamMembership::class.':admin')
            ->name('stock-adjustments.destroy');

        // Stock Transfer (Between Branches) Routes
        Route::get('stock-transfers', [StockTransferController::class, 'index'])->name('stock-transfers.index');
        Route::post('stock-transfers', [StockTransferController::class, 'store'])->name('stock-transfers.store');
        Route::post('stock-transfers/{transfer}/receive', [StockTransferController::class, 'receive'])->name('stock-transfers.receive');
        Route::delete('stock-transfers/{transfer}', [StockTransferController::class, 'destroy'])
            ->middleware(EnsureTeamMembership::class.':admin')
            ->name('stock-transfers.destroy');

        // Yearly Dues Routes
        Route::get('yearly-dues', [YearlyDuesController::class, 'index'])->name('yearly-dues.index');
        Route::patch('yearly-dues/target', [YearlyDuesController::class, 'updateTarget'])
            ->middleware(EnsureTeamMembership::class.':admin')
            ->name('yearly-dues.target.update');

        // Units Routes
        Route::get('units', [UnitController::class, 'index'])->name('units.index');
        Route::post('units', [UnitController::class, 'store'])->name('units.store');
        Route::put('units/{unit}', [UnitController::class, 'update'])->name('units.update');
        Route::delete('units/{unit}', [UnitController::class, 'destroy'])
            ->middleware(EnsureTeamMembership::class.':admin')
            ->name('units.destroy');

        // Discounts & Promotions Routes
        Route::get('discounts', [DiscountController::class, 'index'])->name('discounts.index');
        Route::post('discounts', [DiscountController::class, 'store'])->name('discounts.store');
        Route::put('discounts/{discount}', [DiscountController::class, 'update'])->name('discounts.update');
        Route::patch('discounts/{discount}/toggle', [DiscountController::class, 'toggleStatus'])->name('discounts.toggle');
        Route::delete('discounts/{discount}', [DiscountController::class, 'destroy'])
            ->middleware(EnsureTeamMembership::class.':admin')
            ->name('discounts.destroy');

        // Database Backup (Admin / Owner only)
        Route::get('backup/download', [BackupController::class, 'download'])
            ->middleware(EnsureTeamMembership::class.':admin')
            ->name('backup.download');
    });

Route::middleware(['auth'])->group(function () {
    Route::post('invitations/{invitation}/accept', [TeamInvitationController::class, 'accept'])->name('invitations.accept');
    Route::delete('invitations/{invitation}', [TeamInvitationController::class, 'decline'])->name('invitations.decline');
});

require __DIR__.'/settings.php';
