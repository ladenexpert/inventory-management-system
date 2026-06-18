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
| Admin view transaction history | partially implemented | Usage history report is available and inventory movements are written to `inventory_logs`, but there is no dedicated unified stock in/out/adjustment history screen yet. | Add a dedicated inventory movement history page backed by `inventory_logs`. |
| Admin stock adjustment | implemented | Product quantity edits flow through `BatchService::adjustProductQuantity()`. | None for pilot. |
| Admin generate/export stock report | implemented | Current inventory, expiry, batch, and usage history tables support export. | Keep export smoke tests in regression pack. |
| Formulator view stock availability | implemented | Formulators can access materials, batches, reports, and dashboard views. | None for pilot. |
| Formulator stock out | implemented | Formulators can create `Material Usage` with FEFO or manual batch allocation. | None for pilot. |
| Formulator view own usage history | implemented | Usage history table now restricts formulators to records created/issued by themselves. | None for pilot. |

## Master Data Coverage

| Requirement | Status | Current implementation | Recommended next action |
| --- | --- | --- | --- |
| RM Name | implemented | Stored as `products.name`. | None for pilot. |
| RM Code | implemented | Stored as `products.item_code_ierp` with search support. | None for pilot. |
| Lot Number | implemented | Stored per batch as `batches.batch_number`. | None for pilot. |
| Expiry Date | implemented | Stored per batch and enforced in FEFO/manual allocation rules. | None for pilot. |
| Stock | implemented | Stored per batch and synchronized back to `products.quantity`. | None for pilot. |
| Supplier optional | partially implemented | Supplier records exist, but material receipt/purchase validation still requires `supplier_id`, and product master does not own a nullable supplier field. | Make receipt supplier nullable or add a separate optional supplier association strategy for RNI. |
| Storage Location | missing | No storage-location field is currently stored on product or batch records. | Add `storage_location` to the RNI material/batch model and expose it in forms/import/reporting. |
| Physical Form | missing | No `physical_form` field exists in current master data or imports. | Add `physical_form` to material master and import/report flows. |

## Workflow and Input Coverage

| Requirement | Status | Current implementation | Recommended next action |
| --- | --- | --- | --- |
| Excel upload | implemented | Opening stock import supports `.xlsx`, `.csv`, and `.ods`. | None for pilot. |
| Manual entry | implemented | Materials and receipts can be entered manually through UI forms. | None for pilot. |
| FEFO recommendation | implemented | FEFO recommendation and batch-policy checks are active in material usage flow. | None for pilot. |
| Manual lot selection | implemented | Material usage allows switching each line from auto FEFO to manual batch allocation. | None for pilot. |
| Low stock alert | implemented | Dashboard and reports surface low stock based on `min_stock`. | None for pilot. |
| Near expiry alert | partially implemented | Batch policy, reports, and dashboard surface near-expiry stock, but the threshold is a single configurable setting and does not yet separate the PDF's dashboard-vs-monitoring windows. | Align business policy for dashboard threshold vs operational threshold and document the chosen rule. |
| Transaction history filters | partially implemented | Search and date filtering exist, but there is no dedicated filter set for every PDF field on a single unified transaction-history screen. | Add inventory-log reporting with explicit filters for user, RM code, RM name, lot, and transaction type. |
| Current inventory report | implemented | `Reports -> Current Inventory` exists and exports. | None for pilot. |
| Transaction report | partially implemented | Usage-history reporting exists, but there is no single Excel export combining stock in, stock out, and stock adjustment from `inventory_logs`. | Add a consolidated transaction report powered by `inventory_logs`. |

## Opening Stock Import vs PDF

| PDF field | Status | Notes | Recommended next action |
| --- | --- | --- | --- |
| RM Name | implemented | Present as `name`. | None. |
| RM Code | implemented | Present as `item_code_ierp`. | None. |
| Lot Number | implemented | Present as `opening_batch_number`. | None. |
| Expiry Date | implemented | Added to opening stock import as `opening_expiry_date` / `expiry_date` alias. | Keep template and tests aligned. |
| Stock Quantity | implemented | Present as `opening_quantity`. | None. |
| Unit | implemented | Present as `unit`. | None. |
| Physical Form | missing | Not stored anywhere yet. | Add schema + UI/import support. |
| Supplier optional | missing | Not part of opening stock import today. | Add nullable supplier resolution if required for pilot. |
| Storage Location | missing | Not part of opening stock import today. | Add when storage-location model is introduced. |

## Summary

- Pilot-critical RNI flows are now in place for material receipt, material usage, FEFO/manual lot allocation, reporting, and formulator self-history access.
- The biggest remaining requirement gaps are `storage_location`, `physical_form`, and a consolidated inventory movement history/report.
- Supplier optionality is still not fully aligned with the PDF because receipt creation currently requires a supplier.
