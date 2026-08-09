# Database Schema Documentation

## Multi-Tenant Architecture

This POS system uses a **single-database multi-tenancy** approach with `company_id` and `branch_id` scoping to isolate data between different companies and branches.

### Core Tenant Structure

#### Companies Table
- Central entity representing a SaaS customer
- Each company can have multiple branches
- Contains company-wide settings and subscription information

#### Branches Table
- Represents physical locations (restaurants, shops)
- Each branch belongs to one company
- Contains branch-specific settings (timezone, POS config)

#### Users Table
- Supports multiple user types: `super_admin`, `company_admin`, `branch_manager`, `staff`
- Users are scoped to a company and optionally to a branch
- Super admins can access all companies
- Company admins can access all branches within their company
- Branch managers and staff are limited to their assigned branch

## Inventory Module

### Units of Measure (UOM)
- Supports different measurement types: weight, volume, length, count, other
- Base units for conversion calculations
- Company-scoped

### Ingredients
- Raw materials used in recipes
- Tracked by base unit of measure
- Has cost per unit and stock level thresholds
- Can be set to track or not track stock

### Branch Stock
- Tracks actual inventory levels per branch
- Includes reserved quantity for pending orders
- Maintains average cost for inventory valuation
- Links ingredients to branches with quantities

### Suppliers
- Vendor management
- Company-scoped

### Purchase Orders
- Track incoming inventory
- Support partial receiving
- Branch-scoped (inventory goes to specific branch)
- Status: draft, pending, received, partial, cancelled

### Stock Movements
- Audit trail for all inventory changes
- Types: purchase, sale, adjustment, waste, transfer
- Polymorphic reference to source (PurchaseOrder, Order, etc.)

## Menu & Recipe System

### Categories
- Organize menu items
- Company-scoped

### Menu Items
- Products sold to customers
- Has price, cost (calculated from recipes), variants
- Can track inventory (auto-deduct ingredients) or not

### Recipes
- Links menu items to ingredients with quantities
- Supports waste percentage
- Example: 1 Burger = 50g Cheese + 100g Beef + 1 Bun
- When a burger is sold, ingredients are auto-deducted if `track_inventory` is enabled

## Sales Module

### Tables
- Physical tables for dine-in service
- Status: available, occupied, reserved, dirty, out_of_service
- Supports floor plan visualization with position coordinates
- Branch-scoped

### Orders
- Main sales transaction
- Types: dine_in, takeaway, delivery
- Status: pending, confirmed, preparing, ready, served, completed, cancelled
- Payment status: unpaid, partial, paid, refunded
- Includes customer information, pricing breakdown, coupon application

### Order Items
- Individual items within an order
- Snapshot of menu item name/price at time of order
- Supports variants and special instructions
- Status tracking for kitchen workflow

### Kitchen Display System (KDS)
- Tracks preparation status of order items
- Links to order items and assigned kitchen staff
- Records actual preparation time

## Marketing Module

### Coupons
- Discount codes with various rules
- Types: percentage or fixed amount
- Supports usage limits, date ranges, minimum purchase
- Can be restricted to specific categories or menu items

### Loyalty Programs
- Points-based or tiered discount systems
- Company-scoped
- Configurable points per currency and redemption rates

### Customer Loyalty
- Tracks customer points and spending
- Identified by phone number
- Maintains tier status and lifetime statistics

## Accounting Module

### Daily Cash Ups
- End-of-day reconciliation
- Tracks opening cash, expected vs actual cash
- Records sales by payment method
- Branch-scoped, one per branch per day

### Expenses
- Track operational costs
- Categories: Utilities, Rent, Supplies, etc.
- Branch-scoped with receipt attachments

## Multi-Tenant Scoping

### TenantScope Trait
- Automatically filters queries by `company_id`
- Super admins bypass the scope
- Automatically sets `company_id` on create
- Methods: `withoutTenantScope()`, `forTenant($companyId)`

### BranchScope Trait
- Automatically filters queries by `branch_id`
- Super admins and company admins bypass the scope
- Automatically sets `branch_id` on create
- Methods: `withoutBranchScope()`, `forBranch($branchId)`

### HasTenantAndBranch Trait
- Combines both scopes
- Use for models that need both company and branch isolation
- Methods: `withoutScopes()`, `scopeCurrentTenant()`, `scopeCurrentBranch()`

## Data Isolation Rules

1. **Company Level**: All data is isolated by `company_id` except for super admin access
2. **Branch Level**: Operational data (orders, stock, tables) is further isolated by `branch_id`
3. **User Context**: User's `company_id` and `branch_id` determine what data they can access
4. **Automatic Scoping**: All queries are automatically scoped unless explicitly bypassed
5. **Safety First**: If user has no company/branch context, queries return empty results

## Key Relationships

- Company → Branches (1:N)
- Company → Users (1:N)
- Branch → Tables (1:N)
- Branch → Orders (1:N)
- Branch → Stock (1:N via branch_stock)
- Menu Item → Recipes (1:N)
- Recipe → Ingredient (N:1)
- Order → Order Items (1:N)
- Order Item → Menu Item (N:1)
- Purchase Order → Purchase Order Items (1:N)
- Purchase Order Item → Ingredient (N:1)

