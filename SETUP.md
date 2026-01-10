# Setup Guide - Multi-Tenant POS Backend System

This guide provides detailed step-by-step instructions to set up and run the Multi-Tenant POS Backend System.

## Prerequisites

Before starting, ensure you have the following installed:

| Requirement | Version | Check Command |
|-------------|---------|---------------|
| PHP | 8.2 or higher | `php -v` |
| Composer | Latest | `composer -V` |
| SQLite / MySQL / PostgreSQL | Any recent version | `sqlite3 --version` |

### Installing Prerequisites

**macOS (using Homebrew):**
```bash
brew install php@8.2 composer sqlite
```

**Ubuntu/Debian:**
```bash
sudo apt update
sudo apt install php8.2 php8.2-sqlite3 php8.2-mbstring php8.2-xml php8.2-curl composer
```

**Windows:**
- Download PHP from https://windows.php.net/download
- Download Composer from https://getcomposer.org/download/

## Installation Steps

### Step 1: Clone or Download the Project

```bash
cd /path/to/your/projects
git clone <repository-url> technical-assessment
cd technical-assessment
```

Or if you already have the project:
```bash
cd /Users/tariqulislamtuhin/Desktop/technical-assessment
```

### Step 2: Install PHP Dependencies

```bash
composer install
```

This will install all required packages including:
- Laravel Framework
- Laravel Sanctum (authentication)
- PHPUnit (testing)
- Laravel Pint (code formatting)

### Step 3: Environment Configuration

1. **Copy the example environment file:**
```bash
cp .env.example .env
```

2. **Generate application key:**
```bash
php artisan key:generate
```

3. **Configure the database** (choose one option):

**Option A: SQLite (Recommended for development)**
```bash
# Create the database file
touch database/database.sqlite
```

Update `.env`:
```env
DB_CONNECTION=sqlite
DB_DATABASE=/absolute/path/to/technical-assessment/database/database.sqlite
```

**Option B: MySQL**
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=pos_system
DB_USERNAME=root
DB_PASSWORD=your_password
```

**Option C: PostgreSQL**
```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=pos_system
DB_USERNAME=postgres
DB_PASSWORD=your_password
```

### Step 4: Run Database Migrations

```bash
php artisan migrate
```

This creates the following tables:
- `tenants` - Business/tenant information
- `users` - User accounts with roles
- `personal_access_tokens` - Sanctum tokens
- `products` - Product inventory
- `customers` - Customer records
- `orders` - Order headers
- `order_items` - Order line items

### Step 5: Seed Demo Data (Optional but Recommended)

```bash
php artisan db:seed
```

This creates:
- **Demo Tenant**: "Demo Business" (ID: 1)
- **Owner Account**: owner@demo.com / password
- **Staff Account**: staff@demo.com / password
- **10 Sample Products** with random stock
- **5 Sample Customers**

## Running the Application

### Start the Development Server

```bash
php artisan serve
```

The API will be available at: `http://127.0.0.1:8000`

### Verify Installation

Test the health of your installation:

```bash
curl http://127.0.0.1:8000/api/auth/register \
  -H "Content-Type: application/json" \
  -d '{"business_name":"Test","name":"Test User","email":"test@test.com","password":"password123","password_confirmation":"password123"}'
```

You should receive a JSON response with user data and a token.

## Demo Credentials

After running `php artisan db:seed`:

| Role | Email | Password | Tenant ID |
|------|-------|----------|-----------|
| Owner | owner@demo.com | password | 1 |
| Staff | staff@demo.com | password | 1 |

## Quick API Test

### 1. Login as Owner

```bash
curl -X POST http://127.0.0.1:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -H "X-Tenant-ID: 1" \
  -d '{"email":"owner@demo.com","password":"password"}'
```

Save the `token` from the response.

### 2. List Products

```bash
curl http://127.0.0.1:8000/api/products \
  -H "Authorization: Bearer YOUR_TOKEN_HERE" \
  -H "X-Tenant-ID: 1"
```

### 3. Create a Product (Owner only)

```bash
curl -X POST http://127.0.0.1:8000/api/products \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN_HERE" \
  -H "X-Tenant-ID: 1" \
  -d '{"name":"New Product","sku":"NEW-001","price":29.99,"stock_quantity":50}'
```

## Running Tests

### Run All Tests

```bash
php artisan test
```

Expected output:
```
PASS  Tests\Feature\AuthenticationTest
  ✓ user can register new business
  ✓ user can login with valid credentials
  ✓ login requires tenant id header
  ✓ login fails with invalid credentials
  ✓ authenticated user can get profile

PASS  Tests\Feature\ProductTest
  ✓ can list products
  ✓ owner can create product
  ✓ staff cannot create product
  ✓ sku must be unique per tenant
  ✓ same sku allowed for different tenants

PASS  Tests\Feature\OrderTest
  ✓ can create order
  ✓ cannot create order with insufficient stock
  ✓ owner can cancel order
  ✓ staff cannot cancel order
  ✓ can update order status

PASS  Tests\Feature\TenantIsolationTest
  ✓ tenant cannot see other tenant products
  ✓ tenant cannot see other tenant customers
  ✓ tenant cannot access other tenant product
  ✓ invalid tenant id returns error
  ✓ inactive tenant cannot access api

Tests:    22 passed
```

### Run Specific Test File

```bash
php artisan test tests/Feature/AuthenticationTest.php
```

### Run with Coverage

```bash
php artisan test --coverage
```

## Code Quality

### Run Linter (Laravel Pint)

```bash
./vendor/bin/pint
```

### Check for Issues Without Fixing

```bash
./vendor/bin/pint --test
```

## Troubleshooting

### Common Issues

#### 1. "Class not found" errors
```bash
composer dump-autoload
```

#### 2. Permission denied on storage
```bash
chmod -R 775 storage bootstrap/cache
```

#### 3. SQLite database locked
Ensure only one process accesses the database at a time. For concurrent testing:
```bash
php artisan config:clear
```

#### 4. Migration errors
```bash
php artisan migrate:fresh --seed
```
**Warning**: This will delete all data.

#### 5. Token not working
Ensure you're including both headers:
- `Authorization: Bearer YOUR_TOKEN`
- `X-Tenant-ID: 1`

### Reset Everything

To start fresh:
```bash
php artisan migrate:fresh --seed
php artisan cache:clear
php artisan config:clear
```

## API Headers Reference

| Header | Required | Description |
|--------|----------|-------------|
| `Content-Type` | For POST/PUT | `application/json` |
| `Authorization` | Yes (except register) | `Bearer {token}` |
| `X-Tenant-ID` | Yes (except register) | Tenant ID (integer) |
| `Accept` | Recommended | `application/json` |

## Project Structure Overview

```
technical-assessment/
├── app/
│   ├── Enums/              # PHP enums (UserRole, OrderStatus)
│   ├── Http/
│   │   ├── Controllers/Api/  # API controllers
│   │   ├── Middleware/       # ResolveTenant middleware
│   │   ├── Requests/         # Form request validation
│   │   └── Resources/        # API resources
│   ├── Models/              # Eloquent models
│   ├── Policies/            # Authorization policies
│   ├── Scopes/              # TenantScope for isolation
│   ├── Services/            # OrderService, ReportService
│   └── Traits/              # BelongsToTenant trait
├── database/
│   ├── factories/           # Model factories for testing
│   ├── migrations/          # Database migrations
│   └── seeders/             # Demo data seeders
├── routes/
│   └── api.php              # API route definitions
├── tests/
│   └── Feature/             # Feature tests
├── .env                     # Environment configuration
├── postman_collection.json  # Postman API collection
└── README.md                # Full documentation
```

## Using Postman

1. Open Postman
2. Click **Import** button
3. Select `postman_collection.json` from the project root
4. The collection includes all endpoints with demo credentials

## Next Steps

1. Review the full API documentation in `README.md`
2. Import the Postman collection for easy API testing
3. Explore the codebase starting with `routes/api.php`
4. Run the test suite to understand expected behaviors

## Support

For issues with this project, check:
1. Laravel documentation: https://laravel.com/docs
2. Laravel Sanctum: https://laravel.com/docs/sanctum
