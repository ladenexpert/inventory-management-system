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

- Milestone: `v0.5.0-rni-pilot-readiness`
- Status: implemented and automation-validated
- Owner state: pending owner browser-UAT, manual review, commit, push, and tag
- Release handling note: release decisions remain owner-managed, and Codex did not commit, tag, or push
- Baseline: builds on stable `v0.4.8.1-delete-refresh-and-aggregate-consistency-hotfix`

## v0.5.0 Pilot Readiness Scope

### 1. Discovery Result

- RNI navigation already uses the target user-facing terminology: `Material Receipt`, `Material Usage`, `Stock Take`, `Inventory & Expiry Monitoring`, `Inventory Movement History`, `Usage Report`, `Batch Monitoring`, `RNI Operations`, and `Business Insights`.
- `Item Code` is already the active user-facing label, while `item_code_ierp` remains preserved as the compatibility field behind that label.
- `Team` is already the live RNI usage label, while historical `project` data remains preserved as a fallback for older records.
- Finance already remains its own authorized `Finance` dropdown/menu and is not nested inside `Reports`.
- Material Receipt, Material Usage, Opening Stock, Stock Take, delete guard, cache refresh consistency, historical movement retention, and finance exclusion semantics were already stable from `v0.4.8` and `v0.4.8.1`.

### 2. v0.5.0 Implementation

- v0.5.0 keeps the stock engine unchanged and focuses on RNI pilot readiness evidence, permission safety, and documentation.
- Batch Monitoring export is now permission-gated by `batches.export`, matching the existing role/module/action model instead of relying only on page visibility.
- Report-table exports for `Inventory & Expiry Monitoring`, the legacy expiry preset, `Usage Report`, and `Stock Movement Classification` now reject direct Livewire export calls unless the signed-in user has `reports.export`.
- Non-admin value visibility remains unchanged: valuation and cost fields stay hidden from non-admin/non-finance roles in views and exports.
- Existing documentation now includes a lightweight v0.5.0 RNI pilot readiness checklist and explicit owner browser-UAT checkpoints.

### 3. Preserved Baseline

- v0.4.8 Stock Take reconciliation, stale guard, posting lifecycle, and admin-only valuation guardrail remain preserved.
- v0.4.8.1 delete-refresh consistency and material stock delete guard remain preserved.
- Material/product delete remains a master-data lifecycle action only and is not inventory movement.
- Material/product delete still requires both `SUM(batches.available_quantity) = 0` and `products.quantity = 0` before soft delete is allowed.
- Zero-stock material soft delete remains allowed and still refreshes dashboard/report aggregates.
- Historical movement evidence remains available even after soft delete.
- RNI remains Non-Valuated / Operational Only by default.
- No migration, no new package, no Filament, and no UI framework change were introduced.
- PHP `8.2` compatibility remains preserved.

## v0.4.8.1 Hotfix Scope

### 1. Root Cause Found

- Product/material soft delete was not invalidating the shared dashboard/report cache version.
- Several current-state dashboard and batch-backed report queries were still counting soft-deleted materials because they operated from `batches` plus raw joins without excluding deleted products.
- Stock and finance destructive flows already reused the shared invalidation path in most places, but the hotfix now ensures cache version bumps are registered after successful transaction commit when a database transaction is active.
- The browser delete path was already using `ProductService`, but the delete guard only checked batch availability and did not fail safe on `products.quantity` when legacy/cache drift still showed active stock.

### 2. Hotfix Behavior

- Dashboard/report cache versioning now refreshes after successful destructive stock or finance mutations commit.
- Product/material soft delete is blocked while active stock still exists based on `SUM(batches.available_quantity)`.
- `SUM(batches.available_quantity)` remains the primary delete guard authority, while `products.quantity` also blocks delete as a fail-safe when stock/cache drift still indicates active stock.
- Successful zero-stock product/material soft delete still refreshes dashboard/report aggregates immediately.
- Product/material delete remains a master-data lifecycle action only and does not create `inventory_logs`, inventory adjustments, finance entries, or a new movement type.
- Current-state dashboard cards, inventory monitoring, expiry monitoring, and batch monitoring exclude soft-deleted materials from active operational counts.
- Historical inventory movement evidence remains available for deleted materials through existing history relationships.

### 3. Preserved Semantics

- Material Receipt behavior is unchanged.
- Material Usage behavior is unchanged.
- Stock Take behavior is unchanged.
- Opening Stock behavior is unchanged.
- Batch availability remains the stock authority.
- `products.quantity` remains a synchronized aggregate cache, not the stock authority.
- No finance posting semantics changed.
- No new package, migration, Filament adoption, or UI framework migration was introduced.
- PHP `8.2` compatibility remains preserved.

## v0.4.8 Baseline Scope

### 1. Stock Take Reconciliation Flow

- Stock Take now persists a real session plus row evidence instead of relying only on temporary session preview state.
- Import remains template-based and controller + Blade driven; no Filament or UI framework change was introduced.
- Stock Take in v0.4.8 reconciles existing matched batches only.
- Unmatched or invalid rows remain visible as row-level evidence with `error` status and clear messages.
- Stock Take continues to use the existing `inventory_adjustments`, `inventory_logs`, `BatchService`, and `TransactionContext` foundation instead of creating a parallel stock engine.

### 2. Review, Posting, Closing, And Locking

- Stock Take session lifecycle is now `imported` -> `reviewed` -> `posted` -> `closed`.
- Row lifecycle is aligned with session evidence and also supports `error` and `stale`.
- Recalculate / review refreshes `system_qty`, re-derives `variance_qty`, and records the review timestamp and reviewer.
- Posting is atomic at the session level and does not allow partial stock updates.
- Posted sessions cannot be posted again, and closed sessions cannot be recalculated or posted again.
- Close is now an explicit post-posting lock step for preserving final evidence.

### 3. Stale Data And Concurrency Guard

- Each Stock Take row stores a reviewed `system_qty` snapshot.
- Posting compares current `batches.available_quantity` against the reviewed snapshot before any movement is applied.
- If current quantity changed after review, posting is blocked, affected rows are marked `stale`, and the user must recalculate before retrying.
- Session posting uses database transaction and status checks to prevent duplicate posting from repeated requests or concurrent users.

### 4. TransactionContext And Movement Policy

The current v0.4.8 transaction context contract remains:

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

Stock Take posting rules in v0.4.8:

- plus variance creates `stock_take_adjustment_in`
- minus variance creates `stock_take_adjustment_out`
- zero variance creates no stock movement but keeps evidence
- no Material Receipt, Material Usage, Purchase Order, Sales, Invoice, Opening Stock, or Finance side effect is introduced

### 5. Flexible Valuation Guardrail

- RNI remains Non-Valuated / Operational Only by default.
- Quantity tracking remains mandatory, while valuation remains optional and reporting-only.
- Batch remains the stock quantity source of truth.
- Existing batch `unit_cost` may be used only for admin-visible Stock Take reporting such as adjustment value.
- Stock Take does not overwrite `batch.unit_cost`.
- Average cost remains derived only from the existing aggregate cache behavior and is not used as the posting cost.
- Non-admin users do not receive Stock Take valuation columns in view or export output.

### 6. Preserved Behaviors

The v0.4.8 implementation intentionally preserves:

- Material Receipt behavior
- Material Usage behavior
- manual picking default
- optional Auto FEFO
- Opening Stock semantics
- finance-disabled behavior for RNI and Stock Take
- admin-only valuation visibility
- reference number traceability
- Inventory Monitoring, Batch Monitoring, Expiry Monitoring, Movement History, and Usage Report behavior
- PHP `8.2` compatibility
- existing Laravel controller + Blade + Livewire/PowerGrid project structure

## Validation Evidence

Automation validation completed for v0.5.0:

- `composer validate`: passed
- `composer install --dry-run`: passed
- `php artisan optimize:clear`: passed
- focused export hardening coverage passed: `php artisan test tests/Feature/RniExportPermissionHardeningTest.php`
- focused export regression coverage passed: `php artisan test tests/Feature/ReportExportRegressionTest.php`
- focused role/receipt/usage/view suites passed
- focused Stock Take / opening stock / delete guard / visibility suites passed
- focused batch/report/financial visibility suites passed
- `php artisan test`: passed, `180` tests / `1208` assertions

Focused v0.5.0 readiness coverage includes:

- RNI role access
- Material Receipt permission visibility
- Material Usage workflow
- Opening Stock import
- Stock Take import / post / close regression
- material delete guard regression including row/bulk delete path protection
- dashboard/report refresh after delete/cancel/restore
- Batch Monitoring regression
- Inventory & Expiry Monitoring regression
- Inventory Movement History regression
- Usage Report regression
- Inbound & Purchase Analysis regression
- Sales Analysis regression
- Stock Movement Classification regression
- export permission hardening for Batch Monitoring and report Livewire exports
- transaction view rendering
- finance menu visibility

Automation validation completed previously for v0.4.8.1:

- `composer validate`: passed
- `composer install --dry-run`: passed
- `php artisan optimize:clear`: passed
- `php artisan test --filter=MasterDataDeletionTest`: passed
- `php artisan test tests/Feature/VisibilityConsistencyTest.php`: passed
- `php artisan test --filter=StockTakeImportTest`: passed
- `php artisan test --filter=V047HardeningTest`: passed
- `php artisan test --filter=VisibilityConsistencyTest`: passed
- `php artisan test`: passed, `178` tests / `1203` assertions

Focused v0.4.8.1 hotfix coverage includes:

- active-stock product delete blocking
- ProductTable row delete blocking
- ProductTable bulk delete blocking
- quantity-cache fail-safe delete blocking
- active zero-cost stock product delete blocking
- zero-stock delete without delete movement creation
- material usage cancel / restore refresh consistency
- legacy sale cancel / restore refresh consistency
- soft-deleted material exclusion from current dashboard and batch-backed reports
- historical inventory movement retention for soft-deleted materials

Focused v0.4.8 baseline coverage still includes:

- Stock Take import persistence
- unmatched batch handling
- variance calculation
- stale current-qty guard
- duplicate posting prevention
- closing / locking
- non-admin export privacy
- admin valuation export visibility
- Stock Take context isolation from purchase, usage, and finance flows

## Owner Browser UAT Pending

Owner browser-UAT is still required before any release action.

Recommended owner checks for v0.5.0:

- confirm `Opening Stock`, `Material Receipt`, `Material Usage`, `Stock Take`, `Inventory & Expiry Monitoring`, `Inventory Movement History`, `Usage Report`, `Batch Monitoring`, `Inbound & Purchase Analysis`, `Sales Analysis`, and `Stock Movement Classification` are available for the intended pilot roles
- confirm `Finance` appears only as its own authorized dropdown/menu and remains hidden for non-finance roles
- confirm `Admin RNI` can access valuation-sensitive areas while `RM Desk` and `Formulator` still do not see cost/value fields
- confirm `Batch Monitoring` export is available only for roles granted export permission
- confirm report exports remain available only for roles granted `reports.export`
- confirm Material Receipt, Material Usage, Opening Stock, and Stock Take still create no finance entries
- confirm Stock Take still supports review, post, close, stale guard, and historical evidence
- confirm deleting a zero-stock material refreshes current-state dashboards/reports immediately without creating a movement row
- confirm deleting a material with either active batch stock or positive `products.quantity` remains blocked

Recommended owner checks for v0.4.8.1:

- attempt to delete a material with active stock and verify delete is blocked with guidance to reduce stock to zero first
- attempt to delete a material with active zero-cost stock and verify delete is still blocked
- delete a material that is allowed by the current rules and verify Dashboard, Inventory & Expiry Monitoring, and Batch Monitoring stop counting it immediately
- verify the allowed zero-stock delete creates no new Inventory Movement History row and no auto adjustment
- cancel and restore a Material Usage and verify Dashboard plus Usage Report refresh immediately
- cancel and restore a completed legacy sale and verify sales/finance insight cards stop counting it while cancelled and remain excluded while restored-to-pending
- verify Inventory Movement History still preserves deleted-material history rows

Recommended owner checks for the preserved v0.4.8 baseline:

- import a Stock Take file for existing matched batches
- verify unmatched rows stay visible as `error`
- verify stale guard blocks posting after live stock changes
- verify zero variance rows keep evidence without movement
- verify posted session creates movement history and stays out of finance
- verify closed session no longer allows stock-impacting actions
- verify non-admin users do not see valuation columns in Stock Take export/view

## Guardrails Preserved In v0.5.0

- no Material Receipt rewrite
- no Material Usage rewrite
- no Opening Stock rewrite
- no Stock Take rewrite
- no finance posting change
- no batch creation from Stock Take
- no batch unit-cost overwrite
- no full valuation engine
- no purchasing, maintenance, ERP Lite, or workflow expansion
- no new composer or npm package
- no Filament introduction
