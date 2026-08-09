<?php

namespace App\Services;

use App\Helpers\TenantDefaultRoles;
use App\Models\Account;
use App\Models\AttendanceRecord;
use App\Models\Branch;
use App\Models\BranchStock;
use App\Models\Category;
use App\Models\Company;
use App\Models\Cuisine;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Deal;
use App\Models\EmployeeLeaveRequest;
use App\Models\EmployeePayrollAdjustment;
use App\Models\EmployeeProfile;
use App\Models\Expense;
use App\Models\Floor;
use App\Models\Ingredient;
use App\Models\IngredientCategory;
use App\Models\IngredientUnit;
use App\Models\MenuItem;
use App\Models\MoneySource;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Recipe;
use App\Models\Shift;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Models\Table;
use App\Models\Tax;
use App\Models\Transaction;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class DemoResetService
{
    private const DEMO_HISTORY_DAYS = 60;

    /** Perishables and small consumables bought almost every day (not bulk restock). */
    private const DAILY_PURCHASE_INGREDIENTS = [
        'Mixed Salad Greens', 'Fresh Basil', 'Bell Peppers', 'Plain Yogurt', 'Pizza Dough',
        'Garlic', 'Red Onion', 'Spinach', 'Button Mushrooms', 'Mint Leaves', 'Potatoes',
        'Pineapple', 'Soft Water 250ml', 'Jalapeños', 'Green Chilies', 'Wild Mushrooms',
        'Sweetcorn', 'Kalamata Olives',
    ];

    /** Target recipe food cost as a share of menu price (≈32% → ~68% gross margin). */
    private const TARGET_FOOD_COST_RATIO = 0.32;

    /** Operating expenses as a share of net sales when auto-scaling demo opex. */
    private const TARGET_OPEX_RATIO = 0.48;

    /**
     * Reset demo company: wipe data and seed "Pizza Shop" dataset (~60 days of sales, shifts, menu, customers, employees).
     */
    public function resetDemoCompany(Company $company): void
    {
        if (! $company->demo) {
            throw new \InvalidArgumentException('Company is not a demo company.');
        }

        $companyId = $company->id;
        $branchIds = Branch::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->pluck('id')
            ->toArray();
        $preservedAdmin = $this->preservedCompanyAdmin($companyId);

        try {
            DB::transaction(function () use ($companyId, $branchIds, $company, $preservedAdmin) {
                $this->wipeCompanyData($companyId, $branchIds);
                $this->seedDemoData($company, $preservedAdmin);
            });
        } catch (\Throwable $e) {
            Log::error('Demo reset failed for company '.$company->id.' ('.$company->name.')', [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    protected function wipeCompanyData(int $companyId, array $branchIds): void
    {
        if ($branchIds === []) {
            $branchIds = Branch::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->pluck('id')
                ->toArray();
        }

        $orderIds = Order::withoutGlobalScopes()->where('company_id', $companyId)->pluck('id')->toArray();

        if (! empty($orderIds)) {
            $refundIds = DB::table('order_refunds')->whereIn('order_id', $orderIds)->pluck('id')->toArray();
            if (! empty($refundIds)) {
                DB::table('order_refund_lines')->whereIn('order_refund_id', $refundIds)->delete();
            }
            DB::table('order_refunds')->whereIn('order_id', $orderIds)->delete();
            DB::table('order_items')->whereIn('order_id', $orderIds)->delete();
            DB::table('kitchen_display_orders')->whereIn('order_id', $orderIds)->delete();
        }
        Order::withoutGlobalScopes()->where('company_id', $companyId)->forceDelete();

        $dealIds = DB::table('deals')->where('company_id', $companyId)->pluck('id')->toArray();
        if (! empty($dealIds)) {
            DB::table('deal_menu_item')->whereIn('deal_id', $dealIds)->delete();
        }
        DB::table('deals')->where('company_id', $companyId)->delete();

        $shiftIds = DB::table('shifts')->where('company_id', $companyId)->pluck('id')->toArray();
        if (! empty($shiftIds)) {
            DB::table('shift_money_sources')->whereIn('shift_id', $shiftIds)->delete();
        }
        Shift::withoutGlobalScopes()->where('company_id', $companyId)->forceDelete();

        DB::table('transactions')->where('company_id', $companyId)->delete();

        $purchaseIds = DB::table('purchases')->where('company_id', $companyId)->pluck('id')->toArray();
        if (! empty($purchaseIds)) {
            DB::table('purchase_items')->whereIn('purchase_id', $purchaseIds)->delete();
        }
        Purchase::withoutGlobalScopes()->where('company_id', $companyId)->forceDelete();

        DB::table('supplier_payments')->where('company_id', $companyId)->delete();
        DB::table('customer_payments')->where('company_id', $companyId)->delete();

        $this->wipeHrData($companyId);

        // Always wipe company expenses (do not depend on branchIds — tenant scope can leave it empty).
        DB::table('expenses')->where('company_id', $companyId)->delete();

        if (! empty($branchIds)) {
            DB::table('stock_movements')->whereIn('branch_id', $branchIds)->delete();
            DB::table('menu_item_stock')->whereIn('branch_id', $branchIds)->delete();
            DB::table('branch_stock')->whereIn('branch_id', $branchIds)->delete();
            DB::table('daily_cash_ups')->whereIn('branch_id', $branchIds)->delete();
            DB::table('branch_money_sources')->whereIn('branch_id', $branchIds)->delete();
            DB::table('tables')->whereIn('branch_id', $branchIds)->delete();
            DB::table('floors')->whereIn('branch_id', $branchIds)->delete();
        }

        $customerIds = DB::table('customers')->where('company_id', $companyId)->pluck('id')->toArray();
        if (! empty($customerIds)) {
            DB::table('customer_addresses')->whereIn('customer_id', $customerIds)->delete();
        }
        Customer::withoutTenantScope()->where('company_id', $companyId)->forceDelete();

        $menuItemIds = DB::table('menu_items')->where('company_id', $companyId)->pluck('id')->toArray();
        if (! empty($menuItemIds)) {
            if (Schema::hasTable('menu_item_variant_recipes')) {
                DB::table('menu_item_variant_recipes')->whereIn('menu_item_id', $menuItemIds)->delete();
            }
            if (Schema::hasTable('menu_item_recipe_lines')) {
                DB::table('menu_item_recipe_lines')->whereIn('menu_item_id', $menuItemIds)->delete();
            }
            DB::table('menu_items')->whereIn('id', $menuItemIds)->update(['default_recipe_id' => null]);
            DB::table('menu_item_variant')->whereIn('menu_item_id', $menuItemIds)->delete();
            DB::table('menu_item_product_addon')->whereIn('menu_item_id', $menuItemIds)->delete();
        }
        MenuItem::withoutGlobalScopes()->where('company_id', $companyId)->forceDelete();

        if (Schema::hasTable('recipes')) {
            $recipeIds = DB::table('recipes')->where('company_id', $companyId)->pluck('id');
            if ($recipeIds->isNotEmpty() && Schema::hasTable('recipe_items')) {
                DB::table('recipe_items')->whereIn('recipe_id', $recipeIds)->delete();
            }
            DB::table('recipes')->where('company_id', $companyId)->delete();
        }
        DB::table('ingredients')->where('company_id', $companyId)->delete();
        DB::table('ingredient_categories')->where('company_id', $companyId)->delete();
        DB::table('ingredient_units')->where('company_id', $companyId)->delete();

        $userIds = User::withoutGlobalScopes()->where('company_id', $companyId)->pluck('id')->toArray();
        if (! empty($userIds)) {
            DB::table('user_branches')->whereIn('user_id', $userIds)->delete();
        }
        User::withoutGlobalScopes()->where('company_id', $companyId)->forceDelete();

        MoneySource::withoutGlobalScopes()->where('company_id', $companyId)->forceDelete();
        Branch::withoutGlobalScopes()->where('company_id', $companyId)->forceDelete();

        DB::table('categories')->where('company_id', $companyId)->delete();
        DB::table('taxes')->where('company_id', $companyId)->delete();
        DB::table('cuisines')->where('company_id', $companyId)->delete();
        DB::table('variants')->where('company_id', $companyId)->delete();
        DB::table('product_addons')->where('company_id', $companyId)->delete();
        DB::table('suppliers')->where('company_id', $companyId)->delete();
        Account::withoutGlobalScopes()->where('company_id', $companyId)->delete();
        DB::table('units_of_measure')->where('company_id', $companyId)->delete();
    }

    protected function wipeHrData(int $companyId): void
    {
        if (! Schema::hasTable('employee_profiles')) {
            return;
        }

        if (Schema::hasTable('payroll_advance_recoveries') && Schema::hasTable('payroll_items')) {
            $itemIds = DB::table('payroll_items')->where('company_id', $companyId)->pluck('id');
            if ($itemIds->isNotEmpty()) {
                DB::table('payroll_advance_recoveries')->whereIn('payroll_item_id', $itemIds)->delete();
            }
        }

        foreach ([
            'employee_ledger_entries',
            'employee_advances',
            'employee_payments',
            'employee_payroll_adjustments',
            'payroll_items',
            'payroll_runs',
            'attendance_records',
            'employee_leave_requests',
            'employee_profiles',
        ] as $table) {
            if (Schema::hasTable($table)) {
                DB::table($table)->where('company_id', $companyId)->delete();
            }
        }
    }

    protected function preservedCompanyAdmin(int $companyId): ?array
    {
        $admin = User::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('type', 'company_admin')
            ->first();

        if (! $admin) {
            return null;
        }

        return [
            'name' => $admin->name,
            'email' => $admin->email,
            'password' => $admin->password,
        ];
    }

    protected function tenantScopedEmail(string $email, int $companyId): string
    {
        [$local, $domain] = explode('@', $email, 2);

        return $local.'_'.$companyId.'@'.$domain;
    }

    protected function tenantScopedReferenceNumber(string $prefix, int $companyId, string $datePart, int $sequence, string $sequenceFormat = '%04d'): string
    {
        return sprintf('%s-%d-%s-'.$sequenceFormat, $prefix, $companyId, $datePart, $sequence);
    }

    protected function seedDemoData(Company $company, ?array $preservedAdmin = null): void
    {
        // Use company's existing timezone and currency so multiple demo companies can have their own
        $tz = $company->timezone ?? 'UTC';
        // Preserve company name and currency; only ensure branch uses company timezone
        $company->refresh();

        // --- Branch & base setup ---
        $branch = Branch::withoutTenantScope()->create([
            'company_id' => $company->id,
            'name' => $company->name.' - Main',
            'code' => 'PSH001',
            'email' => $company->email,
            'phone' => $company->phone,
            'address' => $company->address,
            'timezone' => $tz,
            'status' => 'active',
        ]);

        foreach (
            [
                ['name' => 'Sales', 'type' => 'income'],
                ['name' => 'Refund', 'type' => 'expense'],
                ['name' => 'Purchase', 'type' => 'expense'],
                ['name' => 'Salary', 'type' => 'expense'],
                ['name' => 'Maintenance', 'type' => 'expense'],
                ['name' => 'Equipment', 'type' => 'expense'],
                ['name' => 'FOC', 'type' => 'expense'],
            ] as $a
        ) {
            Account::withoutTenantScope()->create([
                'company_id' => $company->id,
                'name' => $a['name'],
                'type' => $a['type'],
                'is_active' => true,
                'is_deletable' => false,
            ]);
        }

        $cashSource = MoneySource::withoutTenantScope()->create([
            'company_id' => $company->id,
            'name' => 'Cash',
            'type' => 'CASH',
            'opening_balance' => 0,
            'active' => true,
        ]);
        $cashSource->branches()->attach($branch->id);

        $jazzCashSource = MoneySource::withoutTenantScope()->create([
            'company_id' => $company->id,
            'name' => 'JazzCash',
            'type' => 'APP',
            'opening_balance' => 0,
            'active' => true,
        ]);
        $jazzCashSource->branches()->attach($branch->id);

        $hblSource = MoneySource::withoutTenantScope()->create([
            'company_id' => $company->id,
            'name' => 'HBL',
            'type' => 'BANK',
            'opening_balance' => 0,
            'active' => true,
        ]);
        $hblSource->branches()->attach($branch->id);

        $moneySources = [$cashSource, $jazzCashSource, $hblSource];

        // --- Floors & tables (2 floors, 6 tables each) ---
        $floorDefinitions = [
            [
                'name' => 'Ground Floor',
                'sort_order' => 0,
                'tables' => [
                    ['name' => 'Table 1', 'code' => 'G01', 'capacity' => 2],
                    ['name' => 'Table 2', 'code' => 'G02', 'capacity' => 4],
                    ['name' => 'Table 3', 'code' => 'G03', 'capacity' => 4],
                    ['name' => 'Table 4', 'code' => 'G04', 'capacity' => 6],
                    ['name' => 'Table 5', 'code' => 'G05', 'capacity' => 6],
                    ['name' => 'Table 6', 'code' => 'G06', 'capacity' => 8],
                ],
            ],
            [
                'name' => 'Upper Floor',
                'sort_order' => 1,
                'tables' => [
                    ['name' => 'Table 7', 'code' => 'U01', 'capacity' => 2],
                    ['name' => 'Table 8', 'code' => 'U02', 'capacity' => 4],
                    ['name' => 'Table 9', 'code' => 'U03', 'capacity' => 4],
                    ['name' => 'Table 10', 'code' => 'U04', 'capacity' => 6],
                    ['name' => 'Table 11', 'code' => 'U05', 'capacity' => 6],
                    ['name' => 'Table 12', 'code' => 'U06', 'capacity' => 8],
                ],
            ],
        ];
        $demoTableIds = [];
        foreach ($floorDefinitions as $floorDef) {
            $floor = Floor::withoutGlobalScope('branch')->create([
                'branch_id' => $branch->id,
                'name' => $floorDef['name'],
                'sort_order' => $floorDef['sort_order'],
                'is_active' => true,
            ]);
            foreach ($floorDef['tables'] as $tableDef) {
                $table = Table::withoutGlobalScope('branch')->create([
                    'company_id' => $company->id,
                    'branch_id' => $branch->id,
                    'floor_id' => $floor->id,
                    'name' => $tableDef['name'],
                    'slug' => Table::generateUniqueSlug($company->id, $tableDef['name']),
                    'code' => $tableDef['code'],
                    'capacity' => $tableDef['capacity'],
                    'status' => 'available',
                ]);
                $demoTableIds[] = $table->id;
            }
        }

        // --- Units of measure (for inventory) ---
        $unitAbbrevs = ['kg' => ['name' => 'Kilogram', 'type' => 'weight'], 'L' => ['name' => 'Liter', 'type' => 'volume'], 'pcs' => ['name' => 'Piece', 'type' => 'count']];
        $unitIds = [];
        foreach ($unitAbbrevs as $abbrev => $u) {
            $unit = UnitOfMeasure::withoutGlobalScopes()->create([
                'company_id' => $company->id,
                'name' => $u['name'],
                'abbreviation' => $abbrev,
                'type' => $u['type'],
                'is_base_unit' => true,
            ]);
            $unitIds[$abbrev] = $unit->id;
        }

        $ingredientUnitIds = [];
        $unitCodeCounter = 1;
        $defaultIngredientUnitLabels = [
            'g' => 'Gram',
            'kg' => 'Kilogram',
            'ml' => 'Milliliter',
            'L' => 'Liter',
            'pcs' => 'Piece',
            'dozen' => 'Dozen',
        ];
        foreach ($defaultIngredientUnitLabels as $key => $label) {
            $ingredientUnit = IngredientUnit::withoutGlobalScopes()->create([
                'company_id' => $company->id,
                'name' => $label,
                'code' => 'C'.str_pad((string) $unitCodeCounter, 2, '0', STR_PAD_LEFT),
                'description' => null,
            ]);
            $ingredientUnitIds[$key] = $ingredientUnit->id;
            $unitCodeCounter++;
        }

        $salesAccount = Account::withoutTenantScope()->where('company_id', $company->id)->where('name', 'Sales')->first();
        $purchaseAccount = Account::withoutTenantScope()->where('company_id', $company->id)->where('name', 'Purchase')->first();
        $suppliers = [];
        foreach (
            [
                ['name' => 'Demo Supplies Co', 'email' => 'supply@demo.com', 'phone' => '555-9000', 'address' => '100 Warehouse Rd'],
                ['name' => 'Fresh Produce Ltd', 'email' => 'fresh@demo.com', 'phone' => '555-9001', 'address' => '200 Market St'],
                ['name' => 'Dairy & Cheese Co', 'email' => 'dairy@demo.com', 'phone' => '555-9002', 'address' => '300 Farm Rd'],
                ['name' => 'Metro Beverages', 'email' => 'metro@demo.com', 'phone' => '555-9003', 'address' => '400 Industrial Pkwy'],
                ['name' => 'Quality Meats Inc', 'email' => 'meats@demo.com', 'phone' => '555-9004', 'address' => '500 Butcher Lane'],
                ['name' => 'Bakery Basics', 'email' => 'bakery@demo.com', 'phone' => '555-9005', 'address' => '600 Flour Mill Rd'],
                ['name' => 'Spice World', 'email' => 'spice@demo.com', 'phone' => '555-9006', 'address' => '700 Spice Ave'],
            ] as $s
        ) {
            $suppliers[] = Supplier::withoutGlobalScopes()->create([
                'company_id' => $company->id,
                'name' => $s['name'],
                'email' => $s['email'],
                'phone' => $s['phone'],
                'address' => $s['address'],
            ]);
        }

        // --- 2) Employees (5) ---
        $admin = User::withoutGlobalScopes()->createOrFirst(
            [
                'email' => $preservedAdmin['email'] ?? 'admin@pizzashop-demo.com',
                'company_id' => $company->id,
            ],
            [
                'branch_id' => $branch->id,
                'name' => $preservedAdmin['name'] ?? 'Pizza Shop Admin',
                'password' => $preservedAdmin['password'] ?? Hash::make('password'),
                'type' => 'company_admin',
                'status' => 'active',
            ]
        );
        $admin->branches()->sync([$branch->id => ['is_primary' => true]]);

        $cashier1 = User::withoutGlobalScopes()->createOrFirst(
            [
                'email' => $this->tenantScopedEmail('cashier@pizzashop-demo.com', $company->id),
                'company_id' => $company->id,
            ],
            [
                'branch_id' => $branch->id,
                'name' => 'Demo Cashier',
                'password' => Hash::make('password'),
                'type' => 'staff',
                'status' => 'active',
            ]
        );
        $cashier1->branches()->sync([$branch->id => ['is_primary' => false]]);

        $cook = User::withoutGlobalScopes()->createOrFirst(
            [
                'email' => $this->tenantScopedEmail('cook@pizzashop-demo.com', $company->id),
                'company_id' => $company->id,
            ],
            [
                'branch_id' => $branch->id,
                'name' => 'Demo Cook',
                'password' => Hash::make('password'),
                'type' => 'staff',
                'status' => 'active',
            ]
        );
        $cook->branches()->sync([$branch->id => ['is_primary' => false]]);

        $cashier2 = User::withoutGlobalScopes()->createOrFirst(
            [
                'email' => $this->tenantScopedEmail('sarah@pizzashop-demo.com', $company->id),
                'company_id' => $company->id,
            ],
            [
                'branch_id' => $branch->id,
                'name' => 'Sarah Cashier',
                'password' => Hash::make('password'),
                'type' => 'staff',
                'status' => 'active',
            ]
        );
        $cashier2->branches()->sync([$branch->id => ['is_primary' => false]]);

        $waiter = User::withoutGlobalScopes()->createOrFirst(
            [
                'email' => $this->tenantScopedEmail('mike@pizzashop-demo.com', $company->id),
                'company_id' => $company->id,
            ],
            [
                'branch_id' => $branch->id,
                'name' => 'Mike Waiter',
                'password' => Hash::make('password'),
                'type' => 'waiter',
                'status' => 'active',
                'can_login' => true,
            ]
        );
        $waiter->branches()->sync([$branch->id => ['is_primary' => false]]);

        $helper = User::withoutGlobalScopes()->createOrFirst(
            [
                'email' => $this->tenantScopedEmail('helper@pizzashop-demo.com', $company->id),
                'company_id' => $company->id,
            ],
            [
                'branch_id' => $branch->id,
                'name' => 'Noor Kitchen Helper',
                'password' => Hash::make(Str::random(32)),
                'type' => 'staff',
                'status' => 'active',
                'can_login' => false,
            ]
        );
        $helper->branches()->sync([$branch->id => ['is_primary' => true]]);

        $employees = [$admin, $cashier1, $cook, $cashier2, $waiter];
        $hrStaff = [$cashier1, $cook, $cashier2, $waiter, $helper];

        // --- Tax ---
        $tax = Tax::withoutGlobalScopes()->createOrFirst(
            ['company_id' => $company->id, 'name' => 'VAT'],
            ['percentage' => 10, 'is_active' => true]
        );

        // --- 3) Ingredient categories ---
        $ingCatDairy = IngredientCategory::withoutGlobalScopes()->createOrFirst(
            ['company_id' => $company->id, 'name' => 'Dairy & Cheese'],
            ['code' => 'C01', 'sort_order' => 1, 'is_active' => true]
        );
        $ingCatProduce = IngredientCategory::withoutGlobalScopes()->createOrFirst(
            ['company_id' => $company->id, 'name' => 'Produce'],
            ['code' => 'C02', 'sort_order' => 2, 'is_active' => true]
        );
        $ingCatDry = IngredientCategory::withoutGlobalScopes()->createOrFirst(
            ['company_id' => $company->id, 'name' => 'Dry Goods'],
            ['code' => 'C03', 'sort_order' => 3, 'is_active' => true]
        );
        $ingCatMeat = IngredientCategory::withoutGlobalScopes()->createOrFirst(
            ['company_id' => $company->id, 'name' => 'Meat & Poultry'],
            ['code' => 'C04', 'sort_order' => 4, 'is_active' => true]
        );
        $ingCatBeverage = IngredientCategory::withoutGlobalScopes()->createOrFirst(
            ['company_id' => $company->id, 'name' => 'Beverage mixes'],
            ['code' => 'C05', 'sort_order' => 5, 'is_active' => true]
        );
        $ingCatSauces = IngredientCategory::withoutGlobalScopes()->createOrFirst(
            ['company_id' => $company->id, 'name' => 'Sauces & oils'],
            ['code' => 'C06', 'sort_order' => 6, 'is_active' => true]
        );

        // --- 4) Ingredients (prices in Rs.) ---
        $ingredients = [];
        $ingredientData = [
            ['name' => 'Mozzarella', 'category' => $ingCatDairy, 'unit' => 'kg', 'cost' => 2500.00],
            ['name' => 'Tomato Sauce', 'category' => $ingCatDry, 'unit' => 'L', 'cost' => 850.00],
            ['name' => 'Pizza Dough', 'category' => $ingCatDry, 'unit' => 'pcs', 'cost' => 400.00],
            ['name' => 'Pepperoni', 'category' => $ingCatMeat, 'unit' => 'kg', 'cost' => 3200.00],
            ['name' => 'Garlic', 'category' => $ingCatProduce, 'unit' => 'kg', 'cost' => 1400.00],
            ['name' => 'Butter', 'category' => $ingCatDairy, 'unit' => 'kg', 'cost' => 1600.00],
            ['name' => 'Cola Syrup', 'category' => $ingCatDry, 'unit' => 'L', 'cost' => 1100.00],
            ['name' => 'Soft Water 250ml', 'category' => $ingCatDry, 'unit' => 'pcs', 'cost' => 100.00],
            ['name' => 'Cheddar Cheese', 'category' => $ingCatDairy, 'unit' => 'kg', 'cost' => 2200.00],
            ['name' => 'Parmesan Cheese', 'category' => $ingCatDairy, 'unit' => 'kg', 'cost' => 4500.00],
            ['name' => 'Gorgonzola Cheese', 'category' => $ingCatDairy, 'unit' => 'kg', 'cost' => 5200.00],
            ['name' => 'Feta Cheese', 'category' => $ingCatDairy, 'unit' => 'kg', 'cost' => 3200.00],
            ['name' => 'Plain Yogurt', 'category' => $ingCatDairy, 'unit' => 'L', 'cost' => 300.00],
            ['name' => 'Grilled Chicken', 'category' => $ingCatMeat, 'unit' => 'kg', 'cost' => 1800.00],
            ['name' => 'Ground Beef', 'category' => $ingCatMeat, 'unit' => 'kg', 'cost' => 2000.00],
            ['name' => 'Turkey Bacon', 'category' => $ingCatMeat, 'unit' => 'kg', 'cost' => 3500.00],
            ['name' => 'Italian Sausage', 'category' => $ingCatMeat, 'unit' => 'kg', 'cost' => 2800.00],
            ['name' => 'Turkey Ham', 'category' => $ingCatMeat, 'unit' => 'kg', 'cost' => 2400.00],
            ['name' => 'Chicken Wings', 'category' => $ingCatMeat, 'unit' => 'kg', 'cost' => 2200.00],
            ['name' => 'Bell Peppers', 'category' => $ingCatProduce, 'unit' => 'kg', 'cost' => 800.00],
            ['name' => 'Black Olives', 'category' => $ingCatProduce, 'unit' => 'kg', 'cost' => 1200.00],
            ['name' => 'Button Mushrooms', 'category' => $ingCatProduce, 'unit' => 'kg', 'cost' => 900.00],
            ['name' => 'Sweetcorn', 'category' => $ingCatProduce, 'unit' => 'kg', 'cost' => 600.00],
            ['name' => 'Pineapple', 'category' => $ingCatProduce, 'unit' => 'kg', 'cost' => 700.00],
            ['name' => 'Red Onion', 'category' => $ingCatProduce, 'unit' => 'kg', 'cost' => 500.00],
            ['name' => 'Jalapeños', 'category' => $ingCatProduce, 'unit' => 'kg', 'cost' => 1100.00],
            ['name' => 'Spinach', 'category' => $ingCatProduce, 'unit' => 'kg', 'cost' => 700.00],
            ['name' => 'Kalamata Olives', 'category' => $ingCatProduce, 'unit' => 'kg', 'cost' => 1400.00],
            ['name' => 'Sun-Dried Tomatoes', 'category' => $ingCatProduce, 'unit' => 'kg', 'cost' => 2000.00],
            ['name' => 'Mixed Salad Greens', 'category' => $ingCatProduce, 'unit' => 'kg', 'cost' => 600.00],
            ['name' => 'Wild Mushrooms', 'category' => $ingCatProduce, 'unit' => 'kg', 'cost' => 1500.00],
            ['name' => 'Green Chilies', 'category' => $ingCatProduce, 'unit' => 'kg', 'cost' => 1000.00],
            ['name' => 'Fresh Basil', 'category' => $ingCatProduce, 'unit' => 'kg', 'cost' => 2500.00],
            ['name' => 'Potatoes', 'category' => $ingCatProduce, 'unit' => 'kg', 'cost' => 400.00],
            ['name' => 'Mint Leaves', 'category' => $ingCatProduce, 'unit' => 'kg', 'cost' => 1800.00],
            ['name' => 'Dried Oregano', 'category' => $ingCatDry, 'unit' => 'kg', 'cost' => 3200.00],
            ['name' => 'French Fries', 'category' => $ingCatDry, 'unit' => 'kg', 'cost' => 800.00],
            ['name' => 'BBQ Sauce', 'category' => $ingCatSauces, 'unit' => 'L', 'cost' => 600.00],
            ['name' => 'Buffalo Sauce', 'category' => $ingCatSauces, 'unit' => 'L', 'cost' => 800.00],
            ['name' => 'Ranch Dressing', 'category' => $ingCatSauces, 'unit' => 'L', 'cost' => 700.00],
            ['name' => 'Caesar Dressing', 'category' => $ingCatSauces, 'unit' => 'L', 'cost' => 720.00],
            ['name' => 'Basil Pesto', 'category' => $ingCatSauces, 'unit' => 'L', 'cost' => 1200.00],
            ['name' => 'Truffle Oil', 'category' => $ingCatSauces, 'unit' => 'L', 'cost' => 8000.00],
            ['name' => 'Tandoori Paste', 'category' => $ingCatSauces, 'unit' => 'L', 'cost' => 900.00],
            ['name' => 'Lemonade Mix', 'category' => $ingCatBeverage, 'unit' => 'L', 'cost' => 400.00],
            ['name' => 'Mojito Mix', 'category' => $ingCatBeverage, 'unit' => 'L', 'cost' => 500.00],
            ['name' => 'Peach Syrup', 'category' => $ingCatBeverage, 'unit' => 'L', 'cost' => 550.00],
            ['name' => 'Iced Tea Base', 'category' => $ingCatBeverage, 'unit' => 'L', 'cost' => 350.00],
            ['name' => 'Passion Fruit Syrup', 'category' => $ingCatBeverage, 'unit' => 'L', 'cost' => 600.00],
            ['name' => 'Blue Lagoon Syrup', 'category' => $ingCatBeverage, 'unit' => 'L', 'cost' => 480.00],
            ['name' => 'Berry Smoothie Base', 'category' => $ingCatBeverage, 'unit' => 'L', 'cost' => 650.00],
            ['name' => 'Mango Puree', 'category' => $ingCatBeverage, 'unit' => 'L', 'cost' => 700.00],
            ['name' => 'Diet Cola Syrup', 'category' => $ingCatBeverage, 'unit' => 'L', 'cost' => 1050.00],
        ];
        foreach ($ingredientData as $i) {
            $unitKey = $i['unit'];
            $consumptionUnitId = $ingredientUnitIds[$unitKey] ?? $ingredientUnitIds['kg'] ?? null;
            $minStock = match ($unitKey) {
                'pcs' => 50.0,
                'L' => 10.0,
                default => 15.0,
            };
            $maxStock = match ($unitKey) {
                'pcs' => 500.0,
                'L' => 100.0,
                default => 100.0,
            };

            $ingredients[$i['name']] = Ingredient::withoutGlobalScopes()->createOrFirst(
                ['company_id' => $company->id, 'name' => $i['name']],
                [
                    'category_id' => $i['category']->id,
                    'created_by' => $admin->id,
                    'base_unit_id' => (string) $consumptionUnitId,
                    'consumption_unit_id' => $consumptionUnitId,
                    'purchase_unit_id' => $consumptionUnitId,
                    'conversion_rate' => 1,
                    'purchase_price' => $i['cost'],
                    'cost_per_unit' => $i['cost'],
                    'min_stock_level' => $minStock,
                    'max_stock_level' => $maxStock,
                    'track_stock' => 'yes',
                    'is_active' => true,
                ]
            );
        }

        // --- 5) Menu categories & cuisine ---
        $catPizzas = Category::withoutGlobalScopes()->createOrFirst(
            ['company_id' => $company->id, 'name' => 'Pizzas'],
            ['slug' => 'pizzas', 'is_active' => true, 'sort_order' => 1]
        );
        $catSides = Category::withoutGlobalScopes()->createOrFirst(
            ['company_id' => $company->id, 'name' => 'Sides'],
            ['slug' => 'sides', 'is_active' => true, 'sort_order' => 2]
        );
        $catDrinks = Category::withoutGlobalScopes()->createOrFirst(
            ['company_id' => $company->id, 'name' => 'Drinks'],
            ['slug' => 'drinks', 'is_active' => true, 'sort_order' => 3]
        );
        $catSandwiches = Category::withoutGlobalScopes()->createOrFirst(
            ['company_id' => $company->id, 'name' => 'Sandwiches'],
            ['slug' => 'sandwiches', 'is_active' => true, 'sort_order' => 4]
        );
        $catShawarma = Category::withoutGlobalScopes()->createOrFirst(
            ['company_id' => $company->id, 'name' => 'Shawarma'],
            ['slug' => 'shawarma', 'is_active' => true, 'sort_order' => 5]
        );
        $catCoffee = Category::withoutGlobalScopes()->createOrFirst(
            ['company_id' => $company->id, 'name' => 'Coffee'],
            ['slug' => 'coffee', 'is_active' => true, 'sort_order' => 6]
        );
        $catTea = Category::withoutGlobalScopes()->createOrFirst(
            ['company_id' => $company->id, 'name' => 'Tea'],
            ['slug' => 'tea', 'is_active' => true, 'sort_order' => 7]
        );
        $catStarters = Category::withoutGlobalScopes()->createOrFirst(
            ['company_id' => $company->id, 'name' => 'Starters'],
            ['slug' => 'starters', 'is_active' => true, 'sort_order' => 8]
        );

        $cuisine = Cuisine::withoutGlobalScopes()->createOrFirst(
            ['company_id' => $company->id, 'name' => 'Italian'],
            ['slug' => 'italian', 'is_active' => true, 'sort_order' => 1]
        );
        $cuisineMiddleEastern = Cuisine::withoutGlobalScopes()->createOrFirst(
            ['company_id' => $company->id, 'name' => 'Middle Eastern'],
            ['slug' => 'middle-eastern', 'is_active' => true, 'sort_order' => 2]
        );

        // --- 6) Menu items (prices in Rs.); recipes attached per row ---
        $attachRecipes = function (MenuItem $item, array $recipes) use ($ingredients): void {
            $lines = [];
            foreach ($recipes as $rec) {
                $lines[] = [
                    'ingredient_id' => $ingredients[$rec[0]]->id,
                    'quantity' => $rec[1],
                    'unit_id' => $rec[2],
                    'waste_percentage' => 0,
                ];
            }

            \App\Support\MenuItemCatalogRecipeBuilder::setDefaultFromLines(
                $item,
                $item->name.' — Default',
                $lines
            );
        };

        $pizzaMenuRows = [
            [
                'slug' => 'margherita',
                'name' => 'Margherita',
                'description' => 'Fresh basil, mozzarella, and tomato sauce.',
                'price' => 350.00,
                'recipes' => [
                    ['Mozzarella', 0.15, 'kg'],
                    ['Tomato Sauce', 0.05, 'L'],
                    ['Pizza Dough', 1, 'pcs'],
                    ['Fresh Basil', 0.002, 'kg'],
                ],
            ],
            [
                'slug' => 'pepperoni-feast',
                'name' => 'Pepperoni Feast',
                'description' => 'Double pepperoni and extra mozzarella.',
                'price' => 450.00,
                'recipes' => [
                    ['Pepperoni', 0.12, 'kg'],
                    ['Mozzarella', 0.18, 'kg'],
                    ['Tomato Sauce', 0.05, 'L'],
                    ['Pizza Dough', 1, 'pcs'],
                ],
            ],
            [
                'slug' => 'bbq-chicken-pizza',
                'name' => 'BBQ Chicken',
                'description' => 'Grilled chicken, red onions, and smoky BBQ sauce.',
                'price' => 480.00,
                'recipes' => [
                    ['Pizza Dough', 1, 'pcs'],
                    ['BBQ Sauce', 0.06, 'L'],
                    ['Mozzarella', 0.14, 'kg'],
                    ['Grilled Chicken', 0.15, 'kg'],
                    ['Red Onion', 0.03, 'kg'],
                ],
            ],
            [
                'slug' => 'veggie-supreme-pizza',
                'name' => 'Veggie Supreme',
                'description' => 'Bell peppers, olives, mushrooms, and sweetcorn.',
                'price' => 420.00,
                'recipes' => [
                    ['Pizza Dough', 1, 'pcs'],
                    ['Tomato Sauce', 0.05, 'L'],
                    ['Mozzarella', 0.14, 'kg'],
                    ['Bell Peppers', 0.06, 'kg'],
                    ['Black Olives', 0.025, 'kg'],
                    ['Button Mushrooms', 0.07, 'kg'],
                    ['Sweetcorn', 0.05, 'kg'],
                ],
            ],
            [
                'slug' => 'meat-lovers-pizza',
                'name' => 'Meat Lovers',
                'description' => 'Beef, pepperoni, turkey bacon, and sausage.',
                'price' => 520.00,
                'recipes' => [
                    ['Pizza Dough', 1, 'pcs'],
                    ['Tomato Sauce', 0.05, 'L'],
                    ['Mozzarella', 0.14, 'kg'],
                    ['Ground Beef', 0.08, 'kg'],
                    ['Pepperoni', 0.06, 'kg'],
                    ['Turkey Bacon', 0.05, 'kg'],
                    ['Italian Sausage', 0.08, 'kg'],
                ],
            ],
            [
                'slug' => 'quattro-formaggi-pizza',
                'name' => 'Quattro Formaggi',
                'description' => 'A blend of mozzarella, cheddar, parmesan, and gorgonzola.',
                'price' => 460.00,
                'recipes' => [
                    ['Pizza Dough', 1, 'pcs'],
                    ['Tomato Sauce', 0.03, 'L'],
                    ['Mozzarella', 0.10, 'kg'],
                    ['Cheddar Cheese', 0.05, 'kg'],
                    ['Parmesan Cheese', 0.03, 'kg'],
                    ['Gorgonzola Cheese', 0.04, 'kg'],
                ],
            ],
            [
                'slug' => 'hawaiian-pizza',
                'name' => 'Hawaiian',
                'description' => 'Turkey ham and juicy pineapple chunks.',
                'price' => 440.00,
                'recipes' => [
                    ['Pizza Dough', 1, 'pcs'],
                    ['Tomato Sauce', 0.05, 'L'],
                    ['Mozzarella', 0.14, 'kg'],
                    ['Turkey Ham', 0.10, 'kg'],
                    ['Pineapple', 0.08, 'kg'],
                ],
            ],
            [
                'slug' => 'spicy-buffalo-pizza',
                'name' => 'Spicy Buffalo',
                'description' => 'Buffalo sauce base, spicy chicken, and ranch drizzle.',
                'price' => 470.00,
                'recipes' => [
                    ['Pizza Dough', 1, 'pcs'],
                    ['Buffalo Sauce', 0.07, 'L'],
                    ['Mozzarella', 0.12, 'kg'],
                    ['Grilled Chicken', 0.14, 'kg'],
                    ['Ranch Dressing', 0.02, 'L'],
                ],
            ],
            [
                'slug' => 'truffle-mushroom-pizza',
                'name' => 'Truffle Mushroom',
                'description' => 'Wild mushrooms with white truffle oil and garlic.',
                'price' => 550.00,
                'recipes' => [
                    ['Pizza Dough', 1, 'pcs'],
                    ['Tomato Sauce', 0.04, 'L'],
                    ['Mozzarella', 0.14, 'kg'],
                    ['Wild Mushrooms', 0.14, 'kg'],
                    ['Truffle Oil', 0.004, 'L'],
                    ['Garlic', 0.008, 'kg'],
                ],
            ],
            [
                'slug' => 'chicken-tikka-pizza',
                'name' => 'Chicken Tikka',
                'description' => 'Tandoori-style chicken, green chilies, and onions.',
                'price' => 480.00,
                'recipes' => [
                    ['Pizza Dough', 1, 'pcs'],
                    ['Tomato Sauce', 0.05, 'L'],
                    ['Mozzarella', 0.13, 'kg'],
                    ['Grilled Chicken', 0.13, 'kg'],
                    ['Tandoori Paste', 0.04, 'L'],
                    ['Green Chilies', 0.015, 'kg'],
                    ['Red Onion', 0.04, 'kg'],
                ],
            ],
            [
                'slug' => 'garden-pesto-pizza',
                'name' => 'Garden Pesto',
                'description' => 'Basil pesto base with roasted vegetables and feta.',
                'price' => 430.00,
                'recipes' => [
                    ['Pizza Dough', 1, 'pcs'],
                    ['Basil Pesto', 0.07, 'L'],
                    ['Mozzarella', 0.08, 'kg'],
                    ['Bell Peppers', 0.07, 'kg'],
                    ['Button Mushrooms', 0.06, 'kg'],
                    ['Feta Cheese', 0.05, 'kg'],
                ],
            ],
            [
                'slug' => 'the-greek-pizza',
                'name' => 'The Greek',
                'description' => 'Spinach, kalamata olives, sun-dried tomatoes, and oregano.',
                'price' => 410.00,
                'recipes' => [
                    ['Pizza Dough', 1, 'pcs'],
                    ['Tomato Sauce', 0.05, 'L'],
                    ['Mozzarella', 0.12, 'kg'],
                    ['Spinach', 0.06, 'kg'],
                    ['Kalamata Olives', 0.04, 'kg'],
                    ['Sun-Dried Tomatoes', 0.03, 'kg'],
                    ['Dried Oregano', 0.003, 'kg'],
                ],
            ],
        ];

        $pizzaItems = [];
        foreach ($pizzaMenuRows as $row) {
            $attrs = [
                'category_id' => $catPizzas->id,
                'cuisine_id' => $cuisine->id,
                'name' => $row['name'],
                'description' => $row['description'],
                'price' => $row['price'],
                'is_available' => true,
                'track_inventory' => true,
            ];
            if (($demoImg = $this->resolveDemoPublicMenuImage('pizzas', $row['slug'], $row['name'])) !== null) {
                $attrs['image'] = $demoImg;
            }
            $item = MenuItem::withoutGlobalScopes()->createOrFirst(
                ['company_id' => $company->id, 'slug' => $row['slug']],
                $attrs
            );
            $pizzaItems[] = $item;
            $attachRecipes($item, $row['recipes']);
        }
        $margherita = $pizzaItems[0];

        $garlicBreadAttrs = [
            'category_id' => $catSides->id,
            'cuisine_id' => $cuisine->id,
            'name' => 'Garlic Bread',
            'price' => 150.00,
            'is_available' => true,
            'track_inventory' => true,
        ];
        if (($gbImg = $this->resolveDemoPublicMenuImage('sides', 'garlic-bread', 'Garlic Bread')) !== null) {
            $garlicBreadAttrs['image'] = $gbImg;
        }
        $garlicBread = MenuItem::withoutGlobalScopes()->createOrFirst(
            ['company_id' => $company->id, 'slug' => 'garlic-bread'],
            $garlicBreadAttrs
        );
        $attachRecipes($garlicBread, [
            ['Garlic', 0.02, 'kg'],
            ['Butter', 0.03, 'kg'],
        ]);

        $extraSideMenuRows = [
            [
                'slug' => 'garlic-breadsticks-marinara',
                'name' => 'Garlic Breadsticks with Marinara',
                'description' => 'Warm breadsticks with marinara dip.',
                'price' => 220.00,
                'recipes' => [
                    ['Pizza Dough', 0.75, 'pcs'],
                    ['Garlic', 0.02, 'kg'],
                    ['Butter', 0.04, 'kg'],
                    ['Tomato Sauce', 0.08, 'L'],
                ],
            ],
            [
                'slug' => 'seasoned-potato-wedges',
                'name' => 'Seasoned Potato Wedges',
                'description' => 'Crispy wedges with seasoned butter.',
                'price' => 190.00,
                'recipes' => [
                    ['Potatoes', 0.28, 'kg'],
                    ['Butter', 0.02, 'kg'],
                ],
            ],
            [
                'slug' => 'cheesy-mozzarella-sticks',
                'name' => 'Cheesy Mozzarella Sticks',
                'description' => 'Fried mozzarella with melty center.',
                'price' => 240.00,
                'recipes' => [
                    ['Mozzarella', 0.10, 'kg'],
                    ['Butter', 0.02, 'kg'],
                ],
            ],
            [
                'slug' => 'loaded-fries-cheese-jalapenos',
                'name' => 'Loaded Fries (Cheese & Jalapeños)',
                'description' => 'Fries topped with melted cheese and jalapeños.',
                'price' => 280.00,
                'recipes' => [
                    ['French Fries', 0.22, 'kg'],
                    ['Cheddar Cheese', 0.05, 'kg'],
                    ['Jalapeños', 0.025, 'kg'],
                ],
            ],
            [
                'slug' => 'caesar-side-salad',
                'name' => 'Caesar Side Salad',
                'description' => 'Romaine-style greens with Caesar dressing.',
                'price' => 200.00,
                'recipes' => [
                    ['Mixed Salad Greens', 0.10, 'kg'],
                    ['Caesar Dressing', 0.03, 'L'],
                ],
            ],
            [
                'slug' => 'buffalo-chicken-wings',
                'name' => 'Buffalo Chicken Wings',
                'description' => 'Wings tossed in buffalo sauce.',
                'price' => 320.00,
                'recipes' => [
                    ['Chicken Wings', 0.25, 'kg'],
                    ['Buffalo Sauce', 0.05, 'L'],
                ],
            ],
        ];
        $extraSideItems = [];
        foreach ($extraSideMenuRows as $row) {
            $sideAttrs = [
                'category_id' => $catSides->id,
                'cuisine_id' => $cuisine->id,
                'name' => $row['name'],
                'description' => $row['description'],
                'price' => $row['price'],
                'is_available' => true,
                'track_inventory' => true,
            ];
            if (($sideImg = $this->resolveDemoPublicMenuImage('sides', $row['slug'], $row['name'])) !== null) {
                $sideAttrs['image'] = $sideImg;
            }
            $item = MenuItem::withoutGlobalScopes()->createOrFirst(
                ['company_id' => $company->id, 'slug' => $row['slug']],
                $sideAttrs
            );
            $extraSideItems[] = $item;
            $attachRecipes($item, $row['recipes']);
        }

        $drinksImageFolder = $catDrinks->slug;
        $colaAttrs = [
            'category_id' => $catDrinks->id,
            'name' => 'Cola',
            'price' => 70.00,
            'is_available' => true,
            'track_inventory' => true,
        ];
        if (($colaImg = $this->resolveDemoPublicMenuImage($drinksImageFolder, 'cola', 'Cola')) !== null) {
            $colaAttrs['image'] = $colaImg;
        }
        $cola = MenuItem::withoutGlobalScopes()->createOrFirst(
            ['company_id' => $company->id, 'slug' => 'cola'],
            $colaAttrs
        );
        $softWaterAttrs = [
            'category_id' => $catDrinks->id,
            'name' => 'Soft Water 250ml',
            'price' => 50.00,
            'is_available' => true,
            'track_inventory' => true,
        ];
        if (($swImg = $this->resolveDemoPublicMenuImage($drinksImageFolder, 'soft-water-250ml', 'Soft Water 250ml')) !== null) {
            $softWaterAttrs['image'] = $swImg;
        }
        $softWater = MenuItem::withoutGlobalScopes()->createOrFirst(
            ['company_id' => $company->id, 'slug' => 'soft-water-250ml'],
            $softWaterAttrs
        );
        $attachRecipes($cola, [['Cola Syrup', 0.05, 'L']]);
        $attachRecipes($softWater, [['Soft Water 250ml', 1, 'pcs']]);

        $extraDrinkMenuRows = [
            ['slug' => 'classic-lemonade', 'name' => 'Classic Lemonade', 'price' => 120.00, 'recipes' => [['Lemonade Mix', 0.07, 'L']]],
            ['slug' => 'fresh-mint-mojito', 'name' => 'Fresh Mint Mojito', 'price' => 150.00, 'recipes' => [['Mojito Mix', 0.06, 'L'], ['Mint Leaves', 0.008, 'kg']]],
            ['slug' => 'sparkling-peach-iced-tea', 'name' => 'Sparkling Peach Iced Tea', 'price' => 140.00, 'recipes' => [['Iced Tea Base', 0.08, 'L'], ['Peach Syrup', 0.04, 'L']]],
            ['slug' => 'passion-fruit-fizz', 'name' => 'Passion Fruit Fizz', 'price' => 145.00, 'recipes' => [['Passion Fruit Syrup', 0.07, 'L']]],
            ['slug' => 'blue-lagoon-mocktail', 'name' => 'Blue Lagoon Mocktail', 'price' => 155.00, 'recipes' => [['Blue Lagoon Syrup', 0.06, 'L']]],
            ['slug' => 'wild-berry-smoothie', 'name' => 'Wild Berry Smoothie', 'price' => 170.00, 'recipes' => [['Berry Smoothie Base', 0.08, 'L'], ['Plain Yogurt', 0.10, 'L']]],
            ['slug' => 'mango-lassi', 'name' => 'Mango Lassi', 'price' => 160.00, 'recipes' => [['Mango Puree', 0.11, 'L'], ['Plain Yogurt', 0.14, 'L']]],
            ['slug' => 'diet-cola', 'name' => 'Diet Cola', 'price' => 70.00, 'recipes' => [['Diet Cola Syrup', 0.05, 'L']]],
        ];
        $extraDrinkItems = [];
        foreach ($extraDrinkMenuRows as $row) {
            $drinkRowAttrs = [
                'category_id' => $catDrinks->id,
                'name' => $row['name'],
                'price' => $row['price'],
                'is_available' => true,
                'track_inventory' => true,
            ];
            if (($dImg = $this->resolveDemoPublicMenuImage($drinksImageFolder, $row['slug'], $row['name'])) !== null) {
                $drinkRowAttrs['image'] = $dImg;
            }
            $item = MenuItem::withoutGlobalScopes()->createOrFirst(
                ['company_id' => $company->id, 'slug' => $row['slug']],
                $drinkRowAttrs
            );
            $extraDrinkItems[] = $item;
            $attachRecipes($item, $row['recipes']);
        }

        $extraDemoMenuDefs = [
            ['cat' => $catSandwiches, 'cuisine' => $cuisine, 'name' => 'Chicken Pesto Panini', 'slug' => 'chicken-pesto-panini', 'price' => 420.00],
            ['cat' => $catSandwiches, 'cuisine' => $cuisine, 'name' => 'Classic Reuben', 'slug' => 'classic-reuben', 'price' => 430.00],
            ['cat' => $catSandwiches, 'cuisine' => $cuisine, 'name' => 'Vietnamese Banh Mi', 'slug' => 'vietnamese-banh-mi', 'price' => 395.00],
            ['cat' => $catSandwiches, 'cuisine' => $cuisine, 'name' => 'Mediterranean Veggie Wrap', 'slug' => 'mediterranean-veggie-wrap', 'price' => 380.00],
            ['cat' => $catSandwiches, 'cuisine' => $cuisine, 'name' => 'Caprese Focaccia', 'slug' => 'caprese-focaccia', 'price' => 365.00],
            ['cat' => $catShawarma, 'cuisine' => $cuisineMiddleEastern, 'name' => 'Traditional Lamb Shawarma', 'slug' => 'traditional-lamb-shawarma', 'price' => 480.00],
            ['cat' => $catShawarma, 'cuisine' => $cuisineMiddleEastern, 'name' => 'Garlic Chicken Shawarma', 'slug' => 'garlic-chicken-shawarma', 'price' => 420.00],
            ['cat' => $catShawarma, 'cuisine' => $cuisineMiddleEastern, 'name' => 'Beef & Hummus Plate', 'slug' => 'beef-hummus-plate', 'price' => 450.00],
            ['cat' => $catShawarma, 'cuisine' => $cuisineMiddleEastern, 'name' => 'Falafel Shawarma (Vegetarian)', 'slug' => 'falafel-shawarma-vegetarian', 'price' => 390.00],
            ['cat' => $catShawarma, 'cuisine' => $cuisineMiddleEastern, 'name' => 'Mexican-Arabe Fusion', 'slug' => 'mexican-arabe-fusion', 'price' => 440.00],
            ['cat' => $catCoffee, 'cuisine' => null, 'name' => 'Spanish Latte', 'slug' => 'spanish-latte', 'price' => 280.00],
            ['cat' => $catCoffee, 'cuisine' => null, 'name' => 'Flat White', 'slug' => 'flat-white', 'price' => 260.00],
            ['cat' => $catCoffee, 'cuisine' => null, 'name' => 'Iced Caramel Macchiato', 'slug' => 'iced-caramel-macchiato', 'price' => 320.00],
            ['cat' => $catCoffee, 'cuisine' => null, 'name' => 'Affogato', 'slug' => 'affogato', 'price' => 340.00],
            ['cat' => $catCoffee, 'cuisine' => null, 'name' => 'Nitro Cold Brew', 'slug' => 'nitro-cold-brew', 'price' => 300.00],
            ['cat' => $catTea, 'cuisine' => null, 'name' => 'Masala Chai', 'slug' => 'masala-chai', 'price' => 220.00],
            ['cat' => $catTea, 'cuisine' => null, 'name' => 'Matcha Green Tea Latte', 'slug' => 'matcha-green-tea-latte', 'price' => 290.00],
            ['cat' => $catTea, 'cuisine' => null, 'name' => 'London Fog', 'slug' => 'london-fog', 'price' => 250.00],
            ['cat' => $catTea, 'cuisine' => null, 'name' => 'Hibiscus Iced Tea', 'slug' => 'hibiscus-iced-tea', 'price' => 200.00],
            ['cat' => $catTea, 'cuisine' => null, 'name' => 'Moroccan Mint Tea', 'slug' => 'moroccan-mint-tea', 'price' => 210.00],
            ['cat' => $catStarters, 'cuisine' => $cuisine, 'name' => 'Crispy Calamari', 'slug' => 'crispy-calamari', 'price' => 380.00],
            ['cat' => $catStarters, 'cuisine' => $cuisine, 'name' => 'Stuffed Mushrooms', 'slug' => 'stuffed-mushrooms', 'price' => 320.00],
            ['cat' => $catStarters, 'cuisine' => $cuisine, 'name' => 'Bruschetta Pomodoro', 'slug' => 'bruschetta-pomodoro', 'price' => 280.00],
            ['cat' => $catStarters, 'cuisine' => $cuisine, 'name' => 'Dynamite Shrimp', 'slug' => 'dynamite-shrimp', 'price' => 410.00],
            ['cat' => $catStarters, 'cuisine' => $cuisine, 'name' => 'Halloumi Fries', 'slug' => 'halloumi-fries', 'price' => 340.00],
        ];
        $extraMenuItems = [];
        foreach ($extraDemoMenuDefs as $def) {
            $attrs = [
                'category_id' => $def['cat']->id,
                'name' => $def['name'],
                'price' => $def['price'],
                'is_available' => true,
                'track_inventory' => false,
            ];
            if ($def['cuisine'] !== null) {
                $attrs['cuisine_id'] = $def['cuisine']->id;
            }
            $catSlug = $def['cat']->slug;
            if ($catSlug && ($catImg = $this->resolveDemoPublicMenuImage($catSlug, $def['slug'], $def['name'])) !== null) {
                $attrs['image'] = $catImg;
            }
            $extraMenuItems[] = MenuItem::withoutGlobalScopes()->createOrFirst(
                ['company_id' => $company->id, 'slug' => $def['slug']],
                $attrs
            );
        }

        $menuItems = array_merge(
            $pizzaItems,
            [$garlicBread],
            $extraSideItems,
            [$cola, $softWater],
            $extraDrinkItems,
            $extraMenuItems
        );

        $this->calibrateDemoPricing($menuItems, $ingredients);

        // --- Deals (combo offers for POS) ---
        $dealDefs = [
            [
                'title' => 'Margherita & Lemonade',
                'description' => 'Margherita pizza with a classic lemonade.',
                'price' => 420.00,
                'items' => [['slug' => 'margherita', 'qty' => 1], ['slug' => 'classic-lemonade', 'qty' => 1]],
            ],
            [
                'title' => 'Feast Trio',
                'description' => 'Pepperoni Feast, garlic breadsticks, and Diet Cola.',
                'price' => 649.00,
                'items' => [['slug' => 'pepperoni-feast', 'qty' => 1], ['slug' => 'garlic-breadsticks-marinara', 'qty' => 1], ['slug' => 'diet-cola', 'qty' => 1]],
            ],
            [
                'title' => 'Shawarma Lunch',
                'description' => 'Traditional lamb shawarma, potato wedges, and masala chai.',
                'price' => 779.00,
                'items' => [['slug' => 'traditional-lamb-shawarma', 'qty' => 1], ['slug' => 'seasoned-potato-wedges', 'qty' => 1], ['slug' => 'masala-chai', 'qty' => 1]],
            ],
            [
                'title' => 'Veggie Light Lunch',
                'description' => 'Mediterranean wrap, hibiscus iced tea, and bruschetta.',
                'price' => 699.00,
                'items' => [['slug' => 'mediterranean-veggie-wrap', 'qty' => 1], ['slug' => 'hibiscus-iced-tea', 'qty' => 1], ['slug' => 'bruschetta-pomodoro', 'qty' => 1]],
            ],
            [
                'title' => 'Coffee Duo',
                'description' => 'Spanish latte and affogato.',
                'price' => 549.00,
                'items' => [['slug' => 'spanish-latte', 'qty' => 1], ['slug' => 'affogato', 'qty' => 1]],
            ],
            [
                'title' => 'Wings & Fizz',
                'description' => 'Buffalo wings, loaded fries, and passion fruit fizz.',
                'price' => 649.00,
                'items' => [['slug' => 'buffalo-chicken-wings', 'qty' => 1], ['slug' => 'loaded-fries-cheese-jalapenos', 'qty' => 1], ['slug' => 'passion-fruit-fizz', 'qty' => 1]],
            ],
            [
                'title' => 'Premium Slice Night',
                'description' => 'Truffle mushroom pizza and wild berry smoothie.',
                'price' => 629.00,
                'items' => [['slug' => 'truffle-mushroom-pizza', 'qty' => 1], ['slug' => 'wild-berry-smoothie', 'qty' => 1]],
            ],
        ];
        $dealSlugs = [];
        foreach ($dealDefs as $def) {
            foreach ($def['items'] as $it) {
                $dealSlugs[$it['slug']] = true;
            }
        }
        $menuItemBySlug = MenuItem::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->whereIn('slug', array_keys($dealSlugs))
            ->get()
            ->keyBy('slug');
        foreach ($dealDefs as $def) {
            $deal = Deal::withoutGlobalScopes()->create([
                'company_id' => $company->id,
                'title' => $def['title'],
                'description' => $def['description'],
                'price' => $def['price'],
                'start_date' => null,
                'end_date' => null,
                'start_time' => null,
                'end_time' => null,
                'is_active' => true,
            ]);
            foreach ($def['items'] as $it) {
                $m = $menuItemBySlug->get($it['slug']);
                if (! $m) {
                    continue;
                }
                $deal->menuItems()->attach($m->id, [
                    'quantity' => $it['qty'],
                    'variant_id' => null,
                    'option_name' => null,
                    'unit_price' => (float) $m->price,
                ]);
            }
        }

        // --- Customers (10–15 clients) ---
        $customersData = [
            ['name' => 'John Doe', 'phone' => '555-0101', 'address' => '123 Main St'],
            ['name' => 'Jane Smith', 'phone' => '555-0102', 'address' => '456 Oak Ave'],
            ['name' => 'Bob Wilson', 'phone' => '555-0103', 'address' => '789 Pine Rd'],
            ['name' => 'Alice Brown', 'phone' => '555-0104', 'address' => '10 Elm St'],
            ['name' => 'Charlie Davis', 'phone' => '555-0105', 'address' => '20 Cedar Lane'],
            ['name' => 'Diana Evans', 'phone' => '555-0106', 'address' => '30 Maple Dr'],
            ['name' => 'Frank Miller', 'phone' => '555-0107', 'address' => '40 Birch Blvd'],
            ['name' => 'Grace Lee', 'phone' => '555-0108', 'address' => '50 Walnut Way'],
            ['name' => 'Henry Clark', 'phone' => '555-0109', 'address' => '60 Ash Ave'],
            ['name' => 'Ivy Martinez', 'phone' => '555-0110', 'address' => '70 Cherry Ct'],
            ['name' => 'Jack Taylor', 'phone' => '555-0111', 'address' => '80 Peach Pl'],
            ['name' => 'Kate Anderson', 'phone' => '555-0112', 'address' => '90 Plum Rd'],
        ];
        $customerModels = [];
        foreach ($customersData as $c) {
            $cust = Customer::withoutTenantScope()->createOrFirst(
                ['company_id' => $company->id, 'phone' => $c['phone']],
                ['name' => $c['name'], 'is_active' => true]
            );
            $customerModels[] = $cust;
            CustomerAddress::createOrFirst(
                ['customer_id' => $cust->id, 'label' => 'Home'],
                ['address_line_1' => $c['address'], 'city' => 'Demo City', 'is_default' => true]
            );
        }
        Customer::withoutTenantScope()->createOrFirst(
            ['company_id' => $company->id, 'name' => 'Walk In'],
            ['is_default' => true, 'is_active' => true]
        );

        // --- Shifts & orders (last ~60 days, in company timezone) ---
        $totalTaxPct = (float) $tax->percentage;
        $historyDays = self::DEMO_HISTORY_DAYS;
        $startDate = Carbon::today($tz)->subDays($historyDays - 1);
        $seq = 1;

        for ($d = 0; $d < $historyDays; $d++) {
            $date = $startDate->copy()->addDays($d);
            $openedAt = $date->copy()->setTime(9, 0);
            $closedAt = $date->copy()->setTime(21, 0);

            $shift = Shift::withoutGlobalScopes()->createOrFirst(
                [
                    'company_id' => $company->id,
                    'branch_id' => $branch->id,
                    'shift_date' => $date,
                ],
                [
                    'opened_by' => $admin->id,
                    'closed_by' => $admin->id,
                    'opened_at' => $openedAt,
                    'closed_at' => $closedAt,
                    'status' => 'closed',
                ]
            );
            $shift->moneySources()->attach($cashSource->id, [
                'opening_balance' => 0,
                'closing_balance' => 0,
                'expected_balance' => 0,
                'difference' => 0,
            ]);
            foreach ([$jazzCashSource, $hblSource] as $ms) {
                $shift->moneySources()->attach($ms->id, [
                    'opening_balance' => 0,
                    'closing_balance' => 0,
                    'expected_balance' => 0,
                    'difference' => 0,
                ]);
            }

            $shiftTotalsBySource = array_fill_keys(array_map(fn ($s) => $s->id, $moneySources), 0);
            $isWeekend = $date->isWeekend();
            $ordersPerDay = $isWeekend ? rand(28, 38) : rand(22, 32);
            for ($o = 0; $o < $ordersPerDay; $o++) {
                $orderTime = $openedAt->copy()->addMinutes(rand(60, 700));
                $numItems = rand(2, 5);
                $subtotal = 0;
                $itemsData = [];
                for ($i = 0; $i < $numItems; $i++) {
                    $mi = $menuItems[array_rand($menuItems)];
                    $qty = rand(1, 3);
                    $price = (float) $mi->price;
                    $total = $price * $qty;
                    $subtotal += $total;
                    $itemsData[] = ['item' => $mi, 'qty' => $qty, 'unit_price' => $price, 'total' => $total];
                }
                $moneySource = $moneySources[array_rand($moneySources)];
                $cashierUser = $employees[array_rand($employees)];

                $orderPaymentMethod = match ($moneySource->type) {
                    'APP' => 'digital_wallet',
                    'BANK' => 'card',
                    default => 'cash',
                };

                $orderType = ['dine_in', 'takeaway', 'delivery'][$o % 3];
                $customer = $customersData[array_rand($customersData)];
                $deliveryFee = 0.0;
                $tableId = null;
                $customerName = null;
                $customerPhone = null;
                $customerEmail = null;
                $customerAddress = null;

                if ($orderType === 'dine_in') {
                    $tableId = $demoTableIds[array_rand($demoTableIds)];
                    $customerName = ($o % 2 === 0) ? $customer['name'] : 'Walk In';
                    $customerPhone = ($o % 2 === 0) ? $customer['phone'] : null;
                } elseif ($orderType === 'takeaway') {
                    $customerName = $customer['name'];
                    $customerPhone = $customer['phone'];
                } else {
                    $customerName = $customer['name'];
                    $customerPhone = $customer['phone'];
                    $customerAddress = $customer['address'];
                    $customerEmail = strtolower(str_replace([' ', "'"], ['.', ''], $customer['name'])).'@demo.local';
                    $deliveryFee = 4.99;
                }

                $taxAmount = round($subtotal * ($totalTaxPct / 100), 2);
                $totalAmount = round($subtotal + $taxAmount + $deliveryFee, 2);
                $shiftTotalsBySource[$moneySource->id] += $totalAmount;

                $order = Order::withoutGlobalScopes()->create([
                    'company_id' => $company->id,
                    'branch_id' => $branch->id,
                    'table_id' => $tableId,
                    'cashier_id' => $cashierUser->id,
                    'order_number' => $this->tenantScopedReferenceNumber('PSH', $company->id, $date->format('Ymd'), $seq++),
                    'type' => $orderType,
                    'status' => 'completed',
                    'payment_status' => 'paid',
                    'payment_method' => $orderPaymentMethod,
                    'money_source_id' => $moneySource->id,
                    'customer_name' => $customerName,
                    'customer_phone' => $customerPhone,
                    'customer_email' => $customerEmail,
                    'customer_address' => $customerAddress,
                    'delivery_fee' => $deliveryFee,
                    'subtotal' => $subtotal,
                    'tax_amount' => $taxAmount,
                    'total_amount' => $totalAmount,
                    'completed_at' => $orderTime,
                ]);
                // Set order date so reports show sales per day and shifts show orders (created_at not in fillable)
                DB::table('orders')->where('id', $order->id)->update([
                    'created_at' => $orderTime,
                    'updated_at' => $orderTime,
                ]);

                foreach ($itemsData as $row) {
                    OrderItem::create([
                        'order_id' => $order->id,
                        'menu_item_id' => $row['item']->id,
                        'item_name' => $row['item']->name,
                        'quantity' => $row['qty'],
                        'unit_price' => $row['unit_price'],
                        'total_price' => $row['total'],
                        'status' => 'served',
                    ]);
                }

                // Transaction for this sale (so shift/transactions reflect reality)
                $transactionPaymentMethod = match ($moneySource->type) {
                    'APP' => 'online',
                    'BANK' => 'transfer',
                    default => 'cash',
                };
                if ($salesAccount) {
                    Transaction::withoutGlobalScopes()->create([
                        'company_id' => $company->id,
                        'branch_id' => $branch->id,
                        'account_id' => $salesAccount->id,
                        'amount' => $totalAmount,
                        'type' => 'in',
                        'payment_method' => $transactionPaymentMethod,
                        'money_source_id' => $moneySource->id,
                        'reference_type' => 'sale',
                        'ref_id' => $order->id,
                        'date' => $orderTime->format('Y-m-d'),
                        'created_by' => $cashierUser->id,
                        'notes' => 'Sale #'.$order->order_number,
                    ]);
                }
            }

            // Sync shift: closing balance per money source
            foreach ($moneySources as $ms) {
                $total = $shiftTotalsBySource[$ms->id] ?? 0;
                $shift->moneySources()->updateExistingPivot($ms->id, [
                    'closing_balance' => $total,
                    'expected_balance' => $total,
                    'difference' => 0,
                ]);
            }
        }

        $this->seedDemoCreditOrders(
            $company,
            $branch,
            $admin,
            $employees,
            $customerModels,
            $menuItems,
            $moneySources,
            $salesAccount,
            $demoTableIds,
            $totalTaxPct,
            $startDate,
            $historyDays,
            $seq
        );

        $this->seedDemoRefunds($company->id, $admin->id);

        $this->seedInitialBranchStock($branch->id, $ingredients, $unitIds, $startDate);

        $purchasesCreated = $this->seedDemoPurchases(
            $company,
            $branch,
            $admin,
            $suppliers,
            $ingredients,
            $unitIds,
            $moneySources,
            $purchaseAccount,
            $startDate,
            $historyDays
        );

        $this->applyDemoLowStockLevels($branch->id, $ingredients, $unitIds);

        $this->seedDemoSupplierPayments($company, $branch, $admin, $purchasesCreated, $purchaseAccount, $moneySources);

        $this->seedDemoCustomerPayments($company, $branch, $admin, $moneySources, $startDate, $historyDays);

        $totalNetSales = (float) Order::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('branch_id', $branch->id)
            ->where('status', '!=', 'cancelled')
            ->sum('subtotal');

        $this->seedDemoOperatingExpenses(
            $company,
            $branch,
            $admin,
            $employees,
            $moneySources,
            $startDate,
            $historyDays,
            $totalNetSales
        );

        $this->seedDemoHrData(
            $company,
            $branch,
            $admin,
            $hrStaff,
            $cashSource,
            $tz
        );

        $this->assignDemoRoles($company, $admin, $cashier1, $cashier2, $waiter);
    }

    /**
     * Scale ingredient costs so recipe COGS averages ~32% of menu price; sync menu item cost field.
     *
     * @param  array<int, MenuItem>  $menuItems
     * @param  array<string, Ingredient>  $ingredients
     */
    protected function calibrateDemoPricing(array $menuItems, array $ingredients): void
    {
        $loadRecipes = static function (MenuItem $item): void {
            $item->load([
                'defaultRecipe.items.ingredient' => fn ($query) => $query->withoutGlobalScopes(),
                'variantRecipes.recipe.items.ingredient' => fn ($query) => $query->withoutGlobalScopes(),
                'legacyRecipeLines.ingredient' => fn ($query) => $query->withoutGlobalScopes(),
            ]);
        };

        $ratios = [];

        foreach ($menuItems as $item) {
            $loadRecipes($item);
            if ($item->recipes->isEmpty() || (float) $item->price <= 0) {
                continue;
            }

            $cost = (float) $item->recipes->sum(fn (Recipe $recipe) => $recipe->lineCost());
            if ($cost > 0) {
                $ratios[] = $cost / (float) $item->price;
            }
        }

        if ($ratios === []) {
            return;
        }

        sort($ratios);
        $medianRatio = $ratios[(int) floor(count($ratios) / 2)];

        if ($medianRatio > self::TARGET_FOOD_COST_RATIO) {
            $scale = self::TARGET_FOOD_COST_RATIO / $medianRatio;

            foreach ($ingredients as $ing) {
                Ingredient::withoutGlobalScopes()
                    ->whereKey($ing->id)
                    ->update([
                        'cost_per_unit' => round((float) $ing->cost_per_unit * $scale, 4),
                        'purchase_price' => round((float) $ing->purchase_price * $scale, 2),
                    ]);
            }
        }

        foreach ($menuItems as $item) {
            $loadRecipes($item);
            if ($item->recipes->isNotEmpty()) {
                $cost = (float) $item->recipes->sum(fn (Recipe $recipe) => $recipe->lineCost());
                MenuItem::withoutGlobalScopes()->whereKey($item->id)->update(['cost' => $cost]);
            }
        }

        foreach ($ingredients as $name => $ing) {
            $ingredients[$name] = Ingredient::withoutGlobalScopes()->find($ing->id);
        }
    }

    /**
     * Opening inventory without a purchase document (avoids one-day purchase spikes).
     *
     * @param  array<string, Ingredient>  $ingredients
     * @param  array<string, int>  $unitIds
     */
    protected function seedInitialBranchStock(int $branchId, array $ingredients, array $unitIds, Carbon $startDate): void
    {
        foreach ($ingredients as $ing) {
            if ($ing->track_stock !== 'yes') {
                continue;
            }

            $uid = $unitIds[$ing->base_unit_id] ?? null;
            if (! $uid) {
                continue;
            }

            $qty = match ($ing->base_unit_id) {
                'pcs' => (float) rand(40, 80),
                'L' => (float) rand(15, 30),
                default => (float) rand(20, 40),
            };

            BranchStock::withoutBranchScope()->create([
                'branch_id' => $branchId,
                'ingredient_id' => $ing->id,
                'quantity' => $qty,
                'reserved_quantity' => 0,
                'unit_id' => $uid,
                'average_cost' => (float) $ing->cost_per_unit,
                'last_restocked_at' => $startDate->copy()->subDays(2),
            ]);
        }
    }

    /**
     * Daily small purchases (produce, dairy) plus weekly bulk restock — every business day has at least one PO.
     *
     * @param  array<int, Supplier>  $suppliers
     * @param  array<string, Ingredient>  $ingredients
     * @param  array<string, int>  $unitIds
     * @param  array<int, MoneySource>  $moneySources
     * @return list<array{purchase: Purchase, total: float}>
     */
    protected function seedDemoPurchases(
        Company $company,
        Branch $branch,
        User $admin,
        array $suppliers,
        array $ingredients,
        array $unitIds,
        array $moneySources,
        ?Account $purchaseAccount,
        Carbon $startDate,
        int $historyDays
    ): array {
        $dailyPool = [];
        $weeklyPool = [];
        foreach ($ingredients as $ing) {
            if (in_array($ing->name, self::DAILY_PURCHASE_INGREDIENTS, true)) {
                $dailyPool[] = $ing;
            } else {
                $weeklyPool[] = $ing;
            }
        }
        if ($dailyPool === []) {
            $dailyPool = array_values($ingredients);
        }
        if ($weeklyPool === []) {
            $weeklyPool = array_values($ingredients);
        }

        $purchasesCreated = [];
        $poSeq = 1;

        for ($day = 1; $day < $historyDays; $day++) {
            $purchaseDate = $startDate->copy()->addDays($day);
            $supplier = $suppliers[array_rand($suppliers)];

            $numDaily = rand(1, 3);
            $pickedDaily = array_rand($dailyPool, min($numDaily, count($dailyPool)));
            if (! is_array($pickedDaily)) {
                $pickedDaily = [$pickedDaily];
            }

            $dailyItems = [];
            foreach ($pickedDaily as $index) {
                $ing = $dailyPool[$index];
                $dailyItems[] = [
                    'ingredient' => $ing,
                    'qty' => $this->demoDailyPurchaseQty($ing),
                    'unit_price' => (float) $ing->cost_per_unit,
                ];
            }

            $dailyPurchase = $this->persistDemoPurchase(
                $company,
                $branch,
                $admin,
                $supplier,
                $purchaseDate,
                $dailyItems,
                $unitIds,
                'Daily market run (demo)',
                $poSeq
            );
            if ($dailyPurchase) {
                $purchasesCreated[] = ['purchase' => $dailyPurchase, 'total' => (float) $dailyPurchase->total_amount];
            }

            if ($day % 7 === 0) {
                $numWeekly = rand(2, 4);
                $pickedWeekly = array_rand($weeklyPool, min($numWeekly, count($weeklyPool)));
                if (! is_array($pickedWeekly)) {
                    $pickedWeekly = [$pickedWeekly];
                }

                $weeklyItems = [];
                foreach ($pickedWeekly as $index) {
                    $ing = $weeklyPool[$index];
                    $weeklyItems[] = [
                        'ingredient' => $ing,
                        'qty' => $this->demoWeeklyPurchaseQty($ing),
                        'unit_price' => (float) $ing->cost_per_unit,
                    ];
                }

                $weeklySupplier = $suppliers[array_rand($suppliers)];
                $weeklyPurchase = $this->persistDemoPurchase(
                    $company,
                    $branch,
                    $admin,
                    $weeklySupplier,
                    $purchaseDate,
                    $weeklyItems,
                    $unitIds,
                    'Weekly restock (demo)',
                    $poSeq
                );
                if ($weeklyPurchase) {
                    $purchasesCreated[] = ['purchase' => $weeklyPurchase, 'total' => (float) $weeklyPurchase->total_amount];
                }
            }
        }

        return $purchasesCreated;
    }

    protected function demoDailyPurchaseQty(Ingredient $ing): float
    {
        return match ($ing->base_unit_id) {
            'pcs' => (float) rand(10, 30),
            'L' => (float) rand(1, 4),
            default => round(rand(5, 25) / 10, 1),
        };
    }

    protected function demoWeeklyPurchaseQty(Ingredient $ing): float
    {
        return match ($ing->base_unit_id) {
            'pcs' => (float) rand(15, 40),
            'L' => (float) rand(4, 12),
            default => (float) rand(5, 15),
        };
    }

    /**
     * @param  list<array{ingredient: Ingredient, qty: float, unit_price: float}>  $items
     * @param  array<string, int>  $unitIds
     */
    protected function persistDemoPurchase(
        Company $company,
        Branch $branch,
        User $admin,
        Supplier $supplier,
        Carbon $purchaseDate,
        array $items,
        array $unitIds,
        string $notes,
        int &$poSeq
    ): ?Purchase {
        if ($items === []) {
            return null;
        }

        $subtotal = round(array_sum(array_map(
            fn (array $row) => $row['qty'] * $row['unit_price'],
            $items
        )), 2);

        if ($subtotal <= 0) {
            return null;
        }

        $purchase = Purchase::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'supplier_id' => $supplier->id,
            'created_by' => $admin->id,
            'purchase_number' => $this->tenantScopedReferenceNumber('PSH-PO', $company->id, $purchaseDate->format('Ymd'), $poSeq++, '%02d'),
            'purchase_date' => $purchaseDate,
            'subtotal' => $subtotal,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'total_amount' => $subtotal,
            'paid_amount' => 0,
            'payment_status' => 'pending',
            'payment_method' => 'cash',
            'notes' => $notes,
        ]);

        $sup = Supplier::withoutGlobalScopes()->find($purchase->supplier_id);
        if ($sup) {
            $sup->balance = ($sup->balance ?? 0) + $subtotal;
            $sup->save();
        }

        foreach ($items as $row) {
            $ing = $row['ingredient'];
            $qty = (float) $row['qty'];
            $unitPrice = (float) $row['unit_price'];
            $totalPrice = round($qty * $unitPrice, 2);

            PurchaseItem::create([
                'purchase_id' => $purchase->id,
                'item_type' => 'ingredient',
                'item_id' => $ing->id,
                'quantity' => $qty,
                'unit_id' => $ing->base_unit_id,
                'unit_price' => $unitPrice,
                'total_price' => $totalPrice,
            ]);

            StockMovement::withoutBranchScope()->create([
                'branch_id' => $branch->id,
                'ingredient_id' => $ing->id,
                'type' => 'purchase',
                'movement' => 'in',
                'quantity' => $qty,
                'unit_id' => $ing->base_unit_id,
                'unit_cost' => $unitPrice,
                'reference_type' => Purchase::class,
                'reference_id' => $purchase->id,
                'created_by' => $admin->id,
                'notes' => 'Purchase '.$purchase->purchase_number,
            ]);

            $uid = $unitIds[$ing->base_unit_id] ?? null;
            if ($uid) {
                $existing = BranchStock::withoutBranchScope()
                    ->where('branch_id', $branch->id)
                    ->where('ingredient_id', $ing->id)
                    ->first();

                if ($existing) {
                    $existing->increment('quantity', $qty);
                    $existing->update([
                        'average_cost' => $unitPrice,
                        'last_restocked_at' => $purchaseDate,
                    ]);
                } else {
                    BranchStock::withoutBranchScope()->create([
                        'branch_id' => $branch->id,
                        'ingredient_id' => $ing->id,
                        'quantity' => $qty,
                        'reserved_quantity' => 0,
                        'unit_id' => $uid,
                        'average_cost' => $unitPrice,
                        'last_restocked_at' => $purchaseDate,
                    ]);
                }
            }
        }

        return $purchase;
    }

    /**
     * @param  list<array{purchase: Purchase, total: float}>  $purchasesCreated
     * @param  array<int, MoneySource>  $moneySources
     */
    protected function seedDemoSupplierPayments(
        Company $company,
        Branch $branch,
        User $admin,
        array $purchasesCreated,
        ?Account $purchaseAccount,
        array $moneySources
    ): void {
        if (! $purchaseAccount || $purchasesCreated === []) {
            return;
        }

        $spSeq = 1;

        foreach ($purchasesCreated as $row) {
            $purchase = $row['purchase'];
            if ($purchase->payment_status !== 'pending') {
                continue;
            }

            $isDailyRun = str_contains((string) $purchase->notes, 'Daily market');
            if ($isDailyRun) {
                if (rand(1, 15) === 1) {
                    continue;
                }
                $payAmount = round((float) $purchase->total_amount, 2);
                $paymentDate = $purchase->purchase_date->copy()->addDays(rand(0, 3));
            } else {
                if (rand(1, 4) === 1) {
                    continue;
                }
                $payAmount = round((float) $purchase->total_amount * rand(70, 100) / 100, 2);
                $paymentDate = $purchase->purchase_date->copy()->addDays(rand(5, 14));
            }

            if ($payAmount <= 0) {
                continue;
            }

            $sp = SupplierPayment::withoutGlobalScopes()->create([
                'company_id' => $company->id,
                'branch_id' => $branch->id,
                'supplier_id' => $purchase->supplier_id,
                'account_id' => $purchaseAccount->id,
                'created_by' => $admin->id,
                'payment_number' => $this->tenantScopedReferenceNumber('PSH-SP', $company->id, $paymentDate->format('Ymd'), $spSeq++, '%02d'),
                'payment_date' => $paymentDate,
                'total_amount' => $payAmount,
                'payment_method' => 'cash',
                'notes' => 'Payment for '.$purchase->purchase_number,
            ]);
            $sp->purchases()->attach($purchase->id, ['amount' => $payAmount]);

            $newPaid = round((float) ($purchase->paid_amount ?? 0) + $payAmount, 2);
            $purchase->update([
                'paid_amount' => $newPaid,
                'payment_status' => $newPaid >= (float) $purchase->total_amount - 0.01 ? 'paid' : 'partial',
            ]);

            $sup = Supplier::withoutGlobalScopes()->find($purchase->supplier_id);
            if ($sup) {
                $sup->balance = max(0, ($sup->balance ?? 0) - $payAmount);
                $sup->save();
            }

            $purchaseSource = $moneySources[array_rand($moneySources)];
            $paymentMethod = match ($purchaseSource->type) {
                'APP' => 'online',
                'BANK' => 'transfer',
                default => 'cash',
            };
            Transaction::withoutGlobalScopes()->create([
                'company_id' => $company->id,
                'branch_id' => $branch->id,
                'account_id' => $purchaseAccount->id,
                'amount' => $payAmount,
                'type' => 'out',
                'payment_method' => $paymentMethod,
                'money_source_id' => $purchaseSource->id,
                'reference_type' => 'purchase',
                'ref_id' => $sp->id,
                'date' => $paymentDate->format('Y-m-d'),
                'created_by' => $admin->id,
                'notes' => 'Supplier payment '.$sp->payment_number,
            ]);
        }
    }

    /**
     * Collect partial payments from customers with outstanding credit balances.
     *
     * @param  array<int, MoneySource>  $moneySources
     */
    protected function seedDemoCustomerPayments(
        Company $company,
        Branch $branch,
        User $admin,
        array $moneySources,
        Carbon $startDate,
        int $historyDays
    ): void {
        if ($moneySources === []) {
            return;
        }

        $previousUser = Auth::user();
        Auth::login($admin);

        try {
            $paymentService = app(CustomerPaymentService::class);
            $cpSeq = 1;

            $paymentDays = [26, 34, 38, 42, 46, 50, 54, 58];

            foreach ($paymentDays as $day) {
                if ($day >= $historyDays) {
                    continue;
                }

                $customers = Customer::withoutTenantScope()
                    ->where('company_id', $company->id)
                    ->where('balance', '>', 0)
                    ->orderByDesc('balance')
                    ->limit(6)
                    ->get();

                if ($customers->isEmpty()) {
                    continue;
                }

                $moneySource = $moneySources[array_rand($moneySources)];
                $paymentDate = $startDate->copy()->addDays($day)->format('Y-m-d');

                foreach ($customers as $customer) {
                    $customer->refresh();
                    $balance = round((float) $customer->balance, 2);
                    if ($balance < 10) {
                        continue;
                    }

                    $receiveAmount = round($balance * rand(45, 85) / 100, 2);
                    if ($receiveAmount < 10) {
                        continue;
                    }

                    $paymentNumber = $this->tenantScopedReferenceNumber(
                        'PSH-CP',
                        $company->id,
                        Carbon::parse($paymentDate)->format('Ymd'),
                        $cpSeq++,
                        '%04d'
                    );

                    try {
                        $paymentService->receivePayment(
                            $customer,
                            $receiveAmount,
                            $moneySource->id,
                            $admin,
                            $branch->id,
                            $paymentDate,
                            'Demo customer balance settlement',
                            0,
                            $paymentNumber
                        );
                    } catch (\Throwable) {
                        continue;
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Demo customer payments skipped for company '.$company->id.': '.$e->getMessage());
        } finally {
            if ($previousUser) {
                Auth::login($previousUser);
            } else {
                Auth::logout();
            }
        }
    }

    /**
     * Operating expenses scaled to net sales, spread across most business days (not monthly lumps).
     *
     * @param  array<int, User>  $employees
     * @param  array<int, MoneySource>  $moneySources
     */
    protected function seedDemoOperatingExpenses(
        Company $company,
        Branch $branch,
        User $admin,
        array $employees,
        array $moneySources,
        Carbon $startDate,
        int $historyDays,
        float $totalNetSales
    ): void {
        $salaryAccount = Account::withoutTenantScope()->where('company_id', $company->id)->where('name', 'Salary')->first();
        $maintenanceAccount = Account::withoutTenantScope()->where('company_id', $company->id)->where('name', 'Maintenance')->first();
        $equipmentAccount = Account::withoutTenantScope()->where('company_id', $company->id)->where('name', 'Equipment')->first();

        if (! $salaryAccount || ! $maintenanceAccount || ! $equipmentAccount || $moneySources === []) {
            return;
        }

        $totalOpexBudget = round($totalNetSales * self::TARGET_OPEX_RATIO, 2);
        if ($totalOpexBudget <= 0) {
            return;
        }

        $expenseDays = [];
        for ($day = 1; $day < $historyDays; $day++) {
            // Skip roughly 1 in 7 days so the chart still looks natural, not perfectly flat.
            if (rand(1, 7) === 1) {
                continue;
            }
            $expenseDays[] = $day;
        }

        if ($expenseDays === []) {
            return;
        }

        $dailyDescriptions = [
            'maintenance' => [
                'Daily cleaning supplies',
                'Gas & utilities top-up',
                'Kitchen consumables',
                'Facility upkeep',
                'Waste disposal',
                'Pest control allowance',
            ],
            'salary' => [
                'Staff shift wages',
                'Delivery rider allowance',
                'Kitchen helper wages',
                'Cashier daily payout',
                'Overtime allowance',
            ],
            'equipment' => [
                'Small tools replacement',
                'Disposable gloves & wraps',
                'Printer rolls & labels',
                'Minor equipment repair',
            ],
        ];

        $weights = [];
        foreach ($expenseDays as $day) {
            $weights[$day] = rand(70, 130);
        }
        $weightSum = array_sum($weights);

        $allocated = 0.0;
        $lastDay = $expenseDays[array_key_last($expenseDays)];

        foreach ($expenseDays as $day) {
            if ($day === $lastDay) {
                $amount = round($totalOpexBudget - $allocated, 2);
            } else {
                $amount = round($totalOpexBudget * ($weights[$day] / $weightSum), 2);
                $allocated += $amount;
            }

            if ($amount <= 0) {
                continue;
            }

            $roll = rand(1, 100);
            if ($roll <= 52) {
                $category = 'Maintenance';
                $account = $maintenanceAccount;
                $pool = 'maintenance';
            } elseif ($roll <= 88) {
                $category = 'Salary';
                $account = $salaryAccount;
                $pool = 'salary';
            } else {
                $category = 'Equipment';
                $account = $equipmentAccount;
                $pool = 'equipment';
            }

            $description = $dailyDescriptions[$pool][array_rand($dailyDescriptions[$pool])];

            $this->createDemoExpense(
                $company,
                $branch,
                $admin,
                $moneySources,
                $account,
                $category,
                $description,
                $amount,
                $startDate->copy()->addDays($day)
            );
        }
    }

    /**
     * @param  array<int, MoneySource>  $moneySources
     */
    protected function createDemoExpense(
        Company $company,
        Branch $branch,
        User $admin,
        array $moneySources,
        Account $account,
        string $category,
        string $description,
        float $amount,
        Carbon $expenseDate
    ): void {
        if ($amount <= 0) {
            return;
        }

        Expense::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'created_by' => $admin->id,
            'category' => $category,
            'description' => $description,
            'amount' => $amount,
            'expense_date' => $expenseDate->format('Y-m-d'),
            'notes' => 'Demo expense',
        ]);
    }

    /**
     * @param  array<int, Customer>  $customerModels
     * @param  array<int, MenuItem>  $menuItems
     * @param  array<int, MoneySource>  $moneySources
     */
    protected function seedDemoCreditOrders(
        Company $company,
        Branch $branch,
        User $admin,
        array $employees,
        array $customerModels,
        array $menuItems,
        array $moneySources,
        ?Account $salesAccount,
        array $demoTableIds,
        float $totalTaxPct,
        Carbon $startDate,
        int $historyDays,
        int &$seq
    ): void {
        if ($customerModels === [] || $menuItems === []) {
            return;
        }

        $creditService = app(PosCreditService::class);

        $creditDefinitions = [
            ['day' => 28, 'full_credit' => false],
            ['day' => 32, 'full_credit' => true],
            ['day' => 35, 'full_credit' => false],
            ['day' => 38, 'full_credit' => true],
            ['day' => 41, 'full_credit' => false],
            ['day' => 44, 'full_credit' => true],
            ['day' => 47, 'full_credit' => false],
            ['day' => 50, 'full_credit' => true],
            ['day' => 53, 'full_credit' => false],
            ['day' => 56, 'full_credit' => true],
            ['day' => 58, 'full_credit' => false],
        ];

        foreach ($creditDefinitions as $def) {
            $dayOffset = $def['day'];
            if ($dayOffset >= $historyDays) {
                continue;
            }

            $date = $startDate->copy()->addDays($dayOffset);
            $orderTime = $date->copy()->setTime(rand(11, 20), rand(0, 59));
            $customer = $customerModels[array_rand($customerModels)];
            $mi = $menuItems[array_rand($menuItems)];
            $qty = rand(8, 16);
            $subtotal = round((float) $mi->price * $qty, 2);
            $taxAmount = round($subtotal * ($totalTaxPct / 100), 2);
            $totalAmount = round($subtotal + $taxAmount, 2);

            if ($def['full_credit']) {
                $paidAmount = 0.0;
                $paymentMethod = 'credit';
            } else {
                $paidAmount = round($totalAmount * rand(15, 40) / 100, 2);
                $paymentMethod = 'cash';
            }

            $outstanding = round($totalAmount - $paidAmount, 2);
            if ($outstanding <= 0) {
                continue;
            }

            $moneySource = $moneySources[0];
            $cashierUser = $employees[array_rand($employees)];

            $order = Order::withoutGlobalScopes()->create([
                'company_id' => $company->id,
                'branch_id' => $branch->id,
                'customer_id' => $customer->id,
                'cashier_id' => $cashierUser->id,
                'order_number' => $this->tenantScopedReferenceNumber('PSH', $company->id, $date->format('Ymd'), $seq++),
                'type' => ['dine_in', 'takeaway', 'delivery'][array_rand(['dine_in', 'takeaway', 'delivery'])],
                'status' => 'completed',
                'payment_status' => 'partial',
                'payment_method' => $paymentMethod,
                'money_source_id' => $moneySource->id,
                'customer_name' => $customer->name,
                'customer_phone' => $customer->phone,
                'table_id' => $demoTableIds[array_rand($demoTableIds)] ?? null,
                'subtotal' => $subtotal,
                'tax_amount' => $taxAmount,
                'total_amount' => $totalAmount,
                'paid_amount' => $paidAmount,
                'completed_at' => $orderTime,
            ]);

            DB::table('orders')->where('id', $order->id)->update([
                'created_at' => $orderTime,
                'updated_at' => $orderTime,
            ]);

            OrderItem::create([
                'order_id' => $order->id,
                'menu_item_id' => $mi->id,
                'item_name' => $mi->name,
                'quantity' => $qty,
                'unit_price' => (float) $mi->price,
                'total_price' => $subtotal,
                'status' => 'served',
            ]);

            if ($salesAccount && $paidAmount > 0) {
                Transaction::withoutGlobalScopes()->create([
                    'company_id' => $company->id,
                    'branch_id' => $branch->id,
                    'account_id' => $salesAccount->id,
                    'amount' => $paidAmount,
                    'type' => 'in',
                    'payment_method' => 'cash',
                    'money_source_id' => $moneySource->id,
                    'reference_type' => 'sale',
                    'ref_id' => $order->id,
                    'date' => $orderTime->format('Y-m-d'),
                    'created_by' => $cashierUser->id,
                    'notes' => 'Partial payment — Sale #'.$order->order_number,
                ]);
            }

            $customer->refresh();
            $creditService->applyCreditToCustomer($customer, $outstanding);
        }

        $accountCustomers = array_slice($customerModels, 0, 4);
        for ($weekStart = 30; $weekStart < $historyDays; $weekStart += 7) {
            foreach ($accountCustomers as $offset => $customer) {
                $dayOffset = $weekStart + $offset;
                if ($dayOffset >= $historyDays) {
                    break;
                }

                $date = $startDate->copy()->addDays($dayOffset);
                $orderTime = $date->copy()->setTime(rand(12, 21), rand(0, 59));
                $mi = $menuItems[array_rand($menuItems)];
                $qty = rand(8, 16);
                $subtotal = round((float) $mi->price * $qty, 2);
                $taxAmount = round($subtotal * ($totalTaxPct / 100), 2);
                $totalAmount = round($subtotal + $taxAmount, 2);
                $paidAmount = round($totalAmount * rand(0, 20) / 100, 2);
                $outstanding = round($totalAmount - $paidAmount, 2);

                if ($outstanding <= 0) {
                    continue;
                }

                $moneySource = $moneySources[0];
                $cashierUser = $employees[array_rand($employees)];

                $order = Order::withoutGlobalScopes()->create([
                    'company_id' => $company->id,
                    'branch_id' => $branch->id,
                    'customer_id' => $customer->id,
                    'cashier_id' => $cashierUser->id,
                    'order_number' => $this->tenantScopedReferenceNumber('PSH', $company->id, $date->format('Ymd'), $seq++),
                    'type' => ['dine_in', 'takeaway', 'delivery'][$offset % 3],
                    'status' => 'completed',
                    'payment_status' => 'partial',
                    'payment_method' => $paidAmount > 0 ? 'cash' : 'credit',
                    'money_source_id' => $moneySource->id,
                    'customer_name' => $customer->name,
                    'customer_phone' => $customer->phone,
                    'table_id' => $demoTableIds[array_rand($demoTableIds)] ?? null,
                    'subtotal' => $subtotal,
                    'tax_amount' => $taxAmount,
                    'total_amount' => $totalAmount,
                    'paid_amount' => $paidAmount,
                    'completed_at' => $orderTime,
                ]);

                DB::table('orders')->where('id', $order->id)->update([
                    'created_at' => $orderTime,
                    'updated_at' => $orderTime,
                ]);

                OrderItem::create([
                    'order_id' => $order->id,
                    'menu_item_id' => $mi->id,
                    'item_name' => $mi->name,
                    'quantity' => $qty,
                    'unit_price' => (float) $mi->price,
                    'total_price' => $subtotal,
                    'status' => 'served',
                ]);

                if ($salesAccount && $paidAmount > 0) {
                    Transaction::withoutGlobalScopes()->create([
                        'company_id' => $company->id,
                        'branch_id' => $branch->id,
                        'account_id' => $salesAccount->id,
                        'amount' => $paidAmount,
                        'type' => 'in',
                        'payment_method' => 'cash',
                        'money_source_id' => $moneySource->id,
                        'reference_type' => 'sale',
                        'ref_id' => $order->id,
                        'date' => $orderTime->format('Y-m-d'),
                        'created_by' => $cashierUser->id,
                        'notes' => 'Partial payment — Sale #'.$order->order_number,
                    ]);
                }

                $customer->refresh();
                $creditService->applyCreditToCustomer($customer, $outstanding);
            }
        }
    }

    /**
     * @param  array<string, Ingredient>  $ingredients
     * @param  array<string, int>  $unitIds
     */
    protected function applyDemoLowStockLevels(int $branchId, array $ingredients, array $unitIds): void
    {
        $lowStockNames = ['Fresh Basil', 'Truffle Oil', 'Pineapple', 'Mozzarella'];

        foreach ($lowStockNames as $name) {
            $ing = $ingredients[$name] ?? null;
            if (! $ing || ! $ing->min_stock_level) {
                continue;
            }

            $uid = $unitIds[$ing->base_unit_id] ?? null;
            if (! $uid) {
                continue;
            }

            $lowQty = max(1, round((float) $ing->min_stock_level * 0.35, 2));

            BranchStock::withoutBranchScope()->updateOrCreate(
                [
                    'branch_id' => $branchId,
                    'ingredient_id' => $ing->id,
                    'average_cost' => $ing->cost_per_unit,
                ],
                [
                    'quantity' => $lowQty,
                    'reserved_quantity' => 0,
                    'unit_id' => $uid,
                    'last_restocked_at' => now(),
                ]
            );
        }
    }

    protected function assignDemoRoles(Company $company, User $admin, User $cashier1, User $cashier2, User $waiter): void
    {
        $bootstrap = app(TenantRoleBootstrapService::class);
        $bootstrap->syncDefaultRolesForCompany($company);

        setPermissionsTeamId($company->id);
        $admin->syncRoles([TenantDefaultRoles::ADMINISTRATOR]);
        $cashier1->syncRoles([TenantDefaultRoles::CASHIER]);
        $cashier2->syncRoles([TenantDefaultRoles::CASHIER]);
        $waiter->syncRoles([TenantDefaultRoles::ORDER_TAKER]);
    }

    /**
     * HR demo dataset: profiles, attendance, leave, adjustments, payments, and a draft payroll.
     *
     * @param  array<int, User>  $hrStaff
     */
    protected function seedDemoHrData(
        Company $company,
        Branch $branch,
        User $admin,
        array $hrStaff,
        MoneySource $cashSource,
        string $tz
    ): void {
        if (! Schema::hasTable('employee_profiles') || $hrStaff === []) {
            return;
        }

        $defs = [
            'Demo Cashier' => [
                'employee_number' => 'EMP-CASH-01',
                'designation' => 'Cashier',
                'department' => 'Front of House',
                'pay_frequency' => 'daily',
                'pay_rate' => 2500,
                'standard_hours_per_day' => 8,
                'overtime_rate' => 400,
                'working_days' => [1, 2, 3, 4, 5, 6],
            ],
            'Demo Cook' => [
                'employee_number' => 'EMP-COOK-01',
                'designation' => 'Head Cook',
                'department' => 'Kitchen',
                'pay_frequency' => 'monthly',
                'pay_rate' => 55000,
                'standard_hours_per_day' => 9,
                'overtime_rate' => 0,
                'working_days' => [1, 2, 3, 4, 5, 6],
            ],
            'Sarah Cashier' => [
                'employee_number' => 'EMP-CASH-02',
                'designation' => 'Cashier',
                'department' => 'Front of House',
                'pay_frequency' => 'daily',
                'pay_rate' => 2200,
                'standard_hours_per_day' => 8,
                'overtime_rate' => 350,
                'working_days' => [1, 2, 3, 4, 5, 6],
            ],
            'Mike Waiter' => [
                'employee_number' => 'EMP-WAIT-01',
                'designation' => 'Waiter',
                'department' => 'Service',
                'pay_frequency' => 'weekly',
                'pay_rate' => 12000,
                'standard_hours_per_day' => 8,
                'overtime_rate' => 250,
                'working_days' => [1, 2, 3, 4, 5, 6, 7],
            ],
            'Noor Kitchen Helper' => [
                'employee_number' => 'EMP-HELP-01',
                'designation' => 'Kitchen Helper',
                'department' => 'Kitchen',
                'pay_frequency' => 'daily',
                'pay_rate' => 1800,
                'standard_hours_per_day' => 8,
                'overtime_rate' => 200,
                'working_days' => [1, 2, 3, 4, 5, 6],
            ],
        ];

        $profilesByUserId = [];
        $hireDate = Carbon::today($tz)->subMonths(4)->toDateString();

        foreach ($hrStaff as $staff) {
            $def = $defs[$staff->name] ?? [
                'employee_number' => null,
                'designation' => 'Staff',
                'department' => 'Operations',
                'pay_frequency' => 'daily',
                'pay_rate' => 2000,
                'standard_hours_per_day' => 8,
                'overtime_rate' => 0,
                'working_days' => EmployeeProfile::DEFAULT_WORKING_DAYS,
            ];

            $profile = EmployeeProfile::withoutGlobalScopes()->updateOrCreate(
                [
                    'company_id' => $company->id,
                    'user_id' => $staff->id,
                ],
                [
                    'employee_number' => $def['employee_number'],
                    'designation' => $def['designation'],
                    'department' => $def['department'],
                    'hire_date' => $hireDate,
                    'employment_status' => 'active',
                    'pay_frequency' => $def['pay_frequency'],
                    'pay_rate' => $def['pay_rate'],
                    'standard_hours_per_day' => $def['standard_hours_per_day'],
                    'overtime_rate' => $def['overtime_rate'],
                    'short_hours_policy' => 'full_day',
                    'working_days' => $def['working_days'],
                    'national_id' => null,
                    'notes' => 'Demo HR profile for testing',
                ]
            );
            $profilesByUserId[(int) $staff->id] = $profile;
        }

        $attendanceDays = 21;
        $attendanceStart = Carbon::today($tz)->subDays($attendanceDays - 1);

        foreach ($hrStaff as $staff) {
            $profile = $profilesByUserId[(int) $staff->id] ?? null;
            if (! $profile) {
                continue;
            }

            $standardMinutes = $profile->standardMinutesPerDay();
            $workingDays = $profile->workingDays();

            for ($d = 0; $d < $attendanceDays; $d++) {
                $date = $attendanceStart->copy()->addDays($d);
                $isoDay = (int) $date->dayOfWeekIso; // 1=Mon … 7=Sun
                if (! in_array($isoDay, $workingDays, true)) {
                    continue;
                }

                // One deliberate absent day per employee mid-period.
                if ($d === 10) {
                    AttendanceRecord::withoutGlobalScopes()->create([
                        'company_id' => $company->id,
                        'branch_id' => $branch->id,
                        'employee_id' => $staff->id,
                        'attendance_date' => $date->toDateString(),
                        'status' => 'absent',
                        'source' => 'manual',
                        'created_by' => $admin->id,
                        'notes' => 'Demo absent day',
                    ]);

                    continue;
                }

                $clockIn = $date->copy()->setTime(10, 0);
                $clockOut = $date->copy()->setTime(18, $d % 5 === 0 ? 45 : 0); // some OT evenings
                $minutes = AttendanceRecord::calculateMinutes(
                    $clockIn->toDateTimeString(),
                    $clockOut->toDateTimeString(),
                    30,
                    $standardMinutes,
                    'present'
                );

                AttendanceRecord::withoutGlobalScopes()->create([
                    'company_id' => $company->id,
                    'branch_id' => $branch->id,
                    'employee_id' => $staff->id,
                    'attendance_date' => $date->toDateString(),
                    'clock_in' => $clockIn,
                    'clock_out' => $clockOut,
                    'break_minutes' => 30,
                    'worked_minutes' => $minutes['worked'],
                    'regular_minutes' => $minutes['regular'],
                    'overtime_minutes' => $minutes['overtime'],
                    'status' => 'present',
                    'source' => 'manual',
                    'created_by' => $admin->id,
                ]);
            }
        }

        $waiter = collect($hrStaff)->firstWhere('name', 'Mike Waiter');
        $cook = collect($hrStaff)->firstWhere('name', 'Demo Cook');
        $cashier1 = collect($hrStaff)->firstWhere('name', 'Demo Cashier');

        if ($waiter) {
            $leaveStart = Carbon::today($tz)->subDays(4);
            $leaveEnd = $leaveStart->copy()->addDay();
            EmployeeLeaveRequest::withoutGlobalScopes()->create([
                'company_id' => $company->id,
                'branch_id' => $branch->id,
                'employee_id' => $waiter->id,
                'leave_type' => 'paid',
                'start_date' => $leaveStart->toDateString(),
                'end_date' => $leaveEnd->toDateString(),
                'days' => 2,
                'status' => 'approved',
                'reason' => 'Family event (demo)',
                'requested_by' => $waiter->id,
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
                'review_notes' => 'Approved for demo data',
            ]);
        }

        if ($cook) {
            EmployeeLeaveRequest::withoutGlobalScopes()->create([
                'company_id' => $company->id,
                'branch_id' => $branch->id,
                'employee_id' => $cook->id,
                'leave_type' => 'unpaid',
                'start_date' => Carbon::today($tz)->addDays(3)->toDateString(),
                'end_date' => Carbon::today($tz)->addDays(4)->toDateString(),
                'days' => 2,
                'status' => 'pending',
                'reason' => 'Personal work (demo pending leave)',
                'requested_by' => $admin->id,
            ]);
        }

        if ($cashier1) {
            EmployeePayrollAdjustment::withoutGlobalScopes()->create([
                'company_id' => $company->id,
                'branch_id' => $branch->id,
                'employee_id' => $cashier1->id,
                'type' => 'bonus',
                'effective_date' => Carbon::today($tz)->subDays(2)->toDateString(),
                'amount' => 1500,
                'status' => 'pending',
                'created_by' => $admin->id,
                'notes' => 'Weekend rush bonus (demo)',
            ]);
        }

        if ($cook) {
            EmployeePayrollAdjustment::withoutGlobalScopes()->create([
                'company_id' => $company->id,
                'branch_id' => $branch->id,
                'employee_id' => $cook->id,
                'type' => 'deduction',
                'effective_date' => Carbon::today($tz)->subDays(1)->toDateString(),
                'amount' => 500,
                'status' => 'pending',
                'created_by' => $admin->id,
                'notes' => 'Uniform deduction (demo)',
            ]);
        }

        try {
            $previousUser = Auth::user();
            Auth::login($admin);
            $paymentService = app(EmployeePaymentService::class);

            if ($cashier1) {
                $paymentService->pay([
                    'kind' => 'wage',
                    'employee_id' => $cashier1->id,
                    'branch_id' => $branch->id,
                    'money_source_id' => $cashSource->id,
                    'payment_date' => Carbon::today($tz)->subDay()->toDateString(),
                    'amount' => 2500,
                    'payment_method' => 'cash',
                    'notes' => 'Demo daily wage payout',
                ], $admin);
            }

            if ($cook) {
                $paymentService->pay([
                    'kind' => 'advance',
                    'employee_id' => $cook->id,
                    'branch_id' => $branch->id,
                    'money_source_id' => $cashSource->id,
                    'payment_date' => Carbon::today($tz)->subDays(3)->toDateString(),
                    'amount' => 5000,
                    'payment_method' => 'cash',
                    'notes' => 'Demo salary advance',
                ], $admin);
            }

            if ($waiter) {
                $paymentService->pay([
                    'kind' => 'bonus',
                    'employee_id' => $waiter->id,
                    'branch_id' => $branch->id,
                    'money_source_id' => $cashSource->id,
                    'payment_date' => Carbon::today($tz)->subDays(2)->toDateString(),
                    'amount' => 1000,
                    'payment_method' => 'cash',
                    'notes' => 'Demo cash tip bonus',
                ], $admin);
            }

            if ($previousUser) {
                Auth::login($previousUser);
            } else {
                Auth::logout();
            }
        } catch (\Throwable $e) {
            if (isset($previousUser) && $previousUser) {
                Auth::login($previousUser);
            } elseif (Auth::check()) {
                Auth::logout();
            }
            Log::warning('Demo HR payments skipped for company '.$company->id.': '.$e->getMessage());
        }

        try {
            $payrollService = app(PayrollService::class);
            $periodEnd = Carbon::today($tz)->subDay();
            $periodStart = $periodEnd->copy()->subDays(6);
            $payrollService->generate(
                (int) $company->id,
                (int) $branch->id,
                'daily',
                $periodStart->toDateString(),
                $periodEnd->toDateString(),
                (int) $admin->id,
                'Demo draft payroll for daily staff'
            );
        } catch (\Throwable $e) {
            Log::warning('Demo HR payroll skipped for company '.$company->id.': '.$e->getMessage());
        }
    }

    /**
     * Sample refund batches for order management demos (partial + full, per service type).
     */
    protected function seedDemoRefunds(int $companyId, int $adminId): void
    {
        $admin = User::withoutGlobalScopes()->find($adminId);
        if (! $admin) {
            return;
        }

        $previousUser = Auth::user();
        Auth::login($admin);

        try {
            $refundService = app(OrderRefundService::class);

            $dineIn = $this->demoOrderForRefund($companyId, 'dine_in', 3);
            if ($dineIn && $dineIn->items->isNotEmpty()) {
                $line = $dineIn->items->first();
                $refundQty = min(1.0, (float) $line->quantity);
                $refundService->processRefund($dineIn, [
                    [
                        'order_item_id' => $line->id,
                        'quantity' => $refundQty,
                        'restock_inventory' => false,
                        'line_notes' => 'Wrong pizza — kitchen remake',
                    ],
                ], 'Partial dine-in refund (demo)', $adminId);
            }

            $delivery = $this->demoOrderForRefund($companyId, 'delivery', 2);
            if ($delivery && $delivery->items->count() >= 2) {
                $line = $delivery->items->get(1);
                $refundService->processRefund($delivery, [
                    [
                        'order_item_id' => $line->id,
                        'quantity' => min(1.0, (float) $line->quantity),
                        'restock_inventory' => false,
                        'line_notes' => 'Missing from delivery bag',
                    ],
                ], 'Partial delivery refund (demo)', $adminId);
            }

            $takeaway = $this->demoOrderForRefund($companyId, 'takeaway', 2);
            if ($takeaway && $takeaway->items->isNotEmpty()) {
                $lines = $takeaway->items->map(fn (OrderItem $item) => [
                    'order_item_id' => $item->id,
                    'quantity' => (float) $item->quantity,
                    'restock_inventory' => false,
                ])->all();
                $refundService->processRefund($takeaway, $lines, 'Full takeaway refund — order cancelled before pickup (demo)', $adminId);
            }

            $multiRefund = $this->demoOrderForRefund($companyId, 'dine_in', 4, requireNoRefunds: true);
            if ($multiRefund && $multiRefund->items->count() >= 2) {
                $first = $multiRefund->items->first();
                $refundService->processRefund($multiRefund, [
                    [
                        'order_item_id' => $first->id,
                        'quantity' => min(1.0, (float) $first->quantity),
                        'restock_inventory' => false,
                        'line_notes' => 'Guest did not eat',
                    ],
                ], 'First partial refund on large table order (demo)', $adminId);

                $multiRefund->refresh()->load(['items.menuItem']);
                $second = $multiRefund->items->get(1);
                if ($second && (float) $second->billableQuantity() > 0) {
                    $refundService->processRefund($multiRefund, [
                        [
                            'order_item_id' => $second->id,
                            'quantity' => min(1.0, (float) $second->billableQuantity()),
                            'restock_inventory' => false,
                        ],
                    ], 'Second partial refund on same order (demo)', $adminId);
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Demo refund seed skipped for company '.$companyId.': '.$e->getMessage());
        } finally {
            if ($previousUser) {
                Auth::login($previousUser);
            } else {
                Auth::logout();
            }
        }
    }

    protected function demoOrderForRefund(int $companyId, string $type, int $minItems, bool $requireNoRefunds = true): ?Order
    {
        $query = Order::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('type', $type)
            ->where('status', 'completed')
            ->where('payment_status', 'paid')
            ->with(['items.menuItem'])
            ->withCount('items')
            ->having('items_count', '>=', $minItems);

        if ($requireNoRefunds) {
            $query->whereDoesntHave('refunds');
        }

        if ($type === 'dine_in') {
            $query->whereNotNull('table_id');
        } elseif ($type === 'delivery') {
            $query->whereNotNull('customer_address')->where('delivery_fee', '>', 0);
        } else {
            $query->whereNull('table_id')->whereNull('customer_address');
        }

        return $query->orderByDesc('id')->first();
    }

    protected function resolveDemoPublicMenuImage(string $folder, string $slug, string $name): ?string
    {
        $normalizedName = preg_replace('/\s+/', ' ', trim($name));
        if ($normalizedName === '') {
            return null;
        }

        $asciiSlug = Str::slug($normalizedName);
        $stripped = preg_replace('/[^a-zA-Z0-9\s_-]/u', '', $normalizedName);
        $simple = strtolower(str_replace([' ', '_'], '-', trim($stripped)));
        $simple = trim(preg_replace('/-+/', '-', $simple), '-');

        // Literal path attempts (case-sensitive filesystems: e.g. "BBQ Chicken.jpg")
        $literalStems = array_values(array_unique(array_filter([
            $normalizedName,
            $slug,
            $asciiSlug,
            $simple !== '' ? $simple : null,
        ])));

        $baseDir = public_path('images/demo/'.$folder);
        if (! is_dir($baseDir)) {
            return null;
        }

        $extensions = ['webp', 'jpg', 'jpeg', 'png', 'gif'];

        foreach ($literalStems as $stem) {
            if ($stem === '') {
                continue;
            }
            foreach ($extensions as $ext) {
                $relative = 'images/demo/'.$folder.'/'.$stem.'.'.$ext;
                if (is_file(public_path($relative))) {
                    return '/'.$relative;
                }
            }
        }

        // Case-insensitive + hyphen/space variants (e.g. BBQ Chicken.jpg vs slug bbq-chicken-pizza)
        $needles = array_unique(array_filter(array_map('strtolower', [
            $normalizedName,
            $slug,
            str_replace('-', ' ', $slug),
            $asciiSlug,
            str_replace('-', ' ', $asciiSlug),
            $simple,
        ])));

        foreach (scandir($baseDir) ?: [] as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            $full = $baseDir.DIRECTORY_SEPARATOR.$file;
            if (! is_file($full)) {
                continue;
            }
            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            if (! in_array($ext, $extensions, true)) {
                continue;
            }
            $fileStem = strtolower(pathinfo($file, PATHINFO_FILENAME));
            foreach ($needles as $needle) {
                if ($needle !== '' && $fileStem === $needle) {
                    return '/images/demo/'.$folder.'/'.$file;
                }
            }
        }

        return null;
    }
}
