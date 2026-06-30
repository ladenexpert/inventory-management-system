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

## v0.5.0 Pilot Readiness

Status:

- milestone: `v0.5.0-rni-pilot-readiness`
- state: implemented and automation-validated
- baseline: built on stable `v0.4.8.1-delete-refresh-and-aggregate-consistency-hotfix`
- owner state: pending owner browser-UAT, manual review, commit, push, and tag
- release note: Codex did not commit, tag, or push

Readiness checklist:

- `Opening Stock` available
- `Material Receipt` available
- `Material Usage` available
- manual batch picking remains default
- `Auto FEFO` remains optional
- `Inventory Monitoring` available through `Inventory & Expiry Monitoring`
- `Batch Monitoring` available
- `Inventory & Expiry Monitoring` available
- `Inventory Movement History` available
- `Usage Report` available
- `Inbound & Purchase Analysis` available
- `Sales Analysis` available
- `Stock Movement Classification` available
- `Dashboard` -> `RNI Operations` available
- `Stock Take` available
- `Stock Take` review / post / close available
- Stock Take stale guard preserved
- Stock Take finance exclusion preserved
- material delete guard available
- material with active stock cannot be deleted
- zero-stock material soft delete remains allowed
- material delete does not create inventory movement
- historical movement evidence remains available after soft delete
- dashboard/report refresh after delete/cancel/restore remains available
- admin-only value visibility preserved
- `RM Desk` and `Formulator` visibility remains permission-safe
- RNI finance side effects remain excluded
- Finance remains its own authorized dropdown/menu, not inside `Reports`
- `Batch Monitoring` export now requires `batches.export`
- report exports require `reports.export`
- non-admin exports remain free of valuation/cost fields
- PHP `8.2` compatibility preserved
- no migration introduced
- no new package introduced
- no Filament introduced

Owner browser-UAT quick pass:

1. verify the RNI navigation labels and menu grouping match the pilot terminology
2. verify `Admin RNI`, `RM Desk`, and `Formulator` each see only the allowed operational and export surfaces
3. verify `Batch Monitoring` export and report exports are blocked when the role lacks export permission
4. verify `Stock Take` still posts only `STK` movement evidence and never creates finance entries
5. verify zero-stock material delete refreshes current-state dashboards/reports immediately without creating a delete movement row

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

## Stock Take

Menu path:

- `Operations` -> `Stock Take`

Steps:

1. Download the Stock Take template.
2. Fill `SKU`, `Batch No`, and `Counted Qty`.
3. Optionally fill `Item Code`, `Material`, `Expiry`, `Storage Location`, `Reference Number`, and `Notes` as cross-check fields.
4. Upload the file to create a Stock Take session.
5. Review row status, `System Qty`, `Counted Qty`, and `Variance Qty`.
6. Recalculate the session if current stock changed after review.
7. Post the session to apply stock take adjustments.
8. Close the session after posting to lock the evidence.

Notes:

- Stock Take in v0.4.8 reconciles existing matched batches only.
- Unmatched or invalid rows remain visible as `error` evidence and block posting.
- If the current batch quantity changes after review, the session becomes stale and must be recalculated before posting.
- Positive variance increases the existing batch, negative variance reduces the existing batch, and zero variance keeps evidence without creating movement.
- Stock Take stays outside Material Receipt, Material Usage, Purchase, Sales, and Finance posting flows.
- Valuation remains admin-only and reporting-only. RNI stays non-valuated by default.

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
3. Manual batch selection is the default. `Auto FEFO` remains available as an option for automatic expiry-first issuing.
4. Fill:
   - usage date
   - purpose
   - formula
   - team
   - requested by
   - issued by
   - notes
5. Confirm the usage slip.
6. Print the usage slip after save.

Notes:

- Material usage reuses the existing stock deduction engine.
- Manual batch selection remains the default workflow.
- `Auto FEFO` remains available as an option for automatic expiry-first issuing.
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

Report menu:

- `Inventory & Expiry Monitoring`
- `Inventory Movement History`
- `Usage Report`
- `Inbound & Purchase Analysis`
- `Sales Analysis`
- `Stock Movement Classification`

### Inventory & Expiry Monitoring

This page consolidates the old inventory and expiry report views into one PowerGrid-backed report. The legacy expiry route still works, but it now renders the same consolidated experience with an expiry-focused preset.

Interactive behavior:

- global search
- sorting
- filters
- column toggle
- saved filter/sort/column state per user
- CSV/XLS export for users with `reports.export`

Columns:

- Item Code
- SKU
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
- Days Remaining

Filters:

- expiry date
- status
- storage location

Sensitive fields:

- `Inventory Value` is shown and exported only when the signed-in role has `inventory_value.view` or equivalent finance visibility.

### Inventory Movement History

This report remains on the existing manual Blade filter/export screen in this sprint to avoid a risky broad rewrite. Existing filters and exports remain available, but PowerGrid column-toggle persistence has not been added here yet.

Columns:

- Date & Time
- User
- Transaction Type
- Material / Product Name
- Item Code
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

### Usage Report

The old usage history / usage analysis naming is now aligned as `Usage Report`.

Interactive behavior:

- global search
- sorting
- filters
- column toggle
- saved filter/sort/column state per user
- CSV/XLS export for users with `reports.export`

Columns:

- Date
- SKU
- Item Code
- Material / Product Name
- Batch
- Expiry Date
- Storage Location
- Qty
- Unit
- Purpose
- Formula
- Team
- User

Usage detail and print views for `Formulator` and `RM Desk` remain operational, but cost/value fields are hidden unless the role has inventory value permission.

### Inbound & Purchase Analysis

The report now separates operational inbound visibility from financial purchase visibility.

Charts:

- inbound trend
- purchase trend

Permission notes:

- operational inbound counts and quantities can be shown without finance visibility
- purchase total and other monetary fields are shown only when the role has the correct finance or value permission
- export remains permission-safe even when the controller-based export cannot mirror every visible/hidden UI column choice

### Sales Analysis

The report now uses a chart-based trend section while keeping commercial sales data separate from RNI usage reporting.

Charts:

- sales trend

Permission notes:

- revenue, gross profit, customer spend, and financial sales totals require finance permission
- non-finance roles only see safe operational context and do not receive financial values in export output

### Stock Movement Classification

This report provides summary cards, a classification chart, and an exportable detail table for mutually exclusive stock movement buckets.

Classification precedence:

1. Dead Stock
2. Slow Moving
3. Fast Moving
4. Normal / Unclassified

Classification rules:

- `Fast Moving`: stock available is greater than zero and last outbound material usage is within the last 90 days
- `Slow Moving`: stock available is greater than zero, not Fast, not Dead, and last outbound material usage is more than 180 days ago
- `Dead Stock`: stock available is greater than zero and last outbound material usage is more than 365 days ago
- if stock exists but outbound usage has never happened, the report uses first stock / receipt date as the movement-age basis
- if that basis date is missing, the material is shown as `No Usage / Unclassified`

Detail fields:

- Classification
- Item Code
- SKU
- RM Name
- Physical Form
- Stock Available
- Unit
- Last Usage Date
- Days Since Last Usage
- Usage Qty 90 Days
- Usage Qty 180 Days
- Usage Qty 365 Days
- Batch Count
- Earliest Expiry Date
- Storage Location
- Inventory Value when permitted
- Status

All upgraded PowerGrid report pages support export through the report table export flow for users with `reports.export`. Export follows the active filters/search/sort where supported by PowerGrid, and unauthorized sensitive columns are not rendered or exported. Legacy manual exports remain permission-safe but do not fully mirror visible-column state in this sprint.

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
- purchase trend
- sales trend
- stock movement classification
- fast moving materials
- slow moving materials
- dead stock
- top suppliers
- top customers

Use `Dashboard` -> `RNI Operations` for operational monitoring and `Dashboard` -> `Business Insights` for management review when the role has permission to see sensitive metrics.

## Immediate Validation Behavior

- After confirming a material receipt or legacy purchase receipt, inventory and movement views reflect the change immediately.
- After issuing material usage, usage analysis and RNI dashboard usage metrics reflect the change immediately.
- After posting a Stock Take session, current inventory, batch monitoring, and inventory movement history reflect the variance immediately.
- After marking a legacy purchase as paid, Finance reflects the expense immediately.
- After completing a legacy sale / POS transaction, Finance and Sales Analysis reflect the income immediately.

## Item Code

- `Item Code` is shown across transaction lists, inventory lists, report screens, and exports.
- `Item Code` is the optional legacy IERP code, separate from the internal `SKU` / RMP code.
- If `item_code_ierp` is empty, the system shows `-`.

## Navigation

- The compact top navigation uses `Dashboard`, `Operations`, `Master Data`, `Reports`, `Finance`, and `Administration` when the related modules and permissions are enabled.
- Finance is separated from `Reports` again for finance-enabled users such as `admin_rni`.
- Menu visibility now follows the seeded role-permission matrix, but the underlying routes remain in place and are blocked server-side when the role lacks permission.
- Export visibility now follows the same permission model: `Batch Monitoring` requires `batches.export`, while report exports require `reports.export`.
