# Project Context

## Product Baseline

Resource Management Platform (`RMP`) is being shaped as a future Lightweight Modular ERP / Small ERP platform.

`RNI Raw Material Room` is the current pilot implementation, but the architecture is intentionally being kept:

- modular
- context-aware
- role-aware
- finance-safe
- export/API-ready
- future-ready for dashboard insight, AI recommendation, integration/API, and multi-tenancy

RMP is therefore not positioned as a one-off RNI inventory UAT system. The current delivery direction is to use the RNI pilot to harden reusable ERP foundations without breaking legacy operational behavior.

## Current Milestone

- Milestone: `v0.4.7-rni-access-management-master-data-and-traceability-hardening`
- Status: validated
- Owner state: ready for owner manual commit/push/tag
- Release handling note: commit, push, and tag decisions remain owner-managed

## v0.4.7 Validated Scope

### 1. Role And Module Access Hardening

- Admin retains full access.
- Non-admin roles remain restricted by module and permission.
- Legacy Purchase, Legacy Sales, Finance, value/cost visibility, and protected exports remain role/module-gated.
- Export authorization is enforced server-side, not only through UI visibility.

### 2. Master Data Improvements

- `Physical Form` master data is added and validated with default examples: `Liquid`, `Powder`, `Wax`, `Others`.
- `Team` master data is added and validated for active RNI material usage.
- Active RNI material usage now uses `Team` and `Requested By`.
- Historical `Project` data remains backward-compatible as fallback where needed.

### 3. Transaction Number vs Reference Number Separation

- `Transaction Number` / `Transaction Code` is internal, mandatory, unique, and system-generated.
- `Reference Number` is external, manual, and optional.
- Reference examples include `AWB`, `PO`, `Invoice`, `DN`, and `Request Form`.
- Reference Number is not reused as Transaction Number.
- Active RNI flows do not use legacy `Invoice Number` terminology as the primary operational label.

### 4. TransactionContext Foundation

The validated v0.4.7 transaction context contract is:

| Context | Meaning | Stock Direction | Finance |
| --- | --- | --- | --- |
| `Opening Stock` / `Saldo Awal` | inventory initialization | stock-in | excluded |
| `MR` | Material Receipt, active RNI inbound | stock-in | excluded |
| `PO` | Legacy Purchase | stock-in | preserved finance/payable |
| `MU` | Material Usage, active RNI outbound/internal usage | stock-out | excluded |
| `INV` | Legacy Sales | stock-out | preserved finance/revenue |
| `ADJ` | Manual Stock Adjustment | plus/minus correction | excluded |
| `STK` | Stock Take Adjustment | plus/minus variance | excluded |
| `Inventory Movement History` | unified movement/report context | movement/reporting | excluded |

Inventory Movement History is a unified reporting context, not a new transaction input type.

### 5. Finance Policy

Current v0.4.7 finance policy:

- `PO` and `INV` remain finance-relevant.
- `MR`, `MU`, `Opening Stock`, `ADJ`, and `STK` are excluded from finance posting, payment, and revenue flow.
- A future ERP roadmap may introduce finance posting policy or accounting impact policy for inventory valuation, COGS, WIP, stock adjustment journals, and opening balance journals.
- That finance-policy roadmap is not implemented in v0.4.7.

### 6. Operations vs Reports vs Analytics vs Legacy

Final row-grain rule:

- Operations UI = header-level transaction workspace
- Operations export = line-level detail for ERP/API readiness
- Reports = line, movement, detail, or monitoring context
- Analytics = aggregate and dashboard insight context
- Legacy modules remain preserved for the future ERP roadmap

Specific validated row-grain rules:

| Surface | UI Grain | Export Grain |
| --- | --- | --- |
| Material Receipt | 1 row per `MR` transaction | line-level receipt item/batch detail |
| Legacy Purchase | 1 row per `PO` transaction | line-level purchase detail |
| Material Usage | 1 row per `MU` transaction | line-level usage allocation detail |
| Legacy Sales | 1 row per `INV` transaction | line-level sales detail |
| Usage Report | line-level | line-level |
| Inventory Movement History | movement-level | movement-level |
| Batch Monitoring | batch-level | batch-level |

### 7. Batch Monitoring Hardening

- Batch Monitoring transaction links are context-aware.
- `MR` opens Material Receipt detail.
- `PO` opens Legacy Purchase detail.
- `Opening Stock`, `Legacy Sync`, missing transaction number, and unsupported source fail safely without an error page.
- Unauthorized transaction links are not exposed.

### 8. Export And Import Consistency

- Operation exports now provide line-level detail while preserving header-level UI.
- Selected header export expands to all related line items by transaction context and transaction code.
- Empty export returns a valid clean workbook.
- Exports use clean scalar values and omit HTML badges and action columns.
- Finance and value columns remain permission-protected.
- Import behavior remains stable.
- Opening Stock import remains inventory initialization.
- Stock Take import remains preview-first variance adjustment.

### 9. Opening Stock And Stock Take Impact

Opening Stock:

- affects stock, batches, current inventory, batch monitoring, inventory movement history, and inventory value where applicable
- is excluded from `MR`, `PO`, finance, and purchase value reporting

Stock Take:

- affects stock, batches, current inventory, batch monitoring, inventory movement history, and inventory value where applicable
- plus variance creates adjustment in
- minus variance creates adjustment out
- zero variance creates no movement
- is excluded from `MR`, `MU`, `PO`, `INV`, finance, and purchase/sales/usage business reporting

### 10. Dashboard Impact

- Recent receipts remain context-aware.
- Recent usage remains `MU`-only.
- Purchase value remains `PO`-only.
- Sales value remains `INV`-only.
- Inventory value remains role-protected.
- Opening Stock and Stock Take do not pollute purchase, sales, or finance widgets.

### 11. Fixes And Stabilizations Included

- FEFO/manual pick regression fixed and preserved
- PowerGrid sort/export regression fixed
- export file integrity fixed
- `TransactionContext` foundation introduced
- operation line export service introduced
- batch transaction resolver introduced
- source/context labels normalized

## Validation Evidence

Latest validation recorded for v0.4.7:

- `composer validate`: passed
- `php artisan optimize:clear`: passed
- `php artisan migrate:fresh --seed`: passed
- `php artisan test`: passed, `160` tests / `1080` assertions
- owner browser/UAT: passed

Focused suites passed:

- `BatchTransactionResolverTest`
- `OperationLineExportTest`
- `TransactionContextFoundationTest`
- `ReportFinancialVisibilityTest`
- `BatchPowerGridConsistencyTest`
- `ReportExportRegressionTest`
- `RniRoleAccessTest`
- `ProductOpeningStockImportTest`
- `V047HardeningTest`

## Owner Browser UAT Confirmation

Owner browser/UAT confirmed:

- Batch Monitoring `MR` and `PO` transaction links open the correct detail pages.
- Opening Stock and Legacy Sync no longer cause a broken transaction action.
- Operations UI remains one row per transaction.
- Operations exports are line-level and include related line items.
- Filtered no-selected exports work according to filtered scope.
- Empty export opens cleanly.
- Non-finance roles do not receive protected cost/value columns.
- Dashboard, finance totals, usage history, opening stock history, and stock-take history were spot-checked successfully.

## Guardrails Preserved In v0.4.7

- no broad legacy refactor
- no batch/lot uniqueness change
- no stock calculation change
- no expiry status rule change
- no FEFO/manual pick behavior change
- no weakening of role/module access
- no removal of export/import capability
- no alteration or renumbering of historical data
- no re-mixing of Transaction Number and Reference Number
