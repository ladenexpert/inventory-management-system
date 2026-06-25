# RNI Pilot Guide

## Purpose

This guide explains the day-to-day RNI workflow now available in RMP for the pilot rollout.

Environment note:

- The default application setup uses database-backed cache, sessions, and queue storage.
- Run `php artisan migrate --seed` or `php artisan migrate:fresh --seed` before commands such as `php artisan optimize:clear` on local, UAT, or production environments.

Core pilot flow:

1. Upload opening stock
2. Receive material
3. Search material
4. Issue material
5. Print usage slip
6. Monitor expiry
7. Export inventory report
8. Export usage report

Inventory ledger, batch allocation, FEFO behavior, and zero-cost batch handling continue to follow:

- `docs/INVENTORY_LEDGER_RULES.md`
- `docs/BATCH_POLICY.md`

## Roles

### Admin RNI

- full access
- opening stock upload
- material receipt
- material usage
- inventory views
- reports
- dashboard
- finance
- business insights
- user and settings management

### Formulator

- read-only usage monitoring
- inventory views
- reports
- dashboard
- export-enabled report access
- no finance access
- no inventory value access by default

### RM Desk

- create `Material Usage`
- view all usage records
- cancel or restore only usage created by themselves
- inventory views
- reports
- dashboard
- export-enabled report access
- no finance access
- no inventory value access by default

## Access Control Notes

- Navigation, direct URLs, action buttons, and shared lookup endpoints now follow the same role-permission rules.
- `Finance` and `Inventory Value` are permission-controlled. They are available to `Admin RNI` by default and hidden from `Formulator` and `RM Desk` by default.
- `Business Insights` is shown only to users who can access finance or inventory value data.
- `Formulator` can monitor usage but cannot create, confirm, cancel, restore, or delete usage transactions.
- `RM Desk` can create usage and can only cancel or restore usage where `sales.created_by` matches their own user id.

## Opening Stock

Menu path:

- `Materials` -> `Upload Opening Stock`

Steps:

1. Download the opening stock template.
2. Fill the RM master data and opening quantities.
3. Fill `physical_form`, optional `supplier`, and optional `storage_location` when available.
4. Include opening batch numbers whenever opening quantity is greater than zero.
5. Upload the file.
6. Review the import summary for created, skipped, and failed rows.

Notes:

- Rows starting with `#` are ignored.
- Legacy templates without the new RNI columns remain valid.
- Existing inventory rules still validate categories, units, and batch uniqueness.
- Zero-cost opening batches remain valid when operationally needed.

## Master Data Import

Menu paths:

- `Materials` -> `Import Excel`
- `Categories` -> `Import Excel`
- `Units` -> `Import Excel`
- `Suppliers` -> `Import Excel`
- `Customers` -> `Import Excel`
- `Storage Locations` -> `Import Excel`

Import flow:

1. Open the relevant master data page.
2. Click `Download Template`.
3. Fill the generated template columns only.
4. Upload the file through `Import Excel`.
5. Review the import summary for processed, created, skipped, and failed rows.

Notes:

- The framework accepts `.xlsx`, `.csv`, and `.ods`.
- Rows starting with `#` are ignored.
- The new `Materials` import creates master data only.
- Opening balances still use the separate `Upload Opening Stock` flow.

## Receiving

Menu path:

- `Material Receipt`

Steps:

1. Create a new material receipt.
2. Select supplier and receipt date. Supplier can be left blank for sample/internal RNI receipts.
3. Add one or more RM lines.
4. Search now includes all active materials, including zero-stock and newly created materials.
5. Enter batch number, expiry date, storage location, quantity, and unit cost for each received line.
6. Save the receipt as draft or continue the receipt lifecycle.
7. Confirm receipt from the detail page to move stock into active batches.

Notes:

- Receipt confirmation still uses the existing receiving engine.
- Batch creation and stock updates happen only through the receipt workflow.
- `Batch No.` remains unique in RMP. If the same supplier/manufacturer batch number is received again in a different receipt, add an internal suffix or reference such as `B240601-2`, `B240601-DN001`, or `B240601-20260625`.

## Material Usage

Menu path:

- `Material Usage` -> `Create Usage`

Steps:

1. Search and add the required raw materials.
2. Set the quantity for each line.
3. Leave allocation on `Auto FEFO` for automatic expiry-first issuing, or switch to manual batch selection when needed.
4. Fill:
   - usage date
   - purpose
   - formula
   - project
   - requested by
   - issued by
   - notes
5. Confirm the usage slip.
6. Print the usage slip after save.

Notes:

- Material usage reuses the existing stock deduction engine.
- FEFO behavior is preserved.
- Manual batch allocation is still validated against batch availability and expiry rules.
- Material usage does not create sales revenue records.
- Usage cost is now derived server-side from the reserved batch allocation. The browser no longer submits the authoritative cost snapshot.

## Batch Monitoring

Menu path:

- `Materials` -> `Batches`

The monitoring page highlights:

- active
- near expiry
- expired
- depleted
- quarantined
- zero cost

The table includes:

- batch
- RM
- physical form
- storage location
- quantity
- expiry
- inventory value when the signed-in role has permission
- lifecycle status

Use filters and export from the same page when needed.

## Reports

Menu path:

- `Reports`

### Current Inventory Report

Columns:

- Item Code IERP
- Material / Product Name
- Batch
- Unit
- Physical Form
- Supplier
- Storage Location
- Qty
- Expiry
- Value for users with permission
- Status

### Inventory Movement History

Columns:

- Date & Time
- User
- Transaction Type
- Material / Product Name
- Item Code IERP
- Lot Number
- Expiry Date
- Storage Location
- Quantity
- Unit
- Remaining Stock
- Reference
- Notes

Filters:

- date range
- user
- transaction type
- RM code
- RM name
- lot number

### Usage Analysis

Columns:

- Date
- Item Code IERP
- Material / Product Name
- Batch
- Expiry Date
- Storage Location
- Qty
- Unit
- Purpose
- Formula
- Project
- User

Usage detail and print views for `Formulator` and `RM Desk` remain operational, but cost/value fields are hidden unless the role has inventory value permission.

### Expiry Report

Columns:

- Item Code IERP
- Material / Product Name
- Batch
- Qty
- Unit
- Storage Location
- Expiry
- Status
- Days Remaining

All report pages support export through the existing table export flow.

## Dashboard

The dashboard now has two views.

### RNI Operations

Focus:

- total materials
- physical stock
- usable stock
- low stock
- near expiry
- expired
- zero-cost batches
- recent receipts
- recent usage
- top used materials
- urgent batches
- expiry risk

### Business Insights

Focus:

- inventory value
- inbound trend
- outbound trend
- purchase trend
- sales trend
- material consumption trend
- fast moving materials
- slow moving materials
- dead stock
- top suppliers
- top customers

Use `Dashboard` -> `RNI Operations` for operational monitoring and `Dashboard` -> `Business Insights` for management review when the role has permission to see sensitive metrics.

## Immediate Validation Behavior

- After confirming a material receipt or legacy purchase receipt, inventory and movement views reflect the change immediately.
- After issuing material usage, usage analysis and RNI dashboard usage metrics reflect the change immediately.
- After marking a legacy purchase as paid, Finance reflects the expense immediately.
- After completing a legacy sale / POS transaction, Finance and Sales Analysis reflect the income immediately.

## Item Code IERP

- `Item Code IERP` is shown across transaction lists, inventory lists, report screens, and exports.
- `Item Code IERP` is the optional legacy IERP code, separate from the internal `SKU` / RMP code.
- If `item_code_ierp` is empty, the system shows `-`.

## Navigation

- The compact top navigation uses `Dashboard`, `Operations`, `Master Data`, `Reports`, and `Administration`.
- Finance remains available inside `Reports` for finance-enabled admin users such as `admin_rni`.
- Menu visibility now follows the seeded role-permission matrix, but the underlying routes remain in place and are blocked server-side when the role lacks permission.
