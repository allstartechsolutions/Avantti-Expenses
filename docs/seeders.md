# Database Seeders

This document describes all database seeders in the application and how to run them.

---

## Available Seeders

### 1. DatabaseSeeder

**File:** `database/seeders/DatabaseSeeder.php`

**Description:** The main seeder that orchestrates other seeders. It automatically calls essential seeders for production and includes development-only data.

**What it does:**
- Calls `RoleSeeder` (production)
- Calls `CatalogCategorySeeder` (production)
- Creates a test user (local/development/testing only):
  - Email: `test@example.com`
  - Password: `password`

---

### 2. RoleSeeder

**File:** `database/seeders/RoleSeeder.php`

**Description:** Creates the default user roles for the application.

**Roles created:**
| Role | Description |
|------|-------------|
| `admin` | Administrator with full system access |
| `manager` | Manager with elevated permissions |
| `employee` | Standard employee user |

**Safe to re-run:** Yes (uses `firstOrCreate`)

---

### 3. CatalogCategorySeeder

**File:** `database/seeders/CatalogCategorySeeder.php`

**Description:** Creates default categories for catalog items (products, services, and rentals).

**Categories created:**

**Product Categories:**
- Hardware & Fasteners
- Lumber & Building Materials
- Electrical Supplies
- Plumbing Supplies
- Paint & Finishing
- Tools & Equipment
- Safety Equipment

**Service Categories:**
- General Labor
- Skilled Labor
- Specialized Services
- Subcontractor Services
- Professional Services

**Rental Categories:**
- Heavy Equipment
- Power Tools
- Scaffolding & Ladders
- Vehicles

**Safe to re-run:** Yes (uses `firstOrCreate`)

---

### 4. DefaultSupplierSeeder

**File:** `database/seeders/DefaultSupplierSeeder.php`

**Description:** Creates a default "General Supplier" for catalog items that don't have a specific preferred supplier.

**Supplier created:**
| Field | Value |
|-------|-------|
| Name | General Supplier |
| Street | N/A |
| City | N/A |
| State | N/A |
| Postal Code | 00000 |
| Country | Based on `config('app.country')` |
| Description | Default supplier for items without a specific preferred supplier |

**Safe to re-run:** Yes (uses `firstOrCreate`)

---

## How to Run Seeders

### Run All Seeders (DatabaseSeeder)

```bash
php artisan db:seed
```

This runs the `DatabaseSeeder` which calls `RoleSeeder` and `CatalogCategorySeeder`.

### Run a Specific Seeder

```bash
php artisan db:seed --class=SeederName
```

**Examples:**

```bash
# Run only RoleSeeder
php artisan db:seed --class=RoleSeeder

# Run only CatalogCategorySeeder
php artisan db:seed --class=CatalogCategorySeeder

# Run only DefaultSupplierSeeder
php artisan db:seed --class=DefaultSupplierSeeder
```

### Run Multiple Specific Seeders

Run them one at a time:

```bash
php artisan db:seed --class=RoleSeeder
php artisan db:seed --class=DefaultSupplierSeeder
```

---

## Initial Setup

For a fresh installation, run:

```bash
php artisan migrate
php artisan db:seed
php artisan db:seed --class=DefaultSupplierSeeder
```

**Note:** `DefaultSupplierSeeder` is not included in `DatabaseSeeder` by default, so it needs to be run separately.

---

## Important Notes

1. **All seeders use `firstOrCreate`** - This means they are safe to run multiple times without creating duplicate records.

2. **Never use `migrate:fresh` or `migrate:refresh`** in production - These commands will delete all data.

3. **Test user is environment-specific** - The test user (`test@example.com`) is only created in `local`, `development`, or `testing` environments.
