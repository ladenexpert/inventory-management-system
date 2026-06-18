# Module Map

## Purpose

This file maps the current functional modules to their main routes, code locations, and status in the repository.

Status legend:

- `Active`: implemented and used
- `Partial`: implemented in limited form
- `Absent`: not implemented as a distinct module

## Module Overview

| Module | Status | Notes |
|---|---|---|
| Dashboard / Analytics | Active | Livewire dashboard with cached sales, cash flow, inventory valuation, low-stock, top-products, top-customers, and batch expiry alerts |
| Inventory | Active | Product master data, quantity maintenance, opening stock import, stock adjustment through product updates |
| Batch / Lot | Active | Dedicated `batches` table, monitoring UI, batch allocation bridge, inventory logs |
| FEFO | Active | Automatic batch deduction ordered by expiry date; manual override available in POS |
| Procurement / Purchase | Active | Purchase lifecycle from draft to paid |
| Inbound / Receiving | Active | Receiving is embedded in purchase workflow, not a separate module |
| POS / Sales | Active | POS UI, cart, customer picker, payment, print, pending/completed status |
| Invoice | Partial | Sales invoice generation and print exist; no standalone invoicing subsystem |
| Finance Ledger | Active | Manual and auto-generated transactions, printable selected transaction set |
| Import / Export | Partial | Opening stock import exists; some PowerGrid exports exist; no centralized import/export module |
| User / Permission | Partial | User management exists; permission system does not |
| Settings | Active | Store identity, opening balance, and currency settings |
| Multi Company | Absent | No company scope in routes, schema, or models |
| Warehouse | Absent | No warehouse/location scope in routes, schema, or models |

## 1. Dashboard / Analytics

Primary files:

- `routes/web.php`
- `app/Http/Controllers/DashboardController.php`
- `app/Livewire/Dashboard/Dashboard.php`
- `app/Services/DashboardStatsService.php`
- `resources/views/dashboard.blade.php`
- `resources/views/livewire/dashboard/dashboard.blade.php`

Responsibilities:

- period-based sales stats
- gross profit using `sale_items.total_cost`
- cash flow stats
- inventory valuation
- low stock alert
- batch expiry alert
- sales trend / cash flow trend / expense breakdown

## 2. Inventory

Primary files:

- `app/Models/Product.php`
- `app/Services/ProductService.php`
- `app/Services/ProductOpeningStockImportService.php`
- `app/Http/Controllers/ProductOpeningStockImportController.php`
- `app/Livewire/Products/*`
- `resources/views/products/index.blade.php`
- `resources/views/products/import-opening-stock.blade.php`

Supporting master-data modules:

- categories: `app/Livewire/Categories/*`
- units: `app/Livewire/Units/*`

Observed scope:

- product CRUD
- SKU and IERP item code support
- quantity, min stock, active flag
- opening stock import from XLSX/CSV/ODS
- stock increase/decrease through product edits

## 3. Batch / Lot

Primary files:

- `app/Models/Batch.php`
- `app/Models/InventoryLog.php`
- `app/Models/SaleItemBatch.php`
- `app/Services/BatchService.php`
- `app/Livewire/Batches/BatchTable.php`
- `app/Http/Controllers/Api/BatchController.php`
- `resources/views/batches/index.blade.php`

Observed scope:

- batch creation from purchase receipt
- opening-balance and adjustment batches
- legacy sync batches
- batch monitoring table
- expiry and source filters
- export to XLS/CSV through PowerGrid
- API for available batch selection in POS

## 4. FEFO

Primary files:

- `app/Services/BatchService.php`
- `app/Services/SaleService.php`
- `resources/views/sales/create.blade.php`
- `tests/Feature/BatchInventoryTest.php`

Observed scope:

- automatic stock deduction by earliest expiry
- fallback ordering by receive time and id
- manual batch allocation override in POS
- batch restoration on sale cancellation

## 5. Procurement / Purchase

Primary files:

- `app/Models/Purchase.php`
- `app/Models/PurchaseItem.php`
- `app/Services/PurchaseService.php`
- `app/Http/Controllers/PurchaseController.php`
- `app/Http/Requests/StorePurchaseRequest.php`
- `app/Http/Requests/UpdatePurchaseRequest.php`
- `app/Livewire/Purchases/PurchaseTable.php`
- `resources/views/purchases/*`

Workflow states:

- `draft`
- `ordered`
- `received`
- `paid`
- `cancelled`

Observed scope:

- purchase create/edit/delete in allowed states
- supplier selection
- invoice number and proof image handling
- receiving and payment transitions

## 6. Inbound / Receiving

Status: `Active`, but embedded inside purchase workflow.

Primary files:

- `app/Http/Controllers/PurchaseController.php`
- `app/Services/PurchaseService.php`
- `resources/views/purchases/show.blade.php`

Observed scope:

- receipt confirmation modal on purchase detail page
- validates invoice number and proof image before receipt
- creates stock batches from purchase items

Conclusion:

- there is no separate receiving document or warehouse receiving module
- inbound is a purchase-status transition

## 7. POS / Sales

Primary files:

- `app/Models/Sale.php`
- `app/Models/SaleItem.php`
- `app/Services/SaleService.php`
- `app/Http/Controllers/SalesController.php`
- `app/Http/Requests/StoreSaleRequest.php`
- `app/Livewire/Sales/SalesTable.php`
- `resources/views/sales/create.blade.php`
- `resources/views/sales/index.blade.php`
- `resources/views/sales/show.blade.php`
- `resources/views/sales/print.blade.php`

Observed scope:

- POS transaction screen
- product search
- customer lookup and inline customer create
- manual or automatic batch allocation
- cash and transfer payment methods
- pending and completed sales
- printable invoice/receipt

## 8. Invoice

Status: `Partial`

Primary files:

- `app/Services/SaleService.php`
- `resources/views/sales/print.blade.php`
- `resources/views/sales/show.blade.php`
- `app/Models/Purchase.php`
- `resources/views/purchases/show.blade.php`

Observed scope:

- sales invoice numbers auto-generated
- purchase invoice number is manual external reference
- printable sales document exists

Not observed:

- invoice numbering for purchases generated by system
- receivables/payables subledger
- invoice settlement history
- tax invoice handling

## 9. Finance Ledger

Primary files:

- `app/Models/FinanceCategory.php`
- `app/Models/FinanceTransaction.php`
- `app/Services/FinanceTransactionService.php`
- `app/Console/Commands/SyncFinanceTransactions.php`
- `app/Http/Controllers/FinanceReportController.php`
- `app/Livewire/FinanceCategories/*`
- `app/Livewire/FinanceTransactions/*`
- `resources/views/finance-transactions/index.blade.php`
- `resources/views/finance-categories/index.blade.php`
- `resources/views/finance/reports/print.blade.php`

Observed scope:

- manual finance categories
- manual finance transactions
- auto-generated sale income
- auto-generated purchase expense
- printable selected cash-flow report
- sync command for backfill

## 10. Import / Export

Status: `Partial`

Import:

- opening stock import via `ProductOpeningStockImportController`
- file formats: `xlsx`, `csv`, `ods`
- parser: `openspout/openspout`

Export:

- `BatchTable` export to `xls` and `csv`
- `FinanceTransactionTable` export to `xls` and `csv`

Not observed:

- dedicated import/export module
- scheduled imports
- purchase/sales import
- accounting export formats

## 11. User / Permission

Status: `Partial`

User management files:

- `app/Models/User.php`
- `app/Livewire/Users/*`
- `resources/views/users/index.blade.php`
- `app/Http/Controllers/Api/UserController.php`

Authentication files:

- `routes/auth.php`
- `config/auth.php`
- `app/Http/Controllers/Auth/*`
- `resources/views/auth/*`

Observed scope:

- user registration/login/reset/profile
- user list and management UI

Not observed:

- roles
- permissions
- policy-driven access matrix
- branch/company-scoped user access

## 12. Settings

Primary files:

- `app/Models/Setting.php`
- `app/Livewire/Settings/*`
- `resources/views/settings/index.blade.php`
- `database/seeders/SettingSeeder.php`

Observed scope:

- store metadata
- opening balance date and amount
- currency formatting parameters

## Navigation Map

The main navigation confirms the intended top-level module map:

- Dashboard
- Sales
  - POS
  - Sales
  - Customers
- Purchases
  - Purchases
  - Suppliers
- Finance
  - Transactions
  - Categories
- Users
- Products
  - Products
  - Import Opening Stock
  - Batches
  - Categories
  - Units
- Profile
- Settings

Evidence:

- `resources/views/layouts/navigation.blade.php`

## Module Gaps Relevant To Future Planning

Important missing foundations discovered during assessment:

- no company dimension
- no warehouse dimension
- no permission matrix
- no stock transfer workflow
- no returns workflow
- no partial receiving workflow
- no immutable accounting ledger

These gaps matter because current purchase, sales, batch, and finance modules are already interconnected and would become harder to evolve safely without stabilizing core stock behavior first.
