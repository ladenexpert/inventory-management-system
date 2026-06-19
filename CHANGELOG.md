# Changelog

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
