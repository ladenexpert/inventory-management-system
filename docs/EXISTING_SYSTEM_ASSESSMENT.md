# Existing System Assessment

## Scope

This document assesses the current Laravel application as it exists in this repository. It is documentation-only and intentionally does not propose schema refactors or business logic changes in code.

Assessment date: 2026-06-18  
Repository path: `c:\xampp\htdocs\inventory-management-system`

## Executive Summary

This is no longer a simple upstream inventory starter project. The current codebase is a Laravel 12 line-of-business application with these active business domains:

- Dashboard and analytics
- Product and inventory master data
- Batch and expiry tracking
- FEFO-assisted stock deduction
- Purchases and receiving
- POS and sales
- Finance categories, transactions, and printable cash-flow reporting
- User management and application settings
- Opening stock import

The inventory model has already evolved into a hybrid design:

- `products.quantity` stores denormalized aggregate stock
- `batches.available_quantity` is treated as the operational source for stock-on-hand
- `inventory_logs` stores movement history
- `sale_item_batches` stores batch-to-sale allocations

That means the system already depends on a ledger-like stock model, but it still carries compatibility behavior from the earlier aggregate-only design. This is the most important architectural fact discovered in the assessment.

## Platform Summary

### Laravel Version

- Laravel framework constraint: `^12.0`
- Installed Laravel version: `12.58.0`

Evidence:

- `composer.json`
- `php artisan --version`

### PHP Version

- Required PHP version: `^8.2`
- Local CLI PHP used during assessment: `8.2.12`

Evidence:

- `composer.json`
- `php -v`

### Composer Packages

Direct production packages:

- `laravel/framework`
- `laravel/tinker`
- `blade-ui-kit/blade-heroicons`
- `openspout/openspout`
- `power-components/livewire-powergrid`

Direct development packages:

- `laravel/breeze`
- `barryvdh/laravel-debugbar`
- `laravel/pail`
- `laravel/pint`
- `laravel/sail`
- `fakerphp/faker`
- `mockery/mockery`
- `nunomaduro/collision`
- `phpunit/phpunit`

Frontend packages:

- `alpinejs`
- `axios`
- `flatpickr`
- `tom-select`
- `tailwindcss`
- `vite`
- `laravel-vite-plugin`

### Authentication Method

Authentication is Laravel Breeze style session authentication:

- guard: `web`
- driver: `session`
- provider: `eloquent`
- password reset: enabled via `password_reset_tokens`
- registration/login/reset routes: present
- email verification routes: present
- route protection: main app is wrapped in `auth` and `verified`

Important nuance:

- `User` does **not** implement `MustVerifyEmail`
- therefore the presence of `verified` middleware does not give real enforced email verification behavior for the main business area

Evidence:

- `config/auth.php`
- `routes/auth.php`
- `routes/web.php`
- `app/Models/User.php`
- `app/Http/Controllers/Auth/*`

### Authorization / Permission State

There is a user management module, but there is no real role-permission subsystem in the codebase:

- no Spatie permission package
- no roles table
- no permissions table
- no policies or gates used for business modules
- several ownership checks are commented out in controllers

Practical conclusion:

- current access control is authenticated-user based, not permission-driven

## Folder Structure

### Top-Level

- `app/` application code
- `bootstrap/`
- `config/`
- `database/`
- `public/`
- `resources/`
- `routes/`
- `storage/`
- `tests/`
- `vendor/`

### `app/`

- `Console/` console command for finance sync
- `DTOs/` request-to-domain transfer objects
- `Enums/` status and filter enums
- `Exceptions/` domain exceptions
- `Helpers/` money formatting helper
- `Http/Controllers/` MVC controllers and AJAX endpoints
- `Http/Requests/` request validation
- `Livewire/` table/form/detail components by module
- `Models/` Eloquent models
- `Services/` business logic layer
- `View/Components/` layout components

### `resources/views/`

Main functional directories:

- `auth/`
- `batches/`
- `categories/`
- `customers/`
- `finance/`
- `finance-categories/`
- `finance-transactions/`
- `products/`
- `profile/`
- `purchases/`
- `sales/`
- `settings/`
- `suppliers/`
- `units/`
- `users/`
- `livewire/`

### `database/`

- `migrations/` contains the full business schema
- `factories/`
- `seeders/`

### `routes/`

- `web.php` main business routes
- `auth.php` Breeze auth routes
- `console.php`

## Route Structure

### Main Route Guard

Most business routes are inside:

- middleware: `auth`, `verified`

### Route Groups

1. Dashboard and profile

- `/` redirect to `dashboard`
- `/dashboard`
- `/profile`

2. Master data under `/master`

- `/master/customers`
- `/master/suppliers`
- `/master/categories`
- `/master/units`
- `/master/products`
- `/master/products/import-opening-stock`
- `/master/products/import-opening-stock/template`
- `/master/batches`

3. Purchases

- resource routes under `/purchases`
- extra workflow routes:
  - `/purchases/{purchase}/ordered`
  - `/purchases/{purchase}/received`
  - `/purchases/{purchase}/paid`
  - `/purchases/{purchase}/cancel`
  - `/purchases/{purchase}/restore-draft`

4. Sales

- resource routes under `/sales` except `edit` and `update`
- extra workflow routes:
  - `/sales/{sale}/print`
  - `/sales/{sale}/complete`
  - `/sales/{sale}/restore`

5. Finance

- `/finance/categories`
- `/finance/transactions`
- `/finance/transactions/print/{printId}`

6. Users and settings

- `/users`
- `/settings`

7. Internal AJAX endpoints under `/ajax`

- product search
- supplier search
- customer search and inline customer create
- category search
- unit search
- user search
- finance category search
- batch lookup for manual sales allocation

### Route Risk Found During Assessment

`php artisan route:list --except-vendor` fails because `routes/web.php` references:

- `\App\Http\Http\Controllers\Api\FinanceCategoryController::class`

The real controller namespace is:

- `\App\Http\Controllers\Api\FinanceCategoryController::class`

This does not stop the application from loading the file itself, but it breaks route reflection tooling and is a real maintenance risk.

## Installed Functional Modules

### Present and Active

- Dashboard / Analytics
- Inventory
- Batch / Lot
- FEFO
- Procurement / Purchase
- Inbound / Receiving
- POS / Sales
- Invoice
- Finance Ledger
- User
- Settings

### Partial / Limited

- Import / Export
  - import exists for product opening stock via spreadsheet files
  - export exists mainly through Livewire PowerGrid export for some tables
  - there is no generalized import/export subsystem

### Absent or Not Yet Implemented

- Company / tenant model
- Warehouse model
- Warehouse stock dimension
- Permission matrix / role system
- Multi-location transfer flows
- Returns workflow
- Purchase receiving quantity splits or partial receipts
- Invoice settlement beyond simple paid status

## Business Flow Summary

### 1. Stock In Flow

Primary stock-in paths:

1. Product opening balance
   - product is created through `ProductService`
   - if opening quantity > 0, `BatchService::createManualInboundBatch()` creates a batch
   - source = `opening_balance`
   - product quantity is synchronized from batch totals

2. Product manual quantity adjustment
   - product update can increase stock
   - `BatchService::adjustProductQuantity()` creates an inbound batch
   - source = `adjustment_in`

3. Purchase receiving
   - purchase is created in `draft`
   - can be marked `ordered`
   - when marked `received`, each purchase item generates a batch
   - source = `purchase`
   - movement logged as `purchase_receive`
   - product quantity and average cost are recalculated

4. Legacy stock migration compatibility
   - migration `2026_04_24_000006_seed_legacy_batches_from_products_table.php`
   - creates `legacy_sync` batches for existing aggregate stock

### 2. Stock Out Flow

Primary stock-out path is sales:

1. user creates sale from POS
2. `SaleService::createSale()` locks products
3. stock is deducted through `BatchService::reserveBatches()`
4. if manual batch allocations are sent, those allocations are respected
5. otherwise the system auto-selects batches using FEFO ordering
6. `sale_item_batches` stores exact consumed batch layers
7. `inventory_logs` stores `sale_out` movements
8. product quantity is synchronized back from remaining batch balances

If sale is cancelled:

- `sale_cancel_restore` movements restore availability to batches

If cancelled sale is restored:

- `sale_restore_out` movements reserve the old batch layers again if still available

### 3. Batch Flow

Batch lifecycle:

1. batch originates from purchase receipt, opening balance, manual adjustment, or legacy sync
2. each batch stores:
   - product
   - optional purchase references
   - batch number
   - expiry date
   - received timestamp
   - unit cost
   - selling price snapshot
   - original quantity
   - available quantity
   - source
3. sales consume `available_quantity`
4. dashboard and monitoring rely on remaining active batches

### 4. FEFO Flow

Automatic FEFO behavior is implemented in `BatchService::reserveBatches()`:

- filter active batches with `available_quantity > 0`
- order batches by:
  1. batches with expiry first
  2. earliest `expiry_date`
  3. earliest `received_at`
  4. lowest `id`

Manual override exists in POS:

- the sales UI can switch a line to manual batch allocation
- manual allocations must exactly equal requested item quantity

### 5. Purchase Flow

Purchase status lifecycle:

- `draft`
- `ordered`
- `received`
- `paid`
- `cancelled`

Observed rules:

- create purchase always starts as `draft`
- only `draft` or `ordered` can be edited
- receive requires invoice number and proof image
- paying requires invoice number and proof image
- receiving creates stock batches
- paying creates finance expense entry
- cancelling is blocked after `received` or `paid`

### 6. POS / Sales Flow

Sales status lifecycle:

- `pending`
- `completed`
- `cancelled`

Observed rules:

- sales are created from a POS screen
- invoice number is auto-generated
- line price is stored as a snapshot from request data
- line discounts and global discount are supported
- payment method supports `cash` and `transfer`
- `completed` sales create finance income entries
- `pending` sales reserve stock already, but do not create finance entries until completion
- cancelled sales restore stock and void finance entries

### 7. Finance Ledger Flow

Finance ledger has two transaction types:

1. Auto-generated transactions
   - sales create income rows
   - paid purchases create expense rows
   - source linked through polymorphic `reference_type` + `reference_id`

2. Manual transactions
   - created directly from finance module
   - editable and deletable only when `reference_type` is null

There is also a console sync command:

- `php artisan finance:sync`

This backfills ledger entries for:

- completed sales
- paid purchases

### 8. Invoice Flow

Invoice behavior is functional but lightweight:

- sales invoice numbers are auto-generated as `INV.YYMMDD.XXXX`
- purchases store an external invoice number provided by user
- sales have printable invoice view
- purchases have invoice/reference fields but no dedicated PDF/document pipeline
- finance transactions use invoice numbers as `external_reference`

## Database Summary

### Major Business Tables

Master/reference:

- `users`
- `customers`
- `suppliers`
- `units`
- `categories`
- `settings`

Inventory:

- `products`
- `batches`
- `inventory_logs`

Procurement:

- `purchases`
- `purchase_items`

Sales / POS:

- `sales`
- `sale_items`
- `sale_item_batches`

Finance:

- `finance_categories`
- `finance_transactions`

Framework/runtime:

- `password_reset_tokens`
- `sessions`
- `cache`
- `cache_locks`
- `jobs`
- `job_batches`
- `failed_jobs`

### Relationship Summary

- `categories 1..n products`
- `units 1..n products`
- `suppliers 1..n purchases`
- `users 1..n purchases` via `created_by`
- `users 1..n sales` via `created_by`
- `customers 1..n sales`
- `products 1..n purchase_items`
- `products 1..n sale_items`
- `products 1..n batches`
- `products 1..n inventory_logs`
- `purchases 1..n purchase_items`
- `purchases 1..n batches`
- `sales 1..n sale_items`
- `sales 1..n inventory_logs`
- `sale_items 1..n sale_item_batches`
- `batches 1..n sale_item_batches`
- `finance_categories 1..n finance_transactions`
- `finance_transactions morphTo reference` for `Sale` or `Purchase`

### Inventory-Related Tables

- `products`
- `batches`
- `inventory_logs`
- `sale_item_batches`
- `purchase_items`
- `sale_items`

### Purchase-Related Tables

- `suppliers`
- `purchases`
- `purchase_items`
- `batches`
- `finance_transactions` when purchase becomes paid

### POS-Related Tables

- `customers`
- `sales`
- `sale_items`
- `sale_item_batches`
- `batches`
- `inventory_logs`
- `finance_transactions` when sale becomes completed

### Finance-Related Tables

- `finance_categories`
- `finance_transactions`
- `settings` for opening balance and store identity used in reports

## Technical Risk Summary

### 1. Stock Calculation Risks

- Stock is stored in two places:
  - `products.quantity`
  - `SUM(batches.available_quantity)`
- The code tries to keep them synchronized, but duplication itself is a risk.
- `pending` sales already reserve stock, which may surprise finance or operations users if they think only completed sales should affect availability.
- Product update can change stock by adjustment logic from the product form, which mixes master-data editing with inventory movements.

### 2. Batch Risks

- Batch numbers are validated in application code, not as workflow-specific scoped uniqueness rules.
- Purchase edit does full delete-and-recreate of items while batches are created later on receipt, which is safe only while status stays pre-receipt.
- Legacy synchronization created synthetic batches, which means some old stock layers may not reflect true historical batch provenance.

### 3. FEFO Risks

- FEFO is based only on `expiry_date`, `received_at`, and `id`; there is no warehouse/location dimension.
- Manual batch allocation can override FEFO and depends on client-side correctness plus server-side total validation.
- No explicit block prevents selecting expired batches in manual mode; expired status is exposed in the payload but not rejected by service logic.

### 4. Finance Risks

- Finance ledger is derivative, not a full double-entry accounting system.
- Auto-generated finance rows are deleted when source is voided; there is no immutable audit ledger behavior.
- `FinanceTransactionService::generateTransactionCode()` uses random suffixes instead of a sequential accounting number.
- The finance print report contains an encoding artifact in fallback period text (`â€”`), indicating a presentation/data-quality issue.

### 5. Performance Risks

- Dashboard heavily caches aggregates, but cache invalidation is not explicit in the domain services, so freshness depends on short TTLs.
- Livewire PowerGrid tables load related data and exports can grow large on bigger datasets.
- Product search and batch search are AJAX driven and currently single-dimension; future warehouse/company growth will increase query complexity.

### 6. Naming / Consistency Risks

- Route namespace typo:
  - `App\Http\Http\Controllers\Api\FinanceCategoryController`
- Mixed terminology:
  - `SalesController` plural vs `PurchaseController` singular
  - `finance-categories` view folder vs `FinanceCategory` model
  - `/master/*` for many CRUD pages but `/purchases` and `/sales` at root
- Some comments refer to admin/creator authorization, but code is commented out
- Finance category seed names differ from runtime auto-generated names:
  - seeded examples include Indonesian business categories
  - service auto-creates English categories `Product Sales` and `Product Purchases`

### 7. Dependency / License Risks

- Composer packages are standard MIT/BSD-style Laravel ecosystem packages, but formal license review was not performed in this assessment.
- `openspout/openspout` is now operationally important because opening stock import depends on it.
- `livewire-powergrid` is central to multiple admin list screens; future upgrades should be regression-tested carefully.

### 8. Security / Access Risks

- No role-permission system exists.
- Several ownership checks are commented out in sales and purchase controllers.
- `verified` middleware suggests email-verification enforcement, but `User` does not implement `MustVerifyEmail`.

## Recommendation For Next Sprint

### Recommended Milestone

`Inventory Ledger Stabilization`

### Why This Is The Best Next Step

Based on the actual codebase, the system already has a non-trivial inventory ledger design:

- batches drive actual available stock
- product quantity is a derived aggregate
- inventory logs are the movement history
- finance and dashboard reporting already depend on stock behavior being correct

Adding multi-company or warehouse dimensions before stabilizing this stock core would multiply risk because every stock movement would need new scoping while the current single-scope model still has synchronization and audit weaknesses.

### What This Recommendation Means Conceptually

This is **not** implementation guidance for this sprint. It means the next development sprint should first make the inventory ledger rules explicit and reliable before broadening the data model.

Highest-value targets for that future sprint would likely include:

- confirming one authoritative stock source
- hardening movement audit rules
- aligning pending/completed reservation semantics
- clarifying how manual product stock edits should behave
- validating expired-batch sale behavior

### Secondary Recommendation After That

`Batch/FEFO Stabilization`

This should likely follow immediately after inventory ledger stabilization, because FEFO currently relies on the same batch state consistency.

## Final Assessment Snapshot

### System Summary

- Laravel 12 + PHP 8.2 business app
- inventory, purchase, sales, finance, dashboard, settings, and user modules are active
- batch tracking and FEFO are already implemented
- finance ledger is integrated with purchase and sales status changes

### Module Summary

- strong: inventory, purchase, POS/sales, batch monitoring, finance transactions
- moderate: dashboard analytics, settings, customer/supplier/product master data
- weak/partial: permission model, generalized import/export, audit immutability, warehouse/company dimensions

### Database Summary

- core business schema revolves around products, purchases, sales, batches, inventory logs, and finance transactions
- stock history is layered on top of the original aggregate product quantity model

### Risk Summary

- biggest risks are stock synchronization, batch integrity, FEFO edge cases, weak access control, and derivative finance behavior

### Next Sprint Recommendation

- Recommend: `Inventory Ledger Stabilization`
- Do not implement it yet in this sprint; use this assessment as the factual baseline for planning
