# File Structure Overview

## Generated Files

### Database Migrations (`database/migrations/`)
```
2024_01_01_000001_create_companies_table.php
2024_01_01_000002_create_branches_table.php
2024_01_01_000003_create_users_table.php
2024_01_01_000004_create_suppliers_table.php
2024_01_01_000005_create_units_of_measure_table.php
2024_01_01_000006_create_ingredients_table.php
2024_01_01_000007_create_branch_stock_table.php
2024_01_01_000008_create_purchase_orders_table.php
2024_01_01_000009_create_purchase_order_items_table.php
2024_01_01_000010_create_categories_table.php
2024_01_01_000011_create_menu_items_table.php
2024_01_01_000012_create_recipes_table.php
2024_01_01_000013_create_tables_table.php
2024_01_01_000014_create_orders_table.php
2024_01_01_000015_create_order_items_table.php
2024_01_01_000016_create_kitchen_display_orders_table.php
2024_01_01_000017_create_coupons_table.php
2024_01_01_000018_create_loyalty_programs_table.php
2024_01_01_000019_create_customer_loyalty_table.php
2024_01_01_000020_create_daily_cash_ups_table.php
2024_01_01_000021_create_expenses_table.php
2024_01_01_000022_create_stock_movements_table.php
```

### Traits (`app/Traits/`)
```
TenantScope.php          - Company-level data isolation
BranchScope.php           - Branch-level data isolation
HasTenantAndBranch.php    - Combined scoping trait
```

### Models (`app/Models/`)
```
Company.php               - SaaS companies (no scope - system level)
Branch.php                - Physical locations (TenantScope)
User.php                  - Users (no scope - system level)
Supplier.php              - Vendors (TenantScope)
UnitOfMeasure.php         - UOM definitions (TenantScope)
Ingredient.php            - Raw materials (TenantScope)
BranchStock.php           - Per-branch inventory (BranchScope)
Category.php              - Menu categories (TenantScope)
MenuItem.php              - Products for sale (TenantScope)
Recipe.php                - Menu item to ingredient mapping
Table.php                 - Dine-in tables (BranchScope)
Order.php                 - Sales transactions (HasTenantAndBranch)
OrderItem.php             - Order line items
KitchenDisplayOrder.php   - KDS tracking
Coupon.php                - Discount codes (TenantScope)
LoyaltyProgram.php        - Loyalty systems (TenantScope)
CustomerLoyalty.php       - Customer points (TenantScope)
PurchaseOrder.php         - Purchase orders (HasTenantAndBranch)
PurchaseOrderItem.php     - PO line items
StockMovement.php         - Inventory audit trail (BranchScope)
DailyCashUp.php           - End-of-day reconciliation (BranchScope)
Expense.php               - Cost tracking (HasTenantAndBranch)
```

### Services (`app/Services/`)
```
InventoryService.php      - Inventory management logic
  - reserveInventory()
  - finalizeInventoryDeduction()
  - releaseReservedInventory()
  - canSellMenuItem()
  - getLowStockAlerts()
```

### HTTP Layer (`app/Http/`)

#### Controllers (`app/Http/Controllers/Api/`)
```
BaseApiController.php     - Base API controller with helpers
```

#### Middleware (`app/Http/Middleware/`)
```
SetTenantContext.php      - Sets tenant/branch context
```

#### Resources (`app/Http/Resources/Api/`)
```
BaseResource.php          - Base API resource
OrderResource.php         - Order transformation
OrderItemResource.php     - Order item transformation
MenuItemResource.php      - Menu item transformation
```

### Documentation
```
README.md                 - Project overview and setup
DATABASE_SCHEMA.md        - Detailed database documentation
ARCHITECTURE.md           - System architecture
FILE_STRUCTURE.md         - This file
```

## Trait Usage Summary

### TenantScope (Company-level isolation)
- Branch
- Supplier
- UnitOfMeasure
- Ingredient
- Category
- MenuItem
- Coupon
- LoyaltyProgram
- CustomerLoyalty

### BranchScope (Branch-level isolation)
- BranchStock
- Table
- StockMovement
- DailyCashUp

### HasTenantAndBranch (Both scopes)
- Order
- PurchaseOrder
- Expense

### No Scope (System-level)
- Company
- User

## Key Relationships

### Company Hierarchy
```
Company
  ├── Branches (1:N)
  ├── Users (1:N)
  ├── Suppliers (1:N)
  ├── Categories (1:N)
  ├── MenuItems (1:N)
  └── Coupons (1:N)
```

### Branch Operations
```
Branch
  ├── Tables (1:N)
  ├── Orders (1:N)
  ├── BranchStock (1:N)
  ├── PurchaseOrders (1:N)
  ├── DailyCashUps (1:N)
  └── Expenses (1:N)
```

### Recipe System
```
MenuItem
  └── Recipes (1:N)
      └── Ingredient (N:1)
          └── BranchStock (1:N per branch)
```

### Order Flow
```
Order
  ├── OrderItems (1:N)
  │   └── MenuItem (N:1)
  │       └── Recipes (1:N)
  └── KitchenDisplayOrders (1:N)
```

## Next Files to Create

### Controllers
- `app/Http/Controllers/OrderController.php`
- `app/Http/Controllers/Api/OrderController.php`
- `app/Http/Controllers/MenuItemController.php`
- `app/Http/Controllers/Api/MenuItemController.php`
- `app/Http/Controllers/InventoryController.php`
- `app/Http/Controllers/PurchaseOrderController.php`

### Livewire Components
- `app/Livewire/Pos/PosScreen.php`
- `app/Livewire/Pos/OrderCart.php`
- `app/Livewire/Kitchen/KitchenDisplay.php`

### Requests (Validation)
- `app/Http/Requests/StoreOrderRequest.php`
- `app/Http/Requests/UpdateOrderRequest.php`
- `app/Http/Requests/Api/StoreOrderRequest.php`

### Seeders
- `database/seeders/DatabaseSeeder.php`
- `database/seeders/CompanySeeder.php`
- `database/seeders/MenuSeeder.php`

### Factories
- `database/factories/CompanyFactory.php`
- `database/factories/MenuItemFactory.php`
- `database/factories/OrderFactory.php`

