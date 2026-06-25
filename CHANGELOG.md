# Changelog

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
