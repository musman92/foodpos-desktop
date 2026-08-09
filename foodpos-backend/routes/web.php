<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

// Public routes
Route::get('/', function () {
    return redirect()->route('login');
});

Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('/login', [LoginController::class, 'login']);
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

// Secret login — SaaS remote support only (disabled in offline edition)
if (! config('offline.enabled')) {
    Route::get('/secret-login/{token}', [\App\Http\Controllers\Auth\SecretLoginController::class, 'login'])->name('secret-login');
}
// Signed print URLs for desktop bridge (no session auth; signature validated)
Route::get('/print/job/{printJob}/{token}', [\App\Http\Controllers\SignedPrintController::class, 'printJob'])
    ->whereNumber('printJob')
    ->where('token', '[a-f0-9]{64}')
    ->name('print.job');
Route::get('/print/kitchen-kot/{kitchenKot}', [\App\Http\Controllers\SignedPrintController::class, 'kitchenKot'])
    ->name('print.kitchen-kot');
Route::get('/print/receipt/{order}', [\App\Http\Controllers\SignedPrintController::class, 'receipt'])
    ->name('print.receipt');
Route::get('/print/test/{printer}', [\App\Http\Controllers\SignedPrintController::class, 'test'])
    ->whereNumber('printer')
    ->name('print.test');

// Protected routes
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    if (! config('offline.enabled')) {
        Route::post('/branch/switch', [\App\Http\Controllers\BranchController::class, 'switch'])->name('branch.switch');

        // Company Management (Super Admin only)
        Route::get('companies/{company}/secret-login', [\App\Http\Controllers\CompanyController::class, 'secretLogin'])->name('companies.secret-login');
        Route::get('companies/{company}/password', [\App\Http\Controllers\CompanyController::class, 'editPassword'])->name('companies.password.edit');
        Route::put('companies/{company}/password', [\App\Http\Controllers\CompanyController::class, 'updatePassword'])->name('companies.password.update');
        Route::post('companies/{company}/reset-demo-data', [\App\Http\Controllers\CompanyController::class, 'resetDemoData'])->name('companies.reset-demo-data');
        Route::post('companies/{company}/reset-transactional-data', [\App\Http\Controllers\CompanyController::class, 'resetTransactionalData'])->name('companies.reset-transactional-data');
        Route::post('companies/{company}/generate-print-logo', [\App\Http\Controllers\CompanyController::class, 'generatePrintLogo'])->name('companies.generate-print-logo');
        Route::resource('companies', \App\Http\Controllers\CompanyController::class);

        // Platform media library (super admin upload; tenants pick when creating menu items)
        Route::get('platform-media/browse', [\App\Http\Controllers\PlatformMediaController::class, 'browse'])->name('platform-media.browse');
        Route::resource('platform-media', \App\Http\Controllers\PlatformMediaController::class)
            ->only(['index', 'store', 'destroy'])
            ->parameters(['platform-media' => 'platform_medium']);

        // Super admin maintenance actions (whitelisted Artisan commands)
        Route::get('platform-actions', [\App\Http\Controllers\PlatformActionsController::class, 'index'])->name('platform-actions.index');
        Route::post('platform-actions/{action}', [\App\Http\Controllers\PlatformActionsController::class, 'run'])->name('platform-actions.run');

        // Super admin database backups
        Route::get('database-backups', [\App\Http\Controllers\DatabaseBackupController::class, 'index'])->name('database-backups.index');
        Route::post('database-backups', [\App\Http\Controllers\DatabaseBackupController::class, 'store'])->name('database-backups.store');
        Route::post('database-backups/upload', [\App\Http\Controllers\DatabaseBackupController::class, 'upload'])->name('database-backups.upload');
        Route::get('database-backups/{backup}/download', [\App\Http\Controllers\DatabaseBackupController::class, 'download'])->name('database-backups.download')->where('backup', '.+');
        Route::post('database-backups/{backup}/restore', [\App\Http\Controllers\DatabaseBackupController::class, 'restore'])->name('database-backups.restore')->where('backup', '.+');
        Route::delete('database-backups/{backup}', [\App\Http\Controllers\DatabaseBackupController::class, 'destroy'])->name('database-backups.destroy')->where('backup', '.+');

        // Platform billing (super admin invoices tenant companies)
        Route::get('platform-billing/report', [\App\Http\Controllers\PlatformBillingReportController::class, 'index'])->name('platform-billing.report');
        Route::get('platform-invoices/billing-context/{company}', [\App\Http\Controllers\PlatformInvoiceController::class, 'billingContext'])->name('platform-invoices.billing-context');
        Route::post('platform-invoices/generate/{company}', [\App\Http\Controllers\PlatformInvoiceController::class, 'generateFromPlan'])->name('platform-invoices.generate');
        Route::post('platform-invoices/{platformInvoice}/void', [\App\Http\Controllers\PlatformInvoiceController::class, 'void'])->name('platform-invoices.void');
        Route::get('platform-invoices/{platformInvoice}/print', [\App\Http\Controllers\PlatformInvoiceController::class, 'print'])->name('platform-invoices.print');
        Route::post('platform-invoices/{platformInvoice}/payments', [\App\Http\Controllers\PlatformInvoicePaymentController::class, 'store'])->name('platform-invoices.payments.store');
        Route::resource('platform-invoices', \App\Http\Controllers\PlatformInvoiceController::class)->except(['destroy']);

        // Multi-branch management (SaaS only — offline uses one hidden default branch)
        Route::resource('branches', \App\Http\Controllers\BranchController::class);
    }

    Route::resource('floors', \App\Http\Controllers\FloorController::class);
    Route::resource('tables', \App\Http\Controllers\TableController::class);

    // User Management
    Route::resource('users', \App\Http\Controllers\UserController::class);

    // Roles & Permissions (tenant-level)
    Route::resource('roles', \App\Http\Controllers\RoleController::class);

    // Printer Settings
    Route::get('/printer-settings', [\App\Http\Controllers\PrinterSettingsController::class, 'index'])->name('printer-settings.index');
    Route::post('/printer-settings/printers', [\App\Http\Controllers\PrinterSettingsController::class, 'storePrinter'])->name('printer-settings.printers.store');
    Route::put('/printer-settings/printers/{printer}', [\App\Http\Controllers\PrinterSettingsController::class, 'updatePrinter'])->name('printer-settings.printers.update');
    Route::delete('/printer-settings/printers/{printer}', [\App\Http\Controllers\PrinterSettingsController::class, 'destroyPrinter'])->name('printer-settings.printers.destroy');
    Route::post('/printer-settings/desktop-keys', [\App\Http\Controllers\PrinterSettingsController::class, 'generateDesktopKey'])->name('printer-settings.desktop-keys.generate');
    Route::post('/printer-settings/desktop-keys/{branchDesktopKey}/ping', [\App\Http\Controllers\PrinterSettingsController::class, 'pingDesktopKey'])->name('printer-settings.desktop-keys.ping');
    Route::post('/printer-settings/desktop-keys/{branchDesktopKey}/fetch-printers', [\App\Http\Controllers\PrinterSettingsController::class, 'fetchDesktopPrinters'])->name('printer-settings.desktop-keys.fetch-printers');
    Route::get('/printer-settings/desktop-keys/{branchDesktopKey}/status', [\App\Http\Controllers\PrinterSettingsController::class, 'desktopKeyStatus'])->name('printer-settings.desktop-keys.status');
    Route::delete('/printer-settings/desktop-keys/{branchDesktopKey}', [\App\Http\Controllers\PrinterSettingsController::class, 'revokeDesktopKey'])->name('printer-settings.desktop-keys.revoke');
    Route::post('/printer-settings/printers/{printer}/test', [\App\Http\Controllers\PrinterSettingsController::class, 'testPrint'])->name('printer-settings.printers.test');
    Route::post('/printer-settings/printers/{printer}/verify', [\App\Http\Controllers\PrinterSettingsController::class, 'verifyPrinter'])->name('printer-settings.printers.verify');

    // Company Settings
    Route::get('/company-settings', [\App\Http\Controllers\CompanySettingsController::class, 'index'])->name('company-settings.index');
    Route::put('/company-settings/{company}/general', [\App\Http\Controllers\CompanySettingsController::class, 'updateGeneral'])->name('company-settings.update.general');
    Route::put('/company-settings/{company}/preferences', [\App\Http\Controllers\CompanySettingsController::class, 'updatePreferences'])->name('company-settings.update.preferences');
    Route::put('/company-settings/{company}/pos', [\App\Http\Controllers\CompanySettingsController::class, 'updatePos'])->name('company-settings.update.pos');
    Route::put('/company-settings/{company}/receipt', [\App\Http\Controllers\CompanySettingsController::class, 'updateReceipt'])->name('company-settings.update.receipt');

    Route::get('/activity-logs', [\App\Http\Controllers\ActivityLogController::class, 'index'])->name('activity-logs.index');
    Route::post('/activity-logs/toggle', [\App\Http\Controllers\ActivityLogController::class, 'toggle'])->name('activity-logs.toggle');

    // Customer Management
    Route::get('customers/import/sample', [\App\Http\Controllers\CustomerController::class, 'importSample'])->name('customers.import.sample');
    Route::get('customers/export', [\App\Http\Controllers\CustomerController::class, 'export'])->name('customers.export');
    Route::get('customers/import', [\App\Http\Controllers\CustomerController::class, 'import'])->name('customers.import');
    Route::post('customers/import', [\App\Http\Controllers\CustomerController::class, 'importStore'])->name('customers.import.store');
    Route::get('customers/search', [\App\Http\Controllers\CustomerController::class, 'search'])->name('customers.search');
    Route::post('customers/quick-store', [\App\Http\Controllers\CustomerController::class, 'quickStore'])->name('customers.quick-store');
    Route::get('customers/{customer}/balance-adjustment', [\App\Http\Controllers\CustomerController::class, 'balanceAdjustment'])->name('customers.balance-adjustment');
    Route::post('customers/{customer}/balance-adjustment', [\App\Http\Controllers\CustomerController::class, 'storeBalanceAdjustment'])->name('customers.balance-adjustment.store');
    Route::resource('customers', \App\Http\Controllers\CustomerController::class);

    // Supplier Management
    Route::get('suppliers/import/sample', [\App\Http\Controllers\SupplierController::class, 'importSample'])->name('suppliers.import.sample');
    Route::get('suppliers/export', [\App\Http\Controllers\SupplierController::class, 'export'])->name('suppliers.export');
    Route::get('suppliers/import', [\App\Http\Controllers\SupplierController::class, 'import'])->name('suppliers.import');
    Route::post('suppliers/import', [\App\Http\Controllers\SupplierController::class, 'importStore'])->name('suppliers.import.store');
    Route::get('suppliers/{supplier}/balance-adjustment', [\App\Http\Controllers\SupplierController::class, 'balanceAdjustment'])->name('suppliers.balance-adjustment');
    Route::post('suppliers/{supplier}/balance-adjustment', [\App\Http\Controllers\SupplierController::class, 'storeBalanceAdjustment'])->name('suppliers.balance-adjustment.store');
    Route::resource('suppliers', \App\Http\Controllers\SupplierController::class);

    // Category Management
    Route::get('categories/import/sample', [\App\Http\Controllers\CategoryController::class, 'importSample'])->name('categories.import.sample');
    Route::get('categories/export', [\App\Http\Controllers\CategoryController::class, 'export'])->name('categories.export');
    Route::get('categories/import', [\App\Http\Controllers\CategoryController::class, 'import'])->name('categories.import');
    Route::post('categories/import', [\App\Http\Controllers\CategoryController::class, 'importStore'])->name('categories.import.store');
    Route::resource('categories', \App\Http\Controllers\CategoryController::class);

    // Cuisine Management
    Route::resource('cuisines', \App\Http\Controllers\CuisineController::class);

    // Tax Management
    Route::resource('taxes', \App\Http\Controllers\TaxController::class);

    // Ingredient Management
    Route::get('ingredients/import/sample', [\App\Http\Controllers\IngredientController::class, 'importSample'])->name('ingredients.import.sample');
    Route::get('ingredients/export', [\App\Http\Controllers\IngredientController::class, 'export'])->name('ingredients.export');
    Route::get('ingredients/import', [\App\Http\Controllers\IngredientController::class, 'import'])->name('ingredients.import');
    Route::post('ingredients/import', [\App\Http\Controllers\IngredientController::class, 'importStore'])->name('ingredients.import.store');
    Route::resource('ingredients', \App\Http\Controllers\IngredientController::class);

    // Ingredient Category Management
    Route::get('ingredient-categories/import/sample', [\App\Http\Controllers\IngredientCategoryController::class, 'importSample'])->name('ingredient-categories.import.sample');
    Route::get('ingredient-categories/export', [\App\Http\Controllers\IngredientCategoryController::class, 'export'])->name('ingredient-categories.export');
    Route::get('ingredient-categories/import', [\App\Http\Controllers\IngredientCategoryController::class, 'import'])->name('ingredient-categories.import');
    Route::post('ingredient-categories/import', [\App\Http\Controllers\IngredientCategoryController::class, 'importStore'])->name('ingredient-categories.import.store');
    Route::resource('ingredient-categories', \App\Http\Controllers\IngredientCategoryController::class);

    // Ingredient Unit Management
    Route::get('ingredient-units/import/sample', [\App\Http\Controllers\IngredientUnitController::class, 'importSample'])->name('ingredient-units.import.sample');
    Route::get('ingredient-units/export', [\App\Http\Controllers\IngredientUnitController::class, 'export'])->name('ingredient-units.export');
    Route::get('ingredient-units/import', [\App\Http\Controllers\IngredientUnitController::class, 'import'])->name('ingredient-units.import');
    Route::post('ingredient-units/import', [\App\Http\Controllers\IngredientUnitController::class, 'importStore'])->name('ingredient-units.import.store');
    Route::resource('ingredient-units', \App\Http\Controllers\IngredientUnitController::class);

    // Product Addon Management
    Route::get('product-addons/import/sample/{format?}', [\App\Http\Controllers\ProductAddonController::class, 'importSample'])->name('product-addons.import.sample');
    Route::get('product-addons/export', [\App\Http\Controllers\ProductAddonController::class, 'export'])->name('product-addons.export');
    Route::get('product-addons/import', [\App\Http\Controllers\ProductAddonController::class, 'import'])->name('product-addons.import');
    Route::post('product-addons/import', [\App\Http\Controllers\ProductAddonController::class, 'importStore'])->name('product-addons.import.store');
    Route::resource('product-addons', \App\Http\Controllers\ProductAddonController::class);
    Route::get('variants/import/sample', [\App\Http\Controllers\VariantController::class, 'importSample'])->name('variants.import.sample');
    Route::get('variants/export', [\App\Http\Controllers\VariantController::class, 'export'])->name('variants.export');
    Route::get('variants/import', [\App\Http\Controllers\VariantController::class, 'import'])->name('variants.import');
    Route::post('variants/import', [\App\Http\Controllers\VariantController::class, 'importStore'])->name('variants.import.store');
    Route::resource('variants', \App\Http\Controllers\VariantController::class);

    Route::get('recipes/import/sample', [\App\Http\Controllers\RecipeController::class, 'importSample'])->name('recipes.import.sample');
    Route::get('recipes/export', [\App\Http\Controllers\RecipeController::class, 'export'])->name('recipes.export');
    Route::get('recipes/import', [\App\Http\Controllers\RecipeController::class, 'import'])->name('recipes.import');
    Route::post('recipes/import', [\App\Http\Controllers\RecipeController::class, 'importStore'])->name('recipes.import.store');
    Route::resource('recipes', \App\Http\Controllers\RecipeController::class);

    // Menu Item Management
    Route::get('menu-items/import/sample', [\App\Http\Controllers\MenuItemController::class, 'importSample'])->name('menu-items.import.sample');
    Route::get('menu-items/export', [\App\Http\Controllers\MenuItemController::class, 'export'])->name('menu-items.export');
    Route::get('menu-items/import', [\App\Http\Controllers\MenuItemController::class, 'import'])->name('menu-items.import');
    Route::post('menu-items/import', [\App\Http\Controllers\MenuItemController::class, 'importStore'])->name('menu-items.import.store');
    Route::post('menu-items/{menu_item}/duplicate', [\App\Http\Controllers\MenuItemController::class, 'duplicate'])->name('menu-items.duplicate');
    Route::resource('menu-items', \App\Http\Controllers\MenuItemController::class);

    // Deals (combo offers)
    Route::get('deals/menu-items/{menuItem}/variants', [\App\Http\Controllers\DealController::class, 'menuItemVariants'])->name('deals.menu-item-variants');
    Route::resource('deals', \App\Http\Controllers\DealController::class);

    // Account Management
    Route::resource('accounts', \App\Http\Controllers\AccountController::class);

    // Money Source Management
    Route::get('money-sources/operational-balances', [\App\Http\Controllers\MoneySourceController::class, 'operationalBalances'])->name('money-sources.operational-balances');
    Route::get('money-sources/reports', [\App\Http\Controllers\MoneySourceController::class, 'reports'])->name('money-sources.reports');
    Route::middleware('require.active.shift')->group(function () {
        Route::get('money-sources/transfer', [\App\Http\Controllers\MoneySourceController::class, 'transferCreate'])->name('money-sources.transfer.create');
        Route::post('money-sources/transfer', [\App\Http\Controllers\MoneySourceController::class, 'transferStore'])->name('money-sources.transfer.store');
        Route::get('money-sources/owner-withdrawal', [\App\Http\Controllers\MoneySourceController::class, 'ownerWithdrawalCreate'])->name('money-sources.owner-withdrawal.create');
        Route::post('money-sources/owner-withdrawal', [\App\Http\Controllers\MoneySourceController::class, 'ownerWithdrawalStore'])->name('money-sources.owner-withdrawal.store');
        Route::get('money-sources/{moneySource}/transfer', [\App\Http\Controllers\MoneySourceController::class, 'transfer'])->name('money-sources.transfer.redirect');
        Route::post('money-sources/{moneySource}/transfer', [\App\Http\Controllers\MoneySourceController::class, 'processTransfer'])->name('money-sources.transfer.process');
        Route::get('money-sources/{moneySource}/reconcile', [\App\Http\Controllers\MoneySourceController::class, 'reconcile'])->name('money-sources.reconcile');
        Route::post('money-sources/{moneySource}/reconcile', [\App\Http\Controllers\MoneySourceController::class, 'processReconcile'])->name('money-sources.reconcile.process');
    });
    Route::resource('money-sources', \App\Http\Controllers\MoneySourceController::class);

    // Inventory Management
    Route::get('/inventory', [\App\Http\Controllers\InventoryController::class, 'index'])->name('inventory.index');
    Route::get('/inventory/adjustment', [\App\Http\Controllers\InventoryAdjustmentController::class, 'index'])->name('inventory.adjustment.index');
    Route::get('/inventory/adjustment/create', [\App\Http\Controllers\InventoryAdjustmentController::class, 'create'])->name('inventory.adjustment.create');
    Route::get('/inventory/adjustment/stock', [\App\Http\Controllers\InventoryAdjustmentController::class, 'stock'])->name('inventory.adjustment.stock');
    Route::post('/inventory/adjustment', [\App\Http\Controllers\InventoryAdjustmentController::class, 'store'])->name('inventory.adjustment.store');
    Route::get('/inventory/adjustment/{stockMovement}/edit', [\App\Http\Controllers\InventoryAdjustmentController::class, 'edit'])->name('inventory.adjustment.edit');
    Route::put('/inventory/adjustment/{stockMovement}', [\App\Http\Controllers\InventoryAdjustmentController::class, 'update'])->name('inventory.adjustment.update');
    Route::delete('/inventory/adjustment/{stockMovement}', [\App\Http\Controllers\InventoryAdjustmentController::class, 'destroy'])->name('inventory.adjustment.destroy');
    Route::get('/inventory/adjustment/{stockMovement}', [\App\Http\Controllers\InventoryAdjustmentController::class, 'show'])->name('inventory.adjustment.show');

    // Order management (refunds, notes; no active shift required)
    Route::prefix('order-management')->name('order-management.')->group(function () {
        Route::get('/', [\App\Http\Controllers\OrderManagementController::class, 'index'])->name('index');
        Route::get('/orders/{order}', [\App\Http\Controllers\OrderManagementController::class, 'show'])->name('show');
        Route::post('/orders/{order}/refund', [\App\Http\Controllers\OrderManagementController::class, 'refund'])->name('refund');
        Route::delete('/orders/{order}', [\App\Http\Controllers\OrderManagementController::class, 'destroy'])->name('destroy');
        Route::post('/orders/{order}/notes', [\App\Http\Controllers\OrderManagementController::class, 'appendNote'])->name('append-note');
        Route::get('/refunds', [\App\Http\Controllers\OrderManagementController::class, 'refundsIndex'])->name('refunds.index');
        Route::get('/refunds/start', [\App\Http\Controllers\OrderManagementController::class, 'refundsStart'])->name('refunds.start');
        Route::post('/refunds/lookup', [\App\Http\Controllers\OrderManagementController::class, 'refundsLookup'])->name('refunds.lookup');
        Route::get('/refunds/process/{order}', [\App\Http\Controllers\OrderManagementController::class, 'refundProcess'])->name('refunds.process');
    });

    // Account statements (uses global branch context; no active shift required)
    Route::get('/account-statements', [\App\Http\Controllers\AccountStatementController::class, 'index'])->name('account-statements.index');
    Route::get('/account-statements/pdf', [\App\Http\Controllers\AccountStatementController::class, 'pdf'])->name('account-statements.pdf');
    Route::get('/account-statements/search', [\App\Http\Controllers\AccountStatementController::class, 'search'])->name('account-statements.search');

    // Reports (collapsible menu; no active shift required)
    Route::get('/reports', [\App\Http\Controllers\ReportHubController::class, 'index'])->name('reports.index');
    Route::get('/reports/panel', [\App\Http\Controllers\ReportHubController::class, 'panel'])->name('reports.panel');
    Route::get('/reports/consumption', [\App\Http\Controllers\ReportController::class, 'consumption'])->name('reports.consumption');
    Route::get('/reports/consumption/filter-options', [\App\Http\Controllers\ReportController::class, 'consumptionFilterOptions'])
        ->name('reports.consumption.filter-options');
    Route::get('/reports/consumption/pdf', [\App\Http\Controllers\ReportController::class, 'consumptionPdf'])->name('reports.consumption.pdf');
    Route::get('/reports/consumption/excel', [\App\Http\Controllers\ReportController::class, 'consumptionExcel'])->name('reports.consumption.excel');
    Route::get('/reports/consumption/{itemType}/{itemId}', [\App\Http\Controllers\ReportController::class, 'consumptionDetail'])
        ->whereIn('itemType', ['ingredient', 'menu_item'])
        ->whereNumber('itemId')
        ->name('reports.consumption.detail');
    Route::get('/reports/ingredient-ledger', [\App\Http\Controllers\ReportController::class, 'ingredientLedger'])->name('reports.ingredient-ledger');
    Route::get('/reports/sales', [\App\Http\Controllers\ReportController::class, 'sales'])->name('reports.sales');
    Route::get('/reports/sales-by-category', [\App\Http\Controllers\ReportController::class, 'salesByCategory'])->name('reports.sales-by-category');
    Route::get('/reports/sales-by-item', [\App\Http\Controllers\ReportController::class, 'salesByItem'])->name('reports.sales-by-item');
    Route::get('/reports/top-selling', [\App\Http\Controllers\ReportController::class, 'topSelling'])->name('reports.top-selling');
    Route::get('/reports/daily', [\App\Http\Controllers\ReportController::class, 'daily'])->name('reports.daily');
    Route::get('/reports/daily/pdf', [\App\Http\Controllers\ReportController::class, 'dailyPdf'])->name('reports.daily.pdf');
    Route::get('/reports/daily/excel', [\App\Http\Controllers\ReportController::class, 'dailyExcel'])->name('reports.daily.excel');
    Route::get('/reports/top-selling/pdf', [\App\Http\Controllers\ReportController::class, 'topSellingPdf'])->name('reports.top-selling.pdf');
    Route::get('/reports/top-selling/excel', [\App\Http\Controllers\ReportController::class, 'topSellingExcel'])->name('reports.top-selling.excel');
    Route::get('/reports/payment-methods/pdf', [\App\Http\Controllers\ReportController::class, 'paymentMethodsPdf'])->name('reports.payment-methods.pdf');
    Route::get('/reports/payment-methods/excel', [\App\Http\Controllers\ReportController::class, 'paymentMethodsExcel'])->name('reports.payment-methods.excel');
    Route::get('/reports/sales/pdf', [\App\Http\Controllers\ReportController::class, 'salesPdf'])->name('reports.sales.pdf');
    Route::get('/reports/sales/excel', [\App\Http\Controllers\ReportController::class, 'salesExcel'])->name('reports.sales.excel');
    Route::get('/reports/sales-by-item/pdf', [\App\Http\Controllers\ReportController::class, 'salesByItemPdf'])->name('reports.sales-by-item.pdf');
    Route::get('/reports/sales-by-item/excel', [\App\Http\Controllers\ReportController::class, 'salesByItemExcel'])->name('reports.sales-by-item.excel');
    Route::get('/reports/foc/pdf', [\App\Http\Controllers\ReportController::class, 'focPdf'])->name('reports.foc.pdf');
    Route::get('/reports/foc/excel', [\App\Http\Controllers\ReportController::class, 'focExcel'])->name('reports.foc.excel');
    Route::get('/reports/gross-margin/pdf', [\App\Http\Controllers\ReportController::class, 'grossMarginPdf'])->name('reports.gross-margin.pdf');
    Route::get('/reports/gross-margin/excel', [\App\Http\Controllers\ReportController::class, 'grossMarginExcel'])->name('reports.gross-margin.excel');
    Route::get('/reports/ingredient-ledger/pdf', [\App\Http\Controllers\ReportController::class, 'ingredientLedgerPdf'])->name('reports.ingredient-ledger.pdf');
    Route::get('/reports/ingredient-ledger/excel', [\App\Http\Controllers\ReportController::class, 'ingredientLedgerExcel'])->name('reports.ingredient-ledger.excel');
    Route::get('/reports/transactions-by-money-source/pdf', [\App\Http\Controllers\ReportController::class, 'transactionsByMoneySourcePdf'])->name('reports.transactions-by-money-source.pdf');
    Route::get('/reports/transactions-by-money-source/excel', [\App\Http\Controllers\ReportController::class, 'transactionsByMoneySourceExcel'])->name('reports.transactions-by-money-source.excel');
    Route::get('/reports/z-report/pdf', [\App\Http\Controllers\ReportController::class, 'zReportListPdf'])->name('reports.z-report.pdf');
    Route::get('/reports/z-report/excel', [\App\Http\Controllers\ReportController::class, 'zReportListExcel'])->name('reports.z-report.excel');
    Route::get('/reports/z-report', [\App\Http\Controllers\ReportController::class, 'zReport'])->name('reports.z-report');
    Route::get('/reports/payment-methods', [\App\Http\Controllers\ReportController::class, 'paymentMethods'])->name('reports.payment-methods');
    Route::get('/reports/transactions-by-money-source', [\App\Http\Controllers\ReportController::class, 'transactionsByMoneySource'])->name('reports.transactions-by-money-source');
    Route::get('/reports/foc', [\App\Http\Controllers\ReportController::class, 'foc'])->name('reports.foc');
    Route::get('/reports/gross-margin', [\App\Http\Controllers\ReportController::class, 'grossMargin'])->name('reports.gross-margin');
    Route::get('/reports/profit-loss', [\App\Http\Controllers\ReportController::class, 'profitLoss'])->name('reports.profit-loss');
    Route::get('/reports/profit-loss/pdf', [\App\Http\Controllers\ReportController::class, 'profitLossPdf'])->name('reports.profit-loss.pdf');
    Route::get('/reports/profit-loss/excel', [\App\Http\Controllers\ReportController::class, 'profitLossExcel'])->name('reports.profit-loss.excel');
    Route::get('/reports/order-history', [\App\Http\Controllers\ReportController::class, 'orderHistory'])->name('reports.order-history');
    Route::get('/reports/order-history/pdf', [\App\Http\Controllers\ReportController::class, 'orderHistoryPdf'])->name('reports.order-history.pdf');
    Route::get('/reports/order-history/excel', [\App\Http\Controllers\ReportController::class, 'orderHistoryExcel'])->name('reports.order-history.excel');
    Route::get('/reports/weekly-closing', [\App\Http\Controllers\ReportController::class, 'weeklyClosing'])->name('reports.weekly-closing');
    Route::get('/reports/weekly-closing/pdf', [\App\Http\Controllers\ReportController::class, 'weeklyClosingPdf'])->name('reports.weekly-closing.pdf');
    Route::get('/reports/weekly-closing/excel', [\App\Http\Controllers\ReportController::class, 'weeklyClosingExcel'])->name('reports.weekly-closing.excel');
    Route::get('/reports/monthly-closing', [\App\Http\Controllers\ReportController::class, 'monthlyClosing'])->name('reports.monthly-closing');
    Route::get('/reports/monthly-closing/pdf', [\App\Http\Controllers\ReportController::class, 'monthlyClosingPdf'])->name('reports.monthly-closing.pdf');
    Route::get('/reports/monthly-closing/excel', [\App\Http\Controllers\ReportController::class, 'monthlyClosingExcel'])->name('reports.monthly-closing.excel');
    Route::get('/reports/accounts-receivable', [\App\Http\Controllers\ReportController::class, 'accountsReceivable'])->name('reports.accounts-receivable');
    Route::get('/reports/accounts-receivable/pdf', [\App\Http\Controllers\ReportController::class, 'accountsReceivablePdf'])->name('reports.accounts-receivable.pdf');
    Route::get('/reports/accounts-receivable/excel', [\App\Http\Controllers\ReportController::class, 'accountsReceivableExcel'])->name('reports.accounts-receivable.excel');
    Route::get('/reports/accounts-payable', [\App\Http\Controllers\ReportController::class, 'accountsPayable'])->name('reports.accounts-payable');
    Route::get('/reports/accounts-payable/pdf', [\App\Http\Controllers\ReportController::class, 'accountsPayablePdf'])->name('reports.accounts-payable.pdf');
    Route::get('/reports/accounts-payable/excel', [\App\Http\Controllers\ReportController::class, 'accountsPayableExcel'])->name('reports.accounts-payable.excel');
    Route::get('/reports/customer-credits', [\App\Http\Controllers\ReportController::class, 'customerCredits'])->name('reports.customer-credits');
    Route::get('/reports/customer-credits/pdf', [\App\Http\Controllers\ReportController::class, 'customerCreditsPdf'])->name('reports.customer-credits.pdf');
    Route::get('/reports/customer-credits/excel', [\App\Http\Controllers\ReportController::class, 'customerCreditsExcel'])->name('reports.customer-credits.excel');
    Route::get('/reports/supplier-prepayments', [\App\Http\Controllers\ReportController::class, 'supplierPrepayments'])->name('reports.supplier-prepayments');
    Route::get('/reports/supplier-prepayments/pdf', [\App\Http\Controllers\ReportController::class, 'supplierPrepaymentsPdf'])->name('reports.supplier-prepayments.pdf');
    Route::get('/reports/supplier-prepayments/excel', [\App\Http\Controllers\ReportController::class, 'supplierPrepaymentsExcel'])->name('reports.supplier-prepayments.excel');

    // Shift Management
    Route::get('/shifts/{shift}/z-report', [\App\Http\Controllers\ShiftController::class, 'zReport'])->name('shifts.z-report');
    Route::get('/shifts/{shift}/z-report/pdf', [\App\Http\Controllers\ShiftController::class, 'zReportPdf'])->name('shifts.z-report.pdf');
    Route::resource('shifts', \App\Http\Controllers\ShiftController::class);
    Route::get('/shifts/active/check', [\App\Http\Controllers\ShiftController::class, 'getActiveShift'])->name('shifts.active.check');

    // Transaction Management (requires active shift)
    Route::middleware('require.active.shift')->group(function () {
        Route::resource('transactions', \App\Http\Controllers\TransactionController::class);
        Route::get('/transactions/{transaction}/adjustment', [\App\Http\Controllers\TransactionController::class, 'createAdjustment'])->name('transactions.adjustment.create');
        Route::post('/transactions/{transaction}/adjustment', [\App\Http\Controllers\TransactionController::class, 'storeAdjustment'])->name('transactions.adjustment.store');
    });

    // Purchase Management (requires active shift)
    Route::middleware('require.active.shift')->group(function () {
        Route::post('purchases/{purchase}/validate-update', [\App\Http\Controllers\PurchaseController::class, 'validateUpdate'])->name('purchases.validate-update');
        Route::post('purchases/{purchase}/validate-delete', [\App\Http\Controllers\PurchaseController::class, 'validateDelete'])->name('purchases.validate-delete');
        Route::resource('purchases', \App\Http\Controllers\PurchaseController::class);
        Route::resource('purchase-returns', \App\Http\Controllers\PurchaseReturnController::class);
    });

    // Supplier Payment Management (requires active shift)
    Route::middleware('require.active.shift')->group(function () {
        Route::resource('supplier-payments', \App\Http\Controllers\SupplierPaymentController::class);
        Route::get('supplier-payments/advance/create', [\App\Http\Controllers\SupplierPaymentController::class, 'createAdvance'])->name('supplier-payments.advance.create');
        Route::post('supplier-payments/advance', [\App\Http\Controllers\SupplierPaymentController::class, 'storeAdvance'])->name('supplier-payments.advance.store');
        Route::get('customer-payments/customer-context', [\App\Http\Controllers\CustomerPaymentController::class, 'customerContext'])->name('customer-payments.customer-context');
        Route::get('customer-payments/advance/create', [\App\Http\Controllers\CustomerPaymentController::class, 'createAdvance'])->name('customer-payments.advance.create');
        Route::post('customer-payments/advance', [\App\Http\Controllers\CustomerPaymentController::class, 'storeAdvance'])->name('customer-payments.advance.store');
        Route::resource('customer-payments', \App\Http\Controllers\CustomerPaymentController::class)->only(['index', 'create', 'store', 'show', 'destroy']);
        Route::resource('employee-payments', \App\Http\Controllers\EmployeePaymentController::class)->only(['index', 'create', 'store', 'show', 'destroy']);
    });

    // Human resources and payroll (separate from POS cash shifts)
    Route::prefix('hr')->name('hr.')->group(function () {
        Route::get(
            'employees/{employeeProfile}/documents/{document}',
            [\App\Http\Controllers\HrEmployeeController::class, 'downloadDocument']
        )->name('employees.documents.download');
        Route::resource('employees', \App\Http\Controllers\HrEmployeeController::class)
            ->parameters(['employees' => 'employeeProfile']);
        Route::post('attendance/action/{employee}', [\App\Http\Controllers\AttendanceController::class, 'action'])
            ->whereNumber('employee')
            ->name('attendance.action');
        Route::resource('attendance', \App\Http\Controllers\AttendanceController::class)
            ->parameters(['attendance' => 'attendanceRecord'])
            ->except(['show']);
        Route::post('leaves/{employeeLeave}/approve', [\App\Http\Controllers\EmployeeLeaveController::class, 'approve'])->name('leaves.approve');
        Route::post('leaves/{employeeLeave}/reject', [\App\Http\Controllers\EmployeeLeaveController::class, 'reject'])->name('leaves.reject');
        Route::resource('leaves', \App\Http\Controllers\EmployeeLeaveController::class)
            ->parameters(['leaves' => 'employeeLeave'])
            ->only(['index', 'create', 'store', 'destroy']);

        Route::get('payroll/adjustments', [\App\Http\Controllers\PayrollAdjustmentController::class, 'index'])->name('adjustments.index');
        Route::get('payroll/adjustments/create', [\App\Http\Controllers\PayrollAdjustmentController::class, 'create'])->name('adjustments.create');
        Route::post('payroll/adjustments', [\App\Http\Controllers\PayrollAdjustmentController::class, 'store'])->name('adjustments.store');
        Route::delete('payroll/adjustments/{payrollAdjustment}', [\App\Http\Controllers\PayrollAdjustmentController::class, 'destroy'])->name('adjustments.destroy');

        Route::get('payroll/payslips/{payrollItem}', [\App\Http\Controllers\PayrollController::class, 'payslip'])->name('payroll.payslip');
        Route::put('payroll/{payrollRun}/items/{payrollItem}', [\App\Http\Controllers\PayrollController::class, 'updateItem'])->name('payroll.items.update');
        Route::post('payroll/{payrollRun}/finalize', [\App\Http\Controllers\PayrollController::class, 'finalize'])->name('payroll.finalize');
        Route::resource('payroll', \App\Http\Controllers\PayrollController::class)
            ->parameters(['payroll' => 'payrollRun'])
            ->except(['edit', 'update']);
    });

    // POS System (requires active shift)
    Route::middleware('require.active.shift')->group(function () {
        Route::get('/pos', [\App\Http\Controllers\PosController::class, 'index'])->name('pos.index');
        Route::get('/pos/open-order', [\App\Http\Controllers\PosController::class, 'openOrder'])->name('pos.open-order');
        Route::get('/pos/open-orders', [\App\Http\Controllers\PosController::class, 'listOpenOrders'])->name('pos.open-orders');
        Route::get('/pos/channel-orders', [\App\Http\Controllers\PosController::class, 'listChannelOrders'])->name('pos.channel-orders');
        Route::get('/pos/kitchen-queue', [\App\Http\Controllers\PosController::class, 'kitchenQueue'])->name('pos.kitchen-queue');
        Route::get('/pos/today-orders', [\App\Http\Controllers\PosController::class, 'todayOrders'])->name('pos.today-orders');
        Route::get('/pos/table-view', [\App\Http\Controllers\PosController::class, 'tableView'])->name('pos.table-view');
        Route::get('/pos/deals', [\App\Http\Controllers\PosController::class, 'deals'])->name('pos.deals');
        Route::post('/pos', [\App\Http\Controllers\PosController::class, 'store'])->name('pos.store');
        Route::patch('/pos/orders/{order}', [\App\Http\Controllers\PosController::class, 'updateOpenOrder'])->name('pos.orders.update');
        Route::get('/pos/orders/{order}/details', [\App\Http\Controllers\PosController::class, 'orderDetails'])->name('pos.orders.details');
        Route::post('/pos/orders/{order}/send-to-kitchen', [\App\Http\Controllers\PosController::class, 'sendToKitchen'])->name('pos.orders.send-to-kitchen');
        Route::get('/pos/print-readiness', [\App\Http\Controllers\PosController::class, 'printReadiness'])->name('pos.print-readiness');
        Route::post('/pos/orders/{order}/print-receipt', [\App\Http\Controllers\PosController::class, 'printReceipt'])->name('pos.orders.print-receipt');
        Route::post('/pos/orders/{order}/reprint-kot', [\App\Http\Controllers\PosController::class, 'reprintKitchenKot'])->name('pos.orders.reprint-kot');
        Route::post('/pos/orders/{order}/cancel', [\App\Http\Controllers\PosController::class, 'cancelOrder'])->name('pos.orders.cancel');
        Route::patch('/pos/orders/{order}/status', [\App\Http\Controllers\PosController::class, 'updateOrderStatus'])->name('pos.orders.status');
        Route::post('/pos/orders/{order}/checkout', [\App\Http\Controllers\PosController::class, 'checkoutOrder'])->name('pos.orders.checkout');
        Route::get('/pos/invoice/{order}', [\App\Http\Controllers\PosController::class, 'invoice'])->name('pos.invoice');
        Route::get('/pos/kitchen-kot/{kitchenKot}', [\App\Http\Controllers\PosController::class, 'kitchenKot'])->name('pos.kitchen-kot');
    });
});
