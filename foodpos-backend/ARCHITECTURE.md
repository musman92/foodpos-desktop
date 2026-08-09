# POS System Architecture

## Overview

This is a high-performance, SaaS-based POS system built with Laravel, designed for restaurants, juice shops, and food chains. The system is multi-tenant, API-first, and highly scalable.

## Technical Stack

- **Backend**: Laravel (latest), PHP 8.3
- **Frontend**: Tailwind CSS, Alpine.js, Livewire (TALL Stack)
- **Database**: MySQL/PostgreSQL with single-database multi-tenancy
- **API**: RESTful API using Laravel Sanctum
- **Authentication**: Laravel Sanctum for API, Session for web
- **Permissions**: Spatie Laravel-Permission

## Architecture Principles

### 1. Multi-Tenancy

**Single-Database Approach with Scoping**
- All tables include `company_id` and/or `branch_id` columns
- Global scopes automatically filter data based on authenticated user
- No data leakage between companies or branches
- Super admins can access all data for system management

**Tenant Hierarchy**
```
Super Admin (System Level)
  └── Company 1
      ├── Branch 1
      │   ├── Users (Branch Manager, Staff)
      │   ├── Orders
      │   ├── Stock
      │   └── Tables
      └── Branch 2
          └── ...
  └── Company 2
      └── ...
```

### 2. Modular Design

The system is organized into logical modules:

- **Inventory Module**: Suppliers, Purchase Orders, Stock Management, UOM
- **Menu Module**: Categories, Menu Items, Recipes
- **Sales Module**: Tables, Orders, Order Items, KDS
- **Accounting Module**: Daily Cash Ups, Expenses
- **Marketing Module**: Coupons, Loyalty Programs

### 3. Recipe & UOM System

**Unit of Measure (UOM)**
- Supports multiple measurement types (weight, volume, length, count)
- Base units for conversion calculations
- Company-scoped configuration

**Recipe Management**
- Menu items link to ingredients via recipes
- Each recipe specifies quantity and unit
- Supports waste percentage for accurate costing
- Example: 1 Burger = 50g Cheese + 100g Beef + 1 Bun

**Auto Inventory Deduction**
- When an order is placed, if `track_inventory` is enabled on the menu item
- System automatically deducts ingredients from branch stock
- Uses recipe quantities (including waste factor)
- Prevents overselling when stock is low

### 4. API-First Design

**Base API Controller**
- Standardized response format
- Helper methods for success/error responses
- Consistent error handling

**API Resources**
- Transform models to API-friendly format
- Include relationships when loaded
- Meta information (timestamps, pagination)

**Mobile App Support**
- All business logic accessible via API
- Same data models used for web and mobile
- Sanctum token authentication

### 5. POS Interface Requirements

**High Performance**
- Optimized queries with eager loading
- Caching for menu items, categories
- Minimal database queries per transaction

**Touch-Friendly**
- Large buttons, easy navigation
- Quick item selection
- Fast order processing

**Offline Resilience**
- Works with jittery internet
- Queue-based order processing
- Local caching of menu data

## Data Flow

### Order Processing Flow

1. **Order Creation**
   - Cashier selects items from menu
   - System checks availability (if tracking inventory)
   - Order created with `pending` status
   - Order number generated

2. **Inventory Check & Reservation**
   - If menu item tracks inventory:
     - Check branch stock for all required ingredients
     - Reserve quantities (move to `reserved_quantity`)
     - Create stock movements (type: `sale`, movement: `out`)

3. **Kitchen Display**
   - Order items appear in KDS
   - Kitchen staff updates status: `preparing` → `ready`
   - System tracks preparation time

4. **Order Completion**
   - Mark order as `served` or `completed`
   - Process payment
   - Finalize stock deduction (remove from reserved, deduct from quantity)
   - Update customer loyalty points

5. **Stock Deduction**
   - On order completion, actual stock is reduced
   - Reserved quantities are cleared
   - Stock movements recorded for audit

### Purchase Order Flow

1. **Create PO**
   - Select supplier and branch
   - Add items (ingredients) with quantities
   - Calculate totals
   - Status: `draft` → `pending`

2. **Receive Goods**
   - Update received quantities
   - Status: `partial` or `received`
   - Create stock movements (type: `purchase`, movement: `in`)
   - Update branch stock levels
   - Update average cost

3. **Stock Update**
   - Branch stock quantity increased
   - Average cost recalculated
   - Last restocked timestamp updated

## Security & Permissions

### Role-Based Access Control

Using Spatie Laravel-Permission:

- **Super Admin**: Full system access
- **Company Admin**: Manage company and all branches
- **Branch Manager**: Manage single branch
- **Cashier**: Process orders, view reports
- **Kitchen Staff**: Update KDS, view orders

### Data Isolation

- All models use global scopes for automatic filtering
- Middleware ensures user context is set
- API requests can specify company/branch via headers (with validation)
- No cross-tenant data access possible

## Performance Optimizations

### Caching Strategy

- **Menu Items**: Cache with tags, invalidate on update
- **Categories**: Long-lived cache
- **Stock Levels**: Cache with short TTL, update on movements
- **User Permissions**: Cache per user session

### Query Optimization

- Eager loading for relationships
- Indexes on foreign keys and frequently queried columns
- Scoped queries to limit result sets
- Pagination for large datasets

### POS Screen Optimization

- Pre-load menu items and categories
- Lazy load images
- Optimistic UI updates
- Background sync for order processing

## API Structure

### Base Endpoints

```
/api/v1/
  ├── auth/
  │   ├── login
  │   ├── logout
  │   └── user
  ├── menu/
  │   ├── categories
  │   ├── items
  │   └── items/{id}
  ├── orders/
  │   ├── (GET) list
  │   ├── (POST) create
  │   ├── (GET) {id}
  │   ├── (PUT) {id}/update-status
  │   └── {id}/items
  ├── inventory/
  │   ├── stock
  │   ├── purchase-orders
  │   └── suppliers
  └── reports/
      ├── sales
      ├── inventory
      └── cash-up
```

### Response Format

**Success Response**
```json
{
  "success": true,
  "message": "Order created successfully",
  "data": { ... }
}
```

**Error Response**
```json
{
  "success": false,
  "message": "Validation failed",
  "errors": { ... }
}
```

## Future Enhancements

1. **Real-time Updates**: WebSocket support for KDS and order status
2. **Advanced Reporting**: Analytics dashboard with charts
3. **Multi-currency**: Support for different currencies per branch
4. **Integration**: Third-party delivery platforms, accounting software
5. **Mobile Apps**: Native iOS/Android apps using the API
6. **Offline Mode**: Full offline capability with sync

