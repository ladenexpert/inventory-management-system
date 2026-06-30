# Changelog

## v0.5.1-rni-pilot-stabilization

- treated this work as a new stabilization milestone on top of stable `v0.5.0-rni-pilot-readiness` without amending, rewriting history, committing, tagging, pushing, or making release decisions
- traced the default seed drift to `ProductSeeder`, which was creating material master sample stock directly in `products.quantity` without matching batch rows
- cleaned the default seed so `php artisan migrate:fresh --seed` now keeps the material catalog pilot-ready but zero-stock and zero-value by default
- preserved base/reference seed data for admin login, role permission matrix, module settings, units, categories, physical forms, storage locations, suppliers, customers, and finance categories/settings
- added base storage-location seeding to keep the current storage-location module usable from the default pilot seed
- added explicit `DemoSeeder` and `DemoMaterialStockSeeder` so stocked demo material data is opt-in only and now creates valid batch-backed opening stock instead of aggregate-only quantity drift
- added regression coverage proving the default seed creates no seeded material stock, no seeded batches, no seeded inventory movement history, no seeded finance transactions, and no `products.quantity > 0` material drift without batches
- preserved the v0.4.8 Stock Take baseline, the v0.4.8.1 material delete guard, the v0.5.0 export/visibility baselines, finance semantics, and PHP `8.2` compatibility without adding migrations, packages, Filament, or UI framework changes
- automation validation passed: `composer validate`, `composer install --dry-run`, `php artisan optimize:clear`, `php artisan migrate:fresh --seed`, focused pilot-clean and RNI regression suites, and `php artisan test` with `182 tests / 1423 assertions`
- status: automation-validated and pending owner browser-UAT, manual review, commit, push, and tag
- release handling note: Codex did not commit, tag, or push

## v0.5.0-rni-pilot-readiness

- treated this work as a new milestone on top of stable `v0.4.8.1` without amending, rewriting history, tagging, pushing, or making release decisions
- kept the stable RNI stock engine unchanged while finishing a docs-first pilot readiness pass for navigation, terminology, evidence surfaces, and owner UAT guidance
- confirmed the live user-facing terminology remains `Item Code`, `Team`, `Material Receipt`, `Material Usage`, `Stock Take`, `Inventory & Expiry Monitoring`, `Inventory Movement History`, `Usage Report`, `Batch Monitoring`, and `Finance` as its own authorized menu
- hardened `Batch Monitoring` export so it now requires `batches.export` instead of exposing export through page access alone
- hardened direct Livewire export calls for `Inventory & Expiry Monitoring`, the legacy expiry preset, `Usage Report`, and `Stock Movement Classification` so they now require `reports.export`
- preserved non-admin valuation privacy: value and cost columns remain hidden from non-admin/non-finance roles in both views and exports
- preserved the v0.4.8 Stock Take reconciliation/stale/post/close behavior and the v0.4.8.1 delete-refresh/material stock guard behavior without changing stock movement or finance semantics
- updated `PROJECT_CONTEXT.md`, `docs/PROJECT_CONTEXT.md`, and `docs/RNI_PILOT_GUIDE.md` with the v0.5.0 pilot-readiness status, checklist, preserved guardrails, and owner browser-UAT checkpoints
- automation validation passed: `composer validate`, `composer install --dry-run`, `php artisan optimize:clear`, focused export/readiness suites, and `php artisan test` with `180 tests / 1208 assertions`
- status: automation-validated and pending owner browser-UAT, manual review, commit, push, and tag
- release handling note: Codex did not commit, tag, or push

## v0.4.8.1-delete-refresh-and-aggregate-consistency-hotfix

- treated this work as a new hotfix milestone on top of committed `v0.4.8` without amending, tagging, pushing, or making release decisions
- fixed dashboard/report refresh consistency after destructive mutations by moving shared dashboard cache version bumps to after-commit timing when transactions are active
- added the missing dashboard/report cache invalidation on successful product/material soft delete while preserving existing delete semantics and audit logging
- blocked material/product soft delete when `SUM(batches.available_quantity)` is still above zero, so materials with active stock or active zero-cost stock cannot be deleted
- confirmed the browser `ProductTable` row delete and bulk delete actions both use the centralized `ProductService::deleteProduct()` guard path
- strengthened the delete guard so batch availability remains the primary authority and `products.quantity` also blocks delete as a fail-safe when stock/cache drift still indicates active stock
- kept material/product delete as a master-data lifecycle action only: no new `inventory_logs`, no auto stock adjustment, and no new delete movement type
- clarified that stock must be reduced to zero first through official stock movement flows such as Stock Take, Manual Adjustment, or Material Usage before delete is allowed
- corrected current-state dashboard, inventory monitoring, expiry monitoring, and batch monitoring queries so soft-deleted materials no longer remain in active operational counts
- preserved historical movement and transaction evidence for soft-deleted materials through existing `withTrashed()` history relationships instead of deleting stock or audit data
- preserved existing Material Receipt, Material Usage, Stock Take, Opening Stock, batch authority, quantity cache sync, and legacy Purchase/Sales finance semantics without adding new movement types
- added hotfix regression coverage for service-level active-stock delete blocking, ProductTable row/bulk delete blocking, quantity-cache fail-safe blocking, zero-cost active-stock delete blocking, zero-stock delete without delete movement creation, material usage cancel/restore refresh, legacy sale cancel/restore refresh, soft-deleted material exclusion from current-state aggregates, and historical evidence retention
- automation validation passed: `composer validate`, `composer install --dry-run`, `php artisan optimize:clear`, focused deletion/visibility hotfix coverage, and `php artisan test` with `178 tests / 1203 assertions`
- status: automation-validated and pending owner browser-UAT, manual review, commit, push, and tag
- release handling note: Codex did not commit, tag, or push

## v0.4.8-stock-take-reconciliation-and-flexible-valuation-guardrail

- replaced the session-only Stock Take preview/apply flow with a persistent Stock Take session and row evidence model while keeping the existing controller + Blade architecture
- added durable Stock Take review data including imported rows, matched-batch evidence, unmatched/error rows, reference number retention, and row/session status tracking
- added explicit Stock Take lifecycle controls for `imported`, `reviewed`, `posted`, and `closed`, plus row-level `error` and `stale` evidence
- added a stale-data guard so posting compares current batch quantity against the reviewed `system_qty` snapshot and blocks posting until the session is recalculated when stock has changed
- kept Stock Take posting atomic at the session level and protected it against duplicate posting and post-close mutation
- continued to post Stock Take only through `inventory_adjustments` + `inventory_logs` with `STK` codes and without introducing purchase, usage, opening-stock, sales, or finance side effects
- preserved the rule that Stock Take reconciles existing batches only and does not create new batches in v0.4.8
- kept RNI non-valuated by default while exposing admin-only Stock Take valuation guardrails for reporting with existing batch unit cost, derived adjustment value, and derived average cost visibility
- kept non-admin Stock Take export and view output free of unit cost, adjustment value, average cost, and other sensitive valuation fields
- kept Material Receipt, Material Usage, FEFO/manual picking, Opening Stock, inventory monitoring, batch monitoring, expiry monitoring, movement history, and finance-disabled RNI behavior unchanged
- updated project context, ledger rules, and RNI pilot guidance to document Stock Take reconciliation, stale-review guard, posting/closing behavior, and valuation guardrails
- automation validation passed: `composer validate`, `composer install --dry-run`, `php artisan optimize:clear`, focused Stock Take/V047 suites, and `php artisan test` with `168 tests / 1146 assertions`
- status: automation-validated and ready for owner browser-UAT, manual review, commit, push, and tag
- release handling note: Codex did not commit, tag, or push

## v0.4.7-rni-access-management-master-data-and-traceability-hardening

- preserved the existing role/module access architecture and extended it instead of introducing a parallel permission system
- added `Physical Forms` as master data with seeded defaults, additive `products.physical_form_id`, backward-compatible legacy string retention, and master-data import support
- added `Teams` as master data with additive `sales.team_id`, import support, navigation, and required usage-team selection for new RNI material usage transactions
- hardened RNI material usage so new transactions now require both `Team` and `Requested By` while historical `project` data remains preserved for fallback display/reporting
- introduced reusable `TransactionCodeService` and extended unique transaction-code generation to material receipts and inventory adjustments while preserving existing legacy numbering behavior
- added additive `inventory_adjustments` traceability so manual stock adjustments and stock-take adjustments get unique codes without renumbering historical sales or purchases
- linked inventory movement history to adjustment transaction codes and adjustment users for better traceability and future ERP audit/report use
- added admin-only `Stock Take Import` preview/apply flow with strict item, batch, expiry, unit, and location validation plus per-row adjustment history
- kept legacy modules, routes, batch uniqueness, FEFO behavior, finance visibility rules, and PowerGrid column visibility patterns intact
- expanded regression coverage for physical-form master linkage, required team/requested-by usage validation, auto-generated material receipt references, adjustment traceability, stock-take authorization, and stock-take variance application
- completed post-validation hardening for Batch Monitoring transaction routing, context-aware operation line exports, export integrity, and permission-safe export visibility
- validated the final `TransactionContext` contract across `Opening Stock`, `MR`, `PO`, `MU`, `INV`, `ADJ`, `STK`, and unified Inventory Movement History reporting
- confirmed the final row-grain rule: operations UI remains header-level while operations export is intentionally line-level for ERP/API readiness
- confirmed Batch Monitoring transaction links are context-aware, fail safe for unsupported or missing sources, and do not expose unauthorized transaction detail links
- confirmed finance policy remains unchanged: `PO` and `INV` stay finance-relevant, while `MR`, `MU`, `Opening Stock`, `ADJ`, and `STK` remain excluded from finance posting and revenue/payment flow
- confirmed Opening Stock and Stock Take remain inventory-affecting but excluded from purchase, sales, and finance business reporting
- validation evidence recorded: `composer validate` passed, `php artisan optimize:clear` passed, `php artisan migrate:fresh --seed` passed, `php artisan test` passed with `160 tests / 1080 assertions`
- focused validation suites passed: `BatchTransactionResolverTest`, `OperationLineExportTest`, `TransactionContextFoundationTest`, `ReportFinancialVisibilityTest`, `BatchPowerGridConsistencyTest`, `ReportExportRegressionTest`, `RniRoleAccessTest`, `ProductOpeningStockImportTest`, and `V047HardeningTest`
- owner browser/UAT passed and confirmed Batch Monitoring links, line-level operation exports, filtered/no-selected export behavior, empty export integrity, non-finance export privacy, and dashboard/finance/history spot checks
- status: validated and ready for owner manual commit/push/tag

## v0.4.6-reports-interactive-view-and-trend-charts

- cleaned up the `Reports` menu into `Inventory & Expiry Monitoring`, `Inventory Movement History`, `Usage Report`, `Inbound & Purchase Analysis`, `Sales Analysis`, and `Stock Movement Classification`
- consolidated the inventory and expiry report experience into a shared `Inventory & Expiry Monitoring` PowerGrid with legacy route compatibility through report presets
- enabled reports-only PowerGrid search, filters, sorting, column toggles, persistence, and permission-gated export behavior for the upgraded inventory, usage, and stock movement report tables
- added a lightweight report-only Chart.js foundation with reusable Blade and Alpine wiring for trend and summary charts
- upgraded `Inbound & Purchase Analysis` and `Sales Analysis` to chart-based trend sections while keeping sensitive purchase, revenue, gross profit, and value data permission-aware
- added the new `Stock Movement Classification` report with shared dashboard/report classification rules, summary cards, chart output, detail export, and boundary-tested fast/slow/dead logic based on outbound material usage
- aligned dashboard fast/slow/dead material widgets with the shared stock movement classification service to avoid conflicting definitions between dashboard and reports
- tightened report exports so purchase and sales CSV output never includes unauthorized finance or inventory-value fields
- documented the sprint limitation that legacy `Inventory Movement History` remains on its existing manual filter/export view rather than being fully migrated to PowerGrid in this release
- expanded regression coverage for consolidated report navigation, stock movement thresholds, chart payloads, and finance-sensitive report visibility

## v0.4.5-rni-access-control-and-uat-defect-stabilization

- added the missing `sessions` infrastructure migration so the default database-backed `cache`, `session`, and `queue` drivers all work after `php artisan migrate:fresh --seed`
- documented that environments using `CACHE_STORE=database`, `SESSION_DRIVER=database`, and `QUEUE_CONNECTION=database` must run migrations before cache clear / optimization commands
- added `RM Desk` and a reusable role-module-action permission foundation backed by seeded defaults and an editable role permission matrix
- moved navigation, route guards, controller checks, and shared AJAX lookups to permission-based access instead of hardcoded role-only branching
- set default access so `admin_rni` keeps full control, `formulator` becomes read-only/export-only for RNI monitoring, and `rm_desk` can create usage plus cancel or restore only their own usage records
- locked finance and inventory value visibility behind permissions across menus, dashboard hydration, product and batch tables, inventory reports, usage detail pages, and shared API payloads
- stopped material usage from trusting browser-supplied unit cost, now snapshots cost server-side from batch allocation while keeping usage creation flow unchanged
- rebuilt usage history search, sort, and date filtering with explicit joins and safe aliases to remove relation-query server errors
- hardened auto-generated usage numbering with retry handling for unique collisions while preserving the existing number format
- clarified duplicate batch rejection guidance so `Batch No.` remains unique and repeated supplier/manufacturer numbers must use an internal suffix or reference
- expanded regression coverage for role access, RM Desk ownership rules, sensitive visibility, usage number collision retries, usage history filtering, and duplicate batch guidance

## v0.4.4-item-code-semantics-and-compact-navigation

- corrected `Item Code IERP` semantics so it now shows only the stored legacy IERP code or `-`, never a fallback from `SKU`
- split product display helpers into explicit `sku_display` and `item_code_ierp_display` accessors to prevent identifier leakage across tables, dashboards, and exports
- added separate `SKU` visibility to inventory, movement, usage, purchase, and sales list/report surfaces where both identifiers are needed
- corrected purchase, sales, and inventory movement exports so `Item Code IERP` no longer inherits `SKU` when null
- clarified product form guidance that `Item Code IERP` is optional and manually maintained
- compacted the top navigation into `Dashboard`, `Operations`, `Master Data`, `Reports`, and `Administration` while preserving finance visibility for allowed admin users
- updated regression coverage for compact navigation and non-fallback `Item Code IERP` behavior in lists, usage analysis fields, and exports

## v0.4.3-finance-report-dashboard-visibility-fix

- restored the top-level `Finance` navigation for finance-enabled admin users and kept it hidden for formulator and non-finance roles
- standardized finance transaction visibility with explicit `Source`, `Reference`, and `Related Document` fields for purchase/sale validation
- added dashboard/report cache version invalidation after stock and finance mutations so receipt, usage, sale, and payment results are visible immediately after commit
- aligned the application timezone with `APP_TIMEZONE` and defaulted UAT date handling to `Asia/Jakarta`
- tightened purchase and dashboard analytics to count received/paid inbound activity instead of draft or ordered documents
- introduced `Item Code IERP` visibility across list screens, detail pages, dashboard cards, reports, and exports
- expanded inventory movement, usage analysis, expiry report, sales analysis, and purchase analysis exports for consistent identity, batch, location, quantity, unit, and amount fields
- added regression coverage for finance visibility, dashboard/report refresh consistency, finance posting visibility, and identifier consistency

## v0.3.4-rni-uat-round2-fix

- fixed procurement material search so purchase and material receipt forms can find all active materials, including zero-stock and newly created records
- rebuilt navigation into `Dashboard`, `Master Data`, `Inbound`, `Outbound`, `Inventory`, `Reports`, and `Administration` groups while keeping legacy routes intact
- added shared master-data import support with template download and Excel upload for materials, categories, units, suppliers, customers, and storage locations
- fixed product edit preselection for category, unit, supplier, and physical form in the Livewire modal workflow
- expanded legacy purchase attachment support to accept PDF alongside JPG and PNG
- added printable legacy purchase receipt output from purchase detail
- replaced the fragile legacy sales print Blade with a stable multi-item printable invoice
- tightened multi-line sales validation and removed avoidable per-line lazy loading in the sale allocation path
- expanded the dashboard into `RNI Operations` and `Business Insights` views using cached aggregate data
- added new `Purchase Analysis`, `Sales Analysis`, and `Roles` pages to support the new navigation structure
- added regression coverage for procurement search, product edit preselection, PDF attachments, purchase print, sales print, multi-line sales, and master-data import

## v0.3.2-rni-requirement-complete

- added `products.physical_form` with material create/edit/detail support plus current inventory, batch monitoring, dashboard, and opening stock import coverage
- added optional `products.supplier_id` for RNI material master alignment while keeping legacy purchase flow supplier validation intact
- added `purchase_items.storage_location` and `batches.storage_location` so opening stock and material receipt can capture lot-level storage without introducing warehouse hierarchy
- made material receipt supplier nullable only for RNI receipt context and preserved existing purchase behavior for legacy purchase routes
- added `Reports -> Inventory Movement History` backed by `inventory_logs` with date range, user, transaction type, RM code, RM name, and lot filters plus XLSX/CSV export
- expanded opening stock template/import aliases to include physical form, optional supplier, and storage location with backward compatibility for legacy templates
- updated current inventory report, batch monitoring, receipt detail, and dashboard insights for new RNI-required fields
- updated RNI requirement coverage and pilot guide documentation
- added regression coverage for physical form persistence, opening stock import additions, optional receipt supplier handling, movement history filtering, and legacy-null compatibility

## v0.3.0-rni-pilot-ready

- added RNI-focused `Material Usage` workflow on top of the existing stock deduction engine
- added usage metadata fields: `usage_date`, `purpose`, `formula`, `project`, `requested_by`, `issued_by`, and typed internal usage transactions
- preserved FEFO allocation, batch validation, inventory ledger behavior, and zero-cost batch support
- exposed `Material Receipt` terminology and wrappers for the existing receiving workflow
- added exportable RNI report pages for current inventory, usage history, and expiry monitoring
- refocused the dashboard on RNI operating metrics and recent material usage activity
- added minimal role separation with `Admin RNI` and `Formulator`
- added `docs/RNI_PILOT_GUIDE.md`
- added pilot coverage for material usage, reports, roles, and dashboard metrics

## v0.2.0-batch-fefo-stable

- standardized computed batch lifecycle states: `active`, `near_expiry`, `expired`, `depleted`, and `quarantined`
- added `BatchPolicyService` for centralized expiry, sellability, and batch valuation rules
- added `FefoService` for future-ready FEFO/FIFO/manual batch recommendation and validation
- tightened sale allocation validation so expired, depleted, and invalid manual selections are rejected server-side
- preserved zero-cost batches as valid operational inventory
- exposed batch lifecycle and valuation signals in batch monitoring, dashboard metrics, and POS batch selection
- documented batch policy and updated inventory ledger rules
- added regression coverage for FEFO expiry skipping, manual allocation validation, zero-cost batches, and batch valuation behavior
