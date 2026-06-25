# RNI Requirement Coverage

Source checked:

- `RNI Raw Material Room Inventory Management System Brief.pdf`
- current Laravel implementation in this repository as of `2026-06-18`

Status legend:

- `implemented`
- `partially implemented`
- `missing`

## Functional Coverage

| Requirement | Status | Current implementation | Recommended next action |
| --- | --- | --- | --- |
| Admin upload initial stock | implemented | `Products` opening stock import exists with Excel upload, template download, and batch creation. | Keep existing flow; expand template guidance for RNI-specific columns. |
| Admin add raw material | implemented | Raw materials are created through `Materials` master data and product form workflow. | None for pilot. |
| Admin stock in | implemented | `Material Receipt` wraps the purchase receiving engine and creates batches on receipt. | Keep RNI naming on receipt screens. |
| Admin edit raw material | implemented | Product/material edit flow exists and updates stock via the existing batch adjustment logic. | None for pilot. |
| Admin view transaction history | implemented | `Reports -> Inventory Movement History` now reads directly from `inventory_logs` with RNI filters and export. | Keep transaction-type labels aligned with future movement codes. |
| Admin stock adjustment | implemented | Product quantity edits flow through `BatchService::adjustProductQuantity()`. | None for pilot. |
| Admin generate/export stock report | implemented | Current inventory, expiry, batch, and usage history tables support export. | Keep export smoke tests in regression pack. |
| Formulator view stock availability | implemented | Formulators can access materials, batches, reports, and dashboard views. | None for pilot. |
| Formulator stock out | implemented | Formulators can monitor `Material Usage`, but stock-out creation is now reserved for `Admin RNI` and `RM Desk` by default. | Revisit only if RNI later wants Formulator transaction rights. |
| Formulator view usage history | implemented | Usage history remains visible for monitoring, but mutation actions are blocked. | None for pilot. |
| RM Desk stock out | implemented | `RM Desk` can create `Material Usage` with FEFO or manual batch allocation. | None for pilot. |
| RM Desk own usage reversal | implemented | `RM Desk` can cancel or restore usage only when `sales.created_by` matches the signed-in user. | None for pilot. |

## Master Data Coverage

| Requirement | Status | Current implementation | Recommended next action |
| --- | --- | --- | --- |
| RM Name | implemented | Stored as `products.name`. | None for pilot. |
| RM Code | implemented | Stored as `products.item_code_ierp` with search support. | None for pilot. |
| Lot Number | implemented | Stored per batch as `batches.batch_number`. | None for pilot. |
| Expiry Date | implemented | Stored per batch and enforced in FEFO/manual allocation rules. | None for pilot. |
| Stock | implemented | Stored per batch and synchronized back to `products.quantity`. | None for pilot. |
| Supplier optional | implemented | `products.supplier_id` is nullable for default supplier reference, and material receipt validation now allows blank supplier only in RNI receipt context while legacy purchase flow stays strict. | Keep nullable supplier limited to RNI receipt routes unless the shared purchase policy changes. |
| Storage Location | implemented | `purchase_items.storage_location` captures receipt-stage location and `batches.storage_location` persists active lot location for monitoring/reporting. | Revisit only when a warehouse/bin hierarchy is needed. |
| Physical Form | implemented | `products.physical_form` is stored on material master and shown in forms, detail, dashboard, and reports. | None for pilot. |

## Workflow and Input Coverage

| Requirement | Status | Current implementation | Recommended next action |
| --- | --- | --- | --- |
| Excel upload | implemented | Opening stock import supports `.xlsx`, `.csv`, and `.ods`. | None for pilot. |
| Manual entry | implemented | Materials and receipts can be entered manually through UI forms. | None for pilot. |
| FEFO recommendation | implemented | FEFO recommendation and batch-policy checks are active in material usage flow. | None for pilot. |
| Manual lot selection | implemented | Material usage allows switching each line from auto FEFO to manual batch allocation. | None for pilot. |
| Low stock alert | implemented | Dashboard and reports surface low stock based on `min_stock`. | None for pilot. |
| Near expiry alert | partially implemented | Batch policy, reports, and dashboard surface near-expiry stock, but the threshold is a single configurable setting and does not yet separate the PDF's dashboard-vs-monitoring windows. | Align business policy for dashboard threshold vs operational threshold and document the chosen rule. |
| Transaction history filters | implemented | Inventory movement history supports date range, user, transaction type, RM code, RM name, and lot number filters. | None for pilot. |
| Current inventory report | implemented | `Reports -> Current Inventory` exists and exports. | None for pilot. |
| Transaction report | implemented | Inventory movement history exports XLSX/CSV from consolidated `inventory_logs` data. | None for pilot. |

## Opening Stock Import vs PDF

| PDF field | Status | Notes | Recommended next action |
| --- | --- | --- | --- |
| RM Name | implemented | Present as `name`. | None. |
| RM Code | implemented | Present as `item_code_ierp`. | None. |
| Lot Number | implemented | Present as `opening_batch_number`. | None. |
| Expiry Date | implemented | Added to opening stock import as `opening_expiry_date` / `expiry_date` alias. | Keep template and tests aligned. |
| Stock Quantity | implemented | Present as `opening_quantity`. | None. |
| Unit | implemented | Present as `unit`. | None. |
| Physical Form | implemented | Present as `physical_form` with backward-compatible aliases. | None. |
| Supplier optional | implemented | Present as nullable `supplier` / `supplier_name` / `supplier_id` mapping to the optional material master supplier. | None. |
| Storage Location | implemented | Present as `storage_location` and stored on the opening batch record. | None. |

## Summary

- Pilot-critical RNI flows now cover material receipt, material usage, FEFO/manual lot allocation, opening stock import, batch monitoring, dashboard insights, and consolidated inventory reporting.
- v0.4.5 adds a reusable role-permission foundation with seeded defaults for `Admin RNI`, `Formulator`, and `RM Desk`.
- Finance and inventory value visibility are now permission-controlled across navigation, reports, detail pages, dashboard surfaces, and shared lookup payloads.
- The former pilot blockers around `storage_location`, `physical_form`, optional supplier handling, and unified inventory movement history are now implemented.
- Remaining pilot caveat: near-expiry policy still uses a single operational threshold for dashboard and monitoring instead of separate windows from the PDF.
