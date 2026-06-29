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

- Milestone: `v0.4.8-stock-take-reconciliation-and-flexible-valuation-guardrail`
- Status: implemented and automation-validated
- Owner state: pending owner browser-UAT, manual review, commit, push, and tag
- Release handling note: release decisions remain owner-managed, and Codex did not commit, tag, or push

## v0.4.8 Scope

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

Automation validation completed for v0.4.8:

- `composer validate`: passed
- `composer install --dry-run`: passed
- `php artisan optimize:clear`: passed
- `php artisan test --filter=StockTakeImportTest`: passed
- `php artisan test --filter=V047HardeningTest`: passed
- `php artisan test`: passed, `168` tests / `1146` assertions

Focused v0.4.8 coverage includes:

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

Recommended owner checks for v0.4.8:

- import a Stock Take file for existing matched batches
- verify unmatched rows stay visible as `error`
- verify stale guard blocks posting after live stock changes
- verify zero variance rows keep evidence without movement
- verify posted session creates movement history and stays out of finance
- verify closed session no longer allows stock-impacting actions
- verify non-admin users do not see valuation columns in Stock Take export/view

## Guardrails Preserved In v0.4.8

- no Material Receipt rewrite
- no Material Usage rewrite
- no Opening Stock rewrite
- no finance posting change
- no batch creation from Stock Take
- no batch unit-cost overwrite
- no full valuation engine
- no purchasing, maintenance, ERP Lite, or workflow expansion
- no new composer or npm package
- no Filament introduction
