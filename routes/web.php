<?php

use App\Http\Controllers\BackupController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CustomerLedgerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DiscountController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\InstallmentController;
use App\Http\Controllers\PosController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductImeiController;
use App\Http\Controllers\RepairTicketController;
use App\Http\Controllers\ReportsController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\SaleReturnController;
use App\Http\Controllers\ShiftController;
use App\Http\Controllers\StockAdjustmentController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\Teams\TeamInvitationController;
use App\Http\Controllers\UnitController;
use App\Http\Controllers\UsedPhonePurchaseController;
use App\Http\Middleware\EnsureTeamMembership;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::prefix('{current_team}')
    ->middleware(['auth', 'verified', EnsureTeamMembership::class])
    ->group(function () {
        Route::get('dashboard', DashboardController::class)->name('dashboard');

        // POS Billing Terminal Routes
        Route::get('pos', [PosController::class, 'index'])->name('pos.index');
        Route::get('pos/products', [PosController::class, 'getProductsApi'])->name('pos.products');
        Route::post('pos/sales', [PosController::class, 'storeSale'])->name('pos.sales.store');
        Route::post('pos/customers', [PosController::class, 'storeCustomer'])->name('pos.customers.store');

        // Mobile Repairing Lab & Ticketing Routes
        Route::get('repairs', [RepairTicketController::class, 'index'])->name('repairs.index');
        Route::post('repairs', [RepairTicketController::class, 'store'])->name('repairs.store');
        Route::put('repairs/{ticket}/status', [RepairTicketController::class, 'updateStatus'])->name('repairs.update-status');
        Route::post('repairs/{ticket}/spare-parts', [RepairTicketController::class, 'addSparePart'])->name('repairs.spare-parts.add');
        Route::delete('repairs/{ticket}', [RepairTicketController::class, 'destroy'])->name('repairs.destroy');

        // Used Phone Purchases (Legal Log)
        Route::get('used-phones', [UsedPhonePurchaseController::class, 'index'])->name('used-phones.index');
        Route::post('used-phones', [UsedPhonePurchaseController::class, 'store'])->name('used-phones.store');
        Route::delete('used-phones/{purchase}', [UsedPhonePurchaseController::class, 'destroy'])->name('used-phones.destroy');

        // Customer Khata Ledger Routes
        Route::get('customers', [CustomerLedgerController::class, 'index'])->name('customers.index');
        Route::post('customers', [CustomerLedgerController::class, 'store'])->name('customers.store');
        Route::put('customers/{customer}', [CustomerLedgerController::class, 'update'])->name('customers.update');
        Route::post('customers/{customer}/payments', [CustomerLedgerController::class, 'recordPayment'])->name('customers.payments.store');
        Route::delete('customers/{customer}', [CustomerLedgerController::class, 'destroy'])->name('customers.destroy');

        // Supplier Payables Routes
        Route::get('suppliers', [SupplierController::class, 'index'])->name('suppliers.index');
        Route::post('suppliers', [SupplierController::class, 'store'])->name('suppliers.store');
        Route::put('suppliers/{supplier}', [SupplierController::class, 'update'])->name('suppliers.update');
        Route::post('suppliers/{supplier}/purchases', [SupplierController::class, 'recordPurchase'])->name('suppliers.purchases.store');
        Route::post('suppliers/{supplier}/payments', [SupplierController::class, 'recordPayment'])->name('suppliers.payments.store');
        Route::delete('suppliers/{supplier}', [SupplierController::class, 'destroy'])->name('suppliers.destroy');

        // Customer Installment Routes
        Route::get('installments', [InstallmentController::class, 'index'])->name('installments.index');
        Route::post('installments', [InstallmentController::class, 'store'])->name('installments.store');
        Route::post('installments/{plan}/payments', [InstallmentController::class, 'recordPayment'])->name('installments.payments.store');

        // Inventory & Product Routes
        Route::get('inventory', [ProductController::class, 'index'])->name('inventory.index');
        Route::get('products', [ProductController::class, 'index'])->name('products.index');
        Route::post('products', [ProductController::class, 'store'])->name('products.store');
        Route::put('products/{product}', [ProductController::class, 'update'])->name('products.update');
        Route::delete('products/{product}', [ProductController::class, 'destroy'])->name('products.destroy');

        // Product IMEI Routes
        Route::post('products/{product}/imeis', [ProductImeiController::class, 'store'])->name('products.imeis.store');
        Route::post('products/{product}/imeis/bulk', [ProductImeiController::class, 'bulkStore'])->name('products.imeis.bulk-store');
        Route::put('imeis/{imei}', [ProductImeiController::class, 'update'])->name('imeis.update');
        Route::delete('imeis/{imei}', [ProductImeiController::class, 'destroy'])->name('imeis.destroy');

        // Shifts & Cash Drawer Management
        Route::get('shifts', [ShiftController::class, 'index'])->name('shifts.index');
        Route::post('shifts/open', [ShiftController::class, 'open'])->name('shifts.open');
        Route::post('shifts/expense', [ShiftController::class, 'storeExpense'])->name('shifts.expense.store');
        Route::post('shifts/close', [ShiftController::class, 'close'])->name('shifts.close');

        // Analytics & Reports
        Route::get('reports', [ReportsController::class, 'index'])->name('reports.index');

        // Module Pages (placeholders)
        // Expenses Routes
        Route::get('expenses', [ExpenseController::class, 'index'])->name('expenses.index');
        Route::post('expenses', [ExpenseController::class, 'store'])->name('expenses.store');
        Route::put('expenses/{expense}', [ExpenseController::class, 'update'])->name('expenses.update');
        Route::delete('expenses/{expense}', [ExpenseController::class, 'destroy'])->name('expenses.destroy');

        // Direct Sales & Sale Returns Routes
        Route::get('sales', [SaleController::class, 'index'])->name('sales.index');
        Route::post('sales', [SaleController::class, 'store'])->name('sales.store');
        Route::get('sales-returns', [SaleReturnController::class, 'index'])->name('sales.returns.index');
        Route::post('sales-returns', [SaleReturnController::class, 'store'])->name('sales.returns.store');

        // Category Routes
        Route::get('categories', [CategoryController::class, 'index'])->name('categories.index');
        Route::post('categories', [CategoryController::class, 'store'])->name('categories.store');
        Route::put('categories/{category}', [CategoryController::class, 'update'])->name('categories.update');
        Route::patch('categories/{category}/toggle', [CategoryController::class, 'toggleStatus'])->name('categories.toggle');
        Route::delete('categories/{category}', [CategoryController::class, 'destroy'])->name('categories.destroy');
        // Stock Adjustments Routes
        Route::get('stock-adjustments', [StockAdjustmentController::class, 'index'])->name('stock-adjustments.index');
        Route::post('stock-adjustments', [StockAdjustmentController::class, 'store'])->name('stock-adjustments.store');
        Route::delete('stock-adjustments/{stock_adjustment}', [StockAdjustmentController::class, 'destroy'])->name('stock-adjustments.destroy');

        // Units Routes
        Route::get('units', [UnitController::class, 'index'])->name('units.index');
        Route::post('units', [UnitController::class, 'store'])->name('units.store');
        Route::put('units/{unit}', [UnitController::class, 'update'])->name('units.update');
        Route::delete('units/{unit}', [UnitController::class, 'destroy'])->name('units.destroy');

        // Discounts & Promotions Routes
        Route::get('discounts', [DiscountController::class, 'index'])->name('discounts.index');
        Route::post('discounts', [DiscountController::class, 'store'])->name('discounts.store');
        Route::put('discounts/{discount}', [DiscountController::class, 'update'])->name('discounts.update');
        Route::patch('discounts/{discount}/toggle', [DiscountController::class, 'toggleStatus'])->name('discounts.toggle');
        Route::delete('discounts/{discount}', [DiscountController::class, 'destroy'])->name('discounts.destroy');

        // Database Backup
        Route::get('backup/download', [BackupController::class, 'download'])->name('backup.download');
    });

Route::middleware(['auth'])->group(function () {
    Route::post('invitations/{invitation}/accept', [TeamInvitationController::class, 'accept'])->name('invitations.accept');
    Route::delete('invitations/{invitation}', [TeamInvitationController::class, 'decline'])->name('invitations.decline');
});

require __DIR__.'/settings.php';
