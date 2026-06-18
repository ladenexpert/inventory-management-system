# RNI Pilot Guide

## Purpose

This guide explains the day-to-day RNI workflow now available in RMP for the pilot rollout.

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
- user and settings management

### Formulator

- material usage
- inventory views
- reports
- dashboard

## Opening Stock

Menu path:

- `Materials` -> `Upload Opening Stock`

Steps:

1. Download the opening stock template.
2. Fill the RM master data and opening quantities.
3. Include opening batch numbers whenever opening quantity is greater than zero.
4. Upload the file.
5. Review the import summary for created, skipped, and failed rows.

Notes:

- Rows starting with `#` are ignored.
- Existing inventory rules still validate categories, units, and batch uniqueness.
- Zero-cost opening batches remain valid when operationally needed.

## Receiving

Menu path:

- `Material Receipt`

Steps:

1. Create a new material receipt.
2. Select supplier and receipt date.
3. Add one or more RM lines.
4. Enter batch number, expiry date, quantity, and unit cost for each received line.
5. Save the receipt as draft or continue the receipt lifecycle.
6. Confirm receipt from the detail page to move stock into active batches.

Notes:

- Receipt confirmation still uses the existing receiving engine.
- Batch creation and stock updates happen only through the receipt workflow.

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
- quantity
- expiry
- inventory value
- lifecycle status

Use filters and export from the same page when needed.

## Reports

Menu path:

- `Reports`

### Current Inventory Report

Columns:

- RM
- Batch
- Qty
- Expiry
- Value
- Status

### Usage History Report

Columns:

- Date
- RM
- Batch
- Qty
- Purpose
- Formula
- Project
- User

### Expiry Report

Columns:

- RM
- Batch
- Qty
- Expiry
- Status
- Days Remaining

All report pages support export through the existing table export flow.

## Dashboard

The RNI dashboard surfaces:

- total RM
- total batch
- low stock
- near expiry
- expired
- zero-cost batch
- recent material usage
- urgent batches
- top batch valuation

Use the dashboard as the daily starting point for monitoring pilot operations.
