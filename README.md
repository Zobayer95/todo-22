# Multi-Tenant POS Backend System

A robust, API-first Multi-Tenant POS (Point of Sale) / Inventory Management Backend System built with Laravel. This system supports multiple businesses (tenants) with complete data isolation, role-based access control, and comprehensive inventory and order management.

## Table of Contents

- [Features](#features)
- [Architecture Overview](#architecture-overview)
- [Multi-Tenancy Strategy](#multi-tenancy-strategy)
- [Installation](#installation)
- [API Documentation](#api-documentation)
- [Key Design Decisions](#key-design-decisions)
- [Performance Considerations](#performance-considerations)
- [Security Measures](#security-measures)

## Features

- **Multi-Tenancy**: Complete data isolation between tenants using a single database with tenant_id columns
- **Authentication**: Laravel Sanctum-based API token authentication
- **Role-Based Access Control**: Owner and Staff roles with policy-based authorization
- **Product Management**: Full CRUD with SKU uniqueness per tenant, stock tracking
- **Customer Management**: Customer records isolated per tenant
- **Order Management**: Transactional order processing with stock validation
- **Reporting**: Daily sales summary, top-selling products, low stock alerts
- **API Rate Limiting**: Protection against abuse

## Architecture Overview

```
app/
├── Enums/
│   ├── OrderStatus.php      # Order status: pending, paid, cancelled
│   └── UserRole.php         # User roles: owner, staff
├── Http/
│   ├── Controllers/Api/
│   │   ├── AuthController.php
│   │   ├── ProductController.php
│   │   ├── CustomerController.php
│   │   ├── OrderController.php
│   │   └── ReportController.php
│   ├── Middleware/
│   │   └── ResolveTenant.php    # Resolves tenant from X-Tenant-ID header
│   ├── Requests/                # Form Request validation classes
│   └── Resources/               # API Resource transformers
├── Models/
│   ├── Tenant.php
│   ├── User.php
│   ├── Product.php
│   ├── Customer.php
│   ├── Order.php
│   └── OrderItem.php
├── Policies/                    # Authorization policies
├── Scopes/
│   └── TenantScope.php          # Global scope for tenant filtering
├── Services/
│   ├── OrderService.php         # Order business logic with transactions
│   └── ReportService.php        # Report generation
└── Traits/
    └── BelongsToTenant.php      # Trait for tenant-aware models
```

## Multi-Tenancy Strategy

### Approach: Single Database with Tenant ID Column

This system uses a **single database** approach where all tenant data is stored in shared tables with a `tenant_id` column for isolation. This approach was chosen because:

1. **Simplicity**: No need to manage multiple databases or schemas
2. **Cost-Effective**: Single database connection, easier hosting
3. **Easy Maintenance**: Single migration path, simpler backups
4. **Scalability**: Can be migrated to separate databases later if needed

### Implementation Details

#### 1. TenantScope (Global Scope)
```php
// Automatically filters all queries by tenant_id
class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $tenantId = app('current_tenant_id');
        if ($tenantId) {
            $builder->where($model->getTable().'.tenant_id', $tenantId);
        }
    }
}
```

#### 2. BelongsToTenant Trait
- Automatically applies TenantScope to models
- Auto-sets tenant_id on model creation
- Provides tenant relationship

#### 3. ResolveTenant Middleware
- Reads `X-Tenant-ID` from request header
- Validates tenant exists and is active
- Stores tenant context in service container

### Tenant Context Resolution Flow
```
Request → ResolveTenant Middleware → Validate Header → Load Tenant → Store in Container → Controller
```

## Installation

### Requirements
- PHP 8.2+
- Composer
- SQLite/MySQL/PostgreSQL

### Setup Steps

```bash
# Clone the repository
git clone <repository-url>
cd technical-assessment

# Install dependencies
composer install

# Copy environment file
cp .env.example .env

# Generate application key
php artisan key:generate

# Run migrations
php artisan migrate

# Seed demo data (optional)
php artisan db:seed

# Start the development server
php artisan serve
```

### Demo Credentials (after seeding)
- **Tenant ID**: 1
- **Owner**: owner@demo.com / password
- **Staff**: staff@demo.com / password

## API Documentation

### Authentication

All authenticated endpoints require:
- `Authorization: Bearer {token}` header
- `X-Tenant-ID: {tenant_id}` header (except registration)

#### Register New Business
```http
POST /api/auth/register
Content-Type: application/json

{
    "business_name": "My Business",
    "name": "John Doe",
    "email": "john@example.com",
    "password": "password123",
    "password_confirmation": "password123"
}
```

#### Login
```http
POST /api/auth/login
Content-Type: application/json
X-Tenant-ID: 1

{
    "email": "owner@demo.com",
    "password": "password"
}
```

### Products

| Method | Endpoint | Description | Role |
|--------|----------|-------------|------|
| GET | /api/products | List products (paginated) | All |
| POST | /api/products | Create product | Owner |
| GET | /api/products/{id} | Get product | All |
| PUT | /api/products/{id} | Update product | Owner |
| DELETE | /api/products/{id} | Delete product | Owner |

#### Create Product
```http
POST /api/products
Authorization: Bearer {token}
X-Tenant-ID: 1
Content-Type: application/json

{
    "name": "Product Name",
    "sku": "SKU-001",
    "price": 99.99,
    "stock_quantity": 100,
    "low_stock_threshold": 10
}
```

### Customers

| Method | Endpoint | Description | Role |
|--------|----------|-------------|------|
| GET | /api/customers | List customers | All |
| POST | /api/customers | Create customer | All |
| GET | /api/customers/{id} | Get customer | All |
| PUT | /api/customers/{id} | Update customer | All |
| DELETE | /api/customers/{id} | Delete customer | Owner |

### Orders

| Method | Endpoint | Description | Role |
|--------|----------|-------------|------|
| GET | /api/orders | List orders | All |
| POST | /api/orders | Create order | All |
| GET | /api/orders/{id} | Get order details | All |
| PATCH | /api/orders/{id}/status | Update status | All (cancel: Owner) |
| POST | /api/orders/{id}/cancel | Cancel order | Owner |

#### Create Order
```http
POST /api/orders
Authorization: Bearer {token}
X-Tenant-ID: 1
Content-Type: application/json

{
    "customer_id": 1,
    "items": [
        {"product_id": 1, "quantity": 2},
        {"product_id": 3, "quantity": 1}
    ]
}
```

### Reports

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | /api/reports/daily-sales?date=2026-01-10 | Daily sales summary |
| GET | /api/reports/top-products?start_date=X&end_date=Y | Top 5 selling products |
| GET | /api/reports/low-stock | Low stock report |

### Staff Management (Owner only)

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | /api/staff | List staff members |
| POST | /api/staff | Create staff user |
| DELETE | /api/staff/{id} | Delete staff user |

## Key Design Decisions

### 1. Single Database Multi-Tenancy
**Decision**: Use tenant_id column instead of separate databases.
**Rationale**: Simpler to implement and maintain, adequate for expected scale, can be migrated to separate databases if needed.

### 2. Global Scope for Tenant Filtering
**Decision**: Use Eloquent Global Scope for automatic tenant filtering.
**Rationale**: Prevents accidental data leakage, reduces boilerplate code, ensures consistent behavior.

### 3. Database Transactions for Orders
**Decision**: Wrap all order operations in database transactions with pessimistic locking.
**Rationale**: Ensures data consistency, prevents race conditions in stock management, allows atomic operations.

### 4. Policy-Based Authorization
**Decision**: Use Laravel Policies instead of in-controller authorization.
**Rationale**: Separation of concerns, reusable authorization logic, easier testing, meets requirement of no hard-coded auth in controllers.

### 5. Service Layer for Complex Operations
**Decision**: Use service classes (OrderService, ReportService) for business logic.
**Rationale**: Keeps controllers thin, improves testability, centralizes business rules.

### 6. Enum Classes for Status Values
**Decision**: Use PHP 8.1 enums for UserRole and OrderStatus.
**Rationale**: Type safety, IDE support, centralized status management.

## Performance Considerations

### Database Indexing
The following indexes are applied for optimal query performance:

```sql
-- Products
INDEX (tenant_id)
INDEX (sku)
INDEX (stock_quantity)
UNIQUE (tenant_id, sku)

-- Orders
INDEX (tenant_id)
INDEX (customer_id)
INDEX (status)
INDEX (created_at)
INDEX (tenant_id, status)
INDEX (tenant_id, created_at)

-- Order Items
INDEX (order_id)
INDEX (product_id)
```

### Eager Loading
All list endpoints use eager loading to prevent N+1 queries:
```php
Order::with(['customer', 'items.product'])->paginate();
```

### Query Optimization
- Reports use raw SQL with aggregations for efficiency
- Pagination on all list endpoints
- Selective column loading where appropriate

### Why These Decisions Matter
1. **Composite indexes** on (tenant_id, field) allow efficient filtered queries
2. **Eager loading** reduces database round trips from O(n) to O(1)
3. **Raw aggregations** in reports prevent loading all records into memory

## Security Measures

### 1. Authentication
- Laravel Sanctum for API token authentication
- Tokens are hashed in database
- Automatic token revocation on logout

### 2. Authorization
- Policy-based access control
- Role checks via Gates
- Tenant isolation at query level

### 3. Input Validation
- Form Request classes for all inputs
- Type validation and sanitization
- SQL injection prevention via Eloquent

### 4. Rate Limiting
- 60 requests/minute for authenticated users
- 5 requests/minute for login attempts

### 5. Error Handling
- Custom exception handler for API responses
- No sensitive data exposed in error messages
- Consistent JSON error format

### 6. Mass Assignment Protection
- Explicit $fillable arrays on all models
- No use of $guarded = []

## Response Format

All API responses follow a consistent format:

### Success Response
```json
{
    "success": true,
    "message": "Operation successful",
    "data": { ... }
}
```

### Error Response
```json
{
    "success": false,
    "message": "Error description",
    "errors": { ... }
}
```

### Paginated Response
```json
{
    "success": true,
    "data": [...],
    "meta": {
        "current_page": 1,
        "last_page": 5,
        "per_page": 15,
        "total": 75
    }
}
```

## Testing

```bash
# Run all tests
php artisan test

# Run with coverage
php artisan test --coverage
```

## License

This project is created as a technical assessment submission.
